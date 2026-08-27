<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use InvalidArgumentException;
use PDOException;
use Throwable;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserRepository $userRepository
    ) {}

    public function showLogin(Request $request): Response
    {
        if ($this->authService->getCurrentUserId() > 0) {
            return Response::redirect('/');
        }

        $authSuccess = $_SESSION['auth_success'] ?? null;
        unset($_SESSION['auth_success']);

        $activeAuthForm = $request->queryString('action') === 'register' ? 'register' : 'login';

        return Response::view('pages/auth', [
            'activeAuthForm' => $activeAuthForm,
            'authErrors' => [],
            'authSuccess' => $authSuccess,
            'oldInput' => ['email' => '', 'username' => ''],
            'csrfToken' => CsrfMiddleware::getToken(),
            'baseUrl' => '/',
        ]);
    }

    public function login(Request $request): Response
    {
        if ($this->authService->getCurrentUserId() > 0) {
            return Response::redirect('/');
        }

        $username = $request->postString('username');
        $password = (string) ($request->post('password') ?? '');
        $oldInput = ['email' => '', 'username' => $username];
        $authErrors = [];

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            $authErrors[] = 'Your session has expired. Please submit the form again.';
        } elseif ($username === '' || $password === '') {
            $authErrors[] = 'Username and password are required.';
        } elseif (!$this->authService->login($username, $password)) {
            $authErrors[] = 'Username or password is incorrect.';
        } else {
            return Response::redirect('/');
        }

        return Response::view('pages/auth', [
            'activeAuthForm' => 'login',
            'authErrors' => $authErrors,
            'authSuccess' => null,
            'oldInput' => $oldInput,
            'csrfToken' => CsrfMiddleware::getToken(),
            'baseUrl' => '/',
        ]);
    }

    public function register(Request $request): Response
    {
        if ($this->authService->getCurrentUserId() > 0) {
            return Response::redirect('/');
        }

        $email = $request->postString('email');
        $username = $request->postString('username');
        $password = (string) ($request->post('password') ?? '');
        $passwordConfirmation = (string) ($request->post('password_confirmation') ?? '');
        $oldInput = ['email' => $email, 'username' => $username];
        $authErrors = [];

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            $authErrors[] = 'Your session has expired. Please submit the form again.';
        } else {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $authErrors[] = 'Please enter a valid email address.';
            }

            if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
                $authErrors[] = 'Username must be 3-50 characters and contain only letters, numbers, or underscores.';
            }

            if (strlen($password) < 8) {
                $authErrors[] = 'Password must contain at least 8 characters.';
            }

            if ($password !== $passwordConfirmation) {
                $authErrors[] = 'Password confirmation does not match.';
            }

            if ($authErrors === [] && $this->userRepository->usernameExists($username)) {
                $authErrors[] = 'This username is already in use.';
            }

            if ($authErrors === [] && $this->userRepository->emailExists($email)) {
                $authErrors[] = 'This email address is already registered.';
            }
        }

        if ($authErrors === []) {
            try {
                $this->authService->register($email, $username, $password);
                $_SESSION['auth_success'] = 'Your account has been created successfully. Please log in.';

                return Response::redirect('/auth?action=login');
            } catch (PDOException) {
                $authErrors[] = 'Registration could not be completed. Please try again.';
            }
        }

        return Response::view('pages/auth', [
            'activeAuthForm' => 'register',
            'authErrors' => $authErrors,
            'authSuccess' => null,
            'oldInput' => $oldInput,
            'csrfToken' => CsrfMiddleware::getToken(),
            'baseUrl' => '/',
        ]);
    }

    public function changePassword(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();

        if ($userId <= 0) {
            return Response::json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json([
                'success' => false,
                'message' => 'Your session has expired. Please refresh the page and try again.',
            ], 403);
        }

        $currentPassword = (string) ($request->post('current_password') ?? '');
        $newPassword = (string) ($request->post('new_password') ?? '');
        $newPasswordConfirmation = (string) ($request->post('new_password_confirmation') ?? '');

        try {
            if ($currentPassword === '') {
                throw new InvalidArgumentException('Current password is required.');
            }

            $this->authService->validateNewPassword($newPassword, $newPasswordConfirmation);

            if (!$this->authService->changePassword($userId, $currentPassword, $newPassword)) {
                throw new InvalidArgumentException('Current password is incorrect.');
            }

            return Response::json([
                'success' => true,
                'message' => 'Your password has been changed successfully.',
            ]);
        } catch (InvalidArgumentException $exception) {
            return Response::json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return Response::json([
                'success' => false,
                'message' => 'Your password could not be changed. Please try again.',
            ], 500);
        }
    }

    public function logout(Request $request): Response
    {
        if ($request->method() !== 'POST') {
            return new Response('Method Not Allowed', 405, ['Allow' => 'POST']);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::text('Invalid security token.', 403);
        }

        $this->authService->logout();

        return Response::redirect('/auth');
    }
}
