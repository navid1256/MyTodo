<?php
include_once "init.php";

if (getCurrentUserId() === 0) {
    http_response_code(401);
    diepage("Authentication required.");
}

if (!isAjaxRequest()) {
    http_response_code(400);
    diepage("Invalid Request");
}

if (!isset($_POST['action']) || empty($_POST['action'])) {
    http_response_code(400);
    diepage("Invalid Action");
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    diepage("Your session has expired. Please refresh the page and try again.");
}

switch ($_POST['action']) {
    case 'newTask':
        $taskTitle = trim($_POST['task_title'] ?? '');
        $dueAtValue = trim($_POST['due_at'] ?? '');
        $hasTimeValue = isset($_POST['has_time']) && is_string($_POST['has_time'])
            ? $_POST['has_time']
            : '0';

        if (mb_strlen($taskTitle) < 3) {
            http_response_code(422);
            diepage("Task title must be at least 3 characters long.");
        }

        if (mb_strlen($taskTitle) > 512) {
            http_response_code(422);
            diepage("Task title must not exceed 512 characters.");
        }

        if (!in_array($hasTimeValue, ['0', '1'], true)) {
            http_response_code(422);
            diepage("Invalid task time option.");
        }

        $dueAt = null;

        if ($dueAtValue !== '') {
            $timezone = new DateTimeZone('Asia/Tehran');
            $dueAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $dueAtValue, $timezone);
            $dateErrors = DateTimeImmutable::getLastErrors();

            if (
                !$dueAt
                || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $dueAt->format('Y-m-d\TH:i') !== $dueAtValue
            ) {
                http_response_code(422);
                diepage("Please select a valid task date and time.");
            }
        }

        if ($hasTimeValue === '1' && $dueAt === null) {
            http_response_code(422);
            diepage("Please set a date before enabling task time.");
        }

        echo addTask($taskTitle, $dueAt, $hasTimeValue === '1');
        break;
    default:
        http_response_code(400);
        diepage("Invalid Action");
        break;
}

// var_dump($_POST);
