<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\NotificationNotFoundException;
use App\Exceptions\NotificationValidationException;
use App\Exceptions\ReminderValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;
use App\Services\NotificationService;
use Throwable;

final class NotificationController
{
    private const AUTH_REQUIRED_MESSAGE = 'Authentication required.';
    private const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page and try again.';

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuthService $authService
    ) {}

    public function index(): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $notifications = $this->notificationService->getNotificationsForUser($userId);

        return Response::view('pages/notifications', [
            'notifications' => $notifications,
            'csrfToken' => CsrfMiddleware::getToken(),
        ]);
    }

    public function update(Request $request): Response
    {
        $guardResponse = $this->guardRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $notificationId = filter_var($request->post('notification_id'), FILTER_VALIDATE_INT);
        $offsetValueInput = $request->postString('offset_value');
        $offsetUnit = $request->postString('offset_unit');

        if (!$notificationId || !ctype_digit($offsetValueInput)) {
            return Response::json(['success' => false, 'message' => 'Enter a valid notification time.'], 422);
        }

        return $this->processUpdate((int) $notificationId, (int) $offsetValueInput, $offsetUnit);
    }

    public function cancel(Request $request): Response
    {
        $guardResponse = $this->guardRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $notificationId = filter_var($request->post('notification_id'), FILTER_VALIDATE_INT);
        if (!$notificationId) {
            return Response::json(['success' => false, 'message' => 'Invalid notification.'], 422);
        }

        return $this->processCancel((int) $notificationId);
    }

    private function processUpdate(int $notificationId, int $offsetValue, string $offsetUnit): Response
    {
        $userId = $this->authService->getCurrentUserId();

        try {
            $notification = $this->notificationService->updateNotification(
                notificationId: $notificationId,
                userId: $userId,
                offsetValue: $offsetValue,
                offsetUnit: $offsetUnit
            );

            return Response::json([
                'success' => true,
                'message' => 'Notification updated successfully.',
                'notification' => [
                    'id' => $notification->id,
                    'offset_value' => $notification->offset_value,
                    'offset_unit' => $notification->offset_unit,
                    'remind_at' => $notification->remind_at,
                    'formatted_remind_at' => $notification->formatted_remind_at,
                    'status' => $notification->status,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handleUpdateError($exception);
        }
    }

    private function handleUpdateError(Throwable $exception): Response
    {
        $statusCode = match (true) {
            $exception instanceof NotificationNotFoundException => 404,
            $exception instanceof NotificationValidationException,
            $exception instanceof ReminderValidationException => 422,
            default => 500,
        };

        $message = $statusCode === 500
            ? 'The notification could not be updated. Please try again.'
            : $exception->getMessage();

        return Response::json(['success' => false, 'message' => $message], $statusCode);
    }

    private function processCancel(int $notificationId): Response
    {
        $userId = $this->authService->getCurrentUserId();

        try {
            if (!$this->notificationService->cancelNotification($notificationId, $userId)) {
                return Response::json(['success' => false, 'message' => 'Only pending or failed notifications can be cancelled.'], 422);
            }

            return Response::json([
                'success' => true,
                'message' => 'Notification cancelled successfully.',
                'notification' => [
                    'id' => $notificationId,
                    'status' => 'cancelled',
                ],
            ]);
        } catch (Throwable) {
            return Response::json(['success' => false, 'message' => 'The notification could not be cancelled. Please try again.'], 500);
        }
    }

    private function guardRequest(Request $request): ?Response
    {
        if ($this->authService->getCurrentUserId() <= 0) {
            return Response::json(['success' => false, 'message' => self::AUTH_REQUIRED_MESSAGE], 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => self::SESSION_EXPIRED_MESSAGE], 403);
        }

        return null;
    }
}
