<?php

declare(strict_types=1);

use App\Config\ReminderWorkerArguments;
use App\Exceptions\ReminderWorkerException;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Checks CLI parsing without loading environment settings, a database or a sender. */
function checkArguments(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$normal = new ReminderWorkerArguments(['--send']);
checkArguments($normal->workerSlot === null && $normal->reminderId === null && $normal->subscriptionId === null, 'The normal launcher contract must remain unchanged.');
$child = new ReminderWorkerArguments(['--send', '--worker=16']);
checkArguments($child->workerSlot === 16 && $child->reminderId === null, 'Child slots must remain supported without a target.');
foreach ([
    ['--send', '--reminder-id=123', '--subscription-id=456'],
    ['--send', '--subscription-id=456', '--reminder-id=123'],
] as $arguments) {
    $target = new ReminderWorkerArguments($arguments);
    checkArguments($target->workerSlot === null && $target->reminderId === 123 && $target->subscriptionId === 456, 'Targeted mode must require both IDs and never select a child slot.');
}
foreach ([
    [], ['--worker=1'], ['--send', '--worker=0'], ['--send', '--worker=17'],
    ['--send', '--reminder-id=1'], ['--send', '--subscription-id=1'],
    ['--send', '--reminder-id=0', '--subscription-id=1'],
    ['--send', '--reminder-id=-1', '--subscription-id=1'],
    ['--send', '--reminder-id=1oops', '--subscription-id=1'],
    ['--send', '--reminder-id=01', '--subscription-id=1'],
    ['--send', '--reminder-id=1', '--subscription-id=999999999999999999999999999999'],
    ['--send', '--reminder-id=1', '--reminder-id=2'],
    ['--send', '--subscription-id=1', '--subscription-id=2'],
    ['--send', '--reminder-id=1', '--extra=2'],
    ['--send', '--worker=1', '--reminder-id=1', '--subscription-id=2'],
] as $arguments) {
    try {
        new ReminderWorkerArguments($arguments);
        throw new RuntimeException('An invalid or incomplete target was accepted.');
    } catch (ReminderWorkerException) {
        // Invalid targets must never fall back to the shared queue.
    }
}

echo "PASS reminder CLI arguments, explicit target pairing and fail-closed validation (no database or provider I/O)\n";
