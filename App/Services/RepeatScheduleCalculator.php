<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RepeatScheduleCalculationException;
use DateTimeImmutable;

final class RepeatScheduleCalculator
{
    private const MAX_SEARCH_DAYS = 370000;
    private const MAX_SEARCH_MONTHS = 120000;
    private const TRANSITION_WINDOW_SECONDS = 86400;

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

    /** Advances calendar days while restoring the original wall-clock time after every step. */
    private function nextDailyOccurrence(
        int $interval,
        DateTimeImmutable $seriesStart,
        DateTimeImmutable $after
    ): DateTimeImmutable {
        $candidate = $seriesStart;

        do {
            $candidate = $this->applySeriesTime($candidate->modify("+{$interval} days"), $seriesStart);
        } while ($candidate <= $after);

        return $candidate;
    }

    /**
     * Searches eligible weekdays without carrying a normalized gap hour into subsequent dates.
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
        $candidate = $this->applySeriesTime($after, $seriesStart);

        if ($candidate <= $after) {
            $candidate = $this->applySeriesTime($candidate->modify('+1 day'), $seriesStart);
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

            $candidate = $this->applySeriesTime($candidate->modify('+1 day'), $seriesStart);
        }

        throw new RepeatScheduleCalculationException(
            'The next weekly repeat occurrence could not be calculated.'
        );
    }

    /**
     * Applies the configured month day and the same local-time policy as daily and weekly schedules.
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
            $candidateDate = $month->setDate(
                (int) $month->format('Y'),
                (int) $month->format('m'),
                $day
            );
            $candidate = $this->applySeriesTime($candidateDate, $seriesStart);

            if ($candidate > $after) {
                return $candidate;
            }
        }

        throw new RepeatScheduleCalculationException(
            'The next monthly repeat occurrence could not be calculated.'
        );
    }

    /** Restores the anchor time, lets PHP normalize gaps, and selects the earlier instant for a repeated local time. */
    private function applySeriesTime(DateTimeImmutable $date, DateTimeImmutable $seriesStart): DateTimeImmutable
    {
        $occurrence = $date->setTime(
            (int) $seriesStart->format('H'),
            (int) $seriesStart->format('i'),
            (int) $seriesStart->format('s')
        );
        $timestamp = $occurrence->getTimestamp();
        $localTime = $occurrence->format('Y-m-d H:i:s');
        $transitions = $occurrence->getTimezone()->getTransitions(
            $timestamp - self::TRANSITION_WINDOW_SECONDS,
            $timestamp + self::TRANSITION_WINDOW_SECONDS
        );

        // PHP can retain the input offset inside a repeated hour. Compare nearby offsets
        // from its timezone database instead of assuming a one-hour transition.
        foreach ($transitions ?: [] as $transition) {
            $offsetDifference = $occurrence->getOffset() - $transition['offset'];
            $alternative = $occurrence->setTimestamp($occurrence->getTimestamp() + $offsetDifference);
            if ($alternative < $occurrence && $alternative->format('Y-m-d H:i:s') === $localTime) {
                $occurrence = $alternative;
            }
        }

        return $occurrence;
    }
}
