<?php

declare(strict_types=1);

namespace App\Repositories;

use JsonException;
use InvalidArgumentException;
use PDO;

final class RepeatRuleRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param array{
     *     user_id: int,
     *     title: string,
     *     start_at: string,
     *     has_time: bool,
     *     timezone: string,
     *     frequency: string,
     *     interval: int,
     *     unit: string,
     *     week_days: array<int, int>,
     *     month_day: int|null,
     *     month_day_mode: string,
     *     end_type: string,
     *     end_date: string|null,
     *     repeat_count: int|null,
     *     next_occurrence_at: string|null
     * } $rule
     * @throws JsonException
     */
    public function create(array $rule): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO task_repeat_rules (
                user_id, title, start_at, has_time, timezone, frequency,
                interval_value, interval_unit, week_days, month_day,
                month_day_mode, end_type, end_date, repeat_count, next_occurrence_at
            ) VALUES (
                :user_id, :title, :start_at, :has_time, :timezone, :frequency,
                :interval_value, :interval_unit, :week_days, :month_day,
                :month_day_mode, :end_type, :end_date, :repeat_count, :next_occurrence_at
            )'
        );
        $statement->execute([
            ':user_id' => $rule['user_id'],
            ':title' => $rule['title'],
            ':start_at' => $rule['start_at'],
            ':has_time' => $rule['has_time'] ? 1 : 0,
            ':timezone' => $rule['timezone'],
            ':frequency' => $rule['frequency'],
            ':interval_value' => $rule['interval'],
            ':interval_unit' => $rule['unit'],
            ':week_days' => $rule['week_days'] === []
                ? null
                : json_encode($rule['week_days'], JSON_THROW_ON_ERROR),
            ':month_day' => $rule['month_day'],
            ':month_day_mode' => $rule['month_day_mode'],
            ':end_type' => $rule['end_type'],
            ':end_date' => $rule['end_date'],
            ':repeat_count' => $rule['repeat_count'],
            ':next_occurrence_at' => $rule['next_occurrence_at'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<int, array{offset_value: int, offset_unit: string}> $reminders
     */
    public function saveReminderTemplates(int $repeatRuleId, array $reminders): void
    {
        if ($reminders === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO task_repeat_reminder_templates (repeat_rule_id, offset_value, offset_unit)
             VALUES (:repeat_rule_id, :offset_value, :offset_unit)'
        );

        foreach ($reminders as $reminder) {
            $statement->execute([
                ':repeat_rule_id' => $repeatRuleId,
                ':offset_value' => $reminder['offset_value'],
                ':offset_unit' => $reminder['offset_unit'],
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    public function findDueRuleIds(string $horizon, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM task_repeat_rules
             WHERE status = :status
               AND next_occurrence_at IS NOT NULL
               AND next_occurrence_at <= :horizon
             ORDER BY next_occurrence_at ASC, id ASC
             LIMIT :rule_limit'
        );
        $statement->bindValue(':status', 'active');
        $statement->bindValue(':horizon', $horizon);
        $statement->bindValue(':rule_limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findByIdForUpdate(int $repeatRuleId): ?object
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM task_repeat_rules WHERE id = :repeat_rule_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':repeat_rule_id' => $repeatRuleId]);
        $rule = $statement->fetch(PDO::FETCH_OBJ);

        return $rule !== false ? $rule : null;
    }

    /**
     * @return array<int, object>
     */
    public function getForUser(int $userId, string $status, string $cutoffAt): array
    {
        if (!in_array($status, ['all', 'active', 'paused', 'completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Invalid repeat rule status.');
        }

        $statusPredicate = $status === 'all' ? '' : ' AND r.status = :status';
        $statement = $this->pdo->prepare(
            'SELECT r.*,
                    (SELECT COUNT(*)
                       FROM tasks t
                      WHERE t.repeat_rule_id = r.id
                        AND t.user_id = r.user_id) AS task_count,
                    (SELECT MIN(t.due_at)
                       FROM tasks t
                      WHERE t.repeat_rule_id = r.id
                        AND t.user_id = r.user_id
                        AND t.is_done = 0
                        AND t.due_at >= :cutoff_at) AS first_future_task_at
             FROM task_repeat_rules r
             WHERE r.user_id = :user_id' . $statusPredicate . '
             ORDER BY r.created_at DESC, r.id DESC'
        );
        $parameters = [
            ':user_id' => $userId,
            ':cutoff_at' => $cutoffAt,
        ];

        if ($status !== 'all') {
            $parameters[':status'] = $status;
        }

        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function findByIdForUserForUpdate(int $repeatRuleId, int $userId): ?object
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM task_repeat_rules
             WHERE id = :repeat_rule_id AND user_id = :user_id
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute([
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
        ]);
        $rule = $statement->fetch(PDO::FETCH_OBJ);

        return $rule !== false ? $rule : null;
    }

    public function updateStatus(
        int $repeatRuleId,
        int $userId,
        string $status,
        ?string $nextOccurrenceAt
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE task_repeat_rules
             SET status = :status, next_occurrence_at = :next_occurrence_at
             WHERE id = :repeat_rule_id AND user_id = :user_id'
        );
        $statement->execute([
            ':status' => $status,
            ':next_occurrence_at' => $nextOccurrenceAt,
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    /** Mark the previous rule terminal after a series split. */
    public function completeForSplit(int $repeatRuleId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE task_repeat_rules
             SET status = :status, next_occurrence_at = NULL
             WHERE id = :repeat_rule_id AND user_id = :user_id'
        );
        $statement->execute([
            ':status' => 'completed',
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    /** Update an edited paused rule while preserving its paused cursor semantics. */
    public function updatePausedRule(int $repeatRuleId, int $userId, array $rule): bool
    {
        $rule['next_occurrence_at'] = null;
        $updated = $this->updateRule($repeatRuleId, $userId, $rule);
        $status = $this->pdo->prepare(
            'UPDATE task_repeat_rules
             SET status = :status, next_occurrence_at = NULL
             WHERE id = :repeat_rule_id AND user_id = :user_id'
        );
        $status->execute([
            ':status' => 'paused',
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
        ]);

        return $updated || $status->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $rule
     * @throws JsonException
     */
    public function updateRule(int $repeatRuleId, int $userId, array $rule): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE task_repeat_rules
             SET title = :title,
                 start_at = :start_at,
                 has_time = :has_time,
                 timezone = :timezone,
                 frequency = :frequency,
                 interval_value = :interval_value,
                 interval_unit = :interval_unit,
                 week_days = :week_days,
                 month_day = :month_day,
                 month_day_mode = :month_day_mode,
                 end_type = :end_type,
                 end_date = :end_date,
                 repeat_count = :repeat_count,
                 next_occurrence_at = :next_occurrence_at
             WHERE id = :repeat_rule_id AND user_id = :user_id'
        );
        $statement->execute([
            ':title' => $rule['title'],
            ':start_at' => $rule['start_at'],
            ':has_time' => $rule['has_time'] ? 1 : 0,
            ':timezone' => $rule['timezone'],
            ':frequency' => $rule['frequency'],
            ':interval_value' => $rule['interval'],
            ':interval_unit' => $rule['unit'],
            ':week_days' => $rule['week_days'] === []
                ? null
                : json_encode($rule['week_days'], JSON_THROW_ON_ERROR),
            ':month_day' => $rule['month_day'],
            ':month_day_mode' => $rule['month_day_mode'],
            ':end_type' => $rule['end_type'],
            ':end_date' => $rule['end_date'],
            ':repeat_count' => $rule['repeat_count'],
            ':next_occurrence_at' => $rule['next_occurrence_at'],
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @param array<int, array{offset_value: int, offset_unit: string}> $reminders
     */
    public function replaceReminderTemplates(int $repeatRuleId, int $userId, array $reminders): void
    {
        $deleteStatement = $this->pdo->prepare(
            'DELETE templates
             FROM task_repeat_reminder_templates templates
             INNER JOIN task_repeat_rules r
                     ON r.id = templates.repeat_rule_id
                    AND r.user_id = :user_id
             WHERE templates.repeat_rule_id = :repeat_rule_id'
        );
        $deleteStatement->execute([
            ':user_id' => $userId,
            ':repeat_rule_id' => $repeatRuleId,
        ]);

        if ($reminders === []) {
            return;
        }

        $insertStatement = $this->pdo->prepare(
            'INSERT INTO task_repeat_reminder_templates (repeat_rule_id, offset_value, offset_unit)
             SELECT r.id, :offset_value, :offset_unit
             FROM task_repeat_rules r
             WHERE r.id = :repeat_rule_id AND r.user_id = :user_id'
        );

        foreach ($reminders as $reminder) {
            $insertStatement->execute([
                ':offset_value' => $reminder['offset_value'],
                ':offset_unit' => $reminder['offset_unit'],
                ':repeat_rule_id' => $repeatRuleId,
                ':user_id' => $userId,
            ]);
        }
    }

    /**
     * @return array<int, object>
     */
    public function getReminderTemplates(int $repeatRuleId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT offset_value, offset_unit
             FROM task_repeat_reminder_templates
             WHERE repeat_rule_id = :repeat_rule_id
             ORDER BY id ASC'
        );
        $statement->execute([':repeat_rule_id' => $repeatRuleId]);

        return $statement->fetchAll(PDO::FETCH_OBJ);
    }

    public function updateGenerationState(
        int $repeatRuleId,
        int $userId,
        int $generatedRepeats,
        ?string $nextOccurrenceAt,
        string $status
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE task_repeat_rules
             SET generated_repeats = :generated_repeats,
                 next_occurrence_at = :next_occurrence_at,
                 status = :status
             WHERE id = :repeat_rule_id AND user_id = :user_id'
        );
        $statement->execute([
            ':generated_repeats' => $generatedRepeats,
            ':next_occurrence_at' => $nextOccurrenceAt,
            ':status' => $status,
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
        ]);
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
