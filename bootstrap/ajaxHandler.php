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

function parseTaskDueAt(string $dueAtValue): ?DateTimeImmutable
{
    if ($dueAtValue === '') {
        return null;
    }

    $timezone = new DateTimeZone('Asia/Tehran');
    $dueAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $dueAtValue, $timezone);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (
        !$dueAt
        || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        || $dueAt->format('Y-m-d\TH:i') !== $dueAtValue
    ) {
        throw new InvalidArgumentException('Please select a valid task date and time.');
    }

    return $dueAt;
}

function readTaskReminders(string $remindersJson): array
{
    try {
        $reminders = json_decode($remindersJson, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new InvalidArgumentException('The reminder settings are invalid.');
    }

    if (!is_array($reminders) || !array_is_list($reminders)) {
        throw new InvalidArgumentException('The reminder settings are invalid.');
    }

    return $reminders;
}

function readHasTimeValue(): string
{
    $hasTimeValue = isset($_POST['has_time']) && is_string($_POST['has_time'])
        ? $_POST['has_time']
        : '0';

    if (!in_array($hasTimeValue, ['0', '1'], true)) {
        throw new InvalidArgumentException('Invalid task time option.');
    }

    return $hasTimeValue;
}

switch ($_POST['action']) {
    case 'previewReminders':
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dueAtValue = isset($_POST['due_at']) && is_string($_POST['due_at'])
                ? trim($_POST['due_at'])
                : '';
            $hasTimeValue = readHasTimeValue();
            $remindersJson = isset($_POST['reminders']) && is_string($_POST['reminders'])
                ? $_POST['reminders']
                : '[]';
            $dueAt = parseTaskDueAt($dueAtValue);
            $reminders = readTaskReminders($remindersJson);
            $preparedReminders = prepareTaskReminders(
                $reminders,
                $dueAt,
                $hasTimeValue === '1'
            );
            $previewItems = array_map(
                static function (array $reminder): array {
                    /** @var DateTimeImmutable $remindAt */
                    $remindAt = $reminder['remind_at'];

                    return [
                        'notification_at' => $remindAt->format(DateTimeInterface::ATOM),
                        'formatted' => $remindAt->format('M j, Y \a\t h:i A'),
                    ];
                },
                $preparedReminders
            );

            echo json_encode([
                'success' => true,
                'reminders' => $previewItems,
            ], JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }
        break;
    case 'newTask':
        $taskTitle = trim($_POST['task_title'] ?? '');
        $dueAtValue = isset($_POST['due_at']) && is_string($_POST['due_at'])
            ? trim($_POST['due_at'])
            : '';
        $remindersJson = isset($_POST['reminders']) && is_string($_POST['reminders'])
            ? $_POST['reminders']
            : '[]';

        if (mb_strlen($taskTitle) < 3) {
            http_response_code(422);
            diepage("Task title must be at least 3 characters long.");
        }

        if (mb_strlen($taskTitle) > 512) {
            http_response_code(422);
            diepage("Task title must not exceed 512 characters.");
        }

        try {
            $hasTimeValue = readHasTimeValue();
            $dueAt = parseTaskDueAt($dueAtValue);
            $reminders = readTaskReminders($remindersJson);
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            diepage($exception->getMessage());
        }

        if ($hasTimeValue === '1' && $dueAt === null) {
            http_response_code(422);
            diepage("Please set a date before enabling task time.");
        }

        try {
            echo addTask(
                $taskTitle,
                $dueAt,
                $hasTimeValue === '1',
                $reminders
            );
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            diepage($exception->getMessage());
        }
        break;
    default:
        http_response_code(400);
        diepage("Invalid Action");
        break;
}

// var_dump($_POST);
