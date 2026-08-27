<?php

declare(strict_types=1);

namespace App\Http\Middleware;

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
        if ($request->method() !== 'POST') {
            return null;
        }

        $token = $request->post('csrf_token') ?? $request->header('X-CSRF-TOKEN');

        if (!self::isValid($token)) {
            if ($request->isAjax()) {
                return Response::json([
                    'success' => false,
                    'message' => 'Your session has expired. Please refresh the page and try again.',
                ], 403);
            }

            return Response::text('Invalid security token.', 403);
        }

        return null;
    }
}
