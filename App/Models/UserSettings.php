<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class UserSettings
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->userId = (int) ($data['user_id'] ?? 0);
        $this->language = (string) ($data['language'] ?? 'default');
        $this->calendarSystem = (string) ($data['calendar_system'] ?? 'gregorian');
        $this->timezone = (string) ($data['timezone'] ?? 'UTC');
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public readonly int $id;
    public readonly int $userId;
    public readonly string $language;
    public readonly string $calendarSystem;
    public readonly string $timezone;
    public readonly ?DateTimeImmutable $createdAt;
    public readonly ?DateTimeImmutable $updatedAt;
}
