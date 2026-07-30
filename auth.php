<?php
include "bootstrap/init.php";

if (getCurrentUserId() > 0) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$authErrors = [];
$oldInput = [
    'email' => '',
    'username' => '',
];
$activeAuthForm = ($_GET['action'] ?? '') === 'register' ? 'register' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $oldInput['username'] = $username;

    if ($action === 'login') {
        $activeAuthForm = 'login';

        if ($username === '' || $password === '') {
            $authErrors[] = 'Username and password are required.';
        } elseif (!loginUser($username, $password)) {
            $authErrors[] = 'Username or password is incorrect.';
        } else {
            header('Location: ' . BASE_URL . 'index.php');
            exit();
        }
    } elseif ($action === 'register') {
        $activeAuthForm = 'register';
        $email = trim($_POST['email'] ?? '');
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';
        $oldInput['email'] = $email;

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

        if (!$authErrors && usernameExists($username)) {
            $authErrors[] = 'This username is already in use.';
        }

        if (!$authErrors && emailExists($email)) {
            $authErrors[] = 'This email address is already registered.';
        }

        if (!$authErrors) {
            try {
                $userId = registerUser($email, $username, $password);
                setAuthenticatedUser((object) [
                    'id' => $userId,
                    'name' => $username,
                    'email' => $email,
                ]);

                header('Location: ' . BASE_URL . 'index.php');
                exit();
            } catch (PDOException $exception) {
                $authErrors[] = 'Registration could not be completed. Please try again.';
            }
        }
    } else {
        $authErrors[] = 'Invalid authentication request.';
    }
}

include "views/views-auth.php";
