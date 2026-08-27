<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\TimezoneHelper;
use DateTimeImmutable;
use DateTimeInterface;
use PDO;

final class TaskRepository
{
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
        $completionFilter = $showCompletedSince !== null
            ? ' AND (is_done = 0 OR completed_at >= :completed_since)'
            : '';

        $sql = "SELECT * FROM tasks WHERE user_id = :user_id AND due_at >= :start_at AND due_at < :end_at{$completionFilter} ORDER BY due_at ASC, id ASC";
        $stmt = $this->pdo->prepare($sql);

        $parameters = [
            ':user_id' => $userId,
            ':start_at' => $start->format('Y-m-d H:i:s'),
            ':end_at' => $end->format('Y-m-d H:i:s'),
        ];

        if ($showCompletedSince !== null) {
            $parameters[':completed_since'] = DateTimeImmutable::createFromInterface($showCompletedSince)
                ->setTime(0, 0)
                ->setTimezone(TimezoneHelper::getApplicationTimezone())
                ->format('Y-m-d H:i:s');
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
                ->format('Y-m-d H:i:s');
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

    public function create(int $userId, string $title, ?string $dueAt, bool $hasTime): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tasks (title, user_id, due_at, has_time) VALUES (:title, :user_id, :due_at, :has_time)'
        );
        $stmt->execute([
            ':title' => $title,
            ':user_id' => $userId,
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
            ':start_at' => $databaseStart->format('Y-m-d H:i:s'),
            ':end_at' => $databaseEnd->format('Y-m-d H:i:s'),
        ]);

        return (int) $stmt->fetchColumn();
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
