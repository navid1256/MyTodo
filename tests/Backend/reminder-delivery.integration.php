<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Database\Database;
use App\Notifications\NotificationSendResult;
use App\Exceptions\NotificationValidationException;
use App\Repositories\NotificationRepository;
use App\Repositories\ReminderDeliveryRepository;
use App\Services\ReminderDispatchService;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Asserts a database delivery invariant without requiring PHPUnit. */
function checkDelivery(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

EnvironmentLoader::load(dirname(__DIR__, 2));
$pdo = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');

// Temporary tables shadow production tables only on this connection and vanish on disconnect.
$pdo->exec("CREATE TEMPORARY TABLE task_repeat_rules (id INT UNSIGNED PRIMARY KEY, status VARCHAR(20) NOT NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE tasks (id INT UNSIGNED PRIMARY KEY, user_id INT UNSIGNED NOT NULL, repeat_rule_id INT UNSIGNED NULL, title VARCHAR(512) NOT NULL, due_at DATETIME NULL, has_time TINYINT NOT NULL DEFAULT 1, is_done TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE task_reminders (id INT UNSIGNED PRIMARY KEY, task_id INT UNSIGNED NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', offset_value INT NOT NULL DEFAULT 0, offset_unit VARCHAR(10) NOT NULL DEFAULT 'minute', remind_at DATETIME NOT NULL, attempt_count INT UNSIGNED NOT NULL DEFAULT 0, last_attempt_at DATETIME NULL, sent_at DATETIME NULL) ENGINE=InnoDB");
$pdo->exec("CREATE TEMPORARY TABLE push_subscriptions (id INT UNSIGNED PRIMARY KEY, user_id INT UNSIGNED NOT NULL, endpoint VARCHAR(2048) NOT NULL, p256dh VARCHAR(87) NOT NULL, auth VARCHAR(22) NOT NULL, content_encoding VARCHAR(16) NOT NULL DEFAULT 'aes128gcm', last_used_at DATETIME NULL) ENGINE=InnoDB");

$migration = file_get_contents(dirname(__DIR__, 2) . '/Database/migrations/20260928_create_task_reminder_deliveries.sql');
checkDelivery(is_string($migration), 'Delivery migration must exist.');
$temporarySchema = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $migration);
$temporarySchema = preg_replace('/,\s*CONSTRAINT `task_reminder_deliveries_reminder_foreign`.*?ON UPDATE CASCADE/s', '', $temporarySchema);
$pdo->exec($temporarySchema);

