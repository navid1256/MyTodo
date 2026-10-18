<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Config\WebPushConfiguration;
use App\Controllers\PushSubscriptionController;
use App\Http\Request;
use App\Repositories\PushSubscriptionRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\PushSubscriptionService;
use App\Services\PushSubscriptionValidator;
use App\Services\UserSettingsService;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
function checkPushApi(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// Deterministic P-256 test-only keys: not used by application configuration.
$encode = static fn(string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
$publicKey = $encode(hex2bin('046b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c2964fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5'));
$privateKey = $encode(str_repeat("\0", 31) . "\1");
$configuration = new WebPushConfiguration(['public_key' => $publicKey, 'private_key' => $privateKey, 'subject' => 'mailto:test@example.test', 'keys_file' => '']);
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE user_settings (user_id INTEGER, language TEXT, calendar_system TEXT, timezone TEXT)');
$pdo->exec("INSERT INTO user_settings VALUES (10, 'persian', 'gregorian', 'UTC')");
$service = new PushSubscriptionService(new PushSubscriptionRepository($pdo), new PushSubscriptionValidator(), $configuration);
$controller = new PushSubscriptionController($service, new UserSettingsService(new UserSettingsRepository($pdo)));
$request = static fn(array $post): Request => new Request([], $post, ['REQUEST_METHOD' => 'POST']);
$_SESSION = [];
checkPushApi($controller->status($request([]))->getStatusCode() === 401, 'All push actions require authentication.');
$_SESSION = ['user' => ['id' => 10], 'csrf_token' => 'test-token'];
foreach (['status', 'subscribe', 'unsubscribe'] as $action) {
    checkPushApi($controller->$action($request(['csrf_token' => 'wrong']))->getStatusCode() === 403, 'All push actions require CSRF.');
}
$response = $controller->status($request(['csrf_token' => 'test-token']));
checkPushApi($response->getStatusCode() === 200, 'Public configuration must be readable by the authenticated user.');
$data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
checkPushApi($data['publicKey'] === $publicKey && $data['configured'] && !$data['subscribed'], 'Status must expose only public configuration.');
checkPushApi(!str_contains($response->getBody(), $privateKey) && !str_contains($response->getBody(), 'privateKey'), 'Private VAPID key must never leave the server.');
$response = $controller->subscribe($request(['csrf_token' => 'test-token', 'subscription' => '{broken']));
checkPushApi($response->getStatusCode() === 422 && str_contains($response->getBody(), 'اطلاعات اشتراک'), 'Malformed subscriptions must return translated validation.');
checkPushApi($controller->unsubscribe($request(['csrf_token' => 'test-token', 'endpoint' => 'https://localhost/test']))->getStatusCode() === 422, 'Unsubscribe also validates hostile endpoints.');
checkPushApi(EnvironmentLoader::get('MYTODO_TEST_ABSENT_VARIABLE', 'fallback') === 'fallback', 'Optional Web Push config must not break environment loading.');
$invalidFile = tempnam(sys_get_temp_dir(), 'mytodo-push-test-');
try {
    file_put_contents($invalidFile, '{invalid-json');
    $lazyConfiguration = new WebPushConfiguration(['public_key' => '', 'private_key' => '', 'subject' => '', 'keys_file' => $invalidFile]);
    // Construction by Application must not load a corrupt optional feature file.
    try {
        $lazyConfiguration->isConfigured();
        throw new RuntimeException('Corrupt configuration was silently accepted.');
    } catch (App\Exceptions\WebPushConfigurationException) {
        // Only notification operations report the configuration failure.
    }
} finally {
    unlink($invalidFile);
}
$en = require dirname(__DIR__, 2) . '/resources/lang/en.php';
$fa = require dirname(__DIR__, 2) . '/resources/lang/fa.php';
checkPushApi(array_diff_key($en, $fa) === [] && array_diff_key($fa, $en) === [], 'Translation catalogs must have identical keys.');
echo "PASS push API authentication, CSRF, translations, configuration and secret isolation\n";
