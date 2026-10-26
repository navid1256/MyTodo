<?php

declare(strict_types=1);

use App\Controllers\CalendarController;
use App\Http\Request;
use App\Http\Router;
use App\Services\CalendarHolidayService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CalendarHolidayServiceTest extends TestCase
{
    private string $directory;
    private const NOW = 1793000000;

    /** Creates an isolated cache without connecting to the application database. */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mytodo-holidays-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    /** Removes only the files created inside this test's randomly named directory. */
    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /** Supplies overlapping occasions while keeping their real calendar day unique. */
    private function response(array $headers = [], int $status = 200): MockResponse
    {
        return new MockResponse(json_encode(['year' => 1405, 'data' => [[
            'year_sh' => 1405, 'month_sh' => 1, 'day_sh' => 1,
            'date_gregorian' => '2026-03-21',
            'events' => [['is_holiday' => true], ['is_holiday' => true]],
        ]]], JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => $headers]);
    }

    /** Shares cached dates between service instances and respects the provider's shorter TTL. */
    public function testSharedCacheExpiresAccordingToCacheControl(): void
    {
        $client = new MockHttpClient([$this->response(['Cache-Control: public, max-age=3600']), $this->response()]);
        $service = new CalendarHolidayService($client, $this->directory);
        $result = $service->getHolidays(1405, self::NOW);
        self::assertSame(['2026-03-21'], $result['dates']);
        self::assertSame(3600, $result['refresh_after']);
        self::assertTrue($result['available']);
        self::assertFalse($result['stale']);
        $otherRequest = new CalendarHolidayService($client, $this->directory);
        self::assertSame($result['dates'], $otherRequest->getHolidays(1405, self::NOW + 3599)['dates']);
        self::assertSame(1, $client->getRequestsCount());
        $otherRequest->getHolidays(1405, self::NOW + 3601);
        self::assertSame(2, $client->getRequestsCount());
    }

    /** Checks daily by default and never treats an upstream response as fresh beyond its Age. */
    public function testDailyDefaultAndAgeHeader(): void
    {
        $default = new CalendarHolidayService(new MockHttpClient($this->response()), $this->directory);
        self::assertSame(86400, $default->getHolidays(1405, self::NOW)['refresh_after']);
        $client = new MockHttpClient($this->response(['Cache-Control: max-age=3600', 'Age: 600']));
        $service = new CalendarHolidayService($client, $this->directory);
        self::assertSame(3000, $service->getHolidays(1405, self::NOW + 86401)['refresh_after']);
    }

    /** Keeps known holiday dates when a refresh fails and avoids repeated failing requests. */
    public function testFailurePreservesStaleDatesAndBacksOff(): void
    {
        $client = new MockHttpClient([$this->response(['Cache-Control: max-age=1']), $this->response([], 503)]);
        $service = new CalendarHolidayService($client, $this->directory);
        $service->getHolidays(1405, self::NOW);
        $result = $service->getHolidays(1405, self::NOW + 2);
        self::assertSame(['2026-03-21'], $result['dates']);
        self::assertTrue($result['stale']);
        self::assertTrue($result['available']);
        $service->getHolidays(1405, self::NOW + 3);
        self::assertSame(2, $client->getRequestsCount());
    }

    /** Honors Retry-After even when there is no earlier successful cache. */
    public function testRateLimitDoesNotBecomeAnEmptySuccessfulCalendar(): void
    {
        $client = new MockHttpClient($this->response(['Retry-After: 7200'], 429));
        $service = new CalendarHolidayService($client, $this->directory);
        $result = $service->getHolidays(1405, self::NOW);
        self::assertFalse($result['available']);
        self::assertSame([], $result['dates']);
        self::assertSame(7200, $result['refresh_after']);
        $service->getHolidays(1405, self::NOW + 4000);
        self::assertSame(1, $client->getRequestsCount());
    }

    /** Rejects a mismatched Gregorian date instead of coloring the wrong day. */
    public function testInvalidUpstreamDatesNeverReplaceGoodData(): void
    {
        $bad = new MockResponse('{"year":1405,"data":[{"year_sh":1405,"month_sh":1,"day_sh":1,"date_gregorian":"2026-03-20","events":[{"is_holiday":true}]}]}');
        $client = new MockHttpClient([$this->response(['Cache-Control: max-age=1']), $bad]);
        $service = new CalendarHolidayService($client, $this->directory);
        $service->getHolidays(1405, self::NOW);
        $result = $service->getHolidays(1405, self::NOW + 2);
        self::assertSame(['2026-03-21'], $result['dates']);
        self::assertTrue($result['stale']);
    }

    /** Uses the existing snapshot while another request holds the refresh lock. */
    public function testBusyRefreshReturnsStaleDatesWithoutAnotherUpstreamRequest(): void
    {
        $client = new MockHttpClient($this->response(['Cache-Control: max-age=1']));
        $service = new CalendarHolidayService($client, $this->directory);
        $service->getHolidays(1405, self::NOW);
        $lock = fopen($this->directory . '/1405.json.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $result = $service->getHolidays(1405, self::NOW + 2);
            self::assertSame(['2026-03-21'], $result['dates']);
            self::assertTrue($result['stale']);
            self::assertSame(1, $client->getRequestsCount());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Does not persist a provider response that explicitly forbids storage. */
    public function testNoStoreIsRespected(): void
    {
        $client = new MockHttpClient([$this->response(['Cache-Control: no-store']), $this->response()]);
        $service = new CalendarHolidayService($client, $this->directory);
        self::assertSame(['2026-03-21'], $service->getHolidays(1405, self::NOW)['dates']);
        $service->getHolidays(1405, self::NOW + 1);
        self::assertSame(2, $client->getRequestsCount());
    }

    /** Protects the endpoint, rejects malformed years, and exposes dates rather than event titles. */
    public function testRouteAuthenticationValidationAndDateOnlyResponse(): void
    {
        $previousSession = $_SESSION ?? [];
        try {
            $client = new MockHttpClient($this->response());
            $controller = new CalendarController(new CalendarHolidayService($client, $this->directory));
            $router = (new Router())->bind(CalendarController::class, $controller);
            $routes = require dirname(__DIR__, 2) . '/routes/api.php';
            $routes($router);
            $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/calendar/holidays'];
            $_SESSION = [];
            self::assertSame(401, $router->dispatch(new Request(['year' => '1405'], [], $server))->getStatusCode());
            $_SESSION = ['user' => ['id' => 10]];
            foreach (['../1405', '1405oops', '1300', '9999', ['1405']] as $year) {
                self::assertSame(422, $router->dispatch(new Request(['year' => $year], [], $server))->getStatusCode());
            }
            self::assertSame(0, $client->getRequestsCount());
            $response = $router->dispatch(new Request(['year' => '1405'], [], $server));
            self::assertSame(200, $response->getStatusCode());
            $body = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(['2026-03-21'], $body['dates']);
            self::assertArrayNotHasKey('events', $body);
        } finally {
            $_SESSION = $previousSession;
        }
    }
}
