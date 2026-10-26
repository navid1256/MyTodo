<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;

$notificationStatusLabels = [
    'pending' => 'Pending',
    'sent' => 'Sent',
    'failed' => 'Failed',
    'cancelled' => 'Cancelled',
];

$userTimezone = TimezoneHelper::getApplicationTimezone();

$formatNotificationDate = static function (?string $dateTime) use ($userTimezone): string {
    if ($dateTime === null || trim($dateTime) === '') {
        return 'Not available';
    }

    return (new DateTimeImmutable($dateTime, $userTimezone))->format('M j, Y \a\t h:i A');
};

$formatNotificationOffset = static function (int $value, string $unit): string {
    if ($value === 0) {
        return 'On due time';
    }

    return $value . ' ' . $unit . ($value === 1 ? '' : 's') . ' before due time';
};
