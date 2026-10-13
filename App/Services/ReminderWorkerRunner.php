<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use InvalidArgumentException;

final class ReminderWorkerRunner
{
    /** Drains one-delivery batches until idle or the reservation time budget expires. */
    public function run(Closure $processOne, int $runtimeSeconds, ?Closure $monotonicClock = null): array
    {
        if ($runtimeSeconds < 1 || $runtimeSeconds > 50) {
            throw new InvalidArgumentException('Reminder runtime must be between 1 and 50 seconds.');
        }
        $monotonicClock ??= static fn(): float => hrtime(true) / 1_000_000_000;
        $deadline = $monotonicClock() + $runtimeSeconds;
        $summary = [
            'claimed' => 0, 'accepted' => 0, 'retryable' => 0, 'expired' => 0,
            'failed' => 0, 'unknown' => 0, 'skipped' => 0,
        ];
        $emptyReads = 0;

        while ($monotonicClock() < $deadline) {
            // Each callback reserves just-in-time and obtains a fresh UTC timestamp.
            $batch = $processOne();
            foreach ($summary as $outcome => $count) {
                $summary[$outcome] = $count + ($batch[$outcome] ?? 0);
            }
            $emptyReads = ($batch['claimed'] ?? 0) === 0 ? $emptyReads + 1 : 0;
            if ($emptyReads >= 3) {
                break;
            }
            if ($emptyReads > 0) {
                // Another worker may have won a candidate; a short recheck avoids early exit.
                usleep(50_000);
            }
        }

        return $summary;
    }
}
