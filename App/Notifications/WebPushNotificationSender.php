<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Config\WebPushConfiguration;
use App\Exceptions\NotificationPayloadValidationException;
use App\Exceptions\NotificationTransportException;
use App\Exceptions\PushSubscriptionValidationException;
use App\Services\PushSubscriptionValidator;
use Closure;
use JsonException;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Encryption;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebPushNotificationSender implements NotificationSender
{
    private const INVALID_PAYLOAD = 'Invalid notification payload.';
    private readonly HttpClientInterface $httpClient;
    private readonly Closure $dnsResolver;

    /**
     * Allows offline transport/DNS tests without reading keys or opening connections.
     *
     * @param (Closure(string): list<string>)|null $dnsResolver
     */
    public function __construct(
        private readonly WebPushConfiguration $configuration,
        private readonly PushSubscriptionValidator $subscriptionValidator,
        ?HttpClientInterface $httpClient = null,
        ?Closure $dnsResolver = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
        $this->dnsResolver = $dnsResolver ?? self::lookupHostAddresses(...);
    }

    /** Validates and encrypts a single notification, returning a sanitized provider outcome. */
    public function send(#[\SensitiveParameter] array $subscription, #[\SensitiveParameter] array $payload): NotificationSendResult
    {
        $browser = $this->createSubscription($subscription);
        $json = $this->encodePayload($payload);
        $webPush = $this->createWebPush($browser->getEndpoint());
        $report = $webPush->sendOneNotification($browser, $json);

        // The library treats redirects as successful; only 2xx means accepted here.
        return NotificationSendResult::fromHttpStatus($report->getResponse()?->getStatusCode());
    }

    /** Revalidates stored browser data and explicitly selects modern payload encryption. */
    private function createSubscription(#[\SensitiveParameter] array $subscription): Subscription
    {
        $validated = $this->subscriptionValidator->validate([
            'endpoint' => $subscription['endpoint'] ?? null,
            'keys' => ['p256dh' => $subscription['p256dh'] ?? null, 'auth' => $subscription['auth'] ?? null],
        ]);
        if (($subscription['content_encoding'] ?? 'aes128gcm') !== 'aes128gcm') {
            throw new PushSubscriptionValidationException('push.invalid_subscription');
        }

        return new Subscription($validated['endpoint'], $validated['p256dh'], $validated['auth'], ContentEncoding::aes128gcm);
    }

    /** Limits payload fields and encoded bytes to match the existing Service Worker contract. */
    private function encodePayload(#[\SensitiveParameter] array $payload): string
    {
        $safePayload = [];
        foreach (['title' => 512, 'body' => 1000, 'tag' => 128, 'url' => 2048] as $field => $limit) {
            $value = $payload[$field] ?? null;
            if (!is_string($value) || preg_match('//u', $value) !== 1 || mb_strlen($value, 'UTF-8') > $limit) {
                throw new NotificationPayloadValidationException(self::INVALID_PAYLOAD);
            }
            $safePayload[$field] = $value;
        }
        if (trim($safePayload['title']) === '' || trim($safePayload['tag']) === '') {
            throw new NotificationPayloadValidationException(self::INVALID_PAYLOAD);
        }
        $this->validateClickUrl($safePayload['url']);
        try {
            $json = json_encode($safePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new NotificationPayloadValidationException(self::INVALID_PAYLOAD, 0, $exception);
        }
        if (strlen($json) > Encryption::MAX_PAYLOAD_LENGTH) {
            throw new NotificationPayloadValidationException('Notification payload exceeds the encrypted byte limit.');
        }

        return $json;
    }

    /** Accepts only local destinations that the Service Worker already permits. */
    private function validateClickUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])
            || preg_match('/[\\\\\x00-\x20\x7F]/', $url)
            || !in_array($parts['path'] ?? '', ['/notifications', '/manage-tasks', '/'], true)) {
            throw new NotificationPayloadValidationException(self::INVALID_PAYLOAD);
        }
    }

    /** Builds a bounded, TLS-verified client with pinned DNS and no redirects or proxy. */
    private function createWebPush(string $endpoint): WebPush
    {
        $authentication = $this->configuration->authentication();
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $address = $this->resolvePublicAddress($host);
        $client = (new NoPrivateNetworkHttpClient($this->httpClient))->withOptions([
            'max_redirects' => 0,
            'timeout' => 10.0,
            'max_duration' => 15.0,
            'verify_peer' => true,
            'verify_host' => true,
            'proxy' => '',
            'no_proxy' => '*',
            'resolve' => [$host => $address],
        ]);
        $factory = new Psr17Factory();
        $adapter = new Psr18Client($client, $factory, $factory);

        // This is provider retention, not the one-minute Cron scheduling interval.
        return new WebPush(['VAPID' => $authentication], ['TTL' => 3600, 'urgency' => 'normal'], $adapter, $factory, $factory);
    }

    /** Checks every DNS answer before pinning one, preventing private-network SSRF. */
    private function resolvePublicAddress(string $host): string
    {
        $addresses = ($this->dnsResolver)($host);
        if ($addresses === []) {
            throw new NotificationTransportException('Push provider DNS resolution failed.');
        }
        $blocked = array_merge(IpUtils::PRIVATE_SUBNETS, ['224.0.0.0/4', 'ff00::/8']);
        foreach ($addresses as $address) {
            if (!is_string($address)
                || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
                || IpUtils::checkIp($address, $blocked)) {
                throw new PushSubscriptionValidationException('push.invalid_subscription');
            }
        }

        return $addresses[0];
    }

    /** Resolves IPv4 and IPv6 records once; the HTTP request must reuse the validated IP. */
    private static function lookupHostAddresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            throw new NotificationTransportException('Push provider DNS resolution failed.');
        }
        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
