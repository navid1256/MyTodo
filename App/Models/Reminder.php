<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class Reminder
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->taskId = (int) ($data['task_id'] ?? 0);
        $this->offsetValue = (int) ($data['offset_value'] ?? 0);
        $this->offsetUnit = (string) ($data['offset_unit'] ?? 'minute');
        $this->remindAt = $data['remind_at'] ?? null;
        $this->status = (string) ($data['status'] ?? 'pending');
        $this->attemptCount = (int) ($data['attempt_count'] ?? 0);
        $this->lastAttemptAt = $data['last_attempt_at'] ?? null;
        $this->sentAt = $data['sent_at'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public readonly int $id;
    public readonly int $taskId;
    public readonly int $offsetValue;
    public readonly string $offsetUnit;
    public readonly ?DateTimeImmutable $remindAt;
    public readonly string $status;
    public readonly int $attemptCount;
    public readonly ?DateTimeImmutable $lastAttemptAt;
    public readonly ?DateTimeImmutable $sentAt;
    public readonly ?DateTimeImmutable $createdAt;
    public readonly ?DateTimeImmutable $updatedAt;
}
