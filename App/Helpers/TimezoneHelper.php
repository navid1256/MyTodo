<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class TimezoneHelper
{
    public static function getApplicationTimezone(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }

    public static function getClientTimezone(?string $timezoneName = null): DateTimeZone
    {
        $candidate = $timezoneName;

        if ($candidate === null) {
            $cookieVal = $_COOKIE['mytodo_timezone'] ?? null;
            $candidate = is_string($cookieVal) ? trim($cookieVal) : '';
        }

        if ($candidate !== '' && strlen($candidate) <= 100) {
            try {
                return new DateTimeZone($candidate);
            } catch (Exception) {
                // Fall back to application timezone if invalid
            }
        }

        try {
            return new DateTimeZone(date_default_timezone_get());
        } catch (Exception) {
            return self::getApplicationTimezone();
        }
    }

    public static function formatNotificationDate(string $dateTime, ?DateTimeZone $timezone = null): string
    {
        $displayTimezone = $timezone ?? self::getClientTimezone();
        $date = new DateTimeImmutable($dateTime, self::getApplicationTimezone());

        return $date->setTimezone($displayTimezone)->format('M j, Y \a\t h:i A');
    }

    public static function formatNotificationOffset(int $value, string $unit): string
    {
        if ($value === 0) {
            return 'On due time';
        }

        return $value . ' ' . $unit . ($value === 1 ? '' : 's') . ' before due time';
    }
}
