<?php

declare(strict_types=1);

namespace App\Services;

final class AuthService
{
    public function logout(): void
    {
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
