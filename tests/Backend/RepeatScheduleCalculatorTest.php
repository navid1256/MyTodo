<?php

declare(strict_types=1);

use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleMapper;
use App\Services\RepeatScheduleCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepeatScheduleCalculatorTest extends TestCase
{
    /** Defines independent local-time expectations across gaps, folds and unchanged offsets. */
    public static function localTimeCases(): iterable
    {
        $daily = ['unit' => 'day', 'interval' => 1, 'week_days' => [], 'month_day' => null, 'month_day_mode' => 'clamp'];
        yield 'daily spring gap does not shift following days' => ['America/New_York', '2026-03-07 02:30:00', $daily, [
            '2026-03-08T03:30:00-04:00', '2026-03-09T02:30:00-04:00', '2026-03-10T02:30:00-04:00',
        ]];
        yield 'daily spring keeps nine oclock with new UTC offset' => ['America/New_York', '2026-03-07 09:00:00', $daily, [
            '2026-03-08T09:00:00-04:00', '2026-03-09T09:00:00-04:00',
        ]];
        yield 'daily fall keeps nine oclock with new UTC offset' => ['America/New_York', '2026-10-31 09:00:00', $daily, [
            '2026-11-01T09:00:00-05:00', '2026-11-02T09:00:00-05:00',
        ]];
        yield 'daily fall emits the repeated hour once' => ['America/New_York', '2026-10-31 01:30:00', $daily, [
            '2026-11-01T01:30:00-04:00', '2026-11-02T01:30:00-05:00',
        ]];
        yield 'custom two day interval keeps its phase after the gap' => ['America/New_York', '2026-03-06 02:30:00',
            array_replace($daily, ['interval' => 2]), [
                '2026-03-08T03:30:00-04:00', '2026-03-10T02:30:00-04:00',
            ]];
        yield 'half hour DST gap is not hardcoded to one hour' => ['Australia/Lord_Howe', '2026-10-03 02:15:00', $daily, [
            '2026-10-04T02:45:00+11:00', '2026-10-05T02:15:00+11:00',
        ]];
        yield 'no DST Tehran' => ['Asia/Tehran', '2026-03-07 02:30:00', $daily, [
            '2026-03-08T02:30:00+03:30', '2026-03-09T02:30:00+03:30',
        ]];
        yield 'fixed offset timezone has no transition table' => ['+03:30', '2026-03-07 02:30:00', $daily, [
            '2026-03-08T02:30:00+03:30', '2026-03-09T02:30:00+03:30',
        ]];
        yield 'weekly search resets a skipped Sunday gap before Monday' => ['America/New_York', '2026-03-07 02:30:00',
            array_replace($daily, ['unit' => 'week', 'week_days' => [1]]), [
                '2026-03-09T02:30:00-04:00', '2026-03-16T02:30:00-04:00',
            ]];
        yield 'weekly Sunday and Monday do not inherit the gap hour' => ['America/New_York', '2026-03-01 02:30:00',
            array_replace($daily, ['unit' => 'week', 'week_days' => [0, 1]]), [
                '2026-03-02T02:30:00-05:00', '2026-03-08T03:30:00-04:00', '2026-03-09T02:30:00-04:00',
            ]];
        yield 'custom two week interval preserves the anchor week' => ['America/New_York', '2026-02-23 02:30:00',
            array_replace($daily, ['unit' => 'week', 'interval' => 2, 'week_days' => [1]]), [
                '2026-03-09T02:30:00-04:00', '2026-03-23T02:30:00-04:00',
            ]];
        yield 'monthly short month clamping still preserves local time' => ['America/New_York', '2026-01-31 09:00:00',
            array_replace($daily, ['unit' => 'month', 'month_day' => 31]), [
                '2026-02-28T09:00:00-05:00', '2026-03-31T09:00:00-04:00',
            ]];
        yield 'monthly gap does not shift later months' => ['America/New_York', '2026-02-08 02:30:00',
            array_replace($daily, ['unit' => 'month', 'month_day' => 8]), [
                '2026-03-08T03:30:00-04:00', '2026-04-08T02:30:00-04:00',
            ]];
        yield 'monthly fall emits one occurrence on the first' => ['America/New_York', '2026-10-01 01:30:00',
            array_replace($daily, ['unit' => 'month', 'month_day' => 1]), [
                '2026-11-01T01:30:00-04:00', '2026-12-01T01:30:00-05:00',
            ]];
        yield 'European fall also emits the earlier offset once' => ['Europe/Berlin', '2026-10-24 02:30:00', $daily, [
            '2026-10-25T02:30:00+02:00', '2026-10-26T02:30:00+01:00',
        ]];
        yield 'midnight repeated hour does not depend on the input offset' => ['America/Havana', '2026-10-31 00:30:00', $daily, [
            '2026-11-01T00:30:00-04:00', '2026-11-02T00:30:00-05:00',
        ]];
    }

    /** Verifies local clocks, automatic UTC conversion, strict ordering and UTC/local cursor equivalence. */
    #[DataProvider('localTimeCases')]
    public function testLocalScheduleSurvivesOffsetChanges(string $timezone, string $startTime, array $rule, array $expected): void
    {
        $calculator = new RepeatScheduleCalculator();
        $mapper = new RepeatRuleMapper();
        $start = new DateTimeImmutable($startTime, new DateTimeZone($timezone));
        $after = $start;
        $timestamps = [];
        foreach ($expected as $expectedTime) {
            $occurrence = $calculator->nextOccurrence($rule, $start, $after);
            self::assertSame($expectedTime, $occurrence->format('c'));
            self::assertGreaterThan($after->getTimestamp(), $occurrence->getTimestamp());
            $expectedUtc = (new DateTimeImmutable($expectedTime))->setTimezone(new DateTimeZone('UTC'));
            self::assertSame($expectedUtc->format('Y-m-d H:i:s'), $mapper->toDatabaseDateTime($occurrence));
            self::assertEquals($occurrence, $calculator->nextOccurrence($rule, $start, $after->setTimezone(new DateTimeZone('UTC'))));
            $timestamps[] = $occurrence->getTimestamp();
            $after = $occurrence;
        }
        self::assertCount(count($expected), array_unique($timestamps));
    }

    /** Exercises calls between the two copies of a repeated hour, including offset-dependent PHP defaults. */
    public static function repeatedHourCases(): iterable
    {
        foreach (['day', 'week', 'month'] as $unit) {
            yield "$unit New York after first copy" => [$unit, 'America/New_York', '2026-10-25 01:30:00',
                '2026-11-01T06:00:00+00:00', match ($unit) {
                    'day' => '2026-11-02T01:30:00-05:00',
                    'week' => '2026-11-08T01:30:00-05:00',
                    'month' => '2026-12-01T01:30:00-05:00',
                }];
        }
        yield 'Europe after first copy' => ['week', 'Europe/Berlin', '2026-10-18 02:30:00',
            '2026-10-25T01:00:00+00:00', '2026-11-01T02:30:00+01:00'];
        yield 'midnight fold after first copy' => ['week', 'America/Havana', '2026-10-25 00:30:00',
            '2026-11-01T05:00:00+00:00', '2026-11-08T00:30:00-05:00'];
    }

    /** Never replays a local date simply because the cursor now has the later UTC offset. */
    #[DataProvider('repeatedHourCases')]
    public function testRepeatedHourIsNotScheduledTwice(string $unit, string $timezone, string $startTime, string $afterTime, string $expected): void
    {
        $rule = ['unit' => $unit, 'interval' => 1, 'week_days' => [0], 'month_day' => 1, 'month_day_mode' => 'clamp'];
        $start = new DateTimeImmutable($startTime, new DateTimeZone($timezone));
        $actual = (new RepeatScheduleCalculator())->nextOccurrence($rule, $start, new DateTimeImmutable($afterTime));
        self::assertSame($expected, $actual->format('c'));
    }

    /** Keeps Count limits and the rolling cursor correct when DST occurs within a planned batch. */
    public function testPlannerPreservesCountsAcrossTheSpringGap(): void
    {
        $start = new DateTimeImmutable('2026-03-07 02:30:00', new DateTimeZone('America/New_York'));
        $rule = ['unit' => 'day', 'interval' => 1, 'week_days' => [], 'month_day' => null,
            'month_day_mode' => 'clamp', 'ends' => ['type' => 'count', 'count' => 2, 'date' => null]];
        $calculator = new RepeatScheduleCalculator();
        $plan = (new RepeatOccurrencePlanner($calculator))->plan($rule, $start,
            $calculator->nextOccurrence($rule, $start, $start), $start->modify('+30 days'), 0);
        self::assertSame(['2026-03-08T03:30:00-04:00', '2026-03-09T02:30:00-04:00'],
            array_map(static fn(DateTimeImmutable $date): string => $date->format('c'), $plan['occurrences']));
        self::assertSame(2, $plan['generated_repeats']);
        self::assertNull($plan['next_occurrence']);
        self::assertSame('active', $plan['status']);
    }
}
