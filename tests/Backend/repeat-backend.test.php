<?php

declare(strict_types=1);

use App\Exceptions\RepeatValidationException;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatScheduleCalculator;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertValidationFails(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RepeatValidationException) {
        return;
    }

    throw new RuntimeException($message);
}

$timezone = new DateTimeZone('Asia/Tehran');
$start = new DateTimeImmutable('2026-09-05 09:30:00', $timezone);
$validator = new RepeatRuleValidator();
$calculator = new RepeatScheduleCalculator();
$planner = new RepeatOccurrencePlanner($calculator);

$weeklyRule = $validator->validate([
    'frequency' => 'weekly',
    'interval' => 1,
    'unit' => 'week',
    'repeat_on' => [1, 3],
    'week_days' => [3, 1, 3],
    'month_day' => null,
    'month_day_mode' => 'clamp',
    'ends' => [
        'type' => 'endlessly',
        'date' => '',
        'count' => null,
    ],
], $start);

assertSameValue([1, 3], $weeklyRule['week_days'], 'Weekdays must be normalized and deduplicated.');
assertSameValue(
    '2026-09-07 09:30:00',
    $calculator->nextOccurrence($weeklyRule, $start, $start)->format('Y-m-d H:i:s'),
    'A weekly rule must select the next configured weekday.'
);

$monthlyRule = $validator->validate([
    'frequency' => 'monthly',
    'interval' => 1,
    'unit' => 'month',
    'repeat_on' => 31,
    'week_days' => [],
    'month_day' => 31,
    'month_day_mode' => 'clamp',
    'ends' => [
        'type' => 'count',
        'date' => '',
        'count' => 3,
    ],
], new DateTimeImmutable('2027-01-31 08:00:00', $timezone));

assertSameValue(
    '2027-02-28 08:00:00',
    $calculator->nextOccurrence(
        $monthlyRule,
        new DateTimeImmutable('2027-01-31 08:00:00', $timezone),
        new DateTimeImmutable('2027-01-31 08:00:00', $timezone)
    )->format('Y-m-d H:i:s'),
    'Day 31 must clamp to the final valid day of a shorter month.'
);

$customDailyRule = $validator->validate([
    'frequency' => 'custom',
    'interval' => 3,
    'unit' => 'day',
    'repeat_on' => null,
    'week_days' => [],
    'month_day' => null,
    'month_day_mode' => 'clamp',
    'ends' => [
        'type' => 'date',
        'date' => '2026-09-30',
        'count' => null,
    ],
], $start);

assertSameValue(
    '2026-09-08 09:30:00',
    $calculator->nextOccurrence($customDailyRule, $start, $start)->format('Y-m-d H:i:s'),
    'A custom daily interval must preserve the local wall-clock time.'
);

$countRule = $validator->validate([
    'frequency' => 'daily',
    'interval' => 1,
    'unit' => 'day',
    'week_days' => [],
    'month_day' => null,
    'month_day_mode' => 'clamp',
    'ends' => [
        'type' => 'count',
        'date' => '',
        'count' => 2,
    ],
], $start);
$countPlan = $planner->plan(
    $countRule,
    $start,
    new DateTimeImmutable('2026-09-06 09:30:00', $timezone),
    new DateTimeImmutable('2026-10-05 23:59:59', $timezone),
    0
);

assertSameValue(2, count($countPlan['occurrences']), 'Repeat Counts must create the requested number of repeats after the original task.');
assertSameValue(2, $countPlan['generated_repeats'], 'Generated repeat count must exclude the original task.');
assertSameValue('completed', $countPlan['status'], 'A count-limited rule must complete after all repeats are planned.');
assertSameValue(null, $countPlan['next_occurrence'], 'A completed rule must not retain another occurrence.');

$rollingPlan = $planner->plan(
    $weeklyRule,
    $start,
    new DateTimeImmutable('2026-09-07 09:30:00', $timezone),
    new DateTimeImmutable('2026-10-05 23:59:59', $timezone),
    0
);

assertSameValue('active', $rollingPlan['status'], 'An endless rule must remain active after filling the rolling window.');
assertSameValue(true, $rollingPlan['next_occurrence'] > new DateTimeImmutable('2026-10-05 23:59:59', $timezone), 'The next occurrence must move beyond the generated horizon.');

assertValidationFails(
    static fn() => $validator->validate($weeklyRule, null),
    'Repeat settings must require a task due date.'
);

assertValidationFails(
    static fn() => $validator->validate([
        'frequency' => 'weekly',
        'interval' => 1,
        'unit' => 'week',
        'week_days' => [],
        'month_day' => null,
        'month_day_mode' => 'clamp',
        'ends' => ['type' => 'endlessly', 'date' => '', 'count' => null],
    ], $start),
    'A weekly rule must require at least one weekday.'
);

assertValidationFails(
    static fn() => $validator->validate([
        'frequency' => 'daily',
        'interval' => 1,
        'unit' => 'day',
        'week_days' => [],
        'month_day' => null,
        'month_day_mode' => 'clamp',
        'ends' => ['type' => 'date', 'date' => '2026-09-05', 'count' => null],
    ], $start),
    'A repeat end date must be after the first task date.'
);

echo "repeat-backend tests passed\n";
