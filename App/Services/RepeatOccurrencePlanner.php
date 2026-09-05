<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final class RepeatOccurrencePlanner
{
    private const DEFAULT_BATCH_LIMIT = 500;

    public function __construct(private readonly RepeatScheduleCalculator $scheduleCalculator) {}

    /**
     * @param array{
     *     interval: int,
     *     unit: string,
     *     week_days: array<int, int>,
     *     month_day: int|null,
     *     month_day_mode: string,
     *     ends: array{type: string, date: string|null, count: int|null}
     * } $rule
     * @return array{
     *     occurrences: array<int, DateTimeImmutable>,
     *     generated_repeats: int,
     *     next_occurrence: DateTimeImmutable|null,
     *     status: string
     * }
     */
    public function plan(
        array $rule,
        DateTimeImmutable $seriesStart,
        DateTimeImmutable $nextOccurrence,
        DateTimeImmutable $horizon,
        int $generatedRepeats,
        int $batchLimit = self::DEFAULT_BATCH_LIMIT
    ): array {
        $horizon = $horizon->setTimezone($seriesStart->getTimezone());
        $nextOccurrence = $nextOccurrence->setTimezone($seriesStart->getTimezone());
        $occurrences = [];
        $limit = max(1, $batchLimit);

        while (
            count($occurrences) < $limit
            && $nextOccurrence <= $horizon
            && $this->canGenerate($rule, $nextOccurrence, $generatedRepeats)
        ) {
            $occurrences[] = $nextOccurrence;
            $generatedRepeats++;
            $nextOccurrence = $this->scheduleCalculator->nextOccurrence(
                $rule,
                $seriesStart,
                $nextOccurrence
            );
        }

        if (!$this->canGenerate($rule, $nextOccurrence, $generatedRepeats)) {
            return [
                'occurrences' => $occurrences,
                'generated_repeats' => $generatedRepeats,
                'next_occurrence' => null,
                'status' => 'completed',
            ];
        }

        return [
            'occurrences' => $occurrences,
            'generated_repeats' => $generatedRepeats,
            'next_occurrence' => $nextOccurrence,
            'status' => 'active',
        ];
    }

    /**
     * @param array{ends: array{type: string, date: string|null, count: int|null}} $rule
     */
    private function canGenerate(
        array $rule,
        DateTimeImmutable $occurrence,
        int $generatedRepeats
    ): bool {
        if ($rule['ends']['type'] === 'count') {
            return $generatedRepeats < (int) $rule['ends']['count'];
        }

        if ($rule['ends']['type'] === 'date') {
            return $occurrence->format('Y-m-d') <= (string) $rule['ends']['date'];
        }

        return true;
    }
}
