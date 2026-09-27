<?php

declare(strict_types=1);

namespace App\Notifications;

final class NotificationSendResult
{
    public const ACCEPTED = 'accepted';
    public const EXPIRED = 'expired';
    public const RETRYABLE = 'retryable';
    public const FAILED = 'failed';
    public const UNKNOWN = 'unknown';

    /** Keeps outcomes immutable and excludes endpoints, keys and raw provider errors. */
    private function __construct(public readonly string $outcome, public readonly ?int $statusCode) {}

    /**
     * Classifies provider responses; acceptance is not proof of browser delivery.
     * A missing response is ambiguous and must not be treated as a confirmed rejection.
     */
    public static function fromHttpStatus(?int $statusCode): self
    {
        $outcome = match (true) {
            $statusCode === null => self::UNKNOWN,
            $statusCode >= 200 && $statusCode < 300 => self::ACCEPTED,
            in_array($statusCode, [404, 410], true) => self::EXPIRED,
            in_array($statusCode, [408, 429], true) || ($statusCode >= 500 && $statusCode < 600) => self::RETRYABLE,
            default => self::FAILED,
        };

        return new self($outcome, $statusCode);
    }
}
