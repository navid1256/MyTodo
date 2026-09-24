<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class RepeatReminderTemplate
{
    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->repeatRuleId = (int) ($data['repeat_rule_id'] ?? 0);
        $this->offsetValue = (int) ($data['offset_value'] ?? 0);
        $this->offsetUnit = (string) ($data['offset_unit'] ?? 'minute');
        $this->createdAt = $data['created_at'] ?? null;
    }

    public readonly int $id;
    public readonly int $repeatRuleId;
    public readonly int $offsetValue;
    public readonly string $offsetUnit;
    public readonly ?DateTimeImmutable $createdAt;
}
