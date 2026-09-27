<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\PushSubscriptionValidationException;
use PDO;
use Throwable;

final class PushSubscriptionRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @param array{endpoint: string, p256dh: string, auth: string} $subscription */
    public function saveForUser(int $userId, array $subscription, string $deviceLabel): void
    {
        $hash = hash('sha256', $subscription['endpoint']);
        $this->pdo->beginTransaction();
        try {
            // Serializes a user's registrations so concurrent requests cannot bypass the limit.
            $lock = $this->pdo->prepare('SELECT id FROM users WHERE id = :user_id FOR UPDATE');
            $lock->execute([':user_id' => $userId]);
            if ($lock->fetchColumn() === false) {
                throw new PushSubscriptionValidationException('push.invalid_subscription');
            }
            $existing = $this->pdo->prepare('SELECT user_id FROM push_subscriptions WHERE endpoint_hash = :hash');
            $existing->execute([':hash' => $hash]);
            $owner = $existing->fetchColumn();
            if ($owner !== false && (int) $owner !== $userId) {
                throw new PushSubscriptionValidationException('push.device_conflict', 409);
            }
            if ($owner === false && $this->countForUser($userId) >= 10) {
                throw new PushSubscriptionValidationException('push.device_limit');
            }
            $statement = $this->pdo->prepare(
                'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, device_label)
                 VALUES (:user_id, :endpoint, :hash, :p256dh, :auth, :device_label)
                 ON DUPLICATE KEY UPDATE
                    p256dh = IF(user_id = VALUES(user_id), VALUES(p256dh), p256dh),
                    auth = IF(user_id = VALUES(user_id), VALUES(auth), auth),
                    device_label = IF(user_id = VALUES(user_id), VALUES(device_label), device_label),
                    updated_at = IF(user_id = VALUES(user_id), UTC_TIMESTAMP(), updated_at)'
            );
            $statement->execute([
                ':user_id' => $userId, ':endpoint' => $subscription['endpoint'], ':hash' => $hash,
                ':p256dh' => $subscription['p256dh'], ':auth' => $subscription['auth'], ':device_label' => $deviceLabel,
            ]);
            // Also catches a competing user inserting the same endpoint between SELECT and INSERT.
            if (!$this->hasForUser($userId, $subscription['endpoint'])) {
                throw new PushSubscriptionValidationException('push.device_conflict', 409);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function hasForUser(int $userId, string $endpoint): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM push_subscriptions WHERE user_id = :user_id AND endpoint_hash = :hash');
        $statement->execute([':user_id' => $userId, ':hash' => hash('sha256', $endpoint)]);

        return $statement->fetchColumn() !== false;
    }

    public function deleteForUser(int $userId, string $endpoint): void
    {
        $statement = $this->pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = :user_id AND endpoint_hash = :hash');
        $statement->execute([':user_id' => $userId, ':hash' => hash('sha256', $endpoint)]);
    }

    private function countForUser(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = :user_id');
        $statement->execute([':user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }
}
