<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Database\Database;
use App\Exceptions\PushSubscriptionValidationException;
use App\Repositories\PushSubscriptionRepository;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
EnvironmentLoader::load(dirname(__DIR__, 2));
$pdo = Database::connect(require dirname(__DIR__, 2) . '/config/database.php');
// Connection-local temporary tables shadow production tables and disappear on exit.
// No real user, subscription or reminder is inserted/modified by this test.
$pdo->exec('CREATE TEMPORARY TABLE users (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('INSERT INTO users VALUES (10), (20)');
$migration = file_get_contents(dirname(__DIR__, 2) . '/Database/migrations/20260927_create_push_subscriptions.sql');
$temporarySchema = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $migration);
$temporarySchema = preg_replace('/,\s*CONSTRAINT `push_subscriptions_user_foreign`.*?ON UPDATE CASCADE/s', '', $temporarySchema);
$pdo->exec($temporarySchema);
$repository = new PushSubscriptionRepository($pdo);
$subscription = ['endpoint' => 'https://fcm.googleapis.com/test', 'p256dh' => 'test-key', 'auth' => 'test-auth'];
$repository->saveForUser(10, $subscription, 'Test browser');
$repository->saveForUser(10, $subscription, 'Updated browser');
if ((int) $pdo->query('SELECT COUNT(*) FROM push_subscriptions')->fetchColumn() !== 1) {
    throw new RuntimeException('Registration retries must not duplicate endpoints.');
}
try {
    $repository->saveForUser(20, $subscription, 'Foreign browser');
    throw new RuntimeException('Another user stole the subscription.');
} catch (PushSubscriptionValidationException $exception) {
    if ($exception->statusCode() !== 409) {
        throw new RuntimeException('Subscription ownership conflict must return 409.');
    }
}
$repository->deleteForUser(20, $subscription['endpoint']);
if (!$repository->hasForUser(10, $subscription['endpoint']) || $repository->hasForUser(20, $subscription['endpoint'])) {
    throw new RuntimeException('Reads/deletes must be scoped to the authenticated owner.');
}
for ($index = 1; $index < 10; $index++) {
    $repository->saveForUser(10, array_replace($subscription, ['endpoint' => $subscription['endpoint'] . $index]), 'Browser');
}
try {
    $repository->saveForUser(10, array_replace($subscription, ['endpoint' => $subscription['endpoint'] . '/overflow']), 'Browser');
    throw new RuntimeException('Per-user device quota was bypassed.');
} catch (PushSubscriptionValidationException $exception) {
    if ($exception->translationKey() !== 'push.device_limit') {
        throw new RuntimeException('Expected the device quota error.');
    }
}
$repository->deleteForUser(10, $subscription['endpoint']);
$repository->deleteForUser(10, $subscription['endpoint']);
if ($repository->hasForUser(10, $subscription['endpoint']) || $pdo->inTransaction()) {
    throw new RuntimeException('Deletion must be idempotent and transactions must close.');
}
echo "PASS MySQL subscription registration, deduplication, ownership and quota (temporary tables only)\n";
