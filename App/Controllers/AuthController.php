<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function logout(string $requestMethod, mixed $csrfToken): Response
    {
        if ($requestMethod !== 'POST') {
            return new Response(
                'Method Not Allowed',
                405,
                ['Allow' => 'POST']
            );
        }

        if (!verifyCsrfToken($csrfToken)) {
            return new Response('Invalid security token.', 403);
        }

        $this->authService->logout();

        return Response::redirect(BASE_URL . 'auth.php');
    }
}
