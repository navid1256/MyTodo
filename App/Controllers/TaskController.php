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
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\TaskService;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Throwable;

final class TaskController
{
    private const DASHBOARD_LAYOUT = 'layouts/dashboard';
    private const AUTH_REQUIRED_MESSAGE = 'Authentication required.';
    private const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page and try again.';

    public function __construct(
        private readonly TaskService $taskService,
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService
    ) {}

    public function index(): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $tasks = $this->taskService->getTasksForUser($userId);
        $currentUser = $this->authService->getCurrentUser();
        $userProfile = $this->userRepository->getProfile($userId);
        $clientToday = new DateTimeImmutable('today', TimezoneHelper::getClientTimezone());
        $completedTodayCount = $this->taskService->countCompletedTasksForDate($userId, $clientToday);
        $sentCount = $this->notificationService->countSentNotifications($userId);

        return Response::view(self::DASHBOARD_LAYOUT, [
            'activeView' => 'manage-tasks',
            'tasks' => $tasks,
            'completedTasksToday' => $completedTodayCount,
            'sentNotificationCount' => $sentCount,
            'currentDisplayName' => $this->resolveDisplayName($userProfile, $currentUser),
            'avatarUrl' => $this->resolveAvatarUrl($userProfile),
            'currentUser' => $currentUser,
            'userProfile' => $userProfile,
            'csrfToken' => CsrfMiddleware::getToken(),
        ]);
    }

    public function showActivity(): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $completedTasks = $this->taskService->getCompletedTasks($userId);
        $currentUser = $this->authService->getCurrentUser();
        $userProfile = $this->userRepository->getProfile($userId);
        $clientToday = new DateTimeImmutable('today', TimezoneHelper::getClientTimezone());
        $completedTodayCount = $this->taskService->countCompletedTasksForDate($userId, $clientToday);
        $sentCount = $this->notificationService->countSentNotifications($userId);

        return Response::view(self::DASHBOARD_LAYOUT, [
            'activeView' => 'activity',
            'completedTasks' => $completedTasks,
            'completedTasksToday' => $completedTodayCount,
            'sentNotificationCount' => $sentCount,
            'currentDisplayName' => $this->resolveDisplayName($userProfile, $currentUser),
            'avatarUrl' => $this->resolveAvatarUrl($userProfile),
            'currentUser' => $currentUser,
            'userProfile' => $userProfile,
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
        if ($taskId === false || $taskId < 1) {
            $response = Response::json(['success' => false, 'message' => 'Invalid task ID.'], 422);
        } else {
            try {
                $updatedTask = $this->taskService->toggleTask($taskId, $this->authService->getCurrentUserId());

                $response = Response::json(['success' => true, 'is_done' => (bool) $updatedTask->is_done]);
            } catch (TaskNotFoundException $exception) {
                $response = Response::json(['success' => false, 'message' => $exception->getMessage()], 404);
            } catch (Throwable $exception) {
                $response = Response::json(['success' => false, 'message' => $exception->getMessage()], 500);
            }
        }

        return $response;
    }

    public function delete(Request $request): Response
    {
        $guardResponse = $this->guardJsonRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $taskId = filter_var($request->post('task_id'), FILTER_VALIDATE_INT);
        if ($taskId === false || $taskId < 1) {
            $response = Response::json(['success' => false, 'message' => 'Invalid task ID.'], 422);
        } else {
            try {
                $this->taskService->deleteTask($taskId, $this->authService->getCurrentUserId());

                $response = Response::json(['success' => true]);
            } catch (TaskNotFoundException $exception) {
                $response = Response::json(['success' => false, 'message' => $exception->getMessage()], 404);
            } catch (Throwable $exception) {
                $response = Response::json(['success' => false, 'message' => $exception->getMessage()], 500);
            }
        }

        return $response;
    }

    private function processTaskCreation(Request $request): Response
    {
        $taskTitle = $request->postString('task_title');
        $dueAtString = $request->postString('due_at');
        $hasTime = $request->postString('has_time') === '1';
        $remindersJson = $request->postString('reminders');

        $reminders = [];
        if ($remindersJson !== '') {
            try {
                $reminders = json_decode($remindersJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return Response::text('Invalid reminders payload.', 422);
            }
        }

        $dueAt = null;
        if ($dueAtString !== '') {
            try {
                $clientTimezone = $this->resolveUserTimezone($request);
                $dueAt = new DateTimeImmutable($dueAtString, $clientTimezone);
            } catch (Throwable) {
                return Response::text('Invalid due date format.', 422);
            }
        }

        try {
            $this->taskService->createTask(
                $this->authService->getCurrentUserId(),
                $taskTitle,
                $dueAt,
                $hasTime,
                is_array($reminders) ? $reminders : []
            );

            $response = Response::text('1', 200);
        } catch (TaskValidationException | ReminderValidationException $exception) {
            $response = Response::text($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            $response = Response::text($exception->getMessage(), 500);
        }

        return $response;
    }

    private function resolveUserTimezone(Request $request): DateTimeZone
    {
        $timezoneCookie = $request->cookieString('mytodo_timezone');

        return TimezoneHelper::getClientTimezone($timezoneCookie !== '' ? $timezoneCookie : null);
    }

    private function guardTextRequest(Request $request): ?Response
    {
        if ($this->authService->getCurrentUserId() === 0) {
            return Response::text(self::AUTH_REQUIRED_MESSAGE, 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::text(self::SESSION_EXPIRED_MESSAGE, 403);
        }

        return null;
    }

    private function guardJsonRequest(Request $request): ?Response
    {
        if ($this->authService->getCurrentUserId() === 0) {
            return Response::json(['success' => false, 'message' => self::AUTH_REQUIRED_MESSAGE], 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => self::SESSION_EXPIRED_MESSAGE], 403);
        }

        return null;
    }

    private function resolveDisplayName(?object $profile, ?array $user): string
    {
        $firstName = trim((string) ($profile->firstname ?? ''));
        $lastName = trim((string) ($profile->lastname ?? ''));

        if ($firstName !== '' && $lastName !== '') {
            return $firstName . ' ' . $lastName;
        }

        return (string) ($user['username'] ?? 'User');
    }

    private function resolveAvatarUrl(?object $profile): string
    {
        $defaultAvatarUrl = '/assets/img/user-default-avatar.webp';
        $savedAvatarUrl = trim((string) ($profile->avatar_url ?? ''));

        if ($savedAvatarUrl === '') {
            return $defaultAvatarUrl;
        }

        return preg_match('#^(?:https?://|data:)#i', $savedAvatarUrl)
            ? $savedAvatarUrl
            : '/' . ltrim($savedAvatarUrl, '/');
    }
}
