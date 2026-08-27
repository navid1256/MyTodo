<?php

function getApplicationTimezone(): DateTimeZone
{
    return new DateTimeZone('Asia/Tehran');
}

function getClientTimezone(): DateTimeZone
{
    $timezoneName = isset($_COOKIE['mytodo_timezone']) && is_string($_COOKIE['mytodo_timezone'])
        ? trim($_COOKIE['mytodo_timezone'])
        : '';

    if ($timezoneName !== '' && strlen($timezoneName) <= 100) {
        try {
            return new DateTimeZone($timezoneName);
        } catch (Exception $exception) {
            // Fall back to the application's timezone for invalid cookie values.
        }
    }

    return getApplicationTimezone();
}
