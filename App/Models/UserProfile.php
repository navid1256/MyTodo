<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class UserProfile
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->userId = (int) ($data['user_id'] ?? 0);
        $this->firstName = $data['firstname'] ?? $data['first_name'] ?? null;
        $this->lastName = $data['lastname'] ?? $data['last_name'] ?? null;
        $this->jobTitle = $data['job_title'] ?? null;
        $this->dateOfBirth = $data['date_of_birth'] ?? null;
        $this->gender = $data['gender'] ?? null;
        $this->country = $data['country'] ?? null;
        $this->avatarUrl = $data['avatar_url'] ?? null;
    }

    public readonly int $id;
    public readonly int $userId;
    public readonly ?string $firstName;
    public readonly ?string $lastName;
    public readonly ?string $jobTitle;
    public readonly ?DateTimeImmutable $dateOfBirth;
    public readonly ?string $gender;
    public readonly ?string $country;
    public readonly ?string $avatarUrl;
}
