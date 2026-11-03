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

final class NotificationArchiveTest extends TestCase
{
    private PDO $pdo;
    private NotificationService $service;
    private Router $router;
    private array $previousSession;

    /** Builds an isolated archive with owned, foreign, pending, and already-cleared reminders. */
    protected function setUp(): void
    {
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = ['user' => ['id' => 10], 'csrf_token' => 'archive-test'];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, due_at TEXT)');
        $this->pdo->exec('CREATE TABLE task_reminders (id INTEGER PRIMARY KEY, task_id INTEGER, status TEXT, sent_at TEXT, remind_at TEXT, read_at TEXT, cleared_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE task_reminder_deliveries (id INTEGER PRIMARY KEY, reminder_id INTEGER, status TEXT)');
        $this->pdo->exec("CREATE TABLE user_settings (user_id INTEGER, language TEXT, calendar_system TEXT, timezone TEXT)");
        $this->pdo->exec("INSERT INTO user_settings VALUES (10, 'english', 'jalali', 'Asia/Tehran')");
        $this->pdo->exec("INSERT INTO tasks VALUES (1, 10, '<Task>', '2026-10-29 09:00:00'), (2, 20, 'Foreign', NULL)");
        $insert = $this->pdo->prepare("INSERT INTO task_reminders VALUES (?, ?, ?, '2026-10-29 08:00:00', '2026-10-29 07:00:00', NULL, ?, '2026-10-29 08:00:00')");
        foreach ([[1, 1, 'pending', null], [2, 1, 'pending', null], [3, 2, 'sent', null], [4, 1, 'sent', '2026-10-29 08:01:00']] as $row) {
            $insert->execute($row);
        }
        for ($id = 5; $id <= 27; $id++) {
            $insert->execute([$id, 1, 'sent', null]);
        }
        $this->pdo->exec("INSERT INTO task_reminder_deliveries VALUES (1, 5, 'sent')");
        $this->service = new NotificationService(new NotificationRepository($this->pdo), new ReminderService(new ReminderRepository($this->pdo)));
        $users = new UserRepository($this->pdo);
        $controller = new NotificationController($this->service, new AuthService($users), $users, new UserSettingsService(new UserSettingsRepository($this->pdo)));
        $this->router = (new Router())->bind(NotificationController::class, $controller);
        (require dirname(__DIR__, 2) . '/routes/api.php')($this->router);
    }

    /** Restores session data without touching any configured database. */
    protected function tearDown(): void
    {
        $_SESSION = $this->previousSession;
    }

    /** Dispatches archive requests through the real routes, authentication, and CSRF guards. */
    private function request(string $path, array $post = [], array $query = [], string $method = 'POST'): App\Http\Response
    {
        return $this->router->dispatch(new Request($query, $post, ['REQUEST_URI' => '/api/notifications/' . $path, 'REQUEST_METHOD' => $method, 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));
    }

    /** Uses a stable cursor to return every owned visible item once, even when send times tie. */
    public function testLoadMoreExcludesForeignAndClearedItemsWithoutDuplicates(): void
    {
        $first = $this->service->getSentPage(10);
        self::assertCount(20, $first['items']);
        self::assertNotNull($first['next_cursor']);
        $second = $this->service->getSentPage(10, $first['next_cursor']);
        self::assertCount(3, $second['items']);
        self::assertNull($second['next_cursor']);
        self::assertSame(range(27, 5), array_column([...$first['items'], ...$second['items']], 'id'));
    }

    /** Sorts by real send time rather than creation ID, with a stable fallback for legacy null send times. */
    public function testCursorUsesSendTimeAndIsUnaffectedByReadUpdates(): void
    {
        $this->pdo->exec("UPDATE task_reminders SET sent_at = '2030-01-01 00:00:00' WHERE id = 5");
        $this->pdo->exec("UPDATE task_reminders SET sent_at = NULL, remind_at = '2029-01-01 00:00:00' WHERE id = 10");
        $first = $this->service->getSentPage(10);
        self::assertSame([5, 10], array_slice(array_column($first['items'], 'id'), 0, 2));
        $this->service->markSentRead(10);
        $second = $this->service->getSentPage(10, $first['next_cursor']);
        self::assertCount(23, array_unique(array_column([...$first['items'], ...$second['items']], 'id')));
    }

    /** Reading is owner-scoped, idempotent, and does not mark subsequently sent reminders read. */
    public function testReadStateDoesNotAffectForeignPendingOrLaterNotifications(): void
    {
        self::assertSame(23, $this->service->countUnreadNotifications(10));
        $response = $this->request('read', ['csrf_token' => 'archive-test']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->service->countUnreadNotifications(10));
        self::assertSame(1, $this->service->countUnreadNotifications(20));
        self::assertNull($this->pdo->query('SELECT read_at FROM task_reminders WHERE id = 2')->fetchColumn());
        $readAt = $this->pdo->query('SELECT read_at FROM task_reminders WHERE id = 5')->fetchColumn();
        $this->service->markSentRead(10);
        self::assertSame($readAt, $this->pdo->query('SELECT read_at FROM task_reminders WHERE id = 5')->fetchColumn());
        $this->pdo->exec("UPDATE task_reminders SET status = 'sent' WHERE id = 2");
        self::assertSame(1, $this->service->countUnreadNotifications(10));
    }

    /** Clear hides the whole owned sent archive, while retaining tasks, pending reminders, and delivery deduplication. */
    public function testClearPreservesOperationalHistoryAndOtherOwners(): void
    {
        $response = $this->request('clear', ['csrf_token' => 'archive-test']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(23, json_decode($response->getBody(), true)['cleared_count']);
        self::assertSame([], $this->service->getSentPage(10)['items']);
        self::assertSame(1, $this->service->countUnreadNotifications(20));
        self::assertSame(27, (int) $this->pdo->query('SELECT COUNT(*) FROM task_reminders')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM task_reminder_deliveries')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
        self::assertNull($this->pdo->query('SELECT cleared_at FROM task_reminders WHERE id = 2')->fetchColumn());
        self::assertSame('sent', $this->pdo->query('SELECT status FROM task_reminder_deliveries WHERE id = 1')->fetchColumn());
        self::assertSame('2026-10-29 08:00:00', $this->pdo->query('SELECT updated_at FROM task_reminders WHERE id = 5')->fetchColumn());
        self::assertSame(0, json_decode($this->request('clear', ['csrf_token' => 'archive-test'])->getBody(), true)['cleared_count']);
    }

    /** Rejects unauthenticated requests, invalid CSRF, unsafe GET mutations, and malformed cursors. */
    public function testArchiveGuardsPreventUnintendedChanges(): void
    {
        foreach (['read', 'clear'] as $action) {
            self::assertSame(403, $this->request($action, ['csrf_token' => 'invalid'])->getStatusCode());
            self::assertSame(404, $this->request($action, [], [], 'GET')->getStatusCode());
        }
        self::assertSame(422, $this->request('sent', [], ['cursor' => 'garbage'], 'GET')->getStatusCode());
        self::assertSame(23, $this->service->countUnreadNotifications(10));
        $_SESSION = [];
        self::assertSame(401, $this->request('clear', ['csrf_token' => 'archive-test'])->getStatusCode());
    }

    /** Returns formatted Jalali dates without exposing reminder or subscription internals. */
    public function testLoadMoreResponseFormatsDatesAndDoesNotWriteReadState(): void
    {
        $response = $this->request('sent', [], [], 'GET');
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('۱۴۰۵', $data['items'][0]['sent_at']);
        self::assertSame('<Task>', $data['items'][0]['title']);
        self::assertArrayNotHasKey('attempt_count', $data['items'][0]);
        self::assertSame(23, $this->service->countUnreadNotifications(10));
    }
}
