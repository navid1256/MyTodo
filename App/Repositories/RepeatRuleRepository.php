<?php

declare(strict_types=1);

namespace App\Repositories;

use JsonException;
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
        int $generatedRepeats,
        ?string $nextOccurrenceAt,
        string $status
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE task_repeat_rules
             SET generated_repeats = :generated_repeats,
                 next_occurrence_at = :next_occurrence_at,
                 status = :status
             WHERE id = :repeat_rule_id'
        );
        $statement->execute([
            ':generated_repeats' => $generatedRepeats,
            ':next_occurrence_at' => $nextOccurrenceAt,
            ':status' => $status,
            ':repeat_rule_id' => $repeatRuleId,
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
