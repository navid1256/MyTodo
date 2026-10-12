<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Config\WebPushConfiguration;
use App\Database\Database;
use App\Notifications\WebPushNotificationSender;
use App\Repositories\ReminderDeliveryRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\PushSubscriptionValidator;
use App\Services\ReminderDispatchService;
use App\Services\ReminderNotificationWorker;
use App\Services\UserSettingsService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$arguments = array_slice($argv, 1);
if ($arguments === ['--help']) {
    fwrite(STDOUT, "Usage: php process/send-reminders.php --send\n");
    fwrite(STDOUT, "The --send option enables real Web Push delivery for due reminders.\n");
    exit(0);
}
if ($arguments !== ['--send']) {
    fwrite(STDERR, "Real delivery requires the explicit --send option. Use --help for usage.\n");
    exit(2);
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/vendor/autoload.php';

try {
    EnvironmentLoader::load($rootPath);
    $pushConfiguration = new WebPushConfiguration(require $rootPath . '/config/web-push.php');
    // Validate keys before reserving any reminder, avoiding stranded claims on setup errors.
    $pushConfiguration->authentication();
    $pdo = Database::connect(require $rootPath . '/config/database.php');
    $worker = new ReminderNotificationWorker(
        new ReminderDispatchService(new ReminderDeliveryRepository($pdo)),
        new WebPushNotificationSender($pushConfiguration, new PushSubscriptionValidator()),
        new UserSettingsService(new UserSettingsRepository($pdo)),
        $rootPath . '/resources/lang'
    );
    // Ten sequential 15-second sends fit inside the five-minute claim lease.
    $summary = $worker->run(10);
    fwrite(STDOUT, sprintf(
        "Reminder worker: claimed=%d accepted=%d retryable=%d expired=%d failed=%d unknown=%d skipped=%d\n",
        $summary['claimed'],
        $summary['accepted'],
        $summary['retryable'],
        $summary['expired'],
        $summary['failed'],
        $summary['unknown'],
        $summary['skipped']
    ));
} catch (Throwable $exception) {
    // The CLI never prints endpoints, key material, task titles or raw provider errors.
    fwrite(STDERR, 'Reminder worker failed (' . $exception::class . ").\n");
    exit(1);
}
