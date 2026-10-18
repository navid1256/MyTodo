<?php

declare(strict_types=1);

use App\Notifications\NotificationSender;
use App\Notifications\NotificationSendResult;
use App\Repositories\ReminderDeliveryRepository;
use App\Repositories\ReminderWorkerLockRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\ReminderDispatchService;
use App\Services\ReminderNotificationWorker;
use App\Services\ReminderWorkerPool;
use App\Services\ReminderWorkerRunner;
use App\Services\UserSettingsService;

require_once __DIR__ . '/reminder-worker-test-connection.php';

final class ConcurrentFakeSender implements NotificationSender
{
    /** Records local fixture deliveries without making any provider request. */
    public function __construct(private readonly PDO $pdo, private readonly string $prefix, private readonly int $slot) {}

    /** Simulates network latency so independently running workers overlap observably. */
    public function send(#[SensitiveParameter] array $subscription, #[SensitiveParameter] array $payload): NotificationSendResult
    {
        $statement = $this->pdo->prepare("INSERT INTO {$this->prefix}sends (tag, endpoint, worker) VALUES (:tag, :endpoint, :worker)");
        $statement->execute([':tag' => $payload['tag'], ':endpoint' => $subscription['endpoint'], ':worker' => $this->slot]);
        usleep(100_000);

        return NotificationSendResult::fromHttpStatus(201);
    }
}

try {
    $prefix = $argv[1] ?? '';
    $pdo = new IsolatedReminderTestConnection($prefix);
    $locks = new ReminderWorkerLockRepository($pdo, $prefix);
    $argument = $argv[2] ?? '';
    $isLauncher = $argument === '--launch';
    $target = [];
    $isTarget = preg_match('/\A--target=([1-9][0-9]*):([1-9][0-9]*):([1-4])\z/', $argument, $target) === 1;
    if (!$isLauncher && !$isTarget && preg_match('/\A--worker=([1-4])\z/', $argument, $matches) !== 1) {
        throw new RuntimeException('Invalid fixture process arguments.');
    }
    $slot = 0;
    if ($isTarget) {
        $slot = (int) $target[3];
    } elseif (!$isLauncher) {
        $slot = (int) $matches[1];
    }
    $lockSlot = $isLauncher ? 'launcher' : 'worker-' . $slot;
    if (!$locks->acquire($lockSlot)) {
        echo json_encode(['status' => 'busy']);
        exit;
    }
    try {
        if ($isLauncher) {
            $summary = (new ReminderWorkerPool())->run([PHP_BINARY, '-d', 'display_errors=0', __FILE__, $prefix], 4);
        } else {
            $pdo->exec("UPDATE {$prefix}metrics SET peak_workers = GREATEST(peak_workers, active_workers + 1), active_workers = active_workers + 1");
            try {
                $worker = new ReminderNotificationWorker(
                    new ReminderDispatchService(new ReminderDeliveryRepository($pdo)),
                    new ConcurrentFakeSender($pdo, $prefix, $slot),
                    new UserSettingsService(new UserSettingsRepository($pdo)),
                    dirname(__DIR__, 3) . '/resources/lang'
                );
                $summary = $isTarget
                    ? $worker->runTarget((int) $target[1], (int) $target[2])
                    : (new ReminderWorkerRunner())->run(static fn(): array => $worker->run(1), 45);
            } finally {
                $pdo->exec("UPDATE {$prefix}metrics SET active_workers = active_workers - 1");
            }
        }
        echo json_encode(['status' => 'completed', 'summary' => $summary]);
    } finally {
        $locks->release($lockSlot);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Fixture worker failed (' . $exception::class . ").\n");
    exit(1);
}
