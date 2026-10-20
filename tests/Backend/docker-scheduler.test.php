<?php

declare(strict_types=1);

/** Checks the deployment contract without starting jobs or accessing credentials. */
function checkSchedulerContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__, 2);
$cronPath = $root . '/docker/scheduler.crontab';
checkSchedulerContract(is_file($cronPath), 'The scheduler must have a versioned crontab.');
$crontab = (string) file_get_contents($cronPath);
$jobs = array_values(array_filter(
    explode("\n", trim($crontab)),
    static fn(string $line): bool => trim($line) !== '' && !str_starts_with(trim($line), '#')
));
checkSchedulerContract($jobs === [
    '* * * * * /usr/local/bin/php -d display_errors=0 /var/www/html/process/send-reminders.php --send',
    '0 0 * * * /usr/local/bin/php -d display_errors=0 /var/www/html/process/generate-repeat-tasks.php',
], 'Run only the minute reminder job and daily repeat job, using absolute CLI paths.');
checkSchedulerContract(!str_contains($crontab, "\r"), 'The crontab must use Linux line endings.');

$dockerfile = (string) file_get_contents($root . '/Dockerfile');
checkSchedulerContract(str_contains($dockerfile, 'ARG MYTODO_IMAGE_VARIANT=web'), 'Web must remain the default image variant.');
checkSchedulerContract(str_contains($dockerfile, 'FROM web AS scheduler'), 'The scheduler must reuse the application runtime.');
checkSchedulerContract(str_contains($dockerfile, 'HEALTHCHECK NONE'), 'The scheduler must not inherit the Apache healthcheck.');
checkSchedulerContract(str_contains($dockerfile, 'sha256sum --check'), 'Verify the pinned scheduler download checksum.');
checkSchedulerContract(str_contains($dockerfile, 'FROM ${MYTODO_IMAGE_VARIANT} AS runtime'), 'Railway must be able to select the scheduler image variant.');

$launcher = (string) file_get_contents($root . '/docker/start-scheduler.sh');
checkSchedulerContract(str_contains($launcher, 'MYTODO_SCHEDULER_ENABLED'), 'Starting real jobs must require explicit opt-in.');
checkSchedulerContract(str_contains($launcher, 'export TZ=UTC'), 'Schedule both jobs in UTC, independently of host timezone.');
checkSchedulerContract(str_contains($launcher, 'exec /usr/local/bin/supercronic'), 'The scheduler must receive container shutdown signals.');
checkSchedulerContract(!str_contains($launcher, '-overlapping'), 'Do not opt into overlapping runs of the same job.');

echo "PASS scheduler schedules, safe activation, default web image and pinned binary contract\n";
