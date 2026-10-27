<?php

declare(strict_types=1);

use App\Controllers\NotificationController;
use App\Helpers\CalendarDateFormatter;
use App\Http\Request;
use App\Repositories\NotificationRepository;
use App\Repositories\ReminderRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\ReminderService;
use App\Services\UserSettingsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationDateDisplayTest extends TestCase
{
    /** Exercises each calendar/language combination without accessing the configured MySQL database. */
    public static function settingsCases(): iterable
    {
        foreach (['english', 'persian'] as $language) {
            foreach (['gregorian', 'jalali'] as $calendar) {
                yield "$calendar/$language" => [$calendar, $language];
            }
        }
    }

    /** Verifies AJAX dates use saved settings rather than cookies, while the persisted instant remains UTC. */
    #[DataProvider('settingsCases')]
    public function testReminderEditResponseUsesSavedCalendarAndTimezone(string $calendar, string $language): void
    {
        $session = $_SESSION ?? [];
        $cookies = $_COOKIE;
        $_SESSION = ['user' => ['id' => 10], 'csrf_token' => 'calendar-date-test'];
        $_COOKIE['mytodo_timezone'] = 'America/Los_Angeles';
        // SQLite is used only to verify response formatting, not MySQL row-lock behavior.
        $pdo = new class('sqlite::memory:') extends PDO {
            /** Removes MySQL-only lock syntax from this isolated response-formatting fixture. */
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
            }
        };
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, due_at TEXT, has_time INTEGER)');
        $pdo->exec('CREATE TABLE task_reminders (id INTEGER PRIMARY KEY, task_id INTEGER, status TEXT, offset_value INTEGER, offset_unit TEXT, remind_at TEXT, attempt_count INTEGER, last_attempt_at TEXT, sent_at TEXT)');
        $pdo->exec('CREATE TABLE task_reminder_deliveries (id INTEGER PRIMARY KEY, reminder_id INTEGER, status TEXT)');
        $pdo->exec('CREATE TABLE user_settings (user_id INTEGER, language TEXT, calendar_system TEXT, timezone TEXT)');
        $settings = $pdo->prepare('INSERT INTO user_settings VALUES (10, ?, ?, ?)');
        $settings->execute([$language, $calendar, 'Asia/Tehran']);
        $dueAt = (new DateTimeImmutable('+1 year', new DateTimeZone('UTC')))->setTime(21, 30);
        $pdo->prepare('INSERT INTO tasks VALUES (1, 10, ?, 1)')->execute([$dueAt->format('Y-m-d H:i:s')]);
        $pdo->exec("INSERT INTO task_reminders (id, task_id, status) VALUES (1, 1, 'pending')");
        $users = new UserRepository($pdo);
        $controller = new NotificationController(
            new NotificationService(new NotificationRepository($pdo), new ReminderService(new ReminderRepository($pdo))),
            new AuthService($users), $users, new UserSettingsService(new UserSettingsRepository($pdo))
        );
        try {
            $response = $controller->update(new Request([], [
                'notification_id' => '1', 'offset_value' => '0', 'offset_unit' => 'minute',
                'csrf_token' => 'calendar-date-test',
            ], ['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT_LANGUAGE' => 'en']));
            self::assertSame(200, $response->getStatusCode());
            $result = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($dueAt->format('Y-m-d H:i:s'), $result['notification']['remind_at']);
            self::assertSame(
                (new CalendarDateFormatter($calendar, new DateTimeZone('Asia/Tehran')))->dateTime($dueAt),
                $result['notification']['formatted_remind_at'] ?? null
            );
            self::assertSame('pending', $result['notification']['status']);
            self::assertNotEmpty($result['message']);
            self::assertSame($dueAt->format('Y-m-d H:i:s'), $pdo->query('SELECT remind_at FROM task_reminders')->fetchColumn());
        } finally {
            $_SESSION = $session;
            $_COOKIE = $cookies;
        }
    }
}
