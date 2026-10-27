<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class User
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->username = (string) ($data['username'] ?? '');
        $this->email = (string) ($data['email'] ?? '');
        $this->passwordHash = (string) ($data['password_hash'] ?? $data['password'] ?? '');
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public readonly int $id;
    public readonly string $username;
    public readonly string $email;
    public readonly string $passwordHash;
    public readonly ?DateTimeImmutable $createdAt;
    public readonly ?DateTimeImmutable $updatedAt;
}
