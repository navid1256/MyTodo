<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByUsername(string $username): ?object
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, password
             FROM users
             WHERE username = :username
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch(PDO::FETCH_OBJ);

        return $user !== false ? $user : null;
    }

    public function findById(int $userId): ?object
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, password
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch(PDO::FETCH_OBJ);

        return $user !== false ? $user : null;
    }

    public function usernameExists(string $username): bool
    {
        return $this->findByUsername($username) !== null;
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        return (bool) $statement->fetchColumn();
    }

    public function create(string $email, string $username, string $passwordHash): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (username, email, password)
             VALUES (:username, :email, :password)'
        );
        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getProfile(int $userId): ?object
    {
        $statement = $this->pdo->prepare(
            'SELECT
                users.id,
                users.username,
                users.email,
                users_info.firstname,
                users_info.lastname,
                users_info.job_title,
                users_info.date_of_birth,
                users_info.gender,
                users_info.country,
                users_info.avatar_url
             FROM users
             LEFT JOIN users_info ON users_info.user_id = users.id
             WHERE users.id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $profile = $statement->fetch(PDO::FETCH_OBJ);

        return $profile !== false ? $profile : null;
    }

    /**
     * @param array<string, mixed> $profileData
     */
    public function updateProfile(int $userId, array $profileData): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users_info (
                user_id,
                firstname,
                lastname,
                job_title,
                date_of_birth,
                gender,
                country,
                avatar_url
             ) VALUES (
                :user_id,
                :firstname,
                :lastname,
                :job_title,
                :date_of_birth,
                :gender,
                :country,
                :avatar_url
             )
             ON DUPLICATE KEY UPDATE
                firstname = VALUES(firstname),
                lastname = VALUES(lastname),
                job_title = VALUES(job_title),
                date_of_birth = VALUES(date_of_birth),
                gender = VALUES(gender),
                country = VALUES(country),
                avatar_url = VALUES(avatar_url)'
        );

        $statement->execute([
            'user_id' => $userId,
            'firstname' => !empty($profileData['firstname']) ? (string) $profileData['firstname'] : null,
            'lastname' => !empty($profileData['lastname']) ? (string) $profileData['lastname'] : null,
            'job_title' => !empty($profileData['job_title']) ? (string) $profileData['job_title'] : null,
            'date_of_birth' => !empty($profileData['date_of_birth']) ? (string) $profileData['date_of_birth'] : null,
            'gender' => !empty($profileData['gender']) ? (string) $profileData['gender'] : null,
            'country' => !empty($profileData['country']) ? (string) $profileData['country'] : null,
            'avatar_url' => !empty($profileData['avatar_url']) ? (string) $profileData['avatar_url'] : null,
        ]);
    }

    public function findPasswordHashById(int $userId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT password
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $hash = $statement->fetchColumn();

        return is_string($hash) ? $hash : null;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
             SET password = :password
             WHERE id = :id'
        );
        $statement->execute([
            'password' => $passwordHash,
            'id' => $userId,
        ]);
    }
}
