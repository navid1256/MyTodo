<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ReminderValidationException;
use App\Exceptions\TaskNotFoundException;
use App\Exceptions\TaskValidationException;
use App\Helpers\TimezoneHelper;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;
use App\Services\TaskService;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Throwable;

final class TaskController
{
    private const AUTH_REQUIRED_MESSAGE = 'Authentication required.';
    private const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page and try again.';

    public function __construct(
        private readonly TaskService $taskService,
        private readonly AuthService $authService
    ) {}

    public function index(): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $tasks = $this->taskService->getTasksForUser($userId);

        return Response::view('pages/manage-tasks', [
            'tasks' => $tasks,
            'csrfToken' => CsrfMiddleware::getToken(),
        ]);
    }

    public function showActivity(): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $completedTasks = $this->taskService->getCompletedTasks($userId);
        $clientToday = new DateTimeImmutable('today', TimezoneHelper::getClientTimezone());
        $completedTodayCount = $this->taskService->countCompletedTasksForDate($userId, $clientToday);

        return Response::view('pages/activity', [
            'completedTasks' => $completedTasks,
            'completedTodayCount' => $completedTodayCount,
            'csrfToken' => CsrfMiddleware::getToken(),
        ]);
    }

    public function create(Request $request): Response
    {
        $guardResponse = $this->guardTextRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        return $this->processTaskCreation($request);
    }

    public function toggle(Request $request): Response
    {
        $guardResponse = $this->guardJsonRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $taskId = filter_var($request->post('task_id'), FILTER_VALIDATE_INT);
        if (!$taskId) {
            return Response::json(['success' => false, 'message' => 'Invalid task.'], 422);
        }

        return $this->processTaskToggle((int) $taskId);
    }

    public function delete(Request $request): Response
    {
        $guardResponse = $this->guardTextRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $taskId = filter_var($request->post('task_id'), FILTER_VALIDATE_INT);
        $userId = $this->authService->getCurrentUserId();

        return ($taskId && $this->taskService->deleteTask((int) $taskId, $userId))
            ? Response::text('1')
            : Response::text('Task not found.', 404);
    }

    private function processTaskCreation(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $taskTitle = $request->postString('task_title');
        $dueAtValue = $request->postString('due_at');
        $hasTime = $request->postString('has_time') === '1';
        $remindersJson = $request->postString('reminders', '[]');

        try {
            if (mb_strlen($taskTitle) < 3) {
                throw new TaskValidationException('Task title must be at least 3 characters long.');
            }

            if (mb_strlen($taskTitle) > 512) {
                throw new TaskValidationException('Task title must not exceed 512 characters.');
            }

            $dueAt = $this->parseDueAt($dueAtValue);
            if ($hasTime && $dueAt === null) {
                throw new TaskValidationException('Please set a date before enabling task time.');
            }

            $reminders = $this->parseRemindersJson($remindersJson);

            $this->taskService->createTask(
                userId: $userId,
                title: $taskTitle,
                dueAt: $dueAt,
                hasTime: $hasTime,
                reminders: $reminders
            );

            return Response::text('1');
        } catch (TaskValidationException | ReminderValidationException $exception) {
            return Response::text($exception->getMessage(), 422);
        } catch (Throwable) {
            return Response::text('The task could not be saved. Please try again.', 500);
        }
    }

    private function processTaskToggle(int $taskId): Response
    {
        $userId = $this->authService->getCurrentUserId();

        try {
            $task = $this->taskService->toggleTask($taskId, $userId);
            $clientToday = new DateTimeImmutable('today', TimezoneHelper::getClientTimezone());
            $completedTodayCount = $this->taskService->countCompletedTasksForDate($userId, $clientToday);

            return Response::json([
                'success' => true,
                'task' => [
                    'id' => $task->id,
                    'is_done' => $task->is_done,
                    'completed_at' => $task->completed_at,
                ],
                'completed_today_count' => $completedTodayCount,
            ]);
        } catch (TaskNotFoundException $exception) {
            return Response::json(['success' => false, 'message' => $exception->getMessage()], 404);
        } catch (Throwable) {
            return Response::json(['success' => false, 'message' => 'The task status could not be updated. Please try again.'], 500);
        }
    }

    private function guardTextRequest(Request $request): ?Response
    {
        if ($this->authService->getCurrentUserId() <= 0) {
            return Response::text(self::AUTH_REQUIRED_MESSAGE, 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::text(self::SESSION_EXPIRED_MESSAGE, 403);
        }

        return null;
    }

    private function guardJsonRequest(Request $request): ?Response
    {
        if ($this->authService->getCurrentUserId() <= 0) {
            return Response::json(['success' => false, 'message' => self::AUTH_REQUIRED_MESSAGE], 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => self::SESSION_EXPIRED_MESSAGE], 403);
        }

        return null;
    }

    private function parseDueAt(string $dueAtValue): ?DateTimeImmutable
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
            throw new TaskValidationException('Please select a valid task date and time.');
        }

        return $dueAt;
    }

    /**
     * @return array<int, mixed>
     */
    private function parseRemindersJson(string $remindersJson): array
    {
        try {
            $reminders = json_decode($remindersJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ReminderValidationException('The reminder settings are invalid.');
        }

        if (!is_array($reminders) || !array_is_list($reminders)) {
            throw new ReminderValidationException('The reminder settings are invalid.');
        }

        return $reminders;
    }
}
