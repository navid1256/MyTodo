<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\TaskService;
use App\Services\UserSettingsService;
use DateTimeImmutable;
use DateTimeZone;

final class HomeController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly NotificationService $notificationService,
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository,
        private readonly UserSettingsService $settingsService
    ) {}

    public function index(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $settings = $this->settingsService->getForUser(
            $userId,
            $request->cookieString('mytodo_timezone'),
            $request->header('Accept-Language')
        );
        $clientTimezone = new DateTimeZone($settings['timezone']);
        $today = new DateTimeImmutable('today', $clientTimezone);
        $tomorrow = $today->modify('+1 day');

        $userProfile = $this->userRepository->getProfile($userId);
        $currentUser = $this->authService->getCurrentUser();
        $displayName = $this->resolveDisplayName($userProfile, $currentUser);
        $avatarUrl = $this->resolveAvatarUrl($userProfile);

        return Response::view('layouts/dashboard', [
            'activeView' => 'home',
            'todayTasks' => $this->taskService->getTasksForDate($userId, $today, $today),
            'tomorrowTasks' => $this->taskService->getTasksForDate($userId, $tomorrow, $today),
            'noDateTasks' => $this->taskService->getTasksWithoutDueDate($userId, $today),
            'completedTasksToday' => $this->taskService->countCompletedTasksForDate($userId, $today),
            'sentNotificationCount' => $this->notificationService->countSentNotifications($userId),
            'currentDisplayName' => $displayName,
            'avatarUrl' => $avatarUrl,
            'csrfToken' => CsrfMiddleware::getToken(),
            'renderDate' => $today->format('Y-m-d'),
            'renderTimezone' => $clientTimezone->getName(),
            'timezoneIsPersisted' => $settings['is_persisted'],
            'effectiveLanguage' => $settings['effective_language'],
            'taskTimezone' => $clientTimezone,
            'currentUser' => $currentUser,
            'userProfile' => $userProfile,
        ]);
    }

    private function resolveDisplayName(?object $profile, ?array $user): string
    {
        $firstName = trim((string) ($profile->firstname ?? ''));
        $lastName = trim((string) ($profile->lastname ?? ''));

        if ($firstName !== '' && $lastName !== '') {
            return $firstName . ' ' . $lastName;
        }

        return trim((string) ($user['username'] ?? 'User'));
    }

    private function resolveAvatarUrl(?object $profile): string
    {
        $savedUrl = trim((string) ($profile->avatar_url ?? ''));

        if ($savedUrl === '') {
            return '/assets/img/user-default-avatar.webp';
        }

        return preg_match('#^(?:https?://|data:)#i', $savedUrl)
            ? $savedUrl
            : '/' . ltrim($savedUrl, '/');
    }
}
