<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final class RepeatOccurrencePlanner
{
    private const DEFAULT_BATCH_LIMIT = 500;

    public function __construct(private readonly RepeatScheduleCalculator $scheduleCalculator) {}

    /**
     * Plans an enabled series without closing its lifecycle; a null cursor alone marks generation exhaustion.
     * Restoring the original occurrence does not spend the additional-repeat allowance.
     *
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
            && $this->canGenerate($rule, $seriesStart, $nextOccurrence, $generatedRepeats)
        ) {
            $occurrences[] = $nextOccurrence;
            if ($nextOccurrence != $seriesStart) {
                $generatedRepeats++;
            }
            $nextOccurrence = $this->scheduleCalculator->nextOccurrence(
                $rule,
                $seriesStart,
                $nextOccurrence
            );
        }

        if (!$this->canGenerate($rule, $seriesStart, $nextOccurrence, $generatedRepeats)) {
            return [
                'occurrences' => $occurrences,
                'generated_repeats' => $generatedRepeats,
                'next_occurrence' => null,
                'status' => 'active',
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
     * Applies the series end rule while keeping occurrence zero separate from the repeat count.
     * @param array{ends: array{type: string, date: string|null, count: int|null}} $rule
     */
    private function canGenerate(
        array $rule,
        DateTimeImmutable $seriesStart,
        DateTimeImmutable $occurrence,
        int $generatedRepeats
    ): bool {
        if ($rule['ends']['type'] === 'count') {
            return $occurrence == $seriesStart || $generatedRepeats < (int) $rule['ends']['count'];
        }

        if ($rule['ends']['type'] === 'date') {
            return $occurrence->format('Y-m-d') <= (string) $rule['ends']['date'];
        }

        return true;
    }
}
