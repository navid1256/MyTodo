<?php

declare(strict_types=1);

// Run inside the scheduler image with --network none and no database or VAPID variables.
/** Fails an isolated runtime check without printing environment variables. */
function checkSchedulerRuntime(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Runs a short validation command that must never start application jobs. */
function runSchedulerCheck(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    checkSchedulerRuntime(is_resource($process), 'Unable to launch scheduler check.');
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output];
}

checkSchedulerRuntime(PHP_OS_FAMILY === 'Linux' && posix_geteuid() === 33, 'The scheduler must run as www-data, not root.');
checkSchedulerRuntime(is_readable('/etc/mytodo.crontab') && !is_writable('/etc/mytodo.crontab'), 'The schedule must be readable but not writable by application jobs.');
[$syntaxStatus] = runSchedulerCheck(['/bin/sh', '-n', '/usr/local/bin/mytodo-scheduler']);
checkSchedulerRuntime($syntaxStatus === 0, 'The entrypoint must be valid POSIX shell.');
[$checkStatus, $checkOutput] = runSchedulerCheck(['/usr/local/bin/mytodo-scheduler', '--check']);
checkSchedulerRuntime($checkStatus === 0 && str_contains($checkOutput, 'crontab is valid'), 'Both production schedules must parse without running jobs.');
putenv('MYTODO_SCHEDULER_ENABLED=false');
[$disabledStatus] = runSchedulerCheck(['/usr/local/bin/mytodo-scheduler']);
checkSchedulerRuntime($disabledStatus === 2, 'A new service must not start real jobs without explicit opt-in.');
putenv('MYTODO_SCHEDULER_ENABLED=true');
[$argumentStatus] = runSchedulerCheck(['/usr/local/bin/mytodo-scheduler', '--unexpected']);
checkSchedulerRuntime($argumentStatus === 2, 'Reject unknown options even when enabled.');

// Accelerate two harmless fixture jobs, never the actual reminder or repeat commands.
$fixturePath = tempnam(sys_get_temp_dir(), 'mytodo-scheduler-');
checkSchedulerRuntime($fixturePath !== false, 'Unable to create the isolated schedule fixture.');
$process = null;
$pipes = [];
try {
    putenv('MYTODO_SCHEDULER_TEST=fixture');
    $fixture = '* * * * * * * test "$MYTODO_SCHEDULER_TEST" = fixture && echo MYTODO_TEST_REMINDERS' . "\n"
        . '* * * * * * * test "$MYTODO_SCHEDULER_TEST" = fixture && echo MYTODO_TEST_REPEATS' . "\n";
    file_put_contents($fixturePath, $fixture);
    $process = proc_open(['/usr/local/bin/supercronic', '-json', $fixturePath], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    checkSchedulerRuntime(is_resource($process), 'Unable to start the fixture scheduler.');
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = '';
    $deadline = microtime(true) + 5;
    do {
        $output .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        if (str_contains($output, '"msg":"MYTODO_TEST_REMINDERS"') && str_contains($output, '"msg":"MYTODO_TEST_REPEATS"')
            && substr_count($output, 'job succeeded') >= 2) {
            break;
        }
        usleep(50000);
    } while (microtime(true) < $deadline);
    checkSchedulerRuntime(
        str_contains($output, '"msg":"MYTODO_TEST_REMINDERS"')
        && str_contains($output, '"msg":"MYTODO_TEST_REPEATS"')
        && substr_count($output, 'job succeeded') >= 2,
        'Both fixture jobs must execute with inherited environment and success logs.'
    );
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
    }
    unlink($fixturePath);
}

echo "PASS non-root scheduler, production crontab parsing, activation guard and two isolated scheduled jobs\n";
