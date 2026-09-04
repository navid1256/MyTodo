<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AvatarStorageException;
use App\Exceptions\ProfileUpdateException;
use App\Http\Request;
use App\Http\Response;
use App\Localization\Translator;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\ProfileService;
use App\Services\UserSettingsService;
use DateTimeZone;
use InvalidArgumentException;

final class ProfileController
{
    private const DASHBOARD_LAYOUT = 'layouts/dashboard';

    public function __construct(
        private readonly ProfileService $profileService,
        private readonly UserRepository $userRepository,
        private readonly UserSettingsService $settingsService
    ) {}

    public function show(Request $request): Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $userProfile = $this->userRepository->getProfile($userId);
        $currentUser = $_SESSION['user'] ?? [];
        $settings = $this->resolveUserSettings($userId, $request);
        $translator = $this->createTranslator($settings['effective_language']);

        $profileSuccessKey = $_SESSION['profile_success'] ?? null;
        unset($_SESSION['profile_success']);
        $profileSuccess = is_string($profileSuccessKey)
            ? $translator->translate($profileSuccessKey)
            : null;

        $viewData = $this->buildProfileViewData(
            userProfile: $userProfile,
            currentUser: $currentUser,
            userId: $userId,
            profileErrors: [],
            profileSuccess: $profileSuccess,
            effectiveLanguage: $settings['effective_language']
        );
        $viewData['activeView'] = 'profile';
        $viewData['calendarSystem'] = $settings['calendar_system'];

        return Response::view(self::DASHBOARD_LAYOUT, $viewData);
    }

    public function update(Request $request): Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $userProfile = $this->userRepository->getProfile($userId);
        $currentUser = $_SESSION['user'] ?? [];
        $profileErrorKeys = [];
        $settings = $this->resolveUserSettings($userId, $request);
        $translator = $this->createTranslator($settings['effective_language']);

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            $profileErrorKeys[] = 'profile.validation.session_expired';
        }

        $avatarAction = $request->postString('avatar_action');
        $avatarChoiceValue = $request->postString('avatar_choice');
        $avatarData = $request->postString('avatar_data');

        $profileInput = [
            'firstname' => $request->postString('firstname'),
            'lastname' => $request->postString('lastname'),
            'job_title' => $request->postString('job_title'),
            'date_of_birth' => $request->postString('date_of_birth'),
            'gender' => $request->postString('gender'),
            'country' => $request->postString('country'),
            'avatar_url' => trim((string) ($userProfile->avatar_url ?? '')),
        ];

        $userTimezone = new DateTimeZone($settings['timezone']);
        $validationErrors = $this->profileService->validateProfile(
            $profileInput,
            $avatarAction,
            $avatarChoiceValue,
            $userTimezone
        );

        $profileErrorKeys = array_merge($profileErrorKeys, $validationErrors);

        if ($profileErrorKeys === []) {
            try {
                $this->profileService->updateProfile(
                    $userId,
                    $profileInput,
                    $avatarAction,
                    (int) $avatarChoiceValue,
                    $avatarData
                );

                $_SESSION['profile_success'] = 'profile.saved';

                return Response::redirect('/profile');
            } catch (AvatarStorageException | InvalidArgumentException | ProfileUpdateException $exception) {
                $profileErrorKeys[] = $exception->getMessage();
            }
        }

        $profileErrors = array_map(
            static fn(string $translationKey): string => $translator->translate($translationKey),
            $profileErrorKeys
        );

        $mergedProfile = (object) array_merge((array) ($userProfile ?? []), $profileInput);

        $viewData = $this->buildProfileViewData(
            userProfile: $mergedProfile,
            currentUser: $currentUser,
            userId: $userId,
            profileErrors: $profileErrors,
            profileSuccess: null,
            effectiveLanguage: $settings['effective_language']
        );
        $viewData['activeView'] = 'profile';
        $viewData['calendarSystem'] = $settings['calendar_system'];

        return Response::view(self::DASHBOARD_LAYOUT, $viewData);
    }

    public function changePassword(Request $request): Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $currentUser = $_SESSION['user'] ?? [];
        $userProfile = $this->userRepository->getProfile($userId);
        $displayName = $this->resolveDisplayName($userProfile, $currentUser);
        $avatarUrl = $this->resolveAvatarUrl($userProfile);
        $settings = $this->resolveUserSettings($userId, $request);

        return Response::view(self::DASHBOARD_LAYOUT, [
            'activeView' => 'change-password',
            'currentUser' => $currentUser,
            'userProfile' => $userProfile,
            'currentDisplayName' => $displayName,
            'avatarUrl' => $avatarUrl,
            'csrfToken' => CsrfMiddleware::getToken(),
            'effectiveLanguage' => $settings['effective_language'],
            'calendarSystem' => $settings['calendar_system'],
        ]);
    }

    /**
     * @param array<string, mixed> $currentUser
     * @param array<int, string> $profileErrors
     * @return array<string, mixed>
     */
    private function buildProfileViewData(
        ?object $userProfile,
        array $currentUser,
        int $userId,
        array $profileErrors,
        ?string $profileSuccess,
        string $effectiveLanguage
    ): array {
        $profileData = $userProfile ?? (object) [];
        $currentUsername = trim((string) ($currentUser['username'] ?? 'User'));
        $currentDisplayName = $this->resolveDisplayName($profileData, $currentUser);
        $avatarUrl = $this->resolveAvatarUrl($profileData);

        $profileFields = [
            'firstname' => (string) ($profileData->firstname ?? ''),
            'lastname' => (string) ($profileData->lastname ?? ''),
            'email' => (string) ($profileData->email ?? ($currentUser['email'] ?? '')),
            'username' => (string) ($profileData->username ?? $currentUsername),
            'job_title' => (string) ($profileData->job_title ?? ''),
            'date_of_birth' => (string) ($profileData->date_of_birth ?? ''),
            'gender' => (string) ($profileData->gender ?? ''),
            'country' => (string) ($profileData->country ?? ''),
        ];

        return [
            'userId' => $userId,
            'currentDisplayName' => $currentDisplayName,
            'avatarUrl' => $avatarUrl,
            'profileFields' => $profileFields,
            'profileErrors' => $profileErrors,
            'profileSuccess' => $profileSuccess,
            'effectiveLanguage' => $effectiveLanguage,
            'csrfToken' => CsrfMiddleware::getToken(),
            'baseUrl' => '/',
        ];
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
    private function resolveUserSettings(int $userId, Request $request): array
    {
        return $this->settingsService->getForUser(
            $userId,
            $request->cookieString('mytodo_timezone'),
            $request->header('Accept-Language')
        );
    }

    private function createTranslator(string $effectiveLanguage): Translator
    {
        return new Translator(
            $effectiveLanguage,
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang'
        );
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

    private function resolveAvatarUrl(?object $profileData): string
    {
        $defaultAvatarUrl = '/assets/img/user-default-avatar.webp';
        $savedAvatarUrl = trim((string) ($profileData->avatar_url ?? ''));

        if ($savedAvatarUrl === '') {
            return $defaultAvatarUrl;
        }

        return preg_match('#^(?:https?://|data:)#i', $savedAvatarUrl)
            ? $savedAvatarUrl
            : '/' . ltrim($savedAvatarUrl, '/');
    }
}
