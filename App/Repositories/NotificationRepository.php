<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NotificationValidationException;
use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @return array<int, object>
     */
    public function getNotificationsForUser(int $userId, ?string $status = null): array
    {
        $statusCondition = $status === null ? '' : ' AND reminder.status = :status';

        $sql = "SELECT
                    reminder.id,
                    reminder.task_id,
                    reminder.offset_value,
                    reminder.offset_unit,
                    reminder.remind_at,
                    reminder.status,
                    reminder.attempt_count,
                    reminder.last_attempt_at,
                    reminder.sent_at,
                    reminder.created_at,
                    reminder.updated_at,
                    task.title AS task_title,
                    task.due_at AS task_due_at
                FROM task_reminders AS reminder
                INNER JOIN tasks AS task ON task.id = reminder.task_id
                WHERE task.user_id = :user_id{$statusCondition}
                ORDER BY
                    CASE reminder.status
                        WHEN 'pending' THEN 1
                        WHEN 'failed' THEN 2
                        WHEN 'sent' THEN 3
                        WHEN 'cancelled' THEN 4
                        ELSE 5
                    END,
                    CASE WHEN reminder.status = 'pending' THEN reminder.remind_at END ASC,
                    reminder.updated_at DESC,
                    reminder.id DESC";

        $statement = $this->pdo->prepare($sql);
        $parameters = [':user_id' => $userId];
        if ($status !== null) {
            $parameters[':status'] = $status;
        }

        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function countSentNotifications(int $userId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM task_reminders AS reminder
             INNER JOIN tasks AS task ON task.id = reminder.task_id
             WHERE task.user_id = :user_id
               AND reminder.status = 'sent'"
        );
        $statement->execute([':user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /** Counts only visible, sent reminders that this user has not read. */
    public function countUnreadNotifications(int $userId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM task_reminders AS reminder
             INNER JOIN tasks AS task ON task.id = reminder.task_id
             WHERE task.user_id = :user_id AND reminder.status = 'sent'
               AND reminder.cleared_at IS NULL AND reminder.read_at IS NULL"
        );
        $statement->execute([':user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    /** Fetches one bounded archive page using immutable send time and ID as the cursor. */
    public function getSentPage(int $userId, ?string $beforeTime, ?int $beforeId, int $limit): array
    {
        $cursorCondition = $beforeTime === null ? '' :
            ' AND (COALESCE(reminder.sent_at, reminder.remind_at) < :before_time
                OR (COALESCE(reminder.sent_at, reminder.remind_at) = :same_time AND reminder.id < :before_id))';
        $statement = $this->pdo->prepare(
            "SELECT reminder.id, reminder.sent_at, reminder.remind_at, reminder.read_at,
                    COALESCE(reminder.sent_at, reminder.remind_at) AS archive_time,
                    task.title AS task_title, task.due_at AS task_due_at
             FROM task_reminders AS reminder
             INNER JOIN tasks AS task ON task.id = reminder.task_id
             WHERE task.user_id = :user_id AND reminder.status = 'sent'
               AND reminder.cleared_at IS NULL{$cursorCondition}
             ORDER BY COALESCE(reminder.sent_at, reminder.remind_at) DESC, reminder.id DESC
             LIMIT :page_limit"
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':page_limit', $limit, PDO::PARAM_INT);
        if ($beforeTime !== null) {
            $statement->bindValue(':before_time', $beforeTime);
            $statement->bindValue(':same_time', $beforeTime);
            $statement->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    /** Marks the user's currently visible sent archive read without modifying delivery state. */
    public function markSentRead(int $userId, string $readAt): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE task_reminders SET read_at = :read_at, updated_at = updated_at
             WHERE status = 'sent' AND cleared_at IS NULL AND read_at IS NULL
               AND task_id IN (SELECT task.id FROM tasks AS task WHERE task.user_id = :user_id)"
        );
        $statement->execute([':read_at' => $readAt, ':user_id' => $userId]);
    }

    /** Hides all owned sent notifications while retaining reminders and delivery deduplication records. */
    public function clearSent(int $userId, string $clearedAt): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE task_reminders SET cleared_at = :cleared_at, updated_at = updated_at
             WHERE status = 'sent' AND cleared_at IS NULL
               AND task_id IN (SELECT task.id FROM tasks AS task WHERE task.user_id = :user_id)"
        );
        $statement->execute([':cleared_at' => $clearedAt, ':user_id' => $userId]);

        return $statement->rowCount();
    }

    public function findTaskReminderForUser(int $notificationId, int $userId): ?object
    {
        $statement = $this->pdo->prepare(
            'SELECT
                reminder.id,
                reminder.status,
                task.due_at,
                task.has_time
             FROM task_reminders AS reminder
             INNER JOIN tasks AS task ON task.id = reminder.task_id
             WHERE reminder.id = :notification_id
               AND task.user_id = :user_id
             FOR UPDATE'
        );
        $statement->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
        ]);
        $notification = $statement->fetch(PDO::FETCH_OBJ);

        return $notification !== false ? $notification : null;
    }

    public function update(int $notificationId, int $offsetValue, string $offsetUnit, string $remindAt): bool
    {
        // The service holds the reminder row lock while this check blocks in-flight edits.
        $inFlight = $this->pdo->prepare(
            "SELECT id FROM task_reminder_deliveries
             WHERE reminder_id = :notification_id AND status = 'leased' LIMIT 1 FOR UPDATE"
        );
        $inFlight->execute([':notification_id' => $notificationId]);
        if ($inFlight->fetchColumn() !== false) {
            throw new NotificationValidationException('A notification currently being sent cannot be edited.');
        }

        // A rescheduled reminder is a new delivery intent; old browser outcomes cannot be reused.
        $clearDeliveries = $this->pdo->prepare(
            'DELETE FROM task_reminder_deliveries WHERE reminder_id = :notification_id'
        );
        $clearDeliveries->execute([':notification_id' => $notificationId]);

        $statement = $this->pdo->prepare(
            "UPDATE task_reminders
             SET offset_value = :offset_value,
                 offset_unit = :offset_unit,
                 remind_at = :remind_at,
                 status = 'pending',
                 attempt_count = 0,
                 last_attempt_at = NULL,
                 sent_at = NULL
             WHERE id = :notification_id"
        );

        return $statement->execute([
            ':offset_value' => $offsetValue,
            ':offset_unit' => $offsetUnit,
            ':remind_at' => $remindAt,
            ':notification_id' => $notificationId,
        ]);
    }

    public function cancel(int $notificationId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE task_reminders AS reminder
             INNER JOIN tasks AS task ON task.id = reminder.task_id
             SET reminder.status = 'cancelled'
             WHERE reminder.id = :notification_id
               AND task.user_id = :user_id
               AND reminder.status IN ('pending', 'failed')"
        );
        $statement->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
