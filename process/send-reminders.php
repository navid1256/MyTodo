<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Config\ReminderWorkerConfiguration;
use App\Config\WebPushConfiguration;
use App\Database\Database;
use App\Notifications\WebPushNotificationSender;
use App\Repositories\ReminderDeliveryRepository;
use App\Repositories\ReminderWorkerLockRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\PushSubscriptionValidator;
use App\Services\ReminderDispatchService;
use App\Services\ReminderNotificationWorker;
use App\Services\ReminderWorkerPool;
use App\Services\ReminderWorkerRunner;
use App\Services\UserSettingsService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$arguments = array_slice($argv, 1);
if ($arguments === ['--help']) {
    fwrite(STDOUT, "Usage: php process/send-reminders.php --send\n");
    fwrite(STDOUT, "The --send option enables real Web Push delivery for due reminders.\n");
    fwrite(STDOUT, "REMINDER_WORKERS=4 (1-16), REMINDER_RUNTIME_SECONDS=45 (1-50).\n");
    fwrite(STDOUT, "The launcher starts a bounded pool; --worker=N is reserved for its children.\n");
    exit(0);
}
$workerSlot = null;
if (count($arguments) === 2 && $arguments[0] === '--send'
    && preg_match('/\A--worker=([1-9]|1[0-6])\z/', $arguments[1], $matches) === 1) {
    $workerSlot = (int) $matches[1];
} elseif ($arguments !== ['--send']) {
    fwrite(STDERR, "Real delivery requires the explicit --send option. Use --help for usage.\n");
    exit(2);
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/vendor/autoload.php';

try {
    EnvironmentLoader::load($rootPath);
    $workerConfiguration = new ReminderWorkerConfiguration(require $rootPath . '/config/reminder-worker.php');
    if ($workerSlot !== null && $workerSlot > $workerConfiguration->workers) {
        fwrite(STDERR, "Worker slot exceeds REMINDER_WORKERS.\n");
        exit(2);
    }
    $pushConfiguration = new WebPushConfiguration(require $rootPath . '/config/web-push.php');
    // Validate keys before reserving any reminder, avoiding stranded claims on setup errors.
    $pushConfiguration->authentication();
    $pdo = Database::connect(require $rootPath . '/config/database.php');
    $locks = new ReminderWorkerLockRepository($pdo);
    $lockSlot = $workerSlot === null ? 'launcher' : 'worker-' . $workerSlot;
    if (!$locks->acquire($lockSlot)) {
        fwrite(STDOUT, $workerSlot === null
            ? "Reminder worker: previous launcher is still active.\n"
            : json_encode(['status' => 'busy']) . "\n");
        exit(0);
    }
    try {
        if ($workerSlot !== null) {
            $worker = new ReminderNotificationWorker(
                new ReminderDispatchService(new ReminderDeliveryRepository($pdo)),
                new WebPushNotificationSender($pushConfiguration, new PushSubscriptionValidator()),
                new UserSettingsService(new UserSettingsRepository($pdo)),
                $rootPath . '/resources/lang'
            );
            $summary = (new ReminderWorkerRunner())->run(
                static fn(): array => $worker->run(1),
                $workerConfiguration->runtimeSeconds
            );
            fwrite(STDOUT, json_encode(['status' => 'completed', 'summary' => $summary]) . "\n");
        } else {
            $summary = (new ReminderWorkerPool())->run(
                [PHP_BINARY, '-d', 'display_errors=0', __FILE__, '--send'],
                $workerConfiguration->workers
            );
        }
    } finally {
        $locks->release($lockSlot);
    }
    if ($workerSlot !== null) {
        exit(0);
    }
    fwrite(STDOUT, sprintf(
        "Reminder worker: claimed=%d accepted=%d retryable=%d expired=%d failed=%d unknown=%d skipped=%d busy_workers=%d failed_workers=%d\n",
        $summary['claimed'],
        $summary['accepted'],
        $summary['retryable'],
        $summary['expired'],
        $summary['failed'],
        $summary['unknown'],
        $summary['skipped'],
        $summary['busy_workers'],
        $summary['failed_workers']
    ));
    exit($summary['failed_workers'] > 0 ? 1 : 0);
} catch (Throwable $exception) {
    // The CLI never prints endpoints, key material, task titles or raw provider errors.
    fwrite(STDERR, 'Reminder worker failed (' . $exception::class . ").\n");
    exit(1);
}
