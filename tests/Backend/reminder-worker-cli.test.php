<?php

declare(strict_types=1);

/** Runs the CLI entrypoint without allowing provider or database side effects. */
function runReminderCommand(array $arguments): array
{
    $command = [PHP_BINARY, dirname(__DIR__, 2) . '/process/send-reminders.php', ...$arguments];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start reminder CLI test.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), (string) $stdout, (string) $stderr];
}

[$helpStatus, $helpOutput] = runReminderCommand(['--help']);
if ($helpStatus !== 0 || !str_contains($helpOutput, '--send') || !str_contains($helpOutput, 'REMINDER_WORKERS')
    || !str_contains($helpOutput, '--reminder-id') || !str_contains($helpOutput, '--subscription-id')) {
    throw new RuntimeException('The CLI help must explain explicit sending and configurable concurrency.');
}

foreach ([['--worker=1'], ['--send', '--worker=0'], ['--send', '--worker=17'], ['--send', '--worker=1', '--extra'],
    ['--send', '--reminder-id=1'], ['--send', '--subscription-id=1'],
    ['--send', '--reminder-id=0', '--subscription-id=1'],
    ['--send', '--reminder-id=1', '--subscription-id=1oops'],
    ['--send', '--worker=1', '--reminder-id=1', '--subscription-id=1'],
] as $arguments) {
    [$status] = runReminderCommand($arguments);
    if ($status !== 2) {
        throw new RuntimeException('Malformed child arguments must be rejected before database or provider I/O.');
    }
}
[$noSendStatus, $noSendOutput, $noSendError] = runReminderCommand([]);
if ($noSendStatus !== 2 || $noSendOutput !== '' || !str_contains($noSendError, '--send')) {
    throw new RuntimeException('The CLI must refuse an unconfirmed live send.');
}

echo "PASS reminder CLI guard and help (no database or provider I/O)\n";
