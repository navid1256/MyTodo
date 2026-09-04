<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AvatarStorageException;
use App\Exceptions\ProfileUpdateException;
use App\Helpers\TimezoneHelper;
use App\Helpers\AvatarHelper;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDOException;

final class ProfileService
{
    public function __construct(private readonly UserRepository $userRepository) {}

    public function getStorageDirectory(): string
    {
        $rootPath = dirname(__DIR__, 2);
        $directory = $rootPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'avatars';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new AvatarStorageException('profile.storage.directory_failed');
        }

        return $directory;
    }

    public function writeAvatarFile(int $userId, string $contents, string $extension): string
    {
        $directory = $this->getStorageDirectory();
        $fileName = sprintf(
            'user-%d-%s.%s',
            $userId,
            bin2hex(random_bytes(8)),
            $extension
        );
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;

        if (file_put_contents($absolutePath, $contents, LOCK_EX) === false) {
            throw new AvatarStorageException('profile.storage.save_failed');
        }

        return 'storage/avatars/' . $fileName;
    }

    public function storeAvatar(int $userId, string $action, int $choice, string $dataUrl): string
    {
        if ($action === 'boring') {
            if ($choice < 1 || $choice > 12) {
                throw new InvalidArgumentException('profile.validation.invalid_avatar');
            }

            $seed = sprintf('user-%d:avatar-%d', $userId, $choice);
            $svg = AvatarHelper::createBoringBeamAvatarSvg($seed);

            return $this->writeAvatarFile($userId, $svg, 'svg');
        }

        if ($action !== 'upload') {
            throw new InvalidArgumentException('profile.validation.invalid_avatar_option');
        }

        if (strlen($dataUrl) > 2_500_000) {
            throw new InvalidArgumentException('profile.validation.cropped_avatar_too_large');
        }

        if (!preg_match('#^data:image/(jpeg|png|webp);base64,#i', $dataUrl)) {
            throw new InvalidArgumentException('profile.validation.invalid_cropped_avatar');
        }

        $encodedImage = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $imageContents = base64_decode($encodedImage, true);

        if ($imageContents === false || strlen($imageContents) > 1_800_000) {
            throw new InvalidArgumentException('profile.validation.invalid_or_large_cropped_avatar');
        }

        $imageInfo = @getimagesizefromstring($imageContents);
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!$imageInfo || !isset($allowedMimeTypes[$imageInfo['mime']])) {
            throw new InvalidArgumentException('profile.validation.invalid_avatar_type');
        }

        if ((int) $imageInfo[0] !== 512 || (int) $imageInfo[1] !== 512) {
            throw new InvalidArgumentException('profile.validation.invalid_avatar_dimensions');
        }

        return $this->writeAvatarFile($userId, $imageContents, $allowedMimeTypes[$imageInfo['mime']]);
    }

    public function deleteAvatar(string $avatarPath): void
    {
        if (!preg_match('#^storage/avatars/[a-zA-Z0-9.-]+$#', $avatarPath)) {
            return;
        }

        $rootPath = dirname(__DIR__, 2);
        $storageDirectory = realpath($this->getStorageDirectory());
        $absolutePath = $rootPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $avatarPath);
        $parentDirectory = realpath(dirname($absolutePath));

        if (
            $storageDirectory !== false
            && $parentDirectory !== false
            && hash_equals($storageDirectory, $parentDirectory)
            && is_file($absolutePath)
        ) {
            unlink($absolutePath);
        }
    }

    /**
     * @param array<string, string> $profileInput
     * @return array<int, string>
     */
    public function validateProfile(
        array $profileInput,
        string $avatarAction,
        string $avatarChoiceValue,
        ?DateTimeZone $userTimezone = null
    ): array {
        $errors = [];
        $tz = $userTimezone ?? TimezoneHelper::getClientTimezone();

        $textFields = [
            'firstname' => 'profile.validation.first_name_too_long',
            'lastname' => 'profile.validation.last_name_too_long',
            'job_title' => 'profile.validation.job_title_too_long',
            'country' => 'profile.validation.country_too_long',
        ];

        foreach ($textFields as $field => $translationKey) {
            if (isset($profileInput[$field]) && mb_strlen($profileInput[$field]) > 100) {
                $errors[] = $translationKey;
            }
        }

        if (
            isset($profileInput['gender'])
            && !in_array($profileInput['gender'], ['', 'male', 'female', 'other'], true)
        ) {
            $errors[] = 'profile.validation.invalid_gender';
        }

        $this->validateBirthDate($profileInput['date_of_birth'] ?? '', $tz, $errors);
        $this->validateAvatarAction($avatarAction, $avatarChoiceValue, $errors);

        return $errors;
    }

    /**
     * @param array<int, string> $errors
     */
    private function validateBirthDate(string $birthDateValue, DateTimeZone $tz, array &$errors): void
    {
        if ($birthDateValue === '') {
            return;
        }

        $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $birthDateValue, $tz);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            !$birthDate
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $birthDate->format('Y-m-d') !== $birthDateValue
        ) {
            $errors[] = 'profile.validation.invalid_birth_date';
        } elseif ($birthDate > new DateTimeImmutable('today', $tz)) {
            $errors[] = 'profile.validation.future_birth_date';
        }
    }

    /**
     * @param array<int, string> $errors
     */
    private function validateAvatarAction(string $avatarAction, string $avatarChoiceValue, array &$errors): void
    {
        if ($avatarAction === 'boring') {
            $choice = (int) $avatarChoiceValue;
            if ($choice < 1 || $choice > 12) {
                $errors[] = 'profile.validation.invalid_boring_avatar';
            }
        } elseif (!in_array($avatarAction, ['unchanged', 'upload'], true)) {
            $errors[] = 'profile.validation.invalid_avatar_action';
        }
    }

    /**
     * @param array<string, string> $profileInput
     */
    public function updateProfile(
        int $userId,
        array $profileInput,
        string $avatarAction = 'unchanged',
        int $avatarChoice = 0,
        string $avatarData = ''
    ): void {
        if ($userId <= 0) {
            throw new InvalidArgumentException('profile.validation.invalid_user');
        }

        $newAvatarPath = '';
        if ($avatarAction !== 'unchanged') {
            $newAvatarPath = $this->storeAvatar($userId, $avatarAction, $avatarChoice, $avatarData);
            $profileInput['avatar_url'] = $newAvatarPath;
        }

        try {
            $this->userRepository->updateProfile($userId, $profileInput);
        } catch (PDOException $exception) {
            if ($newAvatarPath !== '') {
                $this->deleteAvatar($newAvatarPath);
            }

            throw new ProfileUpdateException('profile.update_failed', 0, $exception);
        }
    }
}
