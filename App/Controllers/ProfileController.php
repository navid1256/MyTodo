<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AvatarStorageException;
use App\Exceptions\ProfileUpdateException;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\ProfileService;
use App\Services\UserSettingsService;
use InvalidArgumentException;

final class ProfileController
{
    private const DASHBOARD_LAYOUT = 'layouts/dashboard';

    public function __construct(
        private readonly ProfileService $profileService,
        private readonly UserRepository $userRepository,
        private readonly UserSettingsService $settingsService
    ) {}

    public function show(): Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $userProfile = $this->userRepository->getProfile($userId);
        $currentUser = $_SESSION['user'] ?? [];

        $profileSuccess = $_SESSION['profile_success'] ?? null;
        unset($_SESSION['profile_success']);

        $viewData = $this->buildProfileViewData(
            userProfile: $userProfile,
            currentUser: $currentUser,
            userId: $userId,
            profileErrors: [],
            profileSuccess: $profileSuccess
        );
        $viewData['activeView'] = 'profile';

        return Response::view(self::DASHBOARD_LAYOUT, $viewData);
    }

    public function update(Request $request): Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $userProfile = $this->userRepository->getProfile($userId);
        $currentUser = $_SESSION['user'] ?? [];
        $profileErrors = [];

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            $profileErrors[] = 'Your session has expired. Please submit the form again.';
        }

        $avatarAction = $request->postString('profile_action');
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

        $userTimezone = $this->settingsService->getTimezoneForUser(
            $userId,
            $request->cookieString('mytodo_timezone')
        );
        $validationErrors = $this->profileService->validateProfile(
            $profileInput,
            $avatarAction,
            $avatarChoiceValue,
            $userTimezone
        );

        $profileErrors = array_merge($profileErrors, $validationErrors);

        if ($profileErrors === []) {
            try {
                $this->profileService->updateProfile(
                    $userId,
                    $profileInput,
                    $avatarAction,
                    (int) $avatarChoiceValue,
                    $avatarData
                );

                $_SESSION['profile_success'] = 'Your profile has been saved successfully.';

                return Response::redirect('/profile');
            } catch (AvatarStorageException | InvalidArgumentException | ProfileUpdateException $exception) {
                $profileErrors[] = $exception->getMessage();
            }
        }

        $mergedProfile = (object) array_merge((array) ($userProfile ?? []), $profileInput);

        $viewData = $this->buildProfileViewData(
            userProfile: $mergedProfile,
            currentUser: $currentUser,
            userId: $userId,
            profileErrors: $profileErrors,
            profileSuccess: null
        );
        $viewData['activeView'] = 'profile';

        return Response::view(self::DASHBOARD_LAYOUT, $viewData);
    }

    public function changePassword(): Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $currentUser = $_SESSION['user'] ?? [];
        $userProfile = $this->userRepository->getProfile($userId);
        $displayName = $this->resolveDisplayName($userProfile, $currentUser);
        $avatarUrl = $this->resolveAvatarUrl($userProfile);

        return Response::view(self::DASHBOARD_LAYOUT, [
            'activeView' => 'change-password',
            'currentUser' => $currentUser,
            'userProfile' => $userProfile,
            'currentDisplayName' => $displayName,
            'avatarUrl' => $avatarUrl,
            'csrfToken' => CsrfMiddleware::getToken(),
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
        ?string $profileSuccess
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
            'csrfToken' => CsrfMiddleware::getToken(),
            'baseUrl' => '/',
        ];
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
