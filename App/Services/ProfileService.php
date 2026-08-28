<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AvatarStorageException;
use App\Exceptions\ProfileUpdateException;
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
            throw new AvatarStorageException('The avatar storage directory could not be created.');
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
            throw new AvatarStorageException('The profile picture could not be saved.');
        }

        return 'storage/avatars/' . $fileName;
    }

    public function storeAvatar(int $userId, string $action, int $choice, string $dataUrl): string
    {
        if ($action === 'boring') {
            if ($choice < 1 || $choice > 12) {
                throw new InvalidArgumentException('Please choose a valid avatar.');
            }

            $seed = sprintf('user-%d:avatar-%d', $userId, $choice);
            $svg = AvatarHelper::createBoringBeamAvatarSvg($seed);

            return $this->writeAvatarFile($userId, $svg, 'svg');
        }

        if ($action !== 'upload') {
            throw new InvalidArgumentException('Please choose a valid profile picture option.');
        }

        if (strlen($dataUrl) > 2_500_000) {
            throw new InvalidArgumentException('The cropped profile picture is too large.');
        }

        if (!preg_match('#^data:image/(jpeg|png|webp);base64,#i', $dataUrl)) {
            throw new InvalidArgumentException('The cropped profile picture is invalid.');
        }

        $encodedImage = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $imageContents = base64_decode($encodedImage, true);

        if ($imageContents === false || strlen($imageContents) > 1_800_000) {
            throw new InvalidArgumentException('The cropped profile picture is invalid or too large.');
        }

        $imageInfo = @getimagesizefromstring($imageContents);
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!$imageInfo || !isset($allowedMimeTypes[$imageInfo['mime']])) {
            throw new InvalidArgumentException('Only JPEG, PNG, and WebP profile pictures are allowed.');
        }

        if ((int) $imageInfo[0] !== 512 || (int) $imageInfo[1] !== 512) {
            throw new InvalidArgumentException('The cropped profile picture must be 512 by 512 pixels.');
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
        $tz = $userTimezone ?? new DateTimeZone('Asia/Tehran');

        $textFields = [
            'firstname' => 'First name',
            'lastname' => 'Last name',
            'job_title' => 'Job title',
            'country' => 'Country',
        ];

        foreach ($textFields as $field => $label) {
            if (isset($profileInput[$field]) && mb_strlen($profileInput[$field]) > 100) {
                $errors[] = "{$label} must not exceed 100 characters.";
            }
        }

        if (
            isset($profileInput['gender'])
            && !in_array($profileInput['gender'], ['', 'male', 'female', 'other'], true)
        ) {
            $errors[] = 'Please select a valid gender option.';
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
            $errors[] = 'Please enter a valid date of birth.';
        } elseif ($birthDate > new DateTimeImmutable('today', $tz)) {
            $errors[] = 'Date of birth cannot be in the future.';
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
                $errors[] = 'Please choose a valid boring avatar option.';
            }
        } elseif (!in_array($avatarAction, ['unchanged', 'upload'], true)) {
            $errors[] = 'Please select a valid avatar action.';
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
            throw new InvalidArgumentException('Invalid user ID.');
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

            throw new ProfileUpdateException('Your profile could not be saved. Please try again.', 0, $exception);
        }
    }
}
