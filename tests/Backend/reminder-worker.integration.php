<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Database\Database;
use App\Notifications\NotificationSender;
use App\Notifications\NotificationSendResult;
use App\Repositories\ReminderDeliveryRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\ReminderDispatchService;
use App\Services\ReminderNotificationWorker;
use App\Services\UserSettingsService;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Fails the integration test when a worker invariant is broken. */
function checkWorker(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class RecordingNotificationSender implements NotificationSender
{
    public array $calls = [];
    public array $responses = [];
    public array $exceptions = [];
    public ?Closure $afterSend = null;

    /** Records a send without opening a network connection. */
    public function send(#[\SensitiveParameter] array $subscription, #[\SensitiveParameter] array $payload): NotificationSendResult
    {
        $this->calls[] = ['endpoint' => $subscription['endpoint'], 'payload' => $payload];
        if ($this->afterSend !== null) {
            ($this->afterSend)();
        }
        if (isset($this->exceptions[$subscription['endpoint']])) {
            throw $this->exceptions[$subscription['endpoint']];
        }

        return NotificationSendResult::fromHttpStatus($this->responses[$subscription['endpoint']] ?? 201);
    }
}

EnvironmentLoader::load(dirname(__DIR__, 2));
$pdo = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');

// Temporary tables shadow real tables only on this connection.
$pdo->exec("CREATE TEMPORARY TABLE task_repeat_rules (id INT UNSIGNED PRIMARY KEY, status VARCHAR(20) NOT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE tasks (id INT UNSIGNED PRIMARY KEY, user_id INT UNSIGNED NOT NULL, repeat_rule_id INT UNSIGNED NULL, title VARCHAR(512) NOT NULL, due_at DATETIME NULL, has_time TINYINT NOT NULL DEFAULT 1, is_done TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE task_reminders (id INT UNSIGNED PRIMARY KEY, task_id INT UNSIGNED NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', remind_at DATETIME NOT NULL, attempt_count INT UNSIGNED NOT NULL DEFAULT 0, last_attempt_at DATETIME NULL, sent_at DATETIME NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE push_subscriptions (id INT UNSIGNED PRIMARY KEY, user_id INT UNSIGNED NOT NULL, endpoint VARCHAR(2048) NOT NULL, p256dh VARCHAR(87) NOT NULL, auth VARCHAR(22) NOT NULL, content_encoding VARCHAR(16) NOT NULL DEFAULT 'aes128gcm', last_used_at DATETIME NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE user_settings (user_id INT UNSIGNED PRIMARY KEY, language VARCHAR(20) NOT NULL, calendar_system VARCHAR(20) NOT NULL, timezone VARCHAR(100) NOT NULL) ENGINE=InnoDB");
$migration = file_get_contents(dirname(__DIR__, 2) . '/Database/migrations/20260928_create_task_reminder_deliveries.sql');
checkWorker(is_string($migration), 'Delivery migration must exist.');
$temporarySchema = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $migration);
$temporarySchema = preg_replace('/,\s*CONSTRAINT `task_reminder_deliveries_reminder_foreign`.*?ON UPDATE CASCADE/s', '', $temporarySchema);
$pdo->exec($temporarySchema);

$pdo->exec("INSERT INTO tasks VALUES (101, 1, NULL, 'Pay invoice', '2026-09-28 11:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (201, 101, '2026-09-28 10:00:00')");
$pdo->exec("INSERT INTO push_subscriptions (id, user_id, endpoint, p256dh, auth) VALUES
    (301, 1, 'https://fcm.googleapis.com/a', 'key-a', 'auth-a'),
    (302, 1, 'https://fcm.googleapis.com/b', 'key-b', 'auth-b')");
$pdo->exec("INSERT INTO user_settings VALUES (1, 'persian', 'jalali', 'Asia/Tehran')");

$sender = new RecordingNotificationSender();
$sender->responses['https://fcm.googleapis.com/b'] = 503;
$dispatch = new ReminderDispatchService(new ReminderDeliveryRepository($pdo));
$worker = new ReminderNotificationWorker(
    $dispatch,
    $sender,
    new UserSettingsService(new UserSettingsRepository($pdo)),
    dirname(__DIR__, 2) . '/resources/lang'
);
$now = new DateTimeImmutable('2026-09-28 10:01:00', new DateTimeZone('UTC'));
$summary = $worker->run(10, $now);
checkWorker($summary['claimed'] === 2 && $summary['accepted'] === 1 && $summary['retryable'] === 1, 'The worker must record independent browser outcomes.');
checkWorker(count($sender->calls) === 2, 'Both owned browsers must receive one fake send.');
checkWorker($sender->calls[0]['payload']['title'] === 'Pay invoice', 'The task title must be used as the notification title.');
checkWorker($sender->calls[0]['payload']['url'] === '/manage-tasks', 'Notification clicks must stay inside the application.');
checkWorker(preg_match('/[۰-۹]/u', $sender->calls[0]['payload']['body']) === 1, 'Jalali due dates must contain Persian numerals.');
checkWorker($pdo->query('SELECT status FROM task_reminders WHERE id = 201')->fetchColumn() === 'sent', 'One accepted browser must mark the parent reminder sent.');
checkWorker($worker->run(10, $now)['claimed'] === 0, 'A pending retry must not send before its backoff.');
$sender->responses['https://fcm.googleapis.com/b'] = 201;
checkWorker($worker->run(10, $now->modify('+60 seconds'))['claimed'] === 1, 'Only the failed browser should be retried.');
checkWorker(count($sender->calls) === 3 && $sender->calls[2]['endpoint'] === 'https://fcm.googleapis.com/b', 'The successful browser must not receive a duplicate.');

// Completing a task after the first browser send must stop the second claim.
$pdo->exec("INSERT INTO tasks VALUES (102, 1, NULL, 'Stop after first device', '2026-09-28 12:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (202, 102, '2026-09-28 10:00:00')");
$sender->afterSend = static function () use ($pdo): void {
    $pdo->exec('UPDATE tasks SET is_done = 1 WHERE id = 102');
};
$beforeStop = count($sender->calls);
$stopped = $worker->run(10, $now);
checkWorker(count($sender->calls) === $beforeStop + 1, 'The second browser must not be sent after task completion.');
checkWorker($stopped['skipped'] === 1, 'The invalidated claim must be reported as skipped.');
checkWorker($pdo->query("SELECT COUNT(*) FROM task_reminder_deliveries WHERE reminder_id = 202 AND status = 'unavailable'")->fetchColumn() == 1, 'The unsent browser must become unavailable.');
$sender->afterSend = null;

// Pausing a recurring series after one browser send must suppress remaining browsers.
$pdo->exec("INSERT INTO task_repeat_rules VALUES (1, 'active')");
$pdo->exec("INSERT INTO tasks VALUES (103, 1, 1, 'Weekly task', '2026-09-28 12:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (203, 103, '2026-09-28 10:00:00')");
$sender->afterSend = static function () use ($pdo): void {
    $pdo->exec("UPDATE task_repeat_rules SET status = 'paused' WHERE id = 1");
};
$beforePause = count($sender->calls);
$paused = $worker->run(10, $now);
checkWorker(count($sender->calls) === $beforePause + 1 && $paused['skipped'] === 1, 'Pausing a series must prevent the remaining browser send.');
$sender->afterSend = null;

// Editing a due date after reservation must never send the stale snapshot.
$pdo->exec("INSERT INTO tasks VALUES (104, 1, NULL, 'Rescheduled task', '2026-09-28 12:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (204, 104, '2026-09-28 10:00:00')");
$sender->afterSend = static function () use ($pdo): void {
    $pdo->exec("UPDATE tasks SET due_at = '2026-09-29 12:00:00' WHERE id = 104");
};
$beforeEdit = count($sender->calls);
$edited = $worker->run(10, $now);
checkWorker(count($sender->calls) === $beforeEdit + 1 && $edited['skipped'] === 1, 'Editing the task due date must suppress the stale browser send.');
$sender->afterSend = null;

// A provider exception is ambiguous: it must not trigger an automatic duplicate.
$pdo->exec("INSERT INTO tasks VALUES (105, 1, NULL, 'Network failure', '2026-09-28 12:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (205, 105, '2026-09-28 10:00:00')");
$sender->exceptions['https://fcm.googleapis.com/a'] = new RuntimeException('Simulated connection loss');
$ambiguous = $worker->run(10, $now);
checkWorker($ambiguous['unknown'] === 1, 'A provider exception must be stored as an unknown outcome.');
$callsAfterFailure = count($sender->calls);
checkWorker($worker->run(10, $now->modify('+10 minutes'))['claimed'] === 0 && count($sender->calls) === $callsAfterFailure, 'Unknown deliveries must not be retried automatically.');

// Calendar formatting remains Persian even when the interface language is English.
$pdo->exec("UPDATE user_settings SET language = 'english' WHERE user_id = 1");
$pdo->exec("INSERT INTO tasks VALUES (106, 1, NULL, 'English interface', '2026-09-28 12:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (206, 106, '2026-09-28 10:00:00')");
$sender->exceptions = [];
$englishCalendar = $worker->run(10, $now);
$lastPayload = $sender->calls[array_key_last($sender->calls)]['payload'];
checkWorker($englishCalendar['accepted'] === 2 && str_starts_with($lastPayload['body'], 'Due at '), 'English language must use the English notification label.');
checkWorker(preg_match('/[آ-ی]/u', $lastPayload['body']) === 1 && preg_match('/[۰-۹]/u', $lastPayload['body']) === 1, 'Jalali names and numerals must remain Persian in an English interface.');

// A malformed saved timezone is a preparation error, not an ambiguous provider send.
$pdo->exec("UPDATE user_settings SET timezone = 'Invalid/Timezone' WHERE user_id = 1");
$pdo->exec("INSERT INTO tasks VALUES (107, 1, NULL, 'Bad settings', '2026-09-28 12:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (207, 107, '2026-09-28 10:00:00')");
$beforeBadSettings = count($sender->calls);
$badSettings = $worker->run(10, $now);
checkWorker($badSettings['retryable'] === 2 && count($sender->calls) === $beforeBadSettings, 'Payload preparation failures must not call the provider and must have a bounded retry.');

echo "PASS reminder worker payload, ownership, result recording and per-browser retry (fake sender, temporary MySQL tables)\n";
