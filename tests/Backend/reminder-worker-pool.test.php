<?php

declare(strict_types=1);

use App\Services\ReminderWorkerPool;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$summary = (new ReminderWorkerPool())->run([PHP_BINARY, __DIR__ . '/fixtures/reminder-pool-child.php'], 4);
if ($summary['accepted'] !== 200 || $summary['failed_workers'] !== 2 || $summary['busy_workers'] !== 1) {
    throw new RuntimeException('The pool must aggregate successes, busy slots, child failures and invalid output.');
}
echo "PASS child process aggregation and sanitized failure handling\n";
