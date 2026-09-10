<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\TimezoneHelper;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class TaskRepository
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly PDO $pdo) {}

    /**
     * @return array<int, object>
     */
    public function getTasksForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tasks WHERE user_id = :user_id AND is_done = 0 ORDER BY due_at IS NULL, due_at ASC, id DESC'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * @return array<int, object>
     */
    public function getTasksForDate(
        int $userId,
        DateTimeInterface $date,
        ?DateTimeInterface $showCompletedSince = null
    ): array {
        $start = DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
        $end = $start->modify('+1 day');
        $databaseTimezone = TimezoneHelper::getApplicationTimezone();
        $completionFilter = $showCompletedSince !== null
            ? ' AND (is_done = 0 OR completed_at >= :completed_since)'
            : '';

        $sql = "SELECT * FROM tasks WHERE user_id = :user_id AND due_at >= :start_at AND due_at < :end_at{$completionFilter} ORDER BY due_at ASC, id ASC";
        $stmt = $this->pdo->prepare($sql);

        $parameters = [
            ':user_id' => $userId,
            ':start_at' => $start->setTimezone($databaseTimezone)->format(self::DATETIME_FORMAT),
            ':end_at' => $end->setTimezone($databaseTimezone)->format(self::DATETIME_FORMAT),
        ];

        if ($showCompletedSince !== null) {
            $parameters[':completed_since'] = DateTimeImmutable::createFromInterface($showCompletedSince)
                ->setTime(0, 0)
                ->setTimezone(TimezoneHelper::getApplicationTimezone())
                ->format(self::DATETIME_FORMAT);
        }

        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * @return array<int, object>
     */
    public function getTasksWithoutDueDate(int $userId, ?DateTimeInterface $showCompletedSince = null): array
    {
        $completionFilter = $showCompletedSince !== null
            ? ' AND (is_done = 0 OR completed_at >= :completed_since)'
            : '';

        $sql = "SELECT * FROM tasks WHERE user_id = :user_id AND due_at IS NULL{$completionFilter} ORDER BY created_at DESC, id DESC";
        $stmt = $this->pdo->prepare($sql);
        $parameters = [':user_id' => $userId];

        if ($showCompletedSince !== null) {
            $parameters[':completed_since'] = DateTimeImmutable::createFromInterface($showCompletedSince)
                ->setTime(0, 0)
                ->setTimezone(TimezoneHelper::getApplicationTimezone())
                ->format(self::DATETIME_FORMAT);
        }

        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * @return array<int, object>
     */
    public function getCompletedTasksForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, due_at, has_time, completed_at FROM tasks WHERE user_id = :user_id AND is_done = 1 AND completed_at IS NOT NULL ORDER BY completed_at DESC, id DESC'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function findById(int $taskId, int $userId): ?object
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tasks WHERE id = :task_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $userId,
        ]);
        $task = $stmt->fetch(PDO::FETCH_OBJ);

        return $task !== false ? $task : null;
    }

    public function findRecurringByIdForUpdate(int $taskId, int $userId): ?object
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*
             FROM tasks t
             WHERE t.id = :task_id
               AND t.user_id = :user_id
               AND t.repeat_rule_id IS NOT NULL
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $userId,
        ]);
        $task = $stmt->fetch(PDO::FETCH_OBJ);

        return $task !== false ? $task : null;
    }

    /**
     * @return array<int, int>
     */
    public function deleteFutureIncompleteForRule(
        int $repeatRuleId,
        int $userId,
        string $cutoffAt,
        ?int $exceptTaskId = null
    ): array {
        $exceptPredicate = $exceptTaskId === null ? '' : ' AND t.id <> :except_task_id';
        $stmt = $this->pdo->prepare(
            'SELECT t.id
             FROM tasks t
             WHERE t.repeat_rule_id = :repeat_rule_id
               AND t.user_id = :user_id
               AND t.is_done = 0
               AND t.due_at >= :cutoff_at' . $exceptPredicate . '
             ORDER BY t.due_at ASC, t.id ASC
             FOR UPDATE'
        );
        $parameters = [
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
            ':cutoff_at' => $cutoffAt,
        ];

        if ($exceptTaskId !== null) {
            $parameters[':except_task_id'] = $exceptTaskId;
        }

        $stmt->execute($parameters);
        $taskIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $this->deleteOwnedTaskIds($taskIds, $userId);

        return $taskIds;
    }

    /**
     * @param string $cutoffAt Application UTC cutoff in database datetime format.
     * @return array<int, int>
     */
    public function deleteIncompleteFromOccurrence(
        int $repeatRuleId,
        int $userId,
        string $cutoffAt,
        int $occurrenceNumber,
        int $exceptTaskId
    ): array {
        $stmt = $this->pdo->prepare(
            'SELECT t.id
             FROM tasks t
             WHERE t.repeat_rule_id = :repeat_rule_id
               AND t.user_id = :user_id
               AND t.is_done = 0
               AND t.due_at >= :cutoff_at
               AND t.repeat_occurrence_number >= :occurrence_number
               AND t.id <> :except_task_id
             ORDER BY t.repeat_occurrence_number ASC, t.id ASC
             FOR UPDATE'
        );
        $stmt->execute([
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
            ':cutoff_at' => $cutoffAt,
            ':occurrence_number' => $occurrenceNumber,
            ':except_task_id' => $exceptTaskId,
        ]);
        $taskIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $this->deleteOwnedTaskIds($taskIds, $userId);

        return $taskIds;
    }

    public function countRepeatsForRule(int $repeatRuleId, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM tasks t
             WHERE t.repeat_rule_id = :repeat_rule_id
               AND t.user_id = :user_id
               AND t.repeat_occurrence_number > 0'
        );
        $stmt->execute([
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getMaxOccurrenceNumber(int $repeatRuleId, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(t.repeat_occurrence_number), 0)
             FROM tasks t
             WHERE t.repeat_rule_id = :repeat_rule_id
               AND t.user_id = :user_id'
        );
        $stmt->execute([
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, string> Materialized occurrence dates in application UTC.
     */
    public function getOccurrenceDatesForRule(
        int $repeatRuleId,
        int $userId,
        string $startAt,
        string $horizon
    ): array {
        $stmt = $this->pdo->prepare(
            'SELECT t.due_at
             FROM tasks t
             WHERE t.repeat_rule_id = :repeat_rule_id
               AND t.user_id = :user_id
               AND t.due_at >= :start_at
               AND t.due_at <= :horizon'
        );
        $stmt->execute([
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
            ':start_at' => $startAt,
            ':horizon' => $horizon,
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findFirstFutureIncompleteForRule(
        int $repeatRuleId,
        int $userId,
        string $cutoffAt
    ): ?object {
        $stmt = $this->pdo->prepare(
            'SELECT t.*
             FROM tasks t
             WHERE t.repeat_rule_id = :repeat_rule_id
               AND t.user_id = :user_id
               AND t.is_done = 0
               AND t.due_at >= :cutoff_at
             ORDER BY t.due_at ASC, t.id ASC
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            ':repeat_rule_id' => $repeatRuleId,
            ':user_id' => $userId,
            ':cutoff_at' => $cutoffAt,
        ]);
        $task = $stmt->fetch(PDO::FETCH_OBJ);

        return $task !== false ? $task : null;
    }

    public function attachToRule(
        int $taskId,
        int $userId,
        int $repeatRuleId,
        int $occurrenceNumber
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE tasks t
             SET t.repeat_rule_id = :repeat_rule_id,
                 t.repeat_occurrence_number = :occurrence_number
             WHERE t.id = :task_id
               AND t.user_id = :user_id
               AND EXISTS (
                   SELECT 1
                   FROM task_repeat_rules r
                   WHERE r.id = :owned_repeat_rule_id
                     AND r.user_id = :rule_user_id
               )'
        );
        $stmt->execute([
            ':repeat_rule_id' => $repeatRuleId,
            ':occurrence_number' => $occurrenceNumber,
            ':task_id' => $taskId,
            ':user_id' => $userId,
            ':owned_repeat_rule_id' => $repeatRuleId,
            ':rule_user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function create(
        int $userId,
        string $title,
        ?string $dueAt,
        bool $hasTime,
        ?int $repeatRuleId = null,
        ?int $repeatOccurrenceNumber = null
    ): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tasks (
                title, user_id, repeat_rule_id, repeat_occurrence_number, due_at, has_time
             ) VALUES (
                :title, :user_id, :repeat_rule_id, :repeat_occurrence_number, :due_at, :has_time
             )'
        );
        $stmt->execute([
            ':title' => $title,
            ':user_id' => $userId,
            ':repeat_rule_id' => $repeatRuleId,
            ':repeat_occurrence_number' => $repeatOccurrenceNumber,
            ':due_at' => $dueAt,
            ':has_time' => $hasTime ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $taskId, int $userId, array $data): bool
    {
        $fields = [];
        $params = [
            ':task_id' => $taskId,
            ':user_id' => $userId,
        ];

        foreach ($data as $column => $value) {
            $fields[] = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }

        if ($fields === []) {
            return false;
        }

        $sql = 'UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = :task_id AND user_id = :user_id';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function updateStatus(int $taskId, int $userId, bool $isDone, ?string $completedAt): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tasks SET is_done = :is_done, completed_at = :completed_at WHERE id = :task_id AND user_id = :user_id'
        );

        return $stmt->execute([
            ':is_done' => $isDone ? 1 : 0,
            ':completed_at' => $completedAt,
            ':task_id' => $taskId,
            ':user_id' => $userId,
        ]);
    }

    public function delete(int $taskId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM tasks WHERE id = :task_id AND user_id = :user_id'
        );
        $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function countCompletedTasksForDate(int $userId, DateTimeInterface $date): int
    {
        $start = DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
        $end = $start->modify('+1 day');
        $databaseTimezone = TimezoneHelper::getApplicationTimezone();
        $databaseStart = $start->setTimezone($databaseTimezone);
        $databaseEnd = $end->setTimezone($databaseTimezone);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tasks WHERE user_id = :user_id AND is_done = 1 AND completed_at >= :start_at AND completed_at < :end_at'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':start_at' => $databaseStart->format(self::DATETIME_FORMAT),
            ':end_at' => $databaseEnd->format(self::DATETIME_FORMAT),
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<int, int> $taskIds
     */
    private function deleteOwnedTaskIds(array $taskIds, int $userId): void
    {
        if ($taskIds === []) {
            return;
        }

        $idPlaceholders = [];
        $parameters = [':user_id' => $userId];

        foreach ($taskIds as $index => $taskId) {
            $placeholder = ':task_id_' . $index;
            $idPlaceholders[] = $placeholder;
            $parameters[$placeholder] = $taskId;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM tasks
             WHERE user_id = :user_id
               AND id IN (' . implode(', ', $idPlaceholders) . ')'
        );
        $stmt->execute($parameters);
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
