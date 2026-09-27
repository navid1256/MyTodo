<?php

declare(strict_types=1);

namespace App\Config;

use JsonException;
use Minishlink\WebPush\VAPID;
use App\Exceptions\WebPushConfigurationException;
use Throwable;

final class WebPushConfiguration
{
    private bool $fileChecked = false;

    /** @param array{public_key: string, private_key: string, subject: string, keys_file: string} $config */
    public function __construct(private array $config) {}

    private function loadKeyFile(): void
    {
        if ($this->fileChecked) {
            return;
        }
        $this->fileChecked = true;
        if ($this->config['public_key'] !== '' || $this->config['private_key'] !== '') {
            return;
        }
        if (!is_file($this->config['keys_file'])) {
            return;
        }
        try {
            $keys = json_decode((string) file_get_contents($this->config['keys_file']), true, 8, JSON_THROW_ON_ERROR);
            $this->config['public_key'] = is_string($keys['publicKey'] ?? null) ? $keys['publicKey'] : '';
            $this->config['private_key'] = is_string($keys['privateKey'] ?? null) ? $keys['privateKey'] : '';
        } catch (JsonException $exception) {
            throw new WebPushConfigurationException('Invalid VAPID key file.', 0, $exception);
        }
    }

    public function isConfigured(): bool
    {
        // Notification configuration must not break unrelated dashboard routes.
        $this->loadKeyFile();
        return $this->config['public_key'] !== '' && $this->config['private_key'] !== '' && $this->config['subject'] !== '';
    }

    /** @return array{subject: string, publicKey: string, privateKey: string} */
    public function authentication(): array
    {
        if (!$this->isConfigured()) {
            throw new WebPushConfigurationException('Web Push is not configured.');
        }
        $authentication = [
            'subject' => $this->config['subject'],
            'publicKey' => $this->config['public_key'],
            'privateKey' => $this->config['private_key'],
        ];
        try {
            VAPID::validate($authentication);
        } catch (Throwable $exception) {
            throw new WebPushConfigurationException('Invalid VAPID configuration.', 0, $exception);
        }

        return $authentication;
    }

    public function publicKey(): string
    {
        return $this->authentication()['publicKey'];
    }
}
