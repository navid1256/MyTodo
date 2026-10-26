<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Database\Database;

require_once __DIR__ . '/fixtures/reminder-worker-test-connection.php';

/** Verifies shared-queue invariants without exposing real user data on failure. */
function checkConcurrent(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Starts a local fake-sender process using shell-free arguments. */
function startFixtureWorker(string $prefix, string $argument): array
{
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', __DIR__ . '/fixtures/reminder-concurrent-worker.php', $prefix, $argument],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    checkConcurrent(is_resource($process), 'Unable to start concurrency fixture.');

    return [$process, $pipes];
}

/** Waits for a local fixture and decodes only its non-sensitive report. */
function finishFixtureWorker(array $child): array
{
    [$process, $pipes] = $child;
    $output = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    checkConcurrent($status === 0, 'Fixture worker failed; raw errors are intentionally withheld.');
    $report = json_decode((string) $output, true);
    checkConcurrent(is_array($report), 'Fixture worker returned an invalid report.');

    return $report;
}

EnvironmentLoader::load(dirname(__DIR__, 2));
try {
    $pdo = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
} catch (Throwable) {
    fwrite(STDERR, "FAIL concurrency test: configured MySQL database is unavailable; no fixture tables were created.\n");
    exit(1);
}
$prefix = 'mytodo_worker_' . bin2hex(random_bytes(8)) . '_';
$createdTables = [];
$children = [];
$startedAt = hrtime(true);
try {
    foreach (IsolatedReminderTestConnection::TABLES as $source) {
        $table = $prefix . $source;
        // LIKE copies schema and indexes, never application rows or foreign keys.
        $pdo->exec("CREATE TABLE `{$table}` LIKE `{$source}`");
        $createdTables[] = $table;
    }
    $pdo->exec("CREATE TABLE `{$prefix}sends` (id INT AUTO_INCREMENT PRIMARY KEY, tag VARCHAR(128), endpoint VARCHAR(256), worker INT) ENGINE=InnoDB");
    $createdTables[] = $prefix . 'sends';
    $pdo->exec("CREATE TABLE `{$prefix}metrics` (active_workers INT NOT NULL, peak_workers INT NOT NULL) ENGINE=InnoDB");
    $createdTables[] = $prefix . 'metrics';
    $testPdo = new IsolatedReminderTestConnection($prefix);
    $testPdo->exec("INSERT INTO {$prefix}metrics VALUES (0, 0)");
    $testPdo->exec("INSERT INTO user_settings (user_id, language, calendar_system, timezone) VALUES (1, 'english', 'gregorian', 'UTC')");
    $testPdo->exec("INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth) VALUES
        (1, 'https://fcm.googleapis.com/fake-a', SHA2('https://fcm.googleapis.com/fake-a', 256), 'key-a', 'auth-a'),
        (1, 'https://fcm.googleapis.com/fake-b', SHA2('https://fcm.googleapis.com/fake-b', 256), 'key-b', 'auth-b')");
    $task = $testPdo->prepare("INSERT INTO tasks (title, user_id, due_at, has_time) VALUES ('Concurrency fixture', 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR), 1)");
    $reminder = $testPdo->prepare("INSERT INTO task_reminders (task_id, offset_value, offset_unit, remind_at) VALUES (:task, 60, 'minute', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE))");
    for ($number = 0; $number < 100; $number++) {
        $task->execute();
        $reminder->execute([':task' => $testPdo->lastInsertId()]);
    }
    $children[] = startFixtureWorker($prefix, '--launch');
    $readyDeadline = hrtime(true) + 5_000_000_000;
    do {
        $active = (int) $pdo->query("SELECT active_workers FROM `{$prefix}metrics`")->fetchColumn();
        if ($active === 4) {
            break;
        }
        usleep(10_000);
    } while (hrtime(true) < $readyDeadline);
    checkConcurrent($active === 4, 'All four workers must overlap in the shared fixture queue.');

    $overlap = finishFixtureWorker(startFixtureWorker($prefix, '--launch'));
    checkConcurrent($overlap['status'] === 'busy', 'An overlapping Cron launcher must not spawn another pool.');
    $duplicate = finishFixtureWorker(startFixtureWorker($prefix, '--worker=1'));
    checkConcurrent($duplicate['status'] === 'busy', 'A duplicate child must not enter an occupied slot.');
    $result = finishFixtureWorker(array_pop($children));
    checkConcurrent(($result['summary']['failed_workers'] ?? -1) === 0, 'All concurrency workers must finish successfully.');
    checkConcurrent($result['summary']['accepted'] === 200, 'The workers must drain all 200 fixture deliveries in one launch.');
    checkConcurrent((int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}sends`")->fetchColumn() === 200, 'The fake sender must record exactly 200 sends.');
    checkConcurrent((int) $pdo->query("SELECT COUNT(*) FROM (SELECT tag, endpoint FROM `{$prefix}sends` GROUP BY tag, endpoint HAVING COUNT(*) > 1) AS duplicates")->fetchColumn() === 0, 'No reminder/browser pair may be sent twice.');
    checkConcurrent((int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}task_reminder_deliveries` WHERE status <> 'sent' OR attempt_count <> 1")->fetchColumn() === 0, 'Each delivery must be sent with one reservation.');
    checkConcurrent((int) $pdo->query("SELECT peak_workers FROM `{$prefix}metrics`")->fetchColumn() === 4, 'Overlapping launches must preserve the four-worker ceiling.');
    $elapsed = (hrtime(true) - $startedAt) / 1_000_000_000;
    echo sprintf("PASS 4 concurrent workers, 200 fake deliveries, no duplicate sends, overlapping launcher/slot excluded (%.2fs)\n", $elapsed);

    // Two direct workers racing for one explicit pair must still produce only one fake send.
    $task->execute();
    $reminder->execute([':task' => $testPdo->lastInsertId()]);
    $targetReminder = (int) $testPdo->lastInsertId();
    $task->execute();
    $reminder->execute([':task' => $testPdo->lastInsertId()]);
    $unrelatedReminder = (int) $testPdo->lastInsertId();
    $children[] = startFixtureWorker($prefix, '--target=' . $targetReminder . ':1:1');
    $children[] = startFixtureWorker($prefix, '--target=' . $targetReminder . ':1:2');
    $targetSecond = finishFixtureWorker(array_pop($children));
    $targetFirst = finishFixtureWorker(array_pop($children));
    checkConcurrent($targetFirst['summary']['accepted'] + $targetSecond['summary']['accepted'] === 1, 'Concurrent target reservations must accept only one delivery.');
    checkConcurrent((int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}sends` WHERE tag = 'mytodo-reminder-{$targetReminder}'")->fetchColumn() === 1, 'The targeted reminder must not produce duplicate or second-browser sends.');
    checkConcurrent((int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}task_reminder_deliveries` WHERE reminder_id = {$unrelatedReminder}")->fetchColumn() === 0, 'Targeted workers must not enqueue the unrelated due reminder.');
    checkConcurrent((int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}task_reminder_deliveries` WHERE reminder_id = {$targetReminder} AND status = 'sent' AND attempt_count = 1 AND subscription_id = 1")->fetchColumn() === 1, 'Only the selected target may be claimed and recorded once.');
    echo "PASS concurrent targeted workers: one fake send, selected browser only, unrelated due reminder untouched\n";
} finally {
    // Wait for children before removing only the exact fixture tables created above.
    $childCleanupFailed = false;
    foreach ($children as $child) {
        try {
            finishFixtureWorker($child);
        } catch (Throwable) {
            $childCleanupFailed = true;
        }
    }
    foreach (array_reverse($createdTables) as $table) {
        checkConcurrent(str_starts_with($table, $prefix), 'Refusing to remove a table outside this test run.');
        $pdo->exec("DROP TABLE `{$table}`");
    }
    echo "Fixture tables removed; application rows were never used.\n";
    checkConcurrent(!$childCleanupFailed, 'A fixture child failed during cleanup.');
}
