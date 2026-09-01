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
     *     effective_language: string,
     *     calendar_system: string,
     *     timezone: string,
     *     is_persisted: bool
     * }
     */
    public function getForUser(
        int $userId,
        ?string $fallbackTimezone = null,
        ?string $browserLanguages = null
    ): array {
        $savedSettings = $this->settingsRepository->findUserSettingsByUserId($userId);
        if ($savedSettings !== null) {
            $language = (string) $savedSettings->language;

            return [
                'language' => $language,
                'effective_language' => $this->resolveEffectiveLanguage($language, $browserLanguages),
                'calendar_system' => (string) $savedSettings->calendar_system,
                'timezone' => (string) $savedSettings->timezone,
                'is_persisted' => true,
            ];
        }

        return [
            'language' => self::DEFAULT_LANGUAGE,
            'effective_language' => $this->resolveEffectiveLanguage(
                self::DEFAULT_LANGUAGE,
                $browserLanguages
            ),
            'calendar_system' => self::DEFAULT_CALENDAR_SYSTEM,
            'timezone' => $this->resolveFallbackTimezone($fallbackTimezone),
            'is_persisted' => false,
        ];
    }

    /**
     * @return array{
     *     language: string,
     *     effective_language: string,
     *     calendar_system: string,
     *     timezone: string
     * }
     */
    public function save(
        int $userId,
        string $language,
        string $calendarSystem,
        string $timezone,
        ?string $browserLanguages = null
    ): array {
        if ($userId <= 0) {
            throw new UserSettingsValidationException('settings.validation.authentication_required');
        }

        if (!in_array($language, self::ALLOWED_LANGUAGES, true)) {
            throw new UserSettingsValidationException('settings.validation.invalid_language');
        }

        if (!in_array($calendarSystem, self::ALLOWED_CALENDAR_SYSTEMS, true)) {
            throw new UserSettingsValidationException('settings.validation.invalid_calendar');
        }

        if (!$this->isValidTimezone($timezone)) {
            throw new UserSettingsValidationException('settings.validation.invalid_timezone');
        }

        $this->settingsRepository->upsert($userId, $language, $calendarSystem, $timezone);

        return [
            'language' => $language,
            'effective_language' => $this->resolveEffectiveLanguage($language, $browserLanguages),
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

    public function resolveEffectiveLanguage(string $language, ?string $browserLanguages = null): string
    {
        if ($language === 'english' || $language === 'persian') {
            return $language;
        }

        $preferredLanguage = 'english';
        $highestQuality = -1.0;

        foreach (explode(',', strtolower((string) $browserLanguages)) as $acceptedLanguage) {
            [$languageTag, $parameters] = array_pad(explode(';', $acceptedLanguage, 2), 2, '');
            $languageTag = trim($languageTag);
            $quality = 1.0;

            if (preg_match('/(?:^|;)\s*q=([01](?:\.\d{1,3})?)/', $parameters, $matches) === 1) {
                $quality = (float) $matches[1];
            }

            if ($quality <= $highestQuality || $quality === 0.0) {
                continue;
            }

            if ($languageTag === 'fa' || str_starts_with($languageTag, 'fa-')) {
                $preferredLanguage = 'persian';
                $highestQuality = $quality;
            } elseif ($languageTag === 'en' || str_starts_with($languageTag, 'en-')) {
                $preferredLanguage = 'english';
                $highestQuality = $quality;
            }
        }

        return $preferredLanguage;
    }

    private function resolveFallbackTimezone(?string $fallbackTimezone): string
    {
        $candidate = trim((string) $fallbackTimezone);

        return $this->isValidTimezone($candidate) ? $candidate : self::DEFAULT_TIMEZONE;
    }
}
