<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserSettingsRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findUserSettingsByUserId(int $userId): ?object
    {
        $statement = $this->pdo->prepare(
            'SELECT language, calendar_system, timezone
             FROM user_settings
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $settings = $statement->fetch(PDO::FETCH_OBJ);

        return $settings !== false ? $settings : null;
    }

    public function upsert(
        int $userId,
        string $language,
        string $calendarSystem,
        string $timezone
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_settings (user_id, language, calendar_system, timezone)
             VALUES (:user_id, :language, :calendar_system, :timezone)
             ON DUPLICATE KEY UPDATE
                language = VALUES(language),
                calendar_system = VALUES(calendar_system),
                timezone = VALUES(timezone)'
        );
        $statement->execute([
            'user_id' => $userId,
            'language' => $language,
            'calendar_system' => $calendarSystem,
            'timezone' => $timezone,
        ]);
    }
}
