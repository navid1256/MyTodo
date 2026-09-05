<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RepeatScheduleCalculationException;
use DateTimeImmutable;

final class RepeatScheduleCalculator
{
    private const MAX_SEARCH_DAYS = 370000;
    private const MAX_SEARCH_MONTHS = 120000;

    /**
     * @param array{
     *     interval: int,
     *     unit: string,
     *     week_days: array<int, int>,
     *     month_day: int|null,
     *     month_day_mode: string
     * } $rule
     */
    public function nextOccurrence(
        array $rule,
        DateTimeImmutable $seriesStart,
        DateTimeImmutable $after
    ): DateTimeImmutable {
        $after = $after->setTimezone($seriesStart->getTimezone());

        return match ($rule['unit']) {
            'day' => $this->nextDailyOccurrence($rule['interval'], $seriesStart, $after),
            'week' => $this->nextWeeklyOccurrence($rule, $seriesStart, $after),
            'month' => $this->nextMonthlyOccurrence($rule, $seriesStart, $after),
        };
    }

    private function nextDailyOccurrence(
        int $interval,
        DateTimeImmutable $seriesStart,
        DateTimeImmutable $after
    ): DateTimeImmutable {
        $candidate = $seriesStart;

        do {
            $candidate = $candidate->modify("+{$interval} days");
        } while ($candidate <= $after);

        return $candidate;
    }

    /**
     * @param array{interval: int, week_days: array<int, int>} $rule
     */
    private function nextWeeklyOccurrence(
        array $rule,
        DateTimeImmutable $seriesStart,
        DateTimeImmutable $after
    ): DateTimeImmutable {
        $anchorWeek = $seriesStart
            ->modify('-' . $seriesStart->format('w') . ' days')
            ->setTime(0, 0);
        $candidate = $after
            ->setTime(
                (int) $seriesStart->format('H'),
                (int) $seriesStart->format('i'),
                (int) $seriesStart->format('s')
            );

        if ($candidate <= $after) {
            $candidate = $candidate->modify('+1 day');
        }

        for ($searchedDays = 0; $searchedDays < self::MAX_SEARCH_DAYS; $searchedDays++) {
            $candidateWeek = $candidate
                ->modify('-' . $candidate->format('w') . ' days')
                ->setTime(0, 0);
            $daysSinceAnchor = (int) $anchorWeek->diff($candidateWeek)->format('%r%a');
            $weekIndex = intdiv($daysSinceAnchor, 7);

            if (
                $weekIndex >= 0
                && $weekIndex % $rule['interval'] === 0
                && in_array((int) $candidate->format('w'), $rule['week_days'], true)
            ) {
                return $candidate;
            }

            $candidate = $candidate->modify('+1 day');
        }

        throw new RepeatScheduleCalculationException(
            'The next weekly repeat occurrence could not be calculated.'
        );
    }

    /**
     * @param array{interval: int, month_day: int|null, month_day_mode: string} $rule
     */
    private function nextMonthlyOccurrence(
        array $rule,
        DateTimeImmutable $seriesStart,
        DateTimeImmutable $after
    ): DateTimeImmutable {
        $anchorMonth = $seriesStart->modify('first day of this month')->setTime(0, 0);

        for ($monthOffset = 0; $monthOffset < self::MAX_SEARCH_MONTHS; $monthOffset += $rule['interval']) {
            $month = $anchorMonth->modify("+{$monthOffset} months");
            $lastDay = (int) $month->format('t');
            $day = $rule['month_day_mode'] === 'last_day'
                ? $lastDay
                : min((int) $rule['month_day'], $lastDay);
            $candidate = $month->setDate(
                (int) $month->format('Y'),
                (int) $month->format('m'),
                $day
            )->setTime(
                (int) $seriesStart->format('H'),
                (int) $seriesStart->format('i'),
                (int) $seriesStart->format('s')
            );

            if ($candidate > $after) {
                return $candidate;
            }
        }

        throw new RepeatScheduleCalculationException(
            'The next monthly repeat occurrence could not be calculated.'
        );
    }
}
