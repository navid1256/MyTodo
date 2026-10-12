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
if ($helpStatus !== 0 || !str_contains($helpOutput, '--send')) {
    throw new RuntimeException('The CLI help must explain explicit sending.');
}
[$noSendStatus, $noSendOutput, $noSendError] = runReminderCommand([]);
if ($noSendStatus !== 2 || $noSendOutput !== '' || !str_contains($noSendError, '--send')) {
    throw new RuntimeException('The CLI must refuse an unconfirmed live send.');
}

echo "PASS reminder CLI guard and help (no database or provider I/O)\n";
