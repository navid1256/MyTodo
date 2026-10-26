<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Config\ReminderWorkerArguments;
use App\Config\ReminderWorkerConfiguration;
use App\Config\WebPushConfiguration;
use App\Database\Database;
use App\Exceptions\ReminderWorkerException;
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
    fwrite(STDOUT, "Single target: php process/send-reminders.php --send --reminder-id=N --subscription-id=N\n");
    fwrite(STDOUT, "Targeted mode processes at most one owned pair, without a pool or shared-queue cleanup.\n");
    fwrite(STDOUT, "The --send option enables real Web Push delivery for due reminders.\n");
    fwrite(STDOUT, "REMINDER_WORKERS=4 (1-16), REMINDER_RUNTIME_SECONDS=45 (1-50).\n");
    fwrite(STDOUT, "The launcher starts a bounded pool; --worker=N is reserved for its children.\n");
    exit(0);
}
$rootPath = dirname(__DIR__);
require_once $rootPath . '/vendor/autoload.php';

try {
    $command = new ReminderWorkerArguments($arguments);
} catch (ReminderWorkerException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(2);
}
$workerSlot = $command->workerSlot;
$isTargeted = $command->reminderId !== null;
$isLauncher = $workerSlot === null && !$isTargeted;

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
        if ($isLauncher) {
            fwrite(STDOUT, "Reminder worker: previous launcher is still active.\n");
        } else {
            fwrite(STDOUT, json_encode(['status' => 'busy']) . "\n");
        }
        exit(0);
    }
    try {
        if ($isLauncher) {
            $childCommand = [PHP_BINARY, '-d', 'display_errors=0', __FILE__, '--send'];
            $summary = (new ReminderWorkerPool())->run($childCommand, $workerConfiguration->workers);
        } else {
            $worker = new ReminderNotificationWorker(
                new ReminderDispatchService(new ReminderDeliveryRepository($pdo)),
                new WebPushNotificationSender($pushConfiguration, new PushSubscriptionValidator()),
                new UserSettingsService(new UserSettingsRepository($pdo)),
                $rootPath . '/resources/lang'
            );
            if ($isTargeted) {
                // Argument validation guarantees that both target IDs are present and positive.
                $summary = $worker->runTarget((int) $command->reminderId, (int) $command->subscriptionId);
            } else {
                $summary = (new ReminderWorkerRunner())->run(
                    static fn(): array => $worker->run(1),
                    $workerConfiguration->runtimeSeconds
                );
            }
            $report = ['status' => 'completed', 'summary' => $summary];
            if ($isTargeted) {
                $report['mode'] = 'targeted';
            }
            fwrite(STDOUT, json_encode($report) . "\n");
        }
    } finally {
        $locks->release($lockSlot);
    }
    if (!$isLauncher) {
        exit(0);
    }
    $summaryFields = [];
    foreach ($summary as $counter => $count) {
        $summaryFields[] = $counter . '=' . $count;
    }
    fwrite(STDOUT, 'Reminder worker: ' . implode(' ', $summaryFields) . "\n");
    exit($summary['failed_workers'] > 0 ? 1 : 0);
} catch (Throwable $exception) {
    // The CLI never prints endpoints, key material, task titles or raw provider errors.
    fwrite(STDERR, 'Reminder worker failed (' . $exception::class . ").\n");
    exit(1);
}
