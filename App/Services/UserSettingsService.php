<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserSettingsValidationException;
use App\Repositories\UserSettingsRepository;
use DateTimeZone;

final class UserSettingsService
{
    private const DEFAULT_LANGUAGE = 'default';
    private const DEFAULT_CALENDAR_SYSTEM = 'gregorian';
    private const DEFAULT_TIMEZONE = 'UTC';
    private const ALLOWED_LANGUAGES = ['default', 'english', 'persian'];
    private const ALLOWED_CALENDAR_SYSTEMS = ['gregorian', 'jalali'];

    public function __construct(private readonly UserSettingsRepository $settingsRepository) {}

    /**
     * @return array{
     *     language: string,
     *     calendar_system: string,
     *     timezone: string,
     *     is_persisted: bool
     * }
     */
    public function getForUser(int $userId, ?string $fallbackTimezone = null): array
    {
        $savedSettings = $this->settingsRepository->findByUserId($userId);
        if ($savedSettings !== null) {
            return [
                'language' => (string) $savedSettings->language,
                'calendar_system' => (string) $savedSettings->calendar_system,
                'timezone' => (string) $savedSettings->timezone,
                'is_persisted' => true,
            ];
        }

        return [
            'language' => self::DEFAULT_LANGUAGE,
            'calendar_system' => self::DEFAULT_CALENDAR_SYSTEM,
            'timezone' => $this->resolveFallbackTimezone($fallbackTimezone),
            'is_persisted' => false,
        ];
    }

    /**
     * @return array{language: string, calendar_system: string, timezone: string}
     */
    public function save(
        int $userId,
        string $language,
        string $calendarSystem,
        string $timezone
    ): array {
        if ($userId <= 0) {
            throw new UserSettingsValidationException('Authentication required.');
        }

        if (!in_array($language, self::ALLOWED_LANGUAGES, true)) {
            throw new UserSettingsValidationException('Please select a valid language.');
        }

        if (!in_array($calendarSystem, self::ALLOWED_CALENDAR_SYSTEMS, true)) {
            throw new UserSettingsValidationException('Please select a valid calendar system.');
        }

        if (!$this->isValidTimezone($timezone)) {
            throw new UserSettingsValidationException('Please select a valid time zone.');
        }

        $this->settingsRepository->upsert($userId, $language, $calendarSystem, $timezone);

        return [
            'language' => $language,
            'calendar_system' => $calendarSystem,
            'timezone' => $timezone,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getTimezoneOptions(): array
    {
        return array_values(array_unique(array_merge(
            [self::DEFAULT_TIMEZONE],
            DateTimeZone::listIdentifiers()
        )));
    }

    public function getTimezoneForUser(int $userId, ?string $fallbackTimezone = null): DateTimeZone
    {
        $settings = $this->getForUser($userId, $fallbackTimezone);

        return new DateTimeZone($settings['timezone']);
    }

    public function isValidTimezone(string $timezone): bool
    {
        return $timezone !== ''
            && strlen($timezone) <= 100
            && in_array($timezone, $this->getTimezoneOptions(), true);
    }

    private function resolveFallbackTimezone(?string $fallbackTimezone): string
    {
        $candidate = trim((string) $fallbackTimezone);

        return $this->isValidTimezone($candidate) ? $candidate : self::DEFAULT_TIMEZONE;
    }
}
