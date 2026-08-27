<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReminderRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @return array<int, object>
     */
    public function getByTaskId(int $taskId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM task_reminders WHERE task_id = :task_id ORDER BY remind_at ASC, id ASC'
        );
        $stmt->execute([':task_id' => $taskId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function create(int $taskId, int $offsetValue, string $offsetUnit, string $remindAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO task_reminders (task_id, offset_value, offset_unit, remind_at) VALUES (:task_id, :offset_value, :offset_unit, :remind_at)'
        );
        $stmt->execute([
            ':task_id' => $taskId,
            ':offset_value' => $offsetValue,
            ':offset_unit' => $offsetUnit,
            ':remind_at' => $remindAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteByTaskId(int $taskId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM task_reminders WHERE task_id = :task_id'
        );
        $stmt->execute([':task_id' => $taskId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<int, object>
     */
    public function getActiveRemindersForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, t.title as task_title, t.due_at as task_due_at, t.has_time as task_has_time
             FROM task_reminders r
             JOIN tasks t ON r.task_id = t.id
             WHERE t.user_id = :user_id AND t.is_done = 0
             ORDER BY r.remind_at ASC'
        );
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
