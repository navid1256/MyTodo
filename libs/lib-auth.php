<?php
defined('BASE_PATH') OR die("Permision Denied !");

function getCurrentUser(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user'])
        ? $_SESSION['user']
        : null;
}

function getCurrentUserId(): int
{
    return (int) (getCurrentUser()['id'] ?? 0);
}

function getCurrentUserProfile(): ?object
{
    global $pdo;

    $statement = $pdo->prepare(
        'SELECT
            users.id,
            users.username,
            users.email,
            users_info.firstname,
            users_info.lastname,
            users_info.job_title,
            users_info.date_of_birth,
            users_info.gender
         FROM users
         LEFT JOIN users_info ON users_info.user_id = users.id
         WHERE users.id = :user_id
         LIMIT 1'
    );
    $statement->execute(['user_id' => getCurrentUserId()]);
    $profile = $statement->fetch(PDO::FETCH_OBJ);

    return $profile ?: null;
}

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function findUserByUsername(string $username): ?object
{
    global $pdo;

    $statement = $pdo->prepare(
        'SELECT id, username, email, password
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $statement->execute(['username' => $username]);
    $user = $statement->fetch(PDO::FETCH_OBJ);

    return $user ?: null;
}

function usernameExists(string $username): bool
{
    return findUserByUsername($username) !== null;
}

function emailExists(string $email): bool
{
    global $pdo;

    $statement = $pdo->prepare(
        'SELECT 1
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);

    return (bool) $statement->fetchColumn();
}

function setAuthenticatedUser(object $user): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user->id,
        'username' => $user->username,
        'email' => $user->email,
    ];
}

function loginUser(string $username, string $password): bool
{
    global $pdo;

    $user = findUserByUsername($username);

    if ($user === null) {
        return false;
    }

    $passwordInfo = password_get_info($user->password);
    $isHashedPassword = $passwordInfo['algoName'] !== 'unknown';
    $passwordIsValid = $isHashedPassword
        ? password_verify($password, $user->password)
        : hash_equals($user->password, $password);

    if (!$passwordIsValid) {
        return false;
    }

    if (!$isHashedPassword || password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
        $statement = $pdo->prepare(
            'UPDATE users
             SET password = :password
             WHERE id = :id'
        );
        $statement->execute([
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $user->id,
        ]);
    }

    setAuthenticatedUser($user);

    return true;
}

function registerUser(string $email, string $username, string $password): int
{
    global $pdo;

    $statement = $pdo->prepare(
        'INSERT INTO users (username, email, password)
         VALUES (:username, :email, :password)'
    );
    $statement->execute([
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return (int) $pdo->lastInsertId();
}

function logoutUser(): void
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
