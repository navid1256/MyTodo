<?php
include_once "init.php";

if (getCurrentUserId() === 0) {
    http_response_code(401);
    diepage("Authentication required.");
}

if (!isAjaxRequest()) {
    diepage("Invalid Request");
}

if (!isset($_POST['action']) || empty($_POST['action'])) {
    diepage("Invalid Action");
}

switch ($_POST['action']) {
    case 'newTask':
        $taskTitle = trim($_POST['task_title'] ?? '');
        $dueAtValue = trim($_POST['due_at'] ?? '');

        if (mb_strlen($taskTitle) < 3) {
            diepage("Task title must be at least 3 characters long.");
        }

        $timezone = new DateTimeZone('Asia/Tehran');
        $dueAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $dueAtValue, $timezone);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            !$dueAt
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $dueAt->format('Y-m-d\TH:i') !== $dueAtValue
        ) {
            diepage("A valid task date and time is required.");
        }

        echo addTask($taskTitle, $dueAt);
        break;
    default:
        diepage("Invalid Action");
        break;
}

// var_dump($_POST);
