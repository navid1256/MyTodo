<?php

declare(strict_types=1);

use App\Helpers\CalendarDateFormatter;
use App\Localization\Translator;
use Hekmatinasser\Verta\Verta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalendarDateFormatterTest extends TestCase
{
    /** Preserves Gregorian formatting and the original instant while projecting into the user's timezone. */
    public function testGregorianDisplayIsUnchanged(): void
    {
        $date = new DateTime('2026-03-20 21:30:00', new DateTimeZone('UTC'));
        $formatter = new CalendarDateFormatter('gregorian', new DateTimeZone('Asia/Tehran'));
        self::assertSame('Mar 21, 2026', $formatter->date($date));
        self::assertSame('March 21, 2026', $formatter->date($date, true));
        self::assertSame('Mar 21, 2026 at 01:00 AM', $formatter->dateTime($date));
        self::assertSame('2026-03-20T21:30:00+00:00', $date->format('c'));
    }

    /** Keeps Jalali names and digits Persian even if another caller changes Verta's global locale. */
    public function testJalaliFormattingIgnoresUiAndGlobalLocale(): void
    {
        $previousLocale = Verta::getLocale();
        Verta::setLocale('en');
        try {
            $formatter = new CalendarDateFormatter('jalali', new DateTimeZone('Asia/Tehran'));
            $date = new DateTimeImmutable('2026-03-20 21:30:00', new DateTimeZone('UTC'));
            self::assertSame('۱ فروردین ۱۴۰۵', $formatter->date($date));
            self::assertSame('۰۱:۰۰ ق.ظ', $formatter->time($date));
            self::assertSame('۱ فروردین ۱۴۰۵ ساعت ۰۱:۰۰ ق.ظ', $formatter->dateTime($date));
            self::assertSame('en', Verta::getLocale());
            self::assertSame('۲۹ اسفند ۱۴۰۴', (new CalendarDateFormatter('jalali', new DateTimeZone('America/Los_Angeles')))->date($date));
            self::assertSame('۳۰ اسفند ۱۴۰۳', $formatter->date(new DateTimeImmutable('2025-03-20 09:00:00Z')));
            self::assertSame('۰۳:۳۰ ب.ظ', $formatter->time(new DateTimeImmutable('2026-03-21 12:00:00Z')));
        } finally {
            Verta::setLocale($previousLocale);
        }
    }

    /** Covers each requested page in both interface languages without opening a database connection. */
    public static function pageCases(): iterable
    {
        foreach (['english', 'persian'] as $language) {
            foreach (['gregorian', 'jalali'] as $calendar) {
                foreach (['home', 'activity', 'messages', 'notifications', 'manage-tasks'] as $page) {
                    yield "$page/$language/$calendar" => [$page, $language, $calendar];
                }
            }
        }
    }

    /** Verifies actual templates render Jalali text but retain Gregorian machine-readable dates and escaping. */
    #[DataProvider('pageCases')]
    public function testPageDatesFollowCalendarSystem(string $page, string $language, string $calendar): void
    {
        $html = self::renderPage($page, $language, $calendar);
        $expectedDate = $calendar === 'jalali' ? '۱ فروردین ۱۴۰۵'
            : ($page === 'activity' ? 'March 21, 2026' : 'Mar 21, 2026');
        self::assertStringContainsString($expectedDate, $html);
        if ($calendar === 'jalali') {
            self::assertStringNotContainsString('Mar 21, 2026', $html);
        }
        self::assertStringContainsString('&lt;Example&gt;', $html);
        if ($page === 'activity') {
            self::assertStringContainsString('data-completed-date="2026-03-21"', $html);
            self::assertStringContainsString('datetime="2026-03-21T01:00:00+03:30"', $html);
        }
        if ($page === 'manage-tasks') {
            self::assertStringContainsString('data-task-date="2026-03-21"', $html);
        }
    }

    /** Renders a real page using only deterministic in-memory view data. */
    private static function renderPage(string $page, string $language, string $calendar): string
    {
        $translator = new Translator($language, dirname(__DIR__, 2) . '/resources/lang');
        $calendarSystem = $calendar;
        $calendarCssClass = $calendar === 'jalali' ? 'jalaliCalendar' : 'gregorianCalendar';
        $taskTimezone = $activityTimezone = $notificationTimezone = new DateTimeZone('Asia/Tehran');
        $task = (object) ['id' => 1, 'title' => '<Example>', 'is_done' => 0, 'has_time' => 1,
            'due_at' => '2026-03-20 21:30:00', 'created_at' => '2026-03-20 21:30:00',
            'completed_at' => '2026-03-20 21:30:00'];
        $noDateTasks = $todayTasks = $tomorrowTasks = $tasks = $completedTasks = [$task];
        $notifications = $sentNotifications = [(object) ['id' => 1, 'task_title' => '<Example>',
            'status' => 'sent', 'sent_at' => '2026-03-20 21:30:00',
            'remind_at' => '2026-03-20 21:30:00', 'task_due_at' => '2026-03-20 21:30:00']];
        ob_start();
        try {
            require dirname(__DIR__, 2) . '/views/pages/' . $page . '.php';
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
