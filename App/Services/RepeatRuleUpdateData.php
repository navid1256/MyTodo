<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;

final class RepeatRuleUpdateData
{
    /**
     * @param array{
     *     repeat_rule_id: int,
     *     user_id: int,
     *     title: string,
     *     due_at: DateTimeInterface,
     *     has_time: bool,
     *     repeat_config: array<string, mixed>,
     *     reminders: array<int, mixed>,
     *     now?: DateTimeImmutable|null
     * } $data
     */
    public function __construct(array $data)
    {
        $this->repeatRuleId = (int) $data['repeat_rule_id'];
        $this->userId = (int) $data['user_id'];
        $this->title = (string) $data['title'];
        $this->dueAt = $data['due_at'];
        $this->hasTime = (bool) $data['has_time'];
        $this->repeatConfig = $data['repeat_config'];
        $this->reminders = $data['reminders'];
        $this->now = $data['now'] ?? null;
    }

    public readonly int $repeatRuleId;
    public readonly int $userId;
    public readonly string $title;
    public readonly DateTimeInterface $dueAt;
    public readonly bool $hasTime;
    /** @var array<string, mixed> */
    public readonly array $repeatConfig;
    /** @var array<int, mixed> */
    public readonly array $reminders;
    public readonly ?DateTimeImmutable $now;
}
