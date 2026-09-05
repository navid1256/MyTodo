<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RepeatValidationException;
use DateTimeImmutable;
use DateTimeInterface;

final class RepeatRuleValidator
{
    private const FREQUENCY_UNITS = [
        'daily' => 'day',
        'weekly' => 'week',
        'monthly' => 'month',
    ];
    private const ALLOWED_UNITS = ['day', 'week', 'month'];
    private const ALLOWED_END_TYPES = ['endlessly', 'date', 'count'];

    /**
     * @param array<string, mixed> $payload
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
    public function validate(array $payload, ?DateTimeInterface $startAt): array
    {
        if ($startAt === null) {
            throw new RepeatValidationException('Please set a task date before adding repeat settings.');
        }

        $frequency = $this->readString($payload, 'frequency');
        if (!isset(self::FREQUENCY_UNITS[$frequency]) && $frequency !== 'custom') {
            throw new RepeatValidationException('Choose a valid repeat frequency.');
        }

        $unit = $frequency === 'custom'
            ? $this->readString($payload, 'unit')
            : self::FREQUENCY_UNITS[$frequency];
        if (!in_array($unit, self::ALLOWED_UNITS, true)) {
            throw new RepeatValidationException('Choose a valid repeat interval unit.');
        }

        $interval = $frequency === 'custom'
            ? $this->readInteger($payload['interval'] ?? null)
            : 1;
        if ($interval === null || $interval < 1 || $interval > 999) {
            throw new RepeatValidationException('Repeat Every must be a whole number between 1 and 999.');
        }

        $weekDays = $this->normalizeWeekDays($payload['week_days'] ?? []);
        if ($unit === 'week' && $weekDays === []) {
            throw new RepeatValidationException('Choose at least one weekday for the repeat schedule.');
        }

        [$monthDay, $monthDayMode] = $this->normalizeMonthDay($payload, $unit);
        $ends = $this->normalizeEndRule($payload['ends'] ?? null, $startAt);

        return [
            'frequency' => $frequency,
            'interval' => $interval,
            'unit' => $unit,
            'week_days' => $unit === 'week' ? $weekDays : [],
            'month_day' => $monthDay,
            'month_day_mode' => $monthDayMode,
            'ends' => $ends,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function normalizeWeekDays(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RepeatValidationException('Repeat weekdays are invalid.');
        }

        $weekDays = [];
        foreach ($value as $weekDay) {
            $normalizedWeekDay = $this->readInteger($weekDay);
            if ($normalizedWeekDay === null || $normalizedWeekDay < 0 || $normalizedWeekDay > 6) {
                throw new RepeatValidationException('Repeat weekdays are invalid.');
            }

            $weekDays[$normalizedWeekDay] = $normalizedWeekDay;
        }

        ksort($weekDays);

        return array_values($weekDays);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: int|null, 1: string}
     */
    private function normalizeMonthDay(array $payload, string $unit): array
    {
        if ($unit !== 'month') {
            return [null, 'clamp'];
        }

        $mode = $this->readString($payload, 'month_day_mode');
        if ($mode === 'last_day') {
            return [null, 'last_day'];
        }

        if ($mode !== 'clamp') {
            throw new RepeatValidationException('Choose a valid monthly repeat mode.');
        }

        $monthDay = $this->readInteger($payload['month_day'] ?? null);
        if ($monthDay === null || $monthDay < 1 || $monthDay > 31) {
            throw new RepeatValidationException('Choose a valid day of the month.');
        }

        return [$monthDay, 'clamp'];
    }

    /**
     * @return array{type: string, date: string|null, count: int|null}
     */
    private function normalizeEndRule(mixed $value, DateTimeInterface $startAt): array
    {
        $endRule = $this->requireEndRuleArray($value);
        $type = $this->normalizeEndType($endRule);

        return match ($type) {
            'endlessly' => ['type' => $type, 'date' => null, 'count' => null],
            'count' => $this->normalizeCountEndRule($endRule),
            'date' => $this->normalizeDateEndRule($endRule, $startAt),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function requireEndRuleArray(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RepeatValidationException('Choose a valid repeat ending.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $endRule
     */
    private function normalizeEndType(array $endRule): string
    {
        $type = isset($endRule['type']) && is_string($endRule['type'])
            ? trim($endRule['type'])
            : '';

        if (!in_array($type, self::ALLOWED_END_TYPES, true)) {
            throw new RepeatValidationException('Choose a valid repeat ending.');
        }

        return $type;
    }

    /**
     * @param array<string, mixed> $endRule
     * @return array{type: string, date: null, count: int}
     */
    private function normalizeCountEndRule(array $endRule): array
    {
        $count = $this->readInteger($endRule['count'] ?? null);
        if ($count === null || $count < 1 || $count > 9999) {
            throw new RepeatValidationException('Repeat Counts must be a whole number between 1 and 9999.');
        }

        return ['type' => 'count', 'date' => null, 'count' => $count];
    }

    /**
     * @param array<string, mixed> $endRule
     * @return array{type: string, date: string, count: null}
     */
    private function normalizeDateEndRule(array $endRule, DateTimeInterface $startAt): array
    {
        $date = isset($endRule['date']) && is_string($endRule['date'])
            ? trim($endRule['date'])
            : '';
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $startAt->getTimezone());
        $dateErrors = DateTimeImmutable::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);

        if ($endDate === false || $hasDateErrors || $endDate->format('Y-m-d') !== $date) {
            throw new RepeatValidationException('Please select a valid repeat end date.');
        }

        $startDate = DateTimeImmutable::createFromInterface($startAt)->setTime(0, 0);
        if ($endDate <= $startDate) {
            throw new RepeatValidationException('The repeat end date must be after the task date.');
        }

        return ['type' => 'date', 'date' => $date, 'count' => null];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readString(array $payload, string $key): string
    {
        return isset($payload[$key]) && is_string($payload[$key])
            ? strtolower(trim($payload[$key]))
            : '';
    }

    private function readInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
