<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\TimezoneHelper;
use DateTimeImmutable;
use DateTimeInterface;

final class RepeatRuleMapper
{
    private const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @return array{
     *     frequency: string,
     *     interval: int,
     *     unit: string,
     *     week_days: array<int, int>,
     *     month_day: int|null,
     *     month_day_mode: string,
     *     ends: array{type: string, date: string|null, count: int|null}
     * }
     */
    public function toRuleArray(object $storedRule): array
    {
        $weekDays = $storedRule->week_days === null
            ? []
            : json_decode((string) $storedRule->week_days, true, 512, JSON_THROW_ON_ERROR);

        return [
            'frequency' => (string) $storedRule->frequency,
            'interval' => (int) $storedRule->interval_value,
            'unit' => (string) $storedRule->interval_unit,
            'week_days' => is_array($weekDays) ? array_map('intval', $weekDays) : [],
            'month_day' => $storedRule->month_day === null ? null : (int) $storedRule->month_day,
            'month_day_mode' => (string) $storedRule->month_day_mode,
            'ends' => [
                'type' => (string) $storedRule->end_type,
                'date' => $storedRule->end_date === null ? null : (string) $storedRule->end_date,
                'count' => $storedRule->repeat_count === null ? null : (int) $storedRule->repeat_count,
            ],
        ];
    }

    /**
     * @param array<int, object> $templates
     * @return array<int, array{value: int, unit: string}>
     */
    public function normalizeReminderTemplates(array $templates): array
    {
        return array_map(
            static fn(object $template): array => [
                'value' => (int) $template->offset_value,
                'unit' => (string) $template->offset_unit,
            ],
            $templates
        );
    }

    public function toDatabaseDateTime(DateTimeInterface $dateTime): string
    {
        return DateTimeImmutable::createFromInterface($dateTime)
            ->setTimezone(TimezoneHelper::getApplicationTimezone())
            ->format(self::DATABASE_DATETIME_FORMAT);
    }
}
