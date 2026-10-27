<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class Task
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->userId = (int) ($data['user_id'] ?? 0);
        $this->title = (string) ($data['title'] ?? '');
        $this->repeatRuleId = isset($data['repeat_rule_id']) ? (int) $data['repeat_rule_id'] : null;
        $this->repeatOccurrenceNumber = isset($data['repeat_occurrence_number'])
            ? (int) $data['repeat_occurrence_number']
            : null;
        $this->isDone = (bool) ($data['is_done'] ?? false);
        $this->completedAt = $data['completed_at'] ?? null;
        $this->dueAt = $data['due_at'] ?? null;
        $this->hasTime = (bool) ($data['has_time'] ?? false);
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public readonly int $id;
    public readonly int $userId;
    public readonly string $title;
    public readonly ?int $repeatRuleId;
    public readonly ?int $repeatOccurrenceNumber;
    public readonly bool $isDone;
    public readonly ?DateTimeImmutable $completedAt;
    public readonly ?DateTimeImmutable $dueAt;
    public readonly bool $hasTime;
    public readonly ?DateTimeImmutable $createdAt;
    public readonly ?DateTimeImmutable $updatedAt;
}
