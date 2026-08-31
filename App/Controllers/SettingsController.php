<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\UserSettingsValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\UserSettingsService;

final class SettingsController
{
    private const DASHBOARD_LAYOUT = 'layouts/dashboard';
    private const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please refresh the page and try again.';

    public function __construct(
        private readonly UserSettingsService $settingsService,
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository
    ) {}

    public function show(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();
        $currentUser = $this->authService->getCurrentUser();
        $userProfile = $this->userRepository->getProfile($userId);
        $settings = $this->settingsService->getForUser(
            $userId,
            $request->cookieString('mytodo_timezone')
        );

        return Response::view(self::DASHBOARD_LAYOUT, [
            'activeView' => 'account-settings',
            'accountSettings' => $settings,
            'timezoneOptions' => $this->settingsService->getTimezoneOptions(),
            'currentUser' => $currentUser,
            'userProfile' => $userProfile,
            'currentDisplayName' => $this->resolveDisplayName($userProfile, $currentUser),
            'avatarUrl' => $this->resolveAvatarUrl($userProfile),
            'csrfToken' => CsrfMiddleware::getToken(),
            'renderTimezone' => $settings['timezone'],
            'timezoneIsPersisted' => $settings['is_persisted'],
        ]);
    }

    public function update(Request $request): Response
    {
        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json([
                'success' => false,
                'message' => self::SESSION_EXPIRED_MESSAGE,
            ], 403);
        }

        try {
            $settings = $this->settingsService->save(
                $this->authService->getCurrentUserId(),
                $request->postString('language'),
                $request->postString('calendar_system'),
                $request->postString('timezone')
            );

            return Response::json([
                'success' => true,
                'message' => 'Account settings saved.',
                'settings' => $settings,
            ]);
        } catch (UserSettingsValidationException $exception) {
            return Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
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
        $savedAvatarUrl = trim((string) ($profile->avatar_url ?? ''));

        if ($savedAvatarUrl === '') {
            return '/assets/img/user-default-avatar.webp';
        }

        return preg_match('#^(?:https?://|data:)#i', $savedAvatarUrl)
            ? $savedAvatarUrl
            : '/' . ltrim($savedAvatarUrl, '/');
    }
}
