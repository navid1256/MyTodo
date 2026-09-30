<?php

declare(strict_types=1);

namespace App\Services;

use App\Notifications\NotificationSendResult;
use App\Repositories\ReminderDeliveryRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ReminderDispatchService
{
    private const MAX_BATCH_SIZE = 100;
    private const MAX_ATTEMPTS = 3;
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /** Keeps delivery policy outside database queries. */
    public function __construct(private readonly ReminderDeliveryRepository $repository) {}

    /**
     * Prepares due reminders, resolves abandoned claims and reserves a bounded batch.
     * This method does not contact a push provider or change any real task.
     *
     * @return list<array<string, mixed>>
     */
    public function reserveBatch(int $limit = self::MAX_BATCH_SIZE, ?DateTimeImmutable $now = null): array
    {
        if ($limit < 1 || $limit > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException('Invalid reminder delivery batch size.');
        }

        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $at = $now->format(self::DATETIME_FORMAT);
        $this->repository->expireAbandonedClaims($now->modify('-5 minutes')->format(self::DATETIME_FORMAT), $at, $limit);
        $this->repository->markUnavailableDeliveries($at, $limit);
        $this->repository->enqueueDueReminders($at, $limit);

        return $this->repository->reserveReadyDeliveries($at, $limit);
    }

    /**
     * Records one claimed provider result; the claim token prevents stale workers
     * from changing another worker's result. Ambiguous outcomes are never retried.
     *
     * @param array{id: int, claim_token: string, attempt_count: int} $claim
     */
    public function recordResult(array $claim, NotificationSendResult $result, ?DateTimeImmutable $now = null): bool
    {
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        // Provider acceptance maps to the persisted delivery state, not browser display.
        $status = $result->outcome === NotificationSendResult::ACCEPTED
            ? 'sent'
            : $result->outcome;
        $nextAttemptAt = null;
        if ($status === NotificationSendResult::RETRYABLE) {
            $attempt = (int) $claim['attempt_count'];
            if ($attempt >= self::MAX_ATTEMPTS) {
                $status = NotificationSendResult::FAILED;
            } else {
                $delaySeconds = $attempt === 1 ? 60 : 300;
                $nextAttemptAt = $now->modify('+' . $delaySeconds . ' seconds')->format(self::DATETIME_FORMAT);
            }
        }

        return $this->repository->recordClaimResult(
            (int) $claim['id'],
            (string) $claim['claim_token'],
            $status,
            $result->statusCode,
            $nextAttemptAt,
            $now->format(self::DATETIME_FORMAT),
            $claim
        );
    }
}
