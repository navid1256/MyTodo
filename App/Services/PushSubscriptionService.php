<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\WebPushConfiguration;
use App\Exceptions\PushSubscriptionValidationException;
use App\Repositories\PushSubscriptionRepository;
use JsonException;

final class PushSubscriptionService
{
    public function __construct(
        private readonly PushSubscriptionRepository $repository,
        private readonly PushSubscriptionValidator $validator,
        private readonly WebPushConfiguration $configuration
    ) {}

    /** @return array{configured: bool, publicKey: string, subscribed: bool} */
    public function status(int $userId, string $endpoint): array
    {
        $configured = $this->configuration->isConfigured();

        return [
            'configured' => $configured,
            'publicKey' => $configured ? $this->configuration->publicKey() : '',
            'subscribed' => $endpoint !== '' && $this->repository->hasForUser($userId, $this->validator->validateEndpoint($endpoint)),
        ];
    }

    public function subscribe(int $userId, string $json, string $userAgent): void
    {
        if (!$this->configuration->isConfigured()) {
            throw new PushSubscriptionValidationException('push.not_configured', 503);
        }
        $this->configuration->authentication();
        if (strlen($json) > 4096) {
            throw new PushSubscriptionValidationException('push.invalid_subscription');
        }
        try {
            $subscription = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PushSubscriptionValidationException('push.invalid_subscription');
        }
        $this->repository->saveForUser(
            $userId,
            $this->validator->validate($subscription),
            mb_substr(mb_scrub($userAgent, 'UTF-8'), 0, 255, 'UTF-8')
        );
    }

    public function unsubscribe(int $userId, string $endpoint): void
    {
        $this->repository->deleteForUser($userId, $this->validator->validateEndpoint($endpoint));
    }
}