$pdo->exec("INSERT INTO task_repeat_rules VALUES (1, 'active'), (2, 'paused'), (3, 'completed'), (4, 'cancelled')");
$pdo->exec("INSERT INTO tasks VALUES
    (101, 1, NULL, 'Task for two browsers', '2026-09-28 11:00:00', 1, 0),
    (102, 1, 2, 'Paused series', '2026-09-28 11:00:00', 1, 0),
    (103, 1, NULL, 'Completed task', '2026-09-28 11:00:00', 1, 1),
    (104, 1, NULL, 'Cancelled reminder task', '2026-09-28 11:00:00', 1, 0),
    (105, 2, NULL, 'No browsers', '2026-09-28 11:00:00', 1, 0),
    (106, 1, NULL, 'Future task', '2026-09-30 11:00:00', 1, 0),
    (107, 1, 3, 'Finished rule still has a task', '2026-09-28 11:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, status, remind_at) VALUES
    (201, 101, 'pending', '2026-09-28 10:00:00'),
    (202, 102, 'pending', '2026-09-28 10:00:00'),
    (203, 103, 'pending', '2026-09-28 10:00:00'),
    (204, 104, 'cancelled', '2026-09-28 10:00:00'),
    (205, 105, 'pending', '2026-09-28 10:00:00'),
    (206, 106, 'pending', '2026-09-30 10:00:00'),
    (207, 107, 'pending', '2026-09-28 10:00:00')");
$pdo->exec("INSERT INTO push_subscriptions (id, user_id, endpoint, p256dh, auth) VALUES
    (301, 1, 'https://fcm.googleapis.com/a', 'key-a', 'auth-a'),
    (302, 1, 'https://fcm.googleapis.com/b', 'key-b', 'auth-b')");

$service = new ReminderDispatchService(new ReminderDeliveryRepository($pdo));
$now = new DateTimeImmutable('2026-09-28 10:01:00', new DateTimeZone('UTC'));
$claims = $service->reserveBatch(10, $now);
checkDelivery(count($claims) === 4, 'Only due, incomplete and active/finished-series reminders should fan out to owned browsers.');
checkDelivery(array_column($claims, 'reminder_id') === [201, 201, 207, 207], 'Fan-out must create one delivery per owned browser.');
checkDelivery($service->reserveBatch(10, $now) === [], 'A second worker must not reserve the same in-flight deliveries.');
checkDelivery((int) $pdo->query('SELECT COUNT(*) FROM task_reminder_deliveries')->fetchColumn() === 4, 'Rerunning fan-out must not duplicate deliveries.');
checkDelivery($pdo->query('SELECT status FROM task_reminders WHERE id = 205')->fetchColumn() === 'failed', 'Due reminders without browsers must become failed.');
checkDelivery($pdo->query('SELECT status FROM task_reminders WHERE id = 202')->fetchColumn() === 'pending', 'Paused series must not be sent.');

$first = $claims[0];
$second = $claims[1];
checkDelivery($first['user_id'] === 1 && $first['endpoint'] === 'https://fcm.googleapis.com/a', 'A claim must include only its owner’s subscription.');
$notifications = new NotificationRepository($pdo);
try {
    $notifications->update(201, 5, 'minute', '2026-09-30 10:00:00');
    throw new RuntimeException('An in-flight reminder was edited.');
} catch (NotificationValidationException) {
    // Expected: editing cannot invalidate a live delivery claim.
}
checkDelivery((int) $pdo->query('SELECT COUNT(*) FROM task_reminder_deliveries WHERE reminder_id = 201')->fetchColumn() === 2, 'Rejected edits must preserve both delivery claims.');
checkDelivery($service->recordResult($first, NotificationSendResult::fromHttpStatus(201), $now), 'Accepted result must be recorded.');
checkDelivery(!$service->recordResult($first, NotificationSendResult::fromHttpStatus(201), $now), 'The same claim must not be completed twice.');
checkDelivery($pdo->query('SELECT status FROM task_reminders WHERE id = 201')->fetchColumn() === 'sent', 'One accepted browser makes the reminder sent.');
checkDelivery($pdo->query('SELECT last_used_at FROM push_subscriptions WHERE id = 301')->fetchColumn() === $now->format('Y-m-d H:i:s'), 'Accepted delivery must update browser last-used time.');

checkDelivery($service->recordResult($second, NotificationSendResult::fromHttpStatus(503), $now), 'Retryable result must be recorded.');
checkDelivery($pdo->query('SELECT status FROM task_reminder_deliveries WHERE subscription_id = 302 AND reminder_id = 201')->fetchColumn() === 'retryable', 'Only the failed browser is retryable.');
checkDelivery($service->reserveBatch(10, $now) === [], 'Retry must wait for its backoff.');
$retryTime = $now->modify('+60 seconds');
$retry = $service->reserveBatch(10, $retryTime);
checkDelivery(count($retry) === 1 && $retry[0]['subscription_id'] === 302, 'Retry must not resend to the successful browser.');
checkDelivery($service->recordResult($retry[0], NotificationSendResult::fromHttpStatus(410), $retryTime), 'Expired browser must be recorded.');
checkDelivery((int) $pdo->query('SELECT COUNT(*) FROM push_subscriptions WHERE id = 302')->fetchColumn() === 0, 'Expired subscription must be removed.');
checkDelivery($pdo->query('SELECT status FROM task_reminders WHERE id = 201')->fetchColumn() === 'sent', 'Another browser’s expiry must not erase prior success.');

$unknown = $claims[2];
checkDelivery($service->recordResult($unknown, NotificationSendResult::fromHttpStatus(null), $now), 'Ambiguous network result must be recorded.');
checkDelivery($pdo->query('SELECT status FROM task_reminder_deliveries WHERE id = ' . (int) $unknown['id'])->fetchColumn() === 'unknown', 'Unknown results must not retry automatically.');
checkDelivery($service->reserveBatch(10, $now->modify('+20 minutes')) === [], 'Unknown results and leased claims must not be retried automatically.');
checkDelivery($pdo->query('SELECT status FROM task_reminder_deliveries WHERE id = ' . (int) $claims[3]['id'])->fetchColumn() === 'unknown', 'Stale leases become unknown.');
checkDelivery(!$service->recordResult($claims[3], NotificationSendResult::fromHttpStatus(201), $now->modify('+20 minutes')), 'Late worker must not overwrite an expired claim.');

// A third transient failure is terminal and must never replay a prior accepted browser.
$pdo->exec("INSERT INTO tasks VALUES (108, 1, NULL, 'Retry exhaustion', '2026-09-28 11:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (208, 108, '2026-09-28 10:00:00')");
$retryClaim = $service->reserveBatch(10, $now)[0];
checkDelivery($service->recordResult($retryClaim, NotificationSendResult::fromHttpStatus(429), $now), 'Rate-limited delivery must be recorded.');
$retryClaim = $service->reserveBatch(10, $now->modify('+60 seconds'))[0];
checkDelivery($service->recordResult($retryClaim, NotificationSendResult::fromHttpStatus(503), $now->modify('+60 seconds')), 'Second transient attempt must be recorded.');
$retryClaim = $service->reserveBatch(10, $now->modify('+360 seconds'))[0];
checkDelivery($service->recordResult($retryClaim, NotificationSendResult::fromHttpStatus(503), $now->modify('+360 seconds')), 'Third transient attempt must be recorded.');
checkDelivery($pdo->query('SELECT status FROM task_reminder_deliveries WHERE reminder_id = 208')->fetchColumn() === 'failed', 'Retries stop after three total attempts.');
checkDelivery($pdo->query('SELECT status FROM task_reminders WHERE id = 208')->fetchColumn() === 'failed', 'Exhausted reminder becomes failed.');
checkDelivery($service->reserveBatch(10, $now->modify('+24 hours')) === [], 'Exhausted retries must stay terminal.');

// Editing a failed reminder must discard the delivery attempts tied to its old time.
checkDelivery($notifications->update(208, 5, 'minute', '2026-09-30 10:00:00'), 'A failed reminder can be rescheduled.');
checkDelivery((int) $pdo->query('SELECT COUNT(*) FROM task_reminder_deliveries WHERE reminder_id = 208')->fetchColumn() === 0, 'Rescheduling must remove stale per-browser history.');
checkDelivery($pdo->query('SELECT status FROM task_reminders WHERE id = 208')->fetchColumn() === 'pending', 'Rescheduled reminder is pending again.');

// A removed browser cannot receive a pending delivery on a later run.
$pdo->exec("INSERT INTO tasks VALUES (109, 1, NULL, 'Browser removed', '2026-09-28 11:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (209, 109, '2026-09-28 10:00:00')");
$repository = new ReminderDeliveryRepository($pdo);
$repository->enqueueDueReminders($now->format('Y-m-d H:i:s'), 10);
$pdo->exec('DELETE FROM push_subscriptions WHERE id = 301');
checkDelivery($service->reserveBatch(10, $now) === [], 'Unsubscribed browsers must not be claimed.');
checkDelivery($pdo->query('SELECT status FROM task_reminder_deliveries WHERE reminder_id = 209')->fetchColumn() === 'unavailable', 'Removed browser delivery becomes unavailable.');
checkDelivery($pdo->query('SELECT status FROM task_reminders WHERE id = 209')->fetchColumn() === 'failed', 'No remaining browser leaves the reminder failed.');

// A rejected old key must not remove a subscription refreshed during the request.
$pdo->exec("INSERT INTO push_subscriptions (id, user_id, endpoint, p256dh, auth) VALUES
    (303, 1, 'https://fcm.googleapis.com/c', 'old-key', 'old-auth')");
$pdo->exec("INSERT INTO tasks VALUES (110, 1, NULL, 'Refreshed browser', '2026-09-28 11:00:00', 1, 0)");
$pdo->exec("INSERT INTO task_reminders (id, task_id, remind_at) VALUES (210, 110, '2026-09-28 10:00:00')");
$oldKeyClaim = $service->reserveBatch(10, $now)[0];
$pdo->exec("UPDATE push_subscriptions SET p256dh = 'new-key', auth = 'new-auth' WHERE id = 303");
checkDelivery($service->recordResult($oldKeyClaim, NotificationSendResult::fromHttpStatus(410), $now), 'Expired old-key result must be recorded.');
checkDelivery($pdo->query('SELECT p256dh FROM push_subscriptions WHERE id = 303')->fetchColumn() === 'new-key', 'Expired old keys must not delete a refreshed browser.');

echo "PASS reminder delivery fan-out, ownership, reservation, retry, expiry and ambiguity (temporary MySQL tables only)\n";
