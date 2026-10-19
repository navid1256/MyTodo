<?php

declare(strict_types=1);

use App\Notifications\ReminderDeliverySummary;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Checks the shared delivery report without opening a database or sending notifications. */
function checkDeliverySummary(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$expected = [
    'claimed' => 0, 'accepted' => 0, 'retryable' => 0, 'expired' => 0,
    'failed' => 0, 'unknown' => 0, 'skipped' => 0,
];
checkDeliverySummary(ReminderDeliverySummary::create() === $expected, 'The report must preserve counter names and ordering.');
checkDeliverySummary(ReminderDeliverySummary::create(3) === ['claimed' => 3] + $expected, 'A worker must initialize its reserved delivery count.');

$totals = ['claimed' => 2, 'accepted' => 1] + $expected;
$totals['failed_workers'] = 2;
$merged = ReminderDeliverySummary::merge($totals, ['claimed' => 3, 'accepted' => 2, 'unrelated' => 99]);
checkDeliverySummary($merged['claimed'] === 5 && $merged['accepted'] === 3, 'Partial batch counters must be added to existing totals.');
checkDeliverySummary($merged['failed_workers'] === 2 && !isset($merged['unrelated']), 'Merging deliveries must preserve pool counters and ignore unrelated fields.');
checkDeliverySummary(ReminderDeliverySummary::isValid($expected), 'A complete nonnegative integer report must be accepted.');

$missing = $expected;
unset($missing['skipped']);
foreach ([null, 'invalid', $missing, $expected + ['extra' => 0],
    ['accepted' => -1] + $expected, ['accepted' => 1.5] + $expected,
    ['accepted' => '1'] + $expected, ['accepted' => null] + $expected,
] as $invalid) {
    checkDeliverySummary(!ReminderDeliverySummary::isValid($invalid), 'Malformed or incomplete delivery reports must be rejected.');
}

echo "PASS shared delivery counter creation, merging and strict report validation\n";
