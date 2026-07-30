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

function addTask(string $taskTitle, DateTimeInterface $dueAt)
{
    global $pdo;
    $current_user_id = getCurrentUserId();
    $sql = "INSERT INTO tasks (title, user_id, due_at)
            VALUES (:title, :user_id, :due_at)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => $taskTitle,
        ':user_id' => $current_user_id,
        ':due_at' => $dueAt->format('Y-m-d H:i:s'),
    ]);
    return $stmt->rowCount();
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

function getTasksForDate(DateTimeInterface $date)
{
    global $pdo;
    $current_user_id = getCurrentUserId();
    $start = DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    $end = $start->modify('+1 day');

    $sql = "SELECT *
            FROM tasks
            WHERE user_id = :user_id
              AND due_at >= :start_at
              AND due_at < :end_at
            ORDER BY due_at ASC, id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $current_user_id,
        ':start_at' => $start->format('Y-m-d H:i:s'),
        ':end_at' => $end->format('Y-m-d H:i:s'),
    ]);

    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

function countCompletedTasksForDate(DateTimeInterface $date): int
{
    global $pdo;
    $current_user_id = getCurrentUserId();
    $start = DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    $end = $start->modify('+1 day');

    $sql = "SELECT COUNT(*)
            FROM tasks
            WHERE user_id = :user_id
              AND is_done = 1
              AND due_at >= :start_at
              AND due_at < :end_at";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $current_user_id,
        ':start_at' => $start->format('Y-m-d H:i:s'),
        ':end_at' => $end->format('Y-m-d H:i:s'),
    ]);

    return (int) $stmt->fetchColumn();
}
