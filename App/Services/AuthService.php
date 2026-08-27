<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;

final class AuthService
{
    public function __construct(private readonly UserRepository $userRepository) {}

    public function getCurrentUser(): ?array
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user'])
            ? $_SESSION['user']
            : null;
    }

    public function getCurrentUserId(): int
    {
        return (int) ($this->getCurrentUser()['id'] ?? 0);
    }

    public function setAuthenticatedUser(object $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'email' => (string) $user->email,
        ];
    }

    public function verifyPassword(string $password, string $storedPassword): bool
    {
        $passwordInfo = password_get_info($storedPassword);
        $isHashedPassword = $passwordInfo['algoName'] !== 'unknown';

        return $isHashedPassword
            ? password_verify($password, $storedPassword)
            : hash_equals($storedPassword, $password);
    }

    public function login(string $username, string $password): bool
    {
        $user = $this->userRepository->findByUsername($username);

        if ($user === null) {
            return false;
        }

        $storedPassword = (string) ($user->password ?? '');
        if (!$this->verifyPassword($password, $storedPassword)) {
            return false;
        }

        $passwordInfo = password_get_info($storedPassword);
        $isHashedPassword = $passwordInfo['algoName'] !== 'unknown';

        if (!$isHashedPassword || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->userRepository->updatePasswordHash((int) $user->id, $newHash);
        }

        $this->setAuthenticatedUser($user);

        return true;
    }

    public function register(string $email, string $username, string $password): int
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        return $this->userRepository->create($email, $username, $passwordHash);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $storedPassword = $this->userRepository->findPasswordHashById($userId);

        if ($storedPassword === null || !$this->verifyPassword($currentPassword, $storedPassword)) {
            return false;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userRepository->updatePasswordHash($userId, $passwordHash);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);

        return true;
    }

    public function validateNewPassword(string $newPassword, string $confirmation): void
    {
        if (mb_strlen($newPassword) < 8) {
            throw new InvalidArgumentException('New password must contain at least 8 characters.');
        }

        if (strlen($newPassword) > 72) {
            throw new InvalidArgumentException('New password must not exceed 72 bytes.');
        }

        if (!preg_match('/\p{N}/u', $newPassword)) {
            throw new InvalidArgumentException('New password must include at least one number.');
        }

        if (!preg_match('/[^\p{L}\p{N}\s]/u', $newPassword)) {
            throw new InvalidArgumentException('New password must include at least one special character.');
        }

        if (!hash_equals($newPassword, $confirmation)) {
            throw new InvalidArgumentException('New password confirmation does not match.');
        }
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $cookieParameters = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $cookieParameters['path'],
                    'domain' => $cookieParameters['domain'],
                    'secure' => $cookieParameters['secure'],
                    'httponly' => $cookieParameters['httponly'],
                    'samesite' => $cookieParameters['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}
