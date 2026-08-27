<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

final class AuthMiddleware
{
    public function handle(Request $request): ?Response
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0) {
            if ($request->isAjax()) {
                return Response::json([
                    'success' => false,
                    'message' => 'Authentication required.',
                ], 401);
            }

            return Response::redirect('/auth');
        }

        return null;
    }
}
