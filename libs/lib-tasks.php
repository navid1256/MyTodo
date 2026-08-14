<?php

// defined('BASE_PATH') or die("Permision Denied !");
// if (!defined('BASE_PATH')) {
//     echo "Permission Denied !";
//     die();
// }

defined('BASE_PATH') or die("Permission Denied !");

/***Tasks Function***/
function deleteTask(int $task_id)
{
    global $pdo;
    $current_user_id = getCurrentUserId();
    $sql = "DELETE FROM tasks WHERE id = :task_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':task_id' => $task_id,
        ':user_id' => $current_user_id,
    ]);
    return $stmt->rowCount();
}

function prepareTaskReminders(
    array $reminders,
    ?DateTimeInterface $dueAt,
    bool $hasTime
): array {
    if (!$reminders) {
        return [];
    }

    if (count($reminders) > 5) {
        throw new InvalidArgumentException('You can set up to 5 reminders for each task.');
    }

    if ($dueAt === null || !$hasTime) {
        throw new InvalidArgumentException('Please Set Date And Time before adding reminders.');
    }

    $dueDate = DateTimeImmutable::createFromInterface($dueAt);
    $now = new DateTimeImmutable('now', $dueDate->getTimezone());
    $unitMinutes = [
        'minute' => 1,
        'hour' => 60,
        'day' => 1440,
    ];
    $preparedReminders = [];
    $usedOffsets = [];

    foreach (array_values($reminders) as $index => $reminder) {
        $reminderNumber = $index + 1;

        if (!is_array($reminder)) {
            throw new InvalidArgumentException("Reminder {$reminderNumber} is invalid.");
        }

        $value = $reminder['value'] ?? null;

        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("Reminder {$reminderNumber} has an invalid reminder time.");
        }

        $unit = isset($reminder['unit']) && is_string($reminder['unit'])
            ? strtolower(trim($reminder['unit']))
            : '';
        $unit = rtrim($unit, 's');

        if (!isset($unitMinutes[$unit])) {
            throw new InvalidArgumentException("Reminder {$reminderNumber} has an invalid time unit.");
        }

        if ($value === 0 && $unit !== 'minute') {
            throw new InvalidArgumentException("Reminder {$reminderNumber} must use minutes for On due time.");
        }

        $maximumValue = intdiv(525600, $unitMinutes[$unit]);

        if ($value > $maximumValue) {
            throw new InvalidArgumentException("Reminder {$reminderNumber} cannot be more than one year before the due time.");
        }

        $offsetMinutes = $value * $unitMinutes[$unit];

        if (isset($usedOffsets[$offsetMinutes])) {
            throw new InvalidArgumentException('Each reminder must use a different notification time.');
        }

        $remindAt = $dueDate->sub(new DateInterval('PT' . $offsetMinutes . 'M'));

        if ($remindAt <= $now) {
            throw new InvalidArgumentException(
                "Reminder {$reminderNumber} would be scheduled in the past. Choose a later due time or a shorter reminder."
            );
        }

        $usedOffsets[$offsetMinutes] = true;
        $preparedReminders[] = [
            'offset_value' => $value,
            'offset_unit' => $unit,
            'offset_minutes' => $offsetMinutes,
            'remind_at' => $remindAt,
        ];
    }

    return $preparedReminders;
}

