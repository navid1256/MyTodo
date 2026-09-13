<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\RepeatRuleNotFoundException;
use App\Exceptions\RepeatRuleStateException;
use App\Exceptions\RepeatValidationException;
use App\Helpers\TimezoneHelper;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\RepeatService;
use App\Services\UserSettingsService;
use DateTimeImmutable;
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
        private readonly UserSettingsService $settingsService
    ) {}

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
}
