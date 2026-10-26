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
     * Maps a validated schedule to shared persistence fields, leaving ownership and cursor decisions to the caller.
     * @param array{
     *     frequency: string, interval: int, unit: string, week_days: array<int, int>,
     *     month_day: int|null, month_day_mode: string,
     *     ends: array{type: string, date: string|null, count: int|null}
     * } $rule
     * @return array{
     *     title: string, start_at: string, has_time: bool, timezone: string,
     *     frequency: string, interval: int, unit: string, week_days: array<int, int>,
     *     month_day: int|null, month_day_mode: string,
     *     end_type: string, end_date: string|null, repeat_count: int|null
     * }
     */
    public function toPersistenceData(string $title, DateTimeInterface $startAt, bool $hasTime, array $rule): array
    {
        return [
            'title' => trim($title),
            'start_at' => $this->toDatabaseDateTime($startAt),
            'has_time' => $hasTime,
            'timezone' => $startAt->getTimezone()->getName(),
            'frequency' => $rule['frequency'],
            'interval' => $rule['interval'],
            'unit' => $rule['unit'],
            'week_days' => $rule['week_days'],
            'month_day' => $rule['month_day'],
            'month_day_mode' => $rule['month_day_mode'],
            'end_type' => $rule['ends']['type'],
            'end_date' => $rule['ends']['date'],
            'repeat_count' => $rule['ends']['count'],
        ];
    }

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
