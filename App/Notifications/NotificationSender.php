<?php

declare(strict_types=1);

namespace App\Notifications;

interface NotificationSender
{
    /**
     * Sends one browser notification without retries or database mutations.
     * The caller must verify subscription ownership before calling this method.
     *
     * @param array{endpoint: string, p256dh: string, auth: string, content_encoding?: string} $subscription
     * @param array{title: string, body: string, tag: string, url: string} $payload
     */
    public function send(#[\SensitiveParameter] array $subscription, #[\SensitiveParameter] array $payload): NotificationSendResult;
}