function addTask(
    string $taskTitle,
    ?DateTimeInterface $dueAt,
    bool $hasTime = false,
    array $reminders = []
)
{
    global $pdo;
    $current_user_id = getCurrentUserId();
    $preparedReminders = prepareTaskReminders($reminders, $dueAt, $hasTime);

    $pdo->beginTransaction();

    try {
        $sql = "INSERT INTO tasks (title, user_id, due_at, has_time)
                VALUES (:title, :user_id, :due_at, :has_time)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $taskTitle,
            ':user_id' => $current_user_id,
            ':due_at' => $dueAt ? $dueAt->format('Y-m-d H:i:s') : null,
            ':has_time' => $dueAt && $hasTime ? 1 : 0,
        ]);
        $taskInserted = $stmt->rowCount();
        $taskId = (int) $pdo->lastInsertId();

        if ($preparedReminders) {
            $reminderSql = "INSERT INTO task_reminders
                    (task_id, offset_value, offset_unit, remind_at)
                    VALUES (:task_id, :offset_value, :offset_unit, :remind_at)";
            $reminderStatement = $pdo->prepare($reminderSql);

            foreach ($preparedReminders as $reminder) {
                $reminderStatement->execute([
                    ':task_id' => $taskId,
                    ':offset_value' => $reminder['offset_value'],
                    ':offset_unit' => $reminder['offset_unit'],
                    ':remind_at' => $reminder['remind_at']->format('Y-m-d H:i:s'),
                ]);
            }
        }

        $pdo->commit();
        return $taskInserted;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function getTasks()
{
    global $pdo;
    $current_user_id = getCurrentUserId();
    $sql = "SELECT *
            FROM tasks
            WHERE user_id = :user_id
            ORDER BY due_at IS NULL, due_at ASC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $current_user_id]);

    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function getTasksForDate(
    DateTimeInterface $date,
    ?DateTimeInterface $showCompletedSince = null
): array
{
    global $pdo;
    $current_user_id = getCurrentUserId();
    $start = DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    $end = $start->modify('+1 day');
    $completionFilter = $showCompletedSince !== null
        ? ' AND (is_done = 0 OR completed_at >= :completed_since)'
        : '';

    $sql = "SELECT *
            FROM tasks
            WHERE user_id = :user_id
              AND due_at >= :start_at
              AND due_at < :end_at" . $completionFilter . "
            ORDER BY due_at ASC, id ASC";
    $stmt = $pdo->prepare($sql);
    $parameters = [
        ':user_id' => $current_user_id,
        ':start_at' => $start->format('Y-m-d H:i:s'),
        ':end_at' => $end->format('Y-m-d H:i:s'),
    ];

    if ($showCompletedSince !== null) {
        $parameters[':completed_since'] = DateTimeImmutable::createFromInterface($showCompletedSince)
            ->setTime(0, 0)
            ->setTimezone(getApplicationTimezone())
            ->format('Y-m-d H:i:s');
    }

    $stmt->execute($parameters);

    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function getTasksWithoutDueDate(?DateTimeInterface $showCompletedSince = null): array
{
    global $pdo;

    $completionFilter = $showCompletedSince !== null
        ? ' AND (is_done = 0 OR completed_at >= :completed_since)'
        : '';
    $sql = "SELECT *
            FROM tasks
            WHERE user_id = :user_id
              AND due_at IS NULL" . $completionFilter . "
            ORDER BY created_at DESC, id DESC";
    $statement = $pdo->prepare($sql);
    $parameters = [':user_id' => getCurrentUserId()];

    if ($showCompletedSince !== null) {
        $parameters[':completed_since'] = DateTimeImmutable::createFromInterface($showCompletedSince)
            ->setTime(0, 0)
            ->setTimezone(getApplicationTimezone())
            ->format('Y-m-d H:i:s');
    }

    $statement->execute($parameters);

    return $statement->fetchAll(PDO::FETCH_OBJ);
}

function getCurrentUserCompletedTasks(): array
{
    global $pdo;

    $statement = $pdo->prepare(
        "SELECT id, title, due_at, has_time, completed_at
         FROM tasks
         WHERE user_id = :user_id
           AND is_done = 1
           AND completed_at IS NOT NULL
         ORDER BY completed_at DESC, id DESC"
    );
    $statement->execute([':user_id' => getCurrentUserId()]);

    return $statement->fetchAll(PDO::FETCH_OBJ);
}

function toggleCurrentUserTaskCompletion(int $taskId): object
{
    global $pdo;

    if ($taskId < 1) {
        throw new InvalidArgumentException('Invalid task.');
    }

    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            "SELECT id, is_done
             FROM tasks
             WHERE id = :task_id
               AND user_id = :user_id
             FOR UPDATE"
        );
        $statement->execute([
            ':task_id' => $taskId,
            ':user_id' => getCurrentUserId(),
        ]);
        $task = $statement->fetch(PDO::FETCH_OBJ);

        if (!$task) {
            throw new InvalidArgumentException('Task not found.');
        }

        $isDone = !(bool) $task->is_done;
        $completedAt = $isDone
            ? new DateTimeImmutable('now', getApplicationTimezone())
            : null;
        $updateStatement = $pdo->prepare(
            "UPDATE tasks
             SET is_done = :is_done,
                 completed_at = :completed_at
             WHERE id = :task_id
               AND user_id = :user_id"
        );
        $updateStatement->execute([
            ':is_done' => $isDone ? 1 : 0,
            ':completed_at' => $completedAt?->format('Y-m-d H:i:s'),
            ':task_id' => $taskId,
            ':user_id' => getCurrentUserId(),
        ]);
        $pdo->commit();

        return (object) [
            'id' => $taskId,
            'is_done' => $isDone,
            'completed_at' => $completedAt?->format('Y-m-d H:i:s'),
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function countCompletedTasksForDate(DateTimeInterface $date): int
{
    global $pdo;
    $current_user_id = getCurrentUserId();
    $start = DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    $end = $start->modify('+1 day');
    $databaseTimezone = getApplicationTimezone();
    $databaseStart = $start->setTimezone($databaseTimezone);
    $databaseEnd = $end->setTimezone($databaseTimezone);

    $sql = "SELECT COUNT(*)
            FROM tasks
            WHERE user_id = :user_id
              AND is_done = 1
              AND completed_at >= :start_at
              AND completed_at < :end_at";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $current_user_id,
        ':start_at' => $databaseStart->format('Y-m-d H:i:s'),
        ':end_at' => $databaseEnd->format('Y-m-d H:i:s'),
    ]);

    return (int) $stmt->fetchColumn();
}
