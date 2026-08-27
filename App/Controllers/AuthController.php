<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;
use InvalidArgumentException;
use PDOException;
use Throwable;

final class AuthController
{
    private const AUTH_VIEW = 'pages/auth';

    public function __construct(private readonly AuthService $authService) {}

    public function showLogin(Request $request): Response
    {
        if ($this->authService->getCurrentUserId() > 0) {
            return Response::redirect('/');
        }

        $authSuccess = $_SESSION['auth_success'] ?? null;
        unset($_SESSION['auth_success']);

        $activeAuthForm = $request->queryString('action') === 'register' ? 'register' : 'login';

        return $this->renderAuthView(
            form: $activeAuthForm,
            errors: [],
            success: $authSuccess,
            oldInput: ['email' => '', 'username' => '']
        );
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

        return $this->renderAuthView(
            form: 'login',
            errors: $authErrors,
            success: null,
            oldInput: $oldInput
        );
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

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            $authErrors = ['Your session has expired. Please submit the form again.'];
        } else {
            $authErrors = $this->authService->validateRegisterInput(
                email: $email,
                username: $username,
                password: $password,
                passwordConfirmation: $passwordConfirmation
            );
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

        return $this->renderAuthView(
            form: 'register',
            errors: $authErrors,
            success: null,
            oldInput: $oldInput
        );
    }

    public function changePassword(Request $request): Response
    {
        $userId = $this->authService->getCurrentUserId();
        if ($userId <= 0) {
            return Response::json(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        if (!CsrfMiddleware::isValid($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.'], 403);
        }

        $currentPassword = (string) ($request->post('current_password') ?? '');
        $newPassword = (string) ($request->post('new_password') ?? '');
        $newPasswordConfirmation = (string) ($request->post('new_password_confirmation') ?? '');

        $result = $this->processPasswordChange(
            userId: $userId,
            currentPassword: $currentPassword,
            newPassword: $newPassword,
            confirmation: $newPasswordConfirmation
        );

        return Response::json($result['body'], $result['status']);
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

    /**
     * @return array{status: int, body: array{success: bool, message: string}}
     */
    private function processPasswordChange(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $confirmation
    ): array {
        try {
            if ($currentPassword === '') {
                throw new InvalidArgumentException('Current password is required.');
            }

            $this->authService->validateNewPassword($newPassword, $confirmation);

            if (!$this->authService->changePassword($userId, $currentPassword, $newPassword)) {
                throw new InvalidArgumentException('Current password is incorrect.');
            }

            return ['status' => 200, 'body' => ['success' => true, 'message' => 'Your password has been changed successfully.']];
        } catch (InvalidArgumentException $exception) {
            return ['status' => 422, 'body' => ['success' => false, 'message' => $exception->getMessage()]];
        } catch (Throwable) {
            return ['status' => 500, 'body' => ['success' => false, 'message' => 'Your password could not be changed. Please try again.']];
        }
    }

    /**
     * @param array<int, string> $errors
     * @param array<string, string> $oldInput
     */
    private function renderAuthView(
        string $form,
        array $errors,
        ?string $success,
        array $oldInput
    ): Response {
        return Response::view(self::AUTH_VIEW, [
            'activeAuthForm' => $form,
            'authErrors' => $errors,
            'authSuccess' => $success,
            'oldInput' => $oldInput,
            'csrfToken' => CsrfMiddleware::getToken(),
            'baseUrl' => '/',
        ]);
    }
}
