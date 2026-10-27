<?php

declare(strict_types=1);

use App\Services\ReminderWorkerRunner;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Checks queue progress and deadlines without contacting a database or provider. */
function checkRunner(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pending = 200;
$clock = 0.0;
$runner = new ReminderWorkerRunner();
$summary = $runner->run(
    static function () use (&$pending): array {
        $claimed = $pending > 0 ? 1 : 0;
        $pending -= $claimed;
        return ['claimed' => $claimed, 'accepted' => $claimed];
    },
    45,
    static function () use (&$clock): float { return $clock += 0.01; }
);
checkRunner($pending === 0 && $summary['accepted'] === 200, 'A run must drain more than one batch instead of stopping at ten deliveries.');

$clock = 0.0;
$processed = 0;
$runner->run(
    static function () use (&$clock, &$processed): array {
        $processed++;
        $clock += 15;
        return ['claimed' => 1, 'accepted' => 1];
    },
    45,
    static function () use (&$clock): float { return $clock; }
);
checkRunner($processed === 3, 'The deadline must prevent new reservations while allowing a current send to finish.');

$emptyReads = 0;
$processed = 0;
$runner->run(static function () use (&$emptyReads, &$processed): array {
    if (++$emptyReads === 1) {
        return ['claimed' => 0];
    }
    $claimed = $processed === 0 ? 1 : 0;
    $processed += $claimed;
    return ['claimed' => $claimed, 'accepted' => $claimed];
}, 1);
checkRunner($processed === 1 && $emptyReads < 10, 'An empty reservation caused by contention must be rechecked without spinning forever.');

echo "PASS continuous queue draining, deadline and bounded contention recheck\n";
