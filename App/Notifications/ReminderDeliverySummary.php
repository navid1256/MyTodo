<?php

declare(strict_types=1);

namespace App\Notifications;

final class ReminderDeliverySummary
{
    private const COUNTERS = [
        'claimed', 'accepted', 'retryable', 'expired', 'failed', 'unknown', 'skipped',
    ];

    /** Creates the delivery counters shared by individual workers, runners and the pool. */
    public static function create(int $claimed = 0): array
    {
        $summary = array_fill_keys(self::COUNTERS, 0);
        $summary['claimed'] = $claimed;

        return $summary;
    }

    /** Adds delivery counts while leaving pool-only counters and unrelated fields unchanged. */
    public static function merge(array $totals, array $batch): array
    {
        foreach (self::COUNTERS as $counter) {
            $totals[$counter] += $batch[$counter] ?? 0;
        }

        return $totals;
    }

    /** Rejects child reports with missing, extra, negative or non-integer delivery counters. */
    public static function isValid(mixed $summary): bool
    {
        if (!is_array($summary) || count($summary) !== count(self::COUNTERS)) {
            return false;
        }
        foreach (self::COUNTERS as $counter) {
            $count = $summary[$counter] ?? null;
            if (!is_int($count) || $count < 0) {
                return false;
            }
        }

        return true;
    }
}
