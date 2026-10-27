<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PushSubscriptionValidationException;

final class PushSubscriptionValidator
{
    /** @return array{endpoint: string, p256dh: string, auth: string} */
    public function validate(mixed $subscription): array
    {
        if (!is_array($subscription) || !is_array($subscription['keys'] ?? null)) {
            $this->reject();
        }

        $endpoint = $this->validateEndpoint($subscription['endpoint'] ?? null);
        $publicKey = $this->decodeKey($subscription['keys']['p256dh'] ?? null, 65);
        $auth = $this->decodeKey($subscription['keys']['auth'] ?? null, 16);
        // SubjectPublicKeyInfo for an uncompressed prime256v1 public key.
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $publicKey;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        if ($publicKey[0] !== "\x04" || openssl_pkey_get_public($pem) === false) {
            $this->reject();
        }

        return ['endpoint' => $endpoint, 'p256dh' => $this->encodeKey($publicKey), 'auth' => $this->encodeKey($auth)];
    }

    public function validateEndpoint(mixed $endpoint): string
    {
        if (!is_string($endpoint) || strlen($endpoint) > 2048 || preg_match('/[^\x21-\x7E]/', $endpoint)
            || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            $this->reject();
        }
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)
            || !$this->isAllowedHost($host)) {
            $this->reject();
        }

        return $endpoint;
    }

    private function isAllowedHost(string $host): bool
    {
        return in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
            || str_ends_with($host, '.notify.windows.com');
    }

    private function decodeKey(mixed $key, int $length): string
    {
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $key)) {
            $this->reject();
        }
        $decoded = base64_decode(strtr($key, '-_', '+/'), true);
        if ($decoded === false || strlen($decoded) !== $length) {
            $this->reject();
        }

        return $decoded;
    }

    private function encodeKey(string $key): string
    {
        return rtrim(strtr(base64_encode($key), '+/', '-_'), '=');
    }

    private function reject(): never
    {
        throw new PushSubscriptionValidationException('push.invalid_subscription');
    }
}
