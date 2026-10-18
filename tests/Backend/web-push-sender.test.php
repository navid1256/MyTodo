<?php

declare(strict_types=1);

use App\Config\WebPushConfiguration;
use App\Exceptions\NotificationPayloadValidationException;
use App\Exceptions\NotificationTransportException;
use App\Exceptions\PushSubscriptionValidationException;
use App\Exceptions\WebPushConfigurationException;
use App\Notifications\NotificationSender;
use App\Notifications\WebPushNotificationSender;
use App\Services\PushSubscriptionValidator;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Fails the standalone test without requiring a test framework. */
function checkSender(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Encodes public, deterministic test fixtures; never reads application keys. */
function senderFixtureKey(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/** Supplies deterministic public DNS answers without accessing a DNS server. */
function senderPublicAddresses(string $host): array
{
    checkSender($host === 'fcm.googleapis.com', 'Unexpected DNS host.');
    return ['8.8.8.8'];
}

/** Independently decrypts one RFC 8291 record using only the public test fixture key. */
function senderDecryptFixture(string $wireBody, string $encodedPublicKey): array
{
    $browserKey = base64_decode(strtr($encodedPublicKey, '-_', '+/'), true);
    $salt = substr($wireBody, 0, 16);
    checkSender(ord($wireBody[20]) === 65, 'Encrypted record must contain a P-256 sender key.');
    $senderKey = substr($wireBody, 21, 65);
    $publicDer = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $senderKey;
    $privateDer = hex2bin('30770201010420') . str_repeat("\0", 31) . "\1"
        . hex2bin('a00a06082a8648ce3d030107a144034200') . $browserKey;
    $publicPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($publicDer), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $privatePem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($privateDer), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    $sharedSecret = openssl_pkey_derive($publicPem, $privatePem, 32);
    checkSender($sharedSecret !== false, 'Fixture key agreement must succeed.');
    $ikm = hash_hkdf('sha256', $sharedSecret, 32, "WebPush: info\0" . $browserKey . $senderKey, str_repeat('a', 16));
    $key = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $plaintext = openssl_decrypt(substr($wireBody, 86, -16), 'aes-128-gcm', $key, OPENSSL_RAW_DATA, $nonce, substr($wireBody, -16));
    checkSender($plaintext !== false, 'Fixture must authenticate and decrypt the entire payload.');
    $record = rtrim($plaintext, "\0");
    checkSender(str_ends_with($record, "\2"), 'Final encrypted record delimiter must be present.');

    return json_decode(substr($record, 0, -1), true, 512, JSON_THROW_ON_ERROR);
}

/** Records mock HTTP requests while keeping every send completely offline. */
final class SenderTestTransport
{
    public array $requests = [];

    /** Chooses a simulated provider status or a simulated network failure. */
    public function __construct(private readonly ?int $status = 201) {}

    /** Captures encrypted requests and returns a response without network I/O. */
    public function respond(string $method, string $url, array $options): MockResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
        if ($this->status === null) {
            throw new TransportException('Simulated connection loss.');
        }
        return new MockResponse('', [
            'http_code' => $this->status,
            'response_headers' => ['Location: https://127.0.0.1/never-follow'],
        ]);
    }
}

// The P-256 generator point and scalar 1 are public test fixtures, not secrets.
$publicKey = senderFixtureKey(hex2bin('046b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c2964fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5'));
$config = new WebPushConfiguration([
    'public_key' => $publicKey,
    'private_key' => senderFixtureKey(str_repeat("\0", 31) . "\1"),
    'subject' => 'mailto:sender-test@example.test',
    'keys_file' => '',
]);
$subscription = [
    'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-device',
    'p256dh' => $publicKey,
    'auth' => senderFixtureKey(str_repeat('a', 16)),
];
$payload = ['title' => 'یادآوری کار', 'body' => 'زمان انجام کار رسیده است.', 'tag' => 'reminder-7', 'url' => '/notifications?task=7'];

foreach ([
    [201, 'accepted'], [202, 'accepted'], [204, 'accepted'],
    [404, 'expired'], [410, 'expired'],
    [408, 'retryable'], [429, 'retryable'], [500, 'retryable'], [503, 'retryable'],
    [400, 'failed'], [401, 'failed'], [403, 'failed'], [413, 'failed'], [302, 'failed'],
    [null, 'unknown'],
] as [$status, $outcome]) {
    $transport = new SenderTestTransport($status);
    $sender = new WebPushNotificationSender($config, new PushSubscriptionValidator(), new MockHttpClient($transport->respond(...)), senderPublicAddresses(...));
    checkSender($sender instanceof NotificationSender, 'Sender must implement the shared contract.');
    $result = $sender->send($subscription, $payload);
    checkSender($result->outcome === $outcome && $result->statusCode === $status, 'Provider response classification is incorrect.');
    checkSender(count($transport->requests) === 1, 'Sender must never retry or follow redirects on its own.');
    $request = $transport->requests[0];
    $options = $request['options'];
    $headers = implode("\n", $options['headers']);
    checkSender($request['method'] === 'POST' && $request['url'] === $subscription['endpoint'], 'Push must use the exact validated endpoint.');
    checkSender(str_contains(strtolower($headers), 'content-encoding: aes128gcm'), 'Use the modern encrypted content encoding.');
    checkSender(str_contains(strtolower($headers), 'authorization: vapid '), 'VAPID must authenticate the sender.');
    checkSender(str_contains(strtolower($headers), 'ttl: 3600'), 'Undelivered reminders must have bounded retention.');
    checkSender(is_string($options['body']) && !str_contains($options['body'], $payload['title']), 'Plaintext payload must not leave the sender.');
    checkSender(senderDecryptFixture($options['body'], $publicKey) === $payload, 'Persian payload and click URL must survive encryption unchanged.');
    checkSender($options['max_redirects'] === 0, 'Redirects must be disabled.');
    checkSender($options['verify_peer'] === true && $options['verify_host'] === true, 'TLS verification must stay enabled.');
    checkSender($options['resolve']['fcm.googleapis.com'] === '8.8.8.8', 'Validated DNS must be pinned to prevent rebinding.');
    checkSender($options['timeout'] === 10.0 && $options['max_duration'] === 15.0, 'Network requests must have bounded timeouts.');
    checkSender($options['proxy'] === '' && $options['no_proxy'] === '*', 'Proxy environment variables must not bypass endpoint validation.');
    checkSender(array_keys(get_object_vars($result)) === ['outcome', 'statusCode'], 'Public outcomes must not leak endpoints, payloads or keys.');
}

$transport = new SenderTestTransport();
$sender = new WebPushNotificationSender($config, new PushSubscriptionValidator(), new MockHttpClient($transport->respond(...)), senderPublicAddresses(...));
foreach (['https://localhost/a', 'https://fcm.googleapis.com.attacker.test/a', 'https://user:password@fcm.googleapis.com/a'] as $endpoint) {
    try {
        $sender->send(array_replace($subscription, ['endpoint' => $endpoint]), $payload);
        throw new RuntimeException('Unsafe endpoint was accepted.');
    } catch (PushSubscriptionValidationException) {
        // Expected: rejected before network access.
    }
}
foreach ([['p256dh' => 'invalid'], ['auth' => 'invalid'], ['content_encoding' => 'aesgcm']] as $invalid) {
    try {
        $sender->send(array_replace($subscription, $invalid), $payload);
        throw new RuntimeException('Invalid subscription was accepted.');
    } catch (PushSubscriptionValidationException) {
        // Expected: invalid stored subscriptions cannot reach the provider.
    }
}
foreach ([
    ['title' => ''], ['body' => []], ['body' => "\xFF"], ['body' => str_repeat('a', 1001)],
    ['title' => str_repeat('ش', 512), 'body' => str_repeat('ش', 1000), 'tag' => str_repeat('ش', 128), 'url' => '/notifications?' . str_repeat('a', 1900)],
    ['url' => 'https://attacker.test/'], ['url' => '//attacker.test/'], ['url' => '/auth'], ['url' => '/notifications\\evil'],
] as $invalid) {
    try {
        $sender->send($subscription, array_replace($payload, $invalid));
        throw new RuntimeException('Invalid payload was accepted.');
    } catch (NotificationPayloadValidationException) {
        // Expected: malformed, oversized or external payloads stay local.
    }
}
checkSender($transport->requests === [], 'Validation failures must not send any request.');

foreach ([['127.0.0.1'], ['10.0.0.1'], ['169.254.169.254'], ['::1'], ['fc00::1'], ['224.0.0.1'], ['8.8.8.8', '10.0.0.1']] as $addresses) {
    /** Returns unsafe DNS fixtures to verify all records, not only the first. */
    $resolver = static function (string $host) use ($addresses): array {
        return $addresses;
    };
    $sender = new WebPushNotificationSender($config, new PushSubscriptionValidator(), new MockHttpClient($transport->respond(...)), $resolver);
    try {
        $sender->send($subscription, $payload);
        throw new RuntimeException('Private or reserved DNS answer was accepted.');
    } catch (PushSubscriptionValidationException) {
        // Expected: all DNS answers must belong to public networks.
    }
}

/** Simulates DNS failure without using the network. */
$emptyResolver = static function (string $host): array {
    return [];
};
$sender = new WebPushNotificationSender($config, new PushSubscriptionValidator(), new MockHttpClient($transport->respond(...)), $emptyResolver);
try {
    $sender->send($subscription, $payload);
    throw new RuntimeException('DNS failure was ignored.');
} catch (NotificationTransportException $exception) {
    checkSender(!str_contains($exception->getMessage(), $subscription['endpoint']), 'Transport errors must not expose browser endpoints.');
}

$emptyConfig = new WebPushConfiguration(['public_key' => '', 'private_key' => '', 'subject' => '', 'keys_file' => '']);
$sender = new WebPushNotificationSender($emptyConfig, new PushSubscriptionValidator(), new MockHttpClient($transport->respond(...)), senderPublicAddresses(...));
try {
    $sender->send($subscription, $payload);
    throw new RuntimeException('Missing VAPID configuration was ignored.');
} catch (WebPushConfigurationException) {
    // Expected: configuration failures are distinct from provider failures.
}
checkSender($transport->requests === [], 'Preflight failures must not send anything.');
echo "PASS offline Web Push encryption, response classification, validation and SSRF boundaries\n";
