<?php

declare(strict_types=1);

use App\Services\ReminderWorkerPool;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Executes a controlled child report without loading application configuration. */
function runPoolReportFixture(mixed $report, int $exitCode = 0): array
{
    $output = var_export(json_encode($report), true);
    $script = "fwrite(STDOUT, $output); fwrite(STDERR, 'Private fixture error'); exit($exitCode);";

    return (new ReminderWorkerPool())->run([PHP_BINARY, '-r', $script, '--'], 1);
}

$deliveryCounters = [
    'claimed' => 7, 'accepted' => 2, 'retryable' => 1, 'expired' => 1,
    'failed' => 1, 'unknown' => 1, 'skipped' => 1,
];
$completed = ['status' => 'completed', 'summary' => $deliveryCounters];
if (runPoolReportFixture($completed) !== $deliveryCounters + ['busy_workers' => 0, 'failed_workers' => 0]) {
    throw new RuntimeException('The pool must retain every counter and the existing output shape.');
}
$missingCounter = $deliveryCounters;
unset($missingCounter['skipped']);
foreach ([null, 'invalid', ['status' => 'unexpected', 'summary' => $deliveryCounters],
    ['status' => 'completed'], ['status' => 'completed', 'summary' => $missingCounter],
    ['status' => 'completed', 'summary' => $deliveryCounters + ['extra' => 0]],
    ['status' => 'completed', 'summary' => ['accepted' => -1] + $deliveryCounters],
    ['status' => 'completed', 'summary' => ['accepted' => '2'] + $deliveryCounters],
] as $invalidReport) {
    $failed = runPoolReportFixture($invalidReport);
    if ($failed['failed_workers'] !== 1 || $failed['claimed'] !== 0) {
        throw new RuntimeException('Invalid child reports must fail without adding delivery counters.');
    }
}
if (runPoolReportFixture($completed, 1)['failed_workers'] !== 1) {
    throw new RuntimeException('A nonzero child exit must override an otherwise valid report.');
}
foreach ([[[], 1], [[PHP_BINARY], 0], [[PHP_BINARY], 17]] as [$command, $workers]) {
    try {
        (new ReminderWorkerPool())->run($command, $workers);
    } catch (InvalidArgumentException) {
        continue;
    }
    throw new RuntimeException('Invalid pool arguments must be rejected before starting any process.');
}

echo "PASS existing pool report shape, validation boundaries and failure isolation\n";
