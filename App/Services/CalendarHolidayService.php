<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HolidayDataException;
use Hekmatinasser\Verta\Verta;
use JsonException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CalendarHolidayService
{
    public const MINIMUM_YEAR = 1347;
    public const MAXIMUM_YEAR = 1500;
    private const DAILY_REFRESH = 86400;
    private const FAILURE_BACKOFF = 3600;
    private const MAXIMUM_RESPONSE_BYTES = 262144;
    private const API_URL = 'https://www.persian-calendar.com/api/holidays';

    /** Reuses the existing HTTP client contract and a private, application-wide cache directory. */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $cacheDirectory,
    ) {}

    /**
     * Returns date-only holiday data, retaining the last good result during provider outages.
     *
     * @return array{year: int, dates: list<string>, available: bool, stale: bool, refresh_after: int}
     */
    public function getHolidays(int $year, ?int $now = null): array
    {
        if ($year < self::MINIMUM_YEAR || $year > self::MAXIMUM_YEAR) {
            throw new HolidayDataException('Unsupported holiday year.');
        }
        $now ??= time();
        $cache = $this->readCache($year) ?? [
            'year' => $year, 'dates' => [], 'available' => false, 'stale' => true, 'refresh_at' => 0,
        ];
        if ($cache['refresh_at'] > $now) {
            return $this->result($cache, $now);
        }
        $cache['stale'] = true;
        if (!is_dir($this->cacheDirectory) && !@mkdir($this->cacheDirectory, 0770, true) && !is_dir($this->cacheDirectory)) {
            error_log('Calendar holiday cache directory is unavailable.');
            return $this->result(array_replace($cache, ['refresh_at' => $now + self::FAILURE_BACKOFF]), $now);
        }

        $lock = @fopen($this->cachePath($year) . '.lock', 'c');
        if ($lock === false) {
            return $this->result(array_replace($cache, ['refresh_at' => $now + 60]), $now);
        }
        try {
            // Only one request refreshes a year; other calendars can keep using the old dates.
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return $this->result(array_replace($cache, ['refresh_at' => $now + 15]), $now);
            }
            $cache = $this->readCache($year) ?? $cache;
            if ($cache['refresh_at'] > $now) {
                return $this->result($cache, $now);
            }
            [$updated, $mayStore] = $this->refresh($cache, $now);
            if ($mayStore) {
                $this->writeCache($year, $updated);
            } elseif (is_file($this->cachePath($year))) {
                unlink($this->cachePath($year));
            }
            return $this->result($updated, $now);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Fetches from one fixed HTTPS destination, bounding redirects, time and downloaded bytes. */
    private function refresh(array $previous, int $now): array
    {
        $headers = [];
        $status = 0;
        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => ['year' => $previous['year']],
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'MyTodo/1.0 (calendar holidays)'],
                'max_redirects' => 0, 'timeout' => 3, 'max_duration' => 5,
                // Stop oversized responses before they can exhaust the PHP worker's memory.
                'on_progress' => static function (int $downloaded, int $total): void {
                    if (max($downloaded, $total) > self::MAXIMUM_RESPONSE_BYTES) {
                        throw new HolidayDataException('Holiday response exceeds the size limit.');
                    }
                },
            ]);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            if ($status !== 200) {
                $response->cancel();
                throw new HolidayDataException('Holiday provider returned HTTP ' . $status . '.');
            }
            $body = $response->getContent();
            if (strlen($body) > self::MAXIMUM_RESPONSE_BYTES) {
                throw new HolidayDataException('Holiday response exceeds the size limit.');
            }
            $dates = $this->parseDates(json_decode($body, true, 32, JSON_THROW_ON_ERROR), $previous['year']);
            $cacheControl = strtolower(implode(',', $headers['cache-control'] ?? []));
            $ttl = $this->freshLifetime($cacheControl, (int) ($headers['age'][0] ?? 0));
            return [[
                'year' => $previous['year'], 'dates' => $dates, 'available' => true,
                'stale' => false, 'refresh_at' => $now + $ttl,
            ], !str_contains($cacheControl, 'no-store')];
        } catch (ExceptionInterface | HolidayDataException | JsonException) {
            error_log('Calendar holidays could not be refreshed for year ' . $previous['year'] . ' (HTTP ' . $status . ').');
            $delay = $this->retryDelay($headers['retry-after'][0] ?? '', $status, $now);
            return [array_replace($previous, ['stale' => true, 'refresh_at' => $now + $delay]), true];
        }
    }

    /** Validates both calendar representations and discards all upstream titles and other metadata. */
    private function parseDates(mixed $payload, int $year): array
    {
        if (!is_array($payload) || ($payload['year'] ?? null) !== $year
            || !is_array($payload['data'] ?? null) || !array_is_list($payload['data']) || count($payload['data']) > 366) {
            throw new HolidayDataException('Invalid holiday response.');
        }
        $dates = [];
        foreach ($payload['data'] as $day) {
            if (!is_array($day) || ($day['year_sh'] ?? null) !== $year
                || !is_int($day['month_sh'] ?? null) || !is_int($day['day_sh'] ?? null)
                || !Verta::isValidDate($year, $day['month_sh'], $day['day_sh'])) {
                throw new HolidayDataException('Invalid holiday date.');
            }
            $gregorian = Verta::getGregorian($year, $day['month_sh'], $day['day_sh']);
            $dateKey = sprintf('%04d-%02d-%02d', ...$gregorian);
            if (($day['date_gregorian'] ?? null) !== $dateKey || !$this->hasHolidayEvent($day['events'] ?? null)) {
                throw new HolidayDataException('Inconsistent holiday date.');
            }
            $dates[] = $dateKey;
        }
        $dates = array_values(array_unique($dates));
        sort($dates);
        return $dates;
    }

    /** Requires an explicit boolean holiday marker instead of trusting non-empty strings. */
    private function hasHolidayEvent(mixed $events): bool
    {
        if (!is_array($events) || !array_is_list($events) || count($events) > 32) {
            return false;
        }
        foreach ($events as $event) {
            if (is_array($event) && ($event['is_holiday'] ?? null) === true) {
                return true;
            }
        }
        return false;
    }

    /** Uses daily freshness unless Cache-Control requires an earlier refresh or no caching. */
    private function freshLifetime(string $cacheControl, int $age): int
    {
        if (str_contains($cacheControl, 'no-store') || str_contains($cacheControl, 'no-cache')) {
            return 0;
        }
        $ttl = preg_match('/(?:^|,)\s*max-age\s*=\s*"?(\d+)/', $cacheControl, $matches)
            ? min(self::DAILY_REFRESH, (int) $matches[1])
            : self::DAILY_REFRESH;
        return max(0, $ttl - max(0, $age));
    }

    /** Honors numeric and HTTP-date Retry-After values without retrying every page render. */
    private function retryDelay(string $retryAfter, int $status, int $now): int
    {
        if (ctype_digit($retryAfter)) {
            return max(1, (int) $retryAfter);
        }
        $retryAt = $retryAfter !== '' ? strtotime($retryAfter) : false;
        if ($retryAt !== false && $retryAt > $now) {
            return $retryAt - $now;
        }
        return $status === 403 ? self::DAILY_REFRESH : self::FAILURE_BACKOFF;
    }

    /** Reads only the bounded, date-only JSON snapshot produced by this service. */
    private function readCache(int $year): ?array
    {
        $path = $this->cachePath($year);
        $size = @filesize($path);
        if (!is_file($path) || $size === false || $size > self::MAXIMUM_RESPONSE_BYTES) {
            return null;
        }
        $cache = json_decode((string) @file_get_contents($path), true);
        if (!is_array($cache) || ($cache['year'] ?? null) !== $year
            || !is_array($cache['dates'] ?? null) || !array_is_list($cache['dates'])
            || !is_int($cache['refresh_at'] ?? null) || !is_bool($cache['available'] ?? null) || !is_bool($cache['stale'] ?? null)) {
            return null;
        }
        foreach ($cache['dates'] as $date) {
            if (!is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) !== 1) {
                return null;
            }
        }
        return $cache;
    }

    /** Atomically replaces the snapshot so concurrent readers never see a partial JSON file. */
    private function writeCache(int $year, array $cache): void
    {
        $temporary = @tempnam($this->cacheDirectory, 'holiday-');
        if ($temporary === false) {
            error_log('Calendar holiday cache could not be written.');
            return;
        }
        try {
            $json = json_encode($cache, JSON_THROW_ON_ERROR);
            if (@file_put_contents($temporary, $json) !== strlen($json) || !@rename($temporary, $this->cachePath($year))) {
                error_log('Calendar holiday cache could not be written.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** Builds cache filenames only from the validated numeric year. */
    private function cachePath(int $year): string
    {
        return $this->cacheDirectory . '/' . $year . '.json';
    }

    /** Exposes only calendar dates, availability and the next refresh interval to the browser. */
    private function result(array $cache, int $now): array
    {
        return [
            'year' => $cache['year'], 'dates' => $cache['dates'], 'available' => $cache['available'],
            'stale' => $cache['stale'], 'refresh_after' => max(0, $cache['refresh_at'] - $now),
        ];
    }
}
