<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

final class CsrfMiddleware
{
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function isValid(mixed $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function handle(Request $request): ?Response
    {
        $token = $request->post('csrf_token') ?? $request->header('X-CSRF-TOKEN');

        if ($request->method() !== 'POST' || self::isValid($token)) {
            return null;
        }

        return $request->isAjax()
            ? Response::json([
                'success' => false,
                'message' => 'Your session has expired. Please refresh the page and try again.',
            ], 403)
            : Response::text('Invalid security token.', 403);
    }
}
