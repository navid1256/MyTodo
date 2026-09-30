<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class ReminderDeliveryRepository
{
    /** Shares the existing UTC-configured PDO connection with task repositories. */
    public function __construct(private readonly PDO $pdo) {}

    /** Creates one durable delivery per browser for each due, eligible reminder. */
    public function enqueueDueReminders(string $now, int $limit): void
    {
        $candidates = $this->pdo->prepare(
            "SELECT r.id
             FROM task_reminders r
             JOIN tasks t ON t.id = r.task_id
             LEFT JOIN task_repeat_rules rule ON rule.id = t.repeat_rule_id
             WHERE r.status = 'pending' AND r.remind_at <= :now AND t.is_done = 0
               AND (rule.id IS NULL OR rule.status IN ('active', 'completed'))
               AND NOT EXISTS (SELECT 1 FROM task_reminder_deliveries d WHERE d.reminder_id = r.id)
             ORDER BY r.remind_at, r.id LIMIT :batch_limit"
        );
        $candidates->bindValue(':now', $now);
        $candidates->bindValue(':batch_limit', $limit, PDO::PARAM_INT);
        $candidates->execute();

        foreach ($candidates->fetchAll(PDO::FETCH_COLUMN) as $reminderId) {
            $this->pdo->beginTransaction();
            try {
                // The parent lock serializes fan-out with reminder editing and cancellation.
                $lock = $this->pdo->prepare(
                    "SELECT r.id FROM task_reminders r
                     JOIN tasks t ON t.id = r.task_id
                     LEFT JOIN task_repeat_rules rule ON rule.id = t.repeat_rule_id
                     WHERE r.id = :id AND r.status = 'pending' AND r.remind_at <= :now
                       AND t.is_done = 0 AND (rule.id IS NULL OR rule.status IN ('active', 'completed'))
                     FOR UPDATE"
                );
                $lock->execute([':id' => $reminderId, ':now' => $now]);
                if ($lock->fetchColumn() === false) {
                    $this->pdo->commit();
                    continue;
                }

                $insert = $this->pdo->prepare(
                    "INSERT INTO task_reminder_deliveries (reminder_id, subscription_id)
                     SELECT r.id, p.id FROM task_reminders r
                     JOIN tasks t ON t.id = r.task_id
                     JOIN push_subscriptions p ON p.user_id = t.user_id
                     WHERE r.id = :id
                     ON DUPLICATE KEY UPDATE status = task_reminder_deliveries.status"
                );
                $insert->execute([':id' => $reminderId]);

                // A reminder without a subscribed browser is terminal, not silently sent.
                $count = $this->pdo->prepare('SELECT COUNT(*) FROM task_reminder_deliveries WHERE reminder_id = :id');
                $count->execute([':id' => $reminderId]);
                if ((int) $count->fetchColumn() === 0) {
                    $fail = $this->pdo->prepare("UPDATE task_reminders SET status = 'failed' WHERE id = :id AND status = 'pending'");
                    $fail->execute([':id' => $reminderId]);
                }
                $this->pdo->commit();
            } catch (Throwable $exception) {
                $this->pdo->rollBack();
                throw $exception;
            }
        }
    }

    /** Turns browser removals and cancelled/completed work into terminal deliveries. */
    public function markUnavailableDeliveries(string $now, int $limit): void
    {
        $statement = $this->pdo->prepare(
            "SELECT d.id FROM task_reminder_deliveries d
             JOIN task_reminders r ON r.id = d.reminder_id
             JOIN tasks t ON t.id = r.task_id
             LEFT JOIN push_subscriptions p ON p.id = d.subscription_id
             LEFT JOIN task_repeat_rules rule ON rule.id = t.repeat_rule_id
             WHERE d.status IN ('pending', 'retryable')
               AND (p.id IS NULL OR p.user_id <> t.user_id OR t.is_done = 1
                    OR r.status = 'cancelled' OR rule.status IN ('paused', 'cancelled'))
             ORDER BY d.id LIMIT :batch_limit"
        );
        $statement->bindValue(':batch_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->changeUnclaimedStatus((int) $id, 'unavailable', $now);
        }
    }

    /** Leaves uncertain abandoned HTTP calls unknown rather than automatically replaying them. */
    public function expireAbandonedClaims(string $cutoff, string $now, int $limit): void
    {
        $statement = $this->pdo->prepare(
            "SELECT id, reminder_id FROM task_reminder_deliveries
             WHERE status = 'leased' AND claimed_at <= :cutoff
             ORDER BY claimed_at, id LIMIT :batch_limit"
        );
        $statement->bindValue(':cutoff', $cutoff);
        $statement->bindValue(':batch_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->changeUnclaimedStatus((int) $row['id'], 'unknown', $now, $cutoff);
        }
    }

    /** Applies a bounded terminal transition and updates the parent reminder atomically. */
    private function changeUnclaimedStatus(int $id, string $status, string $now, ?string $cutoff = null): void
    {
        $reminder = $this->pdo->prepare('SELECT reminder_id FROM task_reminder_deliveries WHERE id = :id');
        $reminder->execute([':id' => $id]);
        $reminderId = $reminder->fetchColumn();
        if ($reminderId === false) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            // Always lock the parent before its deliveries, matching the edit path.
            $parentLock = $this->pdo->prepare('SELECT id FROM task_reminders WHERE id = :id FOR UPDATE');
            $parentLock->execute([':id' => $reminderId]);
            $lock = $this->pdo->prepare('SELECT reminder_id, status, claimed_at FROM task_reminder_deliveries WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $id]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if ($row === false || ($cutoff === null && !in_array($row['status'], ['pending', 'retryable'], true))
                || ($cutoff !== null && ($row['status'] !== 'leased' || $row['claimed_at'] > $cutoff))) {
                $this->pdo->commit();
                return;
            }
            if ($status === 'unavailable') {
                // Recheck eligibility at write time; a candidate may become valid again.
                $update = $this->pdo->prepare(
                    "UPDATE task_reminder_deliveries d
                     JOIN task_reminders r ON r.id = d.reminder_id
                     JOIN tasks t ON t.id = r.task_id
                     LEFT JOIN push_subscriptions p ON p.id = d.subscription_id
                     LEFT JOIN task_repeat_rules rule ON rule.id = t.repeat_rule_id
                     SET d.status = 'unavailable', d.claim_token = NULL,
                         d.claimed_at = NULL, d.next_attempt_at = NULL
                     WHERE d.id = :id AND d.status IN ('pending', 'retryable')
                       AND (p.id IS NULL OR p.user_id <> t.user_id OR t.is_done = 1
                            OR r.status = 'cancelled' OR rule.status IN ('paused', 'cancelled'))"
                );
                $update->execute([':id' => $id]);
            } else {
                $update = $this->pdo->prepare(
                    "UPDATE task_reminder_deliveries SET status = 'unknown', claim_token = NULL,
                     claimed_at = NULL, next_attempt_at = NULL
                     WHERE id = :id AND status = 'leased' AND claimed_at <= :cutoff"
                );
                $update->execute([':id' => $id, ':cutoff' => $cutoff]);
            }
            if ($update->rowCount() !== 1) {
                $this->pdo->commit();
                return;
            }
            $this->updateReminderState((int) $row['reminder_id'], $now);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** Atomically claims only still-eligible browser deliveries with unique tokens. */
    public function reserveReadyDeliveries(string $now, int $limit): array
    {
        $candidates = $this->pdo->prepare(
            "SELECT d.id, d.reminder_id FROM task_reminder_deliveries d
             JOIN task_reminders r ON r.id = d.reminder_id
             JOIN tasks t ON t.id = r.task_id
             JOIN push_subscriptions p ON p.id = d.subscription_id AND p.user_id = t.user_id
             LEFT JOIN task_repeat_rules rule ON rule.id = t.repeat_rule_id
             WHERE (d.status = 'pending' OR (d.status = 'retryable' AND d.next_attempt_at <= :retry_at))
               AND r.status IN ('pending', 'sent') AND r.remind_at <= :due_at AND t.is_done = 0
               AND (rule.id IS NULL OR rule.status IN ('active', 'completed'))
             ORDER BY r.remind_at, d.id LIMIT :batch_limit"
        );
        $candidates->bindValue(':retry_at', $now);
        $candidates->bindValue(':due_at', $now);
        $candidates->bindValue(':batch_limit', $limit, PDO::PARAM_INT);
        $candidates->execute();
        $claims = [];

        foreach ($candidates->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            $id = (int) $candidate['id'];
            $token = bin2hex(random_bytes(16));
            $this->pdo->beginTransaction();
            try {
                $parentLock = $this->pdo->prepare('SELECT id FROM task_reminders WHERE id = :id FOR UPDATE');
                $parentLock->execute([':id' => $candidate['reminder_id']]);
                $update = $this->pdo->prepare(
                    "UPDATE task_reminder_deliveries d
                     JOIN task_reminders r ON r.id = d.reminder_id
                     JOIN tasks t ON t.id = r.task_id
                     JOIN push_subscriptions p ON p.id = d.subscription_id AND p.user_id = t.user_id
                     LEFT JOIN task_repeat_rules rule ON rule.id = t.repeat_rule_id
                     SET d.status = 'leased', d.claim_token = :token,
                         d.claimed_at = :claimed_at, d.last_attempt_at = :last_attempt_at,
                         d.next_attempt_at = NULL, d.attempt_count = d.attempt_count + 1
                     WHERE d.id = :id AND (d.status = 'pending'
                         OR (d.status = 'retryable' AND d.next_attempt_at <= :retry_at))
                       AND r.status IN ('pending', 'sent') AND r.remind_at <= :due_at
                       AND t.is_done = 0 AND (rule.id IS NULL OR rule.status IN ('active', 'completed'))"
                );
                $update->execute([
                    ':token' => $token, ':claimed_at' => $now, ':last_attempt_at' => $now,
                    ':id' => $id, ':retry_at' => $now, ':due_at' => $now,
                ]);
                if ($update->rowCount() !== 1) {
                    $this->pdo->commit();
                    continue;
                }
                $claim = $this->findClaim((int) $id, $token);
                $attempt = $this->pdo->prepare(
                    'UPDATE task_reminders SET attempt_count = attempt_count + 1, last_attempt_at = :at WHERE id = :id'
                );
                $attempt->execute([':at' => $now, ':id' => $claim['reminder_id']]);
                $this->pdo->commit();
                $claims[] = $claim;
            } catch (Throwable $exception) {
                $this->pdo->rollBack();
                throw $exception;
            }
        }

        return $claims;
    }

    /** Reads the owned subscription snapshot associated with a successful claim. */
    private function findClaim(int $id, string $token): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.reminder_id, d.subscription_id, d.claim_token, d.attempt_count,
                    t.user_id, t.title AS task_title, t.due_at AS task_due_at,
                    r.remind_at, p.endpoint, p.p256dh, p.auth, p.content_encoding
             FROM task_reminder_deliveries d
             JOIN task_reminders r ON r.id = d.reminder_id
             JOIN tasks t ON t.id = r.task_id
             JOIN push_subscriptions p ON p.id = d.subscription_id AND p.user_id = t.user_id
             WHERE d.id = :id AND d.claim_token = :token'
        );
        $statement->execute([':id' => $id, ':token' => $token]);
        $claim = $statement->fetch(PDO::FETCH_ASSOC);
        if ($claim === false) {
            throw new \LogicException('Reserved reminder delivery disappeared.');
        }

        return $claim;
    }

    /** Records a provider outcome only for the worker still holding the active claim. */
    public function recordClaimResult(
        int $id,
        string $token,
        string $status,
        ?int $httpStatus,
        ?string $nextAttemptAt,
        string $now,
        array $claim
    ): bool {
        $reminder = $this->pdo->prepare('SELECT reminder_id FROM task_reminder_deliveries WHERE id = :id');
        $reminder->execute([':id' => $id]);
        $reminderId = $reminder->fetchColumn();
        if ($reminderId === false) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $parentLock = $this->pdo->prepare('SELECT id FROM task_reminders WHERE id = :id FOR UPDATE');
            $parentLock->execute([':id' => $reminderId]);
            $lock = $this->pdo->prepare(
                'SELECT d.reminder_id, d.subscription_id, t.user_id
                 FROM task_reminder_deliveries d
                 JOIN task_reminders r ON r.id = d.reminder_id
                 JOIN tasks t ON t.id = r.task_id
                 WHERE d.id = :id AND d.status = :status AND d.claim_token = :token FOR UPDATE'
            );
            $lock->execute([':id' => $id, ':status' => 'leased', ':token' => $token]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $this->pdo->commit();
                return false;
            }

            $update = $this->pdo->prepare(
                'UPDATE task_reminder_deliveries
                 SET status = :status, claim_token = NULL, claimed_at = NULL,
                     next_attempt_at = :next_at, http_status = :http_status, sent_at = :sent_at
                 WHERE id = :id AND claim_token = :token'
            );
            $update->execute([
                ':status' => $status, ':next_at' => $nextAttemptAt, ':http_status' => $httpStatus,
                ':sent_at' => $status === 'sent' ? $now : null,
                ':id' => $id, ':token' => $token,
            ]);

            // Compare keys too: an old rejected request must not delete a refreshed subscription.
            $subscriptionParameters = [
                ':id' => $row['subscription_id'], ':user_id' => $row['user_id'],
                ':p256dh' => $claim['p256dh'], ':auth' => $claim['auth'],
            ];
            if ($status === 'expired') {
                $delete = $this->pdo->prepare(
                    'DELETE FROM push_subscriptions WHERE id = :id AND user_id = :user_id
                     AND p256dh = :p256dh AND auth = :auth'
                );
                $delete->execute($subscriptionParameters);
            } else {
                $used = $this->pdo->prepare(
                    'UPDATE push_subscriptions SET last_used_at = :at
                     WHERE id = :id AND user_id = :user_id AND p256dh = :p256dh AND auth = :auth'
                );
                $used->execute([':at' => $now] + $subscriptionParameters);
            }
            $this->updateReminderState((int) $row['reminder_id'], $now);
            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** Aggregates browser results without discarding success from another device. */
    private function updateReminderState(int $reminderId, string $now): void
    {
        $counts = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'sent'), 0) AS accepted,
                    COALESCE(SUM(status IN ('pending', 'leased', 'retryable')), 0) AS waiting
             FROM task_reminder_deliveries WHERE reminder_id = :id"
        );
        $counts->execute([':id' => $reminderId]);
        $summary = $counts->fetch(PDO::FETCH_ASSOC);
        if ((int) $summary['accepted'] > 0) {
            $update = $this->pdo->prepare(
                "UPDATE task_reminders SET status = 'sent', sent_at = COALESCE(sent_at, :at)
                 WHERE id = :id AND status <> 'cancelled'"
            );
            $update->execute([':at' => $now, ':id' => $reminderId]);
        } elseif ((int) $summary['total'] > 0 && (int) $summary['waiting'] === 0) {
            $update = $this->pdo->prepare(
                "UPDATE task_reminders SET status = 'failed'
                 WHERE id = :id AND status NOT IN ('sent', 'cancelled')"
            );
            $update->execute([':id' => $reminderId]);
        }
    }
}
