<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class RepeatRule
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->userId = (int) ($data['user_id'] ?? 0);
        $this->title = (string) ($data['title'] ?? '');
        $this->startAt = $data['start_at'] ?? null;
        $this->hasTime = (bool) ($data['has_time'] ?? false);
        $this->timezone = (string) ($data['timezone'] ?? 'UTC');
        $this->frequency = (string) ($data['frequency'] ?? 'daily');
        $this->intervalValue = (int) ($data['interval_value'] ?? 1);
        $this->intervalUnit = (string) ($data['interval_unit'] ?? 'day');
        $this->weekDays = array_map('intval', $data['week_days'] ?? []);
        $this->monthDay = isset($data['month_day']) ? (int) $data['month_day'] : null;
        $this->monthDayMode = (string) ($data['month_day_mode'] ?? 'clamp');
        $this->endType = (string) ($data['end_type'] ?? 'endlessly');
        $this->endDate = $data['end_date'] ?? null;
        $this->repeatCount = isset($data['repeat_count']) ? (int) $data['repeat_count'] : null;
        $this->generatedRepeats = (int) ($data['generated_repeats'] ?? 0);
        $this->nextOccurrenceAt = $data['next_occurrence_at'] ?? null;
        $this->status = (string) ($data['status'] ?? 'active');
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    public readonly int $id;
    public readonly int $userId;
    public readonly string $title;
    public readonly ?DateTimeImmutable $startAt;
    public readonly bool $hasTime;
    public readonly string $timezone;
    public readonly string $frequency;
    public readonly int $intervalValue;
    public readonly string $intervalUnit;
    /** @var array<int, int> */
    public readonly array $weekDays;
    public readonly ?int $monthDay;
    public readonly string $monthDayMode;
    public readonly string $endType;
    public readonly ?DateTimeImmutable $endDate;
    public readonly ?int $repeatCount;
    public readonly int $generatedRepeats;
    public readonly ?DateTimeImmutable $nextOccurrenceAt;
    public readonly string $status;
    public readonly ?DateTimeImmutable $createdAt;
    public readonly ?DateTimeImmutable $updatedAt;
}
