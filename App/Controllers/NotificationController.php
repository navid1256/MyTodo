<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\NotificationNotFoundException;
use App\Exceptions\NotificationValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\UserSettingsService;
use DateTimeZone;
use InvalidArgumentException;

final class NotificationController
{
    private const DASHBOARD_LAYOUT = 'layouts/dashboard';
    private const AUTH_REQUIRED_MESSAGE = 'Authentication required.';
    private const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page and try again.';

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository,
        private readonly UserSettingsService $settingsService
    ) {}

    public function index(Request $request): Response
    {
        return $this->notifications($request);
    }

    /**
     * Display Sent Notifications Archive (Header Bell Icon -> /notifications)
     */
    public function notifications(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $sentNotifications = $this->notificationService->getNotificationsForUser($userId, 'sent');
        $sentCount = $this->notificationService->countSentNotifications($userId);
        $currentUser = $this->authService->getCurrentUser();
        $userProfile = $this->userRepository->getProfile($userId);
        $settings = $this->resolveUserSettings($request);
        $userTimezone = new DateTimeZone($settings['timezone']);

        return Response::view(self::DASHBOARD_LAYOUT, [
            'activeView' => 'notifications',
            'sentNotifications' => $sentNotifications,
            'sentNotificationCount' => $sentCount,
            'currentDisplayName' => $this->resolveDisplayName($userProfile, $currentUser),
            'avatarUrl' => $this->resolveAvatarUrl($userProfile),
            'currentUser' => $currentUser,
            'userProfile' => $userProfile,
            'csrfToken' => CsrfMiddleware::getToken(),
            'renderTimezone' => $userTimezone->getName(),
            'effectiveLanguage' => $settings['effective_language'],
            'calendarSystem' => $settings['calendar_system'],
            'notificationTimezone' => $userTimezone,
        ]);
    }

    /**
     * Display Notifications Management Center (Sidebar Navigation -> /messages)
     */
    public function messages(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $allNotifications = $this->notificationService->getNotificationsForUser($userId);
        $sentCount = $this->notificationService->countSentNotifications($userId);
        $currentUser = $this->authService->getCurrentUser();
        $userProfile = $this->userRepository->getProfile($userId);
        $settings = $this->resolveUserSettings($request);
        $userTimezone = new DateTimeZone($settings['timezone']);

        return Response::view(self::DASHBOARD_LAYOUT, [
            'activeView' => 'messages',
            'notifications' => $allNotifications,
            'sentNotificationCount' => $sentCount,
            'currentDisplayName' => $this->resolveDisplayName($userProfile, $currentUser),
            'avatarUrl' => $this->resolveAvatarUrl($userProfile),
            'currentUser' => $currentUser,
            'userProfile' => $userProfile,
            'csrfToken' => CsrfMiddleware::getToken(),
            'renderTimezone' => $userTimezone->getName(),
            'effectiveLanguage' => $settings['effective_language'],
            'calendarSystem' => $settings['calendar_system'],
            'notificationTimezone' => $userTimezone,
        ]);
    }

    public function update(Request $request): Response
    {
        $guardResponse = $this->guardRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $notificationId = filter_var($request->post('notification_id'), FILTER_VALIDATE_INT);
        $offsetValue = filter_var($request->post('offset_value'), FILTER_VALIDATE_INT);
        $offsetUnit = $request->postString('offset_unit');

        if ($notificationId === false || $offsetValue === false || $offsetValue < 0) {
            $response = Response::json(['success' => false, 'message' => 'Please provide valid reminder details.'], 422);
        } else {
            try {
                $updatedReminder = $this->notificationService->updateNotification(
                    $notificationId,
                    $this->authService->getCurrentUserId(),
                    $offsetValue,
                    $offsetUnit
                );

                $response = Response::json([
                    'success' => true,
                    'notification' => [
                        'id' => (int) $updatedReminder->id,
                        'remind_at' => (string) $updatedReminder->remind_at,
                        'offset_value' => (int) $updatedReminder->offset_value,
                        'offset_unit' => (string) $updatedReminder->offset_unit,
                    ],
                ]);
            } catch (NotificationNotFoundException | NotificationValidationException | InvalidArgumentException $exception) {
                $response = $this->handleUpdateError($exception);
            }
        }

        return $response;
    }

    public function cancel(Request $request): Response
    {
        $guardResponse = $this->guardRequest($request);
        if ($guardResponse !== null) {
            return $guardResponse;
        }

        $notificationId = filter_var($request->post('notification_id'), FILTER_VALIDATE_INT);
        if ($notificationId === false || $notificationId < 1) {
            $response = Response::json(['success' => false, 'message' => 'Invalid notification.'], 422);
        } else {
            try {
                $this->notificationService->cancelNotification(
                    $notificationId,
                    $this->authService->getCurrentUserId()
                );

                $response = Response::json(['success' => true]);
            } catch (NotificationNotFoundException $exception) {
                $response = Response::json(['success' => false, 'message' => $exception->getMessage()], 404);
            } catch (NotificationValidationException | InvalidArgumentException $exception) {
                $response = Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
            }
        }

        return $response;
    }

    private function guardRequest(Request $request): ?Response
    {
        if ($this->authService->getCurrentUserId() === 0) {
            return Response::json(['success' => false, 'message' => self::AUTH_REQUIRED_MESSAGE], 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => self::SESSION_EXPIRED_MESSAGE], 403);
        }

        return null;
    }

    private function handleUpdateError(
        NotificationNotFoundException | NotificationValidationException | InvalidArgumentException $exception
    ): Response
    {
        $statusCode = $exception instanceof NotificationNotFoundException ? 404 : 422;

        return Response::json(['success' => false, 'message' => $exception->getMessage()], $statusCode);
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

    /**
     * @return array{
     *     language: string,
     *     effective_language: string,
     *     calendar_system: string,
     *     timezone: string,
     *     is_persisted: bool
     * }
     */
    private function resolveUserSettings(Request $request): array
    {
        return $this->settingsService->getForUser(
            $this->authService->getCurrentUserId(),
            $request->cookieString('mytodo_timezone'),
            $request->header('Accept-Language')
        );
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
