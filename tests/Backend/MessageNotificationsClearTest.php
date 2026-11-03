<?php

declare(strict_types=1);

use App\Controllers\NotificationController;
use App\Http\Request;
use App\Http\Router;
use App\Repositories\NotificationRepository;
use App\Repositories\ReminderRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\ReminderService;
use App\Services\UserSettingsService;
use PHPUnit\Framework\TestCase;

final class MessageNotificationsClearTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private NotificationService $service;
    private array $previousSession;

    /** Creates owned and foreign reminders without accessing the configured MySQL database. */
    protected function setUp(): void
    {
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = ['user' => ['id' => 10], 'csrf_token' => 'messages-clear-test'];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, due_at TEXT)');
        $this->pdo->exec('CREATE TABLE task_reminders (id INTEGER PRIMARY KEY, task_id INTEGER, status TEXT, offset_value INTEGER DEFAULT 0, offset_unit TEXT DEFAULT \'minute\', remind_at TEXT, attempt_count INTEGER DEFAULT 0, last_attempt_at TEXT, sent_at TEXT, read_at TEXT, cleared_at TEXT, messages_cleared_at TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE task_reminder_deliveries (id INTEGER PRIMARY KEY, reminder_id INTEGER, status TEXT)');
        $this->pdo->exec("INSERT INTO tasks VALUES (1, 10, 'Owned task', NULL), (2, 20, 'Other user', NULL)");
        $this->pdo->exec("INSERT INTO task_reminders (id, task_id, status, remind_at, sent_at, updated_at) VALUES (1, 1, 'pending', '2030-01-01 00:00:00', NULL, '2026-01-01'), (2, 1, 'failed', '2030-01-01 00:00:00', NULL, '2026-01-01'), (3, 1, 'sent', '2026-01-01 00:00:00', '2026-01-01 00:00:00', '2026-01-01'), (4, 1, 'cancelled', '2026-01-01 00:00:00', NULL, '2026-01-01'), (5, 2, 'sent', '2026-01-01 00:00:00', '2026-01-01 00:00:00', '2026-01-01'), (6, 2, 'cancelled', '2026-01-01 00:00:00', NULL, '2026-01-01')");
        $this->pdo->exec("INSERT INTO task_reminder_deliveries VALUES (1, 3, 'sent'), (2, 4, 'cancelled')");
        $this->service = new NotificationService(new NotificationRepository($this->pdo), new ReminderService(new ReminderRepository($this->pdo)));
        $users = new UserRepository($this->pdo);
        $controller = new NotificationController($this->service, new AuthService($users), $users, new UserSettingsService(new UserSettingsRepository($this->pdo)));
        $this->router = (new Router())->bind(NotificationController::class, $controller);
        (require dirname(__DIR__, 2) . '/routes/api.php')($this->router);
    }

    /** Restores the caller's session after each isolated example. */
    protected function tearDown(): void
    {
        $_SESSION = $this->previousSession;
    }

    /** Exercises the real route, middleware, and CSRF guard without bootstrapping the live app. */
    private function request(array $post, string $method = 'POST'): App\Http\Response
    {
        return $this->router->dispatch(new Request([], $post, [
            'REQUEST_URI' => '/api/notifications/clear-messages', 'REQUEST_METHOD' => $method,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]));
    }

    /** Clears only owned terminal entries and preserves delivery history, scheduling, and the sent archive. */
    public function testClearIsOwnerScopedAndIndependentOfArchive(): void
    {
        $response = $this->request(['csrf_token' => 'messages-clear-test', 'user_id' => '20']);
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $data['cleared_count']);
        self::assertSame([1, 2], $data['remaining_ids']);
        self::assertSame([1, 2], array_column($this->service->getNotificationsForUser(10), 'id'));
        self::assertCount(1, $this->service->getSentPage(10)['items']);
        self::assertSame(1, $this->service->countUnreadNotifications(10));
        self::assertSame(6, (int) $this->pdo->query('SELECT COUNT(*) FROM task_reminders')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM task_reminder_deliveries')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
        self::assertSame([3, 4], $this->pdo->query('SELECT id FROM task_reminders WHERE messages_cleared_at IS NOT NULL ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame('2026-01-01', $this->pdo->query('SELECT updated_at FROM task_reminders WHERE id = 3')->fetchColumn());
        self::assertSame(0, json_decode($this->request(['csrf_token' => 'messages-clear-test'])->getBody(), true)['cleared_count']);
    }

    /** Clearing the sent archive does not silently clear the Messages management list. */
    public function testArchiveClearDoesNotHideMessages(): void
    {
        $this->service->clearSent(10);
        self::assertCount(4, $this->service->getNotificationsForUser(10));
        self::assertSame([], $this->service->getSentPage(10)['items']);
    }

    /** Missing or forged CSRF, GET requests, and unauthenticated requests never mutate visibility. */
    public function testClearRequiresPostAuthenticationAndCsrf(): void
    {
        self::assertSame(403, $this->request([])->getStatusCode());
        self::assertSame(403, $this->request(['csrf_token' => 'wrong'])->getStatusCode());
        self::assertSame(404, $this->request(['csrf_token' => 'messages-clear-test'], 'GET')->getStatusCode());
        $_SESSION = [];
        self::assertSame(401, $this->request(['csrf_token' => 'messages-clear-test'])->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM task_reminders WHERE messages_cleared_at IS NOT NULL')->fetchColumn());
    }
}
