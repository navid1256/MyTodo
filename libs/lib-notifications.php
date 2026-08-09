<?php

defined('BASE_PATH') or die('Permission Denied !');

function getCurrentUserNotifications(): array
{
    global $pdo;

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
            WHERE task.user_id = :user_id
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
    $statement = $pdo->prepare($sql);
    $statement->execute([':user_id' => getCurrentUserId()]);

    return $statement->fetchAll(PDO::FETCH_OBJ);
}

function updateCurrentUserNotification(int $notificationId, int $offsetValue, string $offsetUnit): object
{
    global $pdo;

    if ($notificationId < 1) {
        throw new InvalidArgumentException('Invalid notification.');
    }

    $offsetUnit = strtolower(trim($offsetUnit));
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            "SELECT
                reminder.id,
                reminder.status,
                task.due_at,
                task.has_time
             FROM task_reminders AS reminder
             INNER JOIN tasks AS task ON task.id = reminder.task_id
             WHERE reminder.id = :notification_id
               AND task.user_id = :user_id
             FOR UPDATE"
        );
        $statement->execute([
            ':notification_id' => $notificationId,
            ':user_id' => getCurrentUserId(),
        ]);
        $notification = $statement->fetch(PDO::FETCH_OBJ);

        if (!$notification) {
            throw new InvalidArgumentException('Notification not found.');
        }

        if (!in_array($notification->status, ['pending', 'failed'], true)) {
            throw new InvalidArgumentException('Only pending or failed notifications can be edited.');
        }

        if (empty($notification->due_at) || !(bool) $notification->has_time) {
            throw new InvalidArgumentException('This task no longer has a due date and time.');
        }

        $timezone = new DateTimeZone('Asia/Tehran');
        $dueAt = new DateTimeImmutable((string) $notification->due_at, $timezone);
        $preparedReminders = prepareTaskReminders(
            [[
                'value' => $offsetValue,
                'unit' => $offsetUnit,
            ]],
            $dueAt,
            true
        );
        $preparedReminder = $preparedReminders[0];

        $updateStatement = $pdo->prepare(
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
        $updateStatement->execute([
            ':offset_value' => $preparedReminder['offset_value'],
            ':offset_unit' => $preparedReminder['offset_unit'],
            ':remind_at' => $preparedReminder['remind_at']->format('Y-m-d H:i:s'),
            ':notification_id' => $notificationId,
        ]);
        $pdo->commit();

        return (object) [
            'id' => $notificationId,
            'offset_value' => $preparedReminder['offset_value'],
            'offset_unit' => $preparedReminder['offset_unit'],
            'remind_at' => $preparedReminder['remind_at']->format('Y-m-d H:i:s'),
            'status' => 'pending',
        ];
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception->getCode() === '23000') {
            throw new InvalidArgumentException('This task already has a notification at that time.');
        }

        throw $exception;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function cancelCurrentUserNotification(int $notificationId): bool
{
    global $pdo;

    if ($notificationId < 1) {
        throw new InvalidArgumentException('Invalid notification.');
    }

    $statement = $pdo->prepare(
        "UPDATE task_reminders AS reminder
         INNER JOIN tasks AS task ON task.id = reminder.task_id
         SET reminder.status = 'cancelled'
         WHERE reminder.id = :notification_id
           AND task.user_id = :user_id
           AND reminder.status IN ('pending', 'failed')"
    );
    $statement->execute([
        ':notification_id' => $notificationId,
        ':user_id' => getCurrentUserId(),
    ]);

    return $statement->rowCount() === 1;
}
