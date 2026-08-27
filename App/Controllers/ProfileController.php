<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\TimezoneHelper;
use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\ProfileService;
use Exception;

final class ProfileController
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly UserRepository $userRepository
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

        return Response::view('pages/profile', $viewData);
    }

    public function update(Request $request): Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $userProfile = $this->userRepository->getProfile($userId);
        $currentUser = $_SESSION['user'] ?? [];

        $profileAction = $request->postString('profile_action');
        $profileInput = [
            'firstname' => $request->postString('firstname'),
            'lastname' => $request->postString('lastname'),
            'job_title' => $request->postString('job_title'),
            'date_of_birth' => $request->postString('date_of_birth'),
            'gender' => $request->postString('gender'),
            'country' => $request->postString('country'),
            'avatar_url' => trim((string) ($userProfile->avatar_url ?? '')),
        ];

        $avatarAction = $request->postString('avatar_action', 'unchanged');
        $avatarChoiceValue = $request->postString('avatar_choice');
        $avatarData = $request->postString('avatar_data');

        $profileErrors = [];

        if ($profileAction !== 'save') {
            $profileErrors[] = 'Invalid profile request.';
        } elseif (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            $profileErrors[] = 'Your session has expired. Please submit the form again.';
        }

        $userTimezone = TimezoneHelper::getApplicationTimezone();
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
            } catch (Exception $exception) {
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

        return Response::view('pages/profile', $viewData);
    }

    public function showChangePassword(): Response
    {
        return Response::view('pages/change-password', [
            'csrfToken' => CsrfMiddleware::getToken(),
        ]);
    }

    public function showAccountSettings(): Response
    {
        return Response::view('pages/account-settings', [
            'csrfToken' => CsrfMiddleware::getToken(),
            'currentUser' => $_SESSION['user'] ?? [],
        ]);
    }

    /**
     * @param array<int, string> $profileErrors
     * @return array<string, mixed>
     */
    private function buildProfileViewData(
        ?object $userProfile,
        array $currentUser,
        int $userId,
        array $profileErrors,
        ?string $profileSuccess
    ): array{
        $currentUsername = trim((string) ($currentUser['username'] ?? 'User'));
        $profileData = $userProfile ?? (object) [];

        $profileFirstName = trim((string) ($profileData->firstname ?? ''));
        $profileLastName = trim((string) ($profileData->lastname ?? ''));
        $hasFullName = $profileFirstName !== '' && $profileLastName !== '';
        $currentDisplayName = $hasFullName
            ? $profileFirstName . ' ' . $profileLastName
            : $currentUsername;

        $savedAvatarUrl = trim((string) ($profileData->avatar_url ?? ''));
        $avatarUrl = $this->resolveAvatarUrl($savedAvatarUrl);

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
            'csrfToken' => CsrfMiddleware::getToken(),
            'profileFields' => $profileFields,
            'profileErrors' => $profileErrors,
            'profileSuccess' => $profileSuccess,
            'avatarUrl' => $avatarUrl,
            'currentDisplayName' => $currentDisplayName,
            'currentUser' => $currentUser,
            'currentUserId' => $userId,
            'userProfile' => $userProfile,
        ];
    }

    private function resolveAvatarUrl(string $savedAvatarUrl): string
    {
        if ($savedAvatarUrl === '') {
            return '/assets/img/user-default-avatar.webp';
        }

        if (preg_match('#^(?:https?://|data:)#i', $savedAvatarUrl)) {
            return $savedAvatarUrl;
        }

        return '/' . ltrim($savedAvatarUrl, '/');
    }
}
