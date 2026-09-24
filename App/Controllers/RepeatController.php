<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\RepeatRuleNotFoundException;
use App\Exceptions\RepeatRuleStateException;
use App\Exceptions\RepeatValidationException;
use App\Exceptions\ReminderValidationException;
use App\Exceptions\TaskValidationException;
use App\Helpers\TimezoneHelper;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\RepeatService;
use App\Services\TaskService;
use App\Services\UserSettingsService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class RepeatController
{
    private const AUTH_REQUIRED_MESSAGE = 'Authentication required.';
    private const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page and try again.';

    public function __construct(
        private readonly RepeatService $repeatService,
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService,
        private readonly UserSettingsService $settingsService,
        private readonly TaskService $taskService
    ) {}

    public function index(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();
        if ($userId === 0) {
            return Response::redirect('/auth');
        }

        $filter = $request->queryString('filter', 'all');
        if (!in_array($filter, ['all', 'active', 'paused', 'completed', 'cancelled'], true)) {
            $filter = 'all';
        }
        $settings = $this->settingsService->getForUser(
            $userId,
            $request->cookieString('mytodo_timezone'),
            $request->header('Accept-Language')
        );
        $clientTimezone = new DateTimeZone($settings['timezone']);
        $clientToday = new DateTimeImmutable('today', $clientTimezone);

        return Response::view('layouts/dashboard', [
            'activeView' => 'recurring-tasks',
            'repeatRules' => $this->repeatService->getRulesForUser(
                $userId, $filter, new DateTimeImmutable('now', TimezoneHelper::getApplicationTimezone())
            ),
            'repeatFilter' => $filter,
            'completedTasksToday' => $this->taskService->countCompletedTasksForDate($userId, $clientToday),
            'sentNotificationCount' => $this->notificationService->countSentNotifications($userId),
            // The shared dashboard header resolves display name and avatar from these values.
            'currentUser' => $this->authService->getCurrentUser(),
            'userProfile' => $this->userRepository->getProfile($userId),
            'csrfToken' => CsrfMiddleware::getToken(),
            'renderDate' => $clientToday->format('Y-m-d'),
            'renderTimezone' => $clientTimezone->getName(),
            'timezoneIsPersisted' => $settings['is_persisted'],
            'effectiveLanguage' => $settings['effective_language'],
            'calendarSystem' => $settings['calendar_system'],
            'isPartial' => $request->queryString('partial') === '1',
        ]);
    }

    public function pause(Request $request): Response
    {
        return $this->handleLifecycleOperation(
            $request,
            fn(int $repeatRuleId, int $userId, DateTimeImmutable $now): array =>
                $this->repeatService->pauseRule($repeatRuleId, $userId, $now)
        );
    }

    public function resume(Request $request): Response
    {
        return $this->handleLifecycleOperation(
            $request,
            fn(int $repeatRuleId, int $userId, DateTimeImmutable $now): array =>
                $this->repeatService->resumeRule($repeatRuleId, $userId, $now)
        );
    }

    public function cancel(Request $request): Response
    {
        return $this->handleLifecycleOperation(
            $request,
            fn(int $repeatRuleId, int $userId, DateTimeImmutable $now): array =>
                $this->repeatService->cancelRule($repeatRuleId, $userId, $now)
        );
    }

    public function updateTask(Request $request): Response
    {
        $guardResponse = $this->guardJsonRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $taskId = $this->readPositiveId($request, 'task_id');
        $scope = $request->postString('scope');
        if ($taskId === null || !in_array($scope, ['single', 'future'], true)) {
            return Response::json(['success' => false, 'message' => 'Invalid recurring task edit request.'], 422);
        }
        if ($scope === 'future') {
            return Response::json(['success' => false, 'message' => 'This and future tasks are not available yet.'], 422);
        }
        if ($request->postString('repeat_config') !== '') {
            return Response::json(['success' => false, 'message' => 'Repeat settings cannot be changed for one task.'], 422);
        }

        $reminders = [];
        $remindersJson = $request->postString('reminders');
        if ($remindersJson !== '') {
            try {
                $decoded = json_decode($remindersJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return Response::json(['success' => false, 'message' => 'Invalid reminders payload.'], 422);
            }
            if (!is_array($decoded)) {
                return Response::json(['success' => false, 'message' => 'Invalid reminders payload.'], 422);
            }
            $reminders = $decoded;
        }

        $dueAtString = $request->postString('due_at');
        $dueAt = TimezoneHelper::parseCanonicalDateTime($dueAtString, $this->resolveUserTimezone($request));
        if ($dueAt === null) {
            return Response::json(['success' => false, 'message' => 'A valid due date is required.'], 422);
        }

        try {
            $result = $this->repeatService->updateSingleOccurrence(
                $taskId,
                $this->authService->getCurrentUserId(),
                $request->postString('task_title'),
                $dueAt,
                $request->postString('has_time') === '1',
                $reminders
            );

            return Response::json(array_merge(['success' => true], $result));
        } catch (RepeatRuleNotFoundException $exception) {
            return Response::json(['success' => false, 'message' => $exception->getMessage()], 404);
        } catch (TaskValidationException | ReminderValidationException | RepeatValidationException | RepeatRuleStateException $exception) {
            return Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (Throwable) {
            return Response::json(['success' => false, 'message' => 'The recurring task could not be updated.'], 500);
        }
    }

    /**
     * @param callable(int, int, DateTimeImmutable): array<string, mixed> $operation
     */
    private function handleLifecycleOperation(Request $request, callable $operation): Response
    {
        $guardResponse = $this->guardJsonRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $repeatRuleId = $this->readPositiveId($request, 'repeat_rule_id');
        if ($repeatRuleId === null) {
            return Response::json(['success' => false, 'message' => 'Invalid repeat rule ID.'], 422);
        }

        try {
            $result = $operation(
                $repeatRuleId,
                $this->authService->getCurrentUserId(),
                new DateTimeImmutable('now', TimezoneHelper::getApplicationTimezone())
            );

            return Response::json(array_merge(['success' => true], $result));
        } catch (RepeatRuleNotFoundException $exception) {
            return Response::json(['success' => false, 'message' => $exception->getMessage()], 404);
        } catch (RepeatValidationException | RepeatRuleStateException $exception) {
            return Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (Throwable) {
            return Response::json(['success' => false, 'message' => 'The repeat rule could not be updated.'], 500);
        }
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

    private function readPositiveId(Request $request, string $key): ?int
    {
        $value = filter_var($request->post($key), FILTER_VALIDATE_INT);

        return $value !== false && $value > 0 ? $value : null;
    }

    private function resolveUserTimezone(Request $request): DateTimeZone
    {
        $settings = $this->settingsService->getForUser(
            $this->authService->getCurrentUserId(),
            $request->cookieString('mytodo_timezone'),
            $request->header('Accept-Language')
        );

        return new DateTimeZone($settings['timezone']);
    }
}
