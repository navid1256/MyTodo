<?php

declare(strict_types=1);

use App\Controllers\TaskController;
use App\Http\Request;
use App\Http\Router;
use App\Localization\Translator;
use App\Repositories\NotificationRepository;
use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\ReminderService;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatScheduleCalculator;
use App\Services\RepeatService;
use App\Services\TaskService;
use App\Services\UserSettingsService;
use PHPUnit\Framework\TestCase;

final class ClearCompletedTasksTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private array $previousSession;

    /** Builds an isolated database with both owners, recurring occurrences, and dependent notifications. */
    protected function setUp(): void
    {
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = ['user' => ['id' => 10], 'csrf_token' => 'clear-completed-test'];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('CREATE TABLE task_repeat_rules (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT)');
        $this->pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, is_done INTEGER, completed_at TEXT, repeat_rule_id INTEGER REFERENCES task_repeat_rules(id) ON DELETE SET NULL)');
        $this->pdo->exec('CREATE TABLE task_reminders (id INTEGER PRIMARY KEY, task_id INTEGER REFERENCES tasks(id) ON DELETE CASCADE, status TEXT, read_at TEXT, cleared_at TEXT)');
        $this->pdo->exec('CREATE TABLE task_reminder_deliveries (id INTEGER PRIMARY KEY, reminder_id INTEGER REFERENCES task_reminders(id) ON DELETE CASCADE)');
        $this->pdo->exec('CREATE TABLE user_settings (user_id INTEGER, language TEXT, calendar_system TEXT, timezone TEXT)');
        $this->pdo->exec("INSERT INTO user_settings VALUES (10, 'english', 'gregorian', 'Asia/Tehran')");
        $this->pdo->exec("INSERT INTO task_repeat_rules VALUES (11, 10, 'active')");
        $this->pdo->exec("INSERT INTO tasks VALUES (1, 10, 1, '2020-01-01 00:00:00', NULL), (2, 10, 1, '2026-10-29 00:00:00', 11), (3, 10, 1, NULL, NULL), (4, 10, 0, NULL, 11), (5, 20, 1, '2026-10-29 00:00:00', NULL), (6, 20, 0, NULL, NULL)");
        $this->pdo->exec("INSERT INTO task_reminders VALUES (1, 1, 'sent', NULL, NULL), (2, 2, 'sent', NULL, NULL), (4, 4, 'pending', NULL, NULL), (5, 5, 'sent', NULL, NULL)");
        $this->pdo->exec('INSERT INTO task_reminder_deliveries VALUES (1, 1), (2, 2), (4, 4), (5, 5)');
        $tasks = new TaskRepository($this->pdo);
        $reminders = new ReminderService(new ReminderRepository($this->pdo));
        $calculator = new RepeatScheduleCalculator();
        $repeat = new RepeatService(new RepeatRuleRepository($this->pdo), new RepeatRuleValidator(), $calculator, new RepeatOccurrencePlanner($calculator), $tasks, $reminders);
        $users = new UserRepository($this->pdo);
        $controller = new TaskController(
            new TaskService($tasks, $reminders, $repeat), new AuthService($users), $users,
            new NotificationService(new NotificationRepository($this->pdo), $reminders),
            new UserSettingsService(new UserSettingsRepository($this->pdo))
        );
        $this->router = (new Router())->bind(TaskController::class, $controller);
        (require dirname(__DIR__, 2) . '/routes/api.php')($this->router);
    }

    /** Restores the caller's session after each independent test. */
    protected function tearDown(): void
    {
        $_SESSION = $this->previousSession;
    }

    /** Sends the real AJAX request shape without any connection to the configured MySQL database. */
    private function request(array $post, string $method = 'POST'): App\Http\Response
    {
        return $this->router->dispatch(new Request([], $post, [
            'REQUEST_METHOD' => $method, 'REQUEST_URI' => '/api/tasks/clear-completed',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]));
    }

    /** Clears all owned completion dates, ignoring forged owner/filter fields and preserving incomplete series. */
    public function testClearDeletesOnlyOwnedCompletedTasksAndTheirDependents(): void
    {
        $response = $this->request(['csrf_token' => 'clear-completed-test', 'user_id' => '20', 'filter' => 'today']);
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertSame(3, $data['deleted_count']);
        self::assertSame(0, $data['completed_today_count']);
        self::assertSame(0, $data['unread_count']);
        self::assertSame([4, 5, 6], $this->pdo->query('SELECT id FROM tasks ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame([4, 5], $this->pdo->query('SELECT id FROM task_reminders ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame([4, 5], $this->pdo->query('SELECT id FROM task_reminder_deliveries ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame('active', $this->pdo->query('SELECT status FROM task_repeat_rules WHERE id = 11')->fetchColumn());
    }

    /** Requires authentication and valid CSRF, and never accepts navigation GETs as deletion. */
    public function testUnauthorizedAndInvalidRequestsCannotClearTasks(): void
    {
        self::assertSame(403, $this->request(['csrf_token' => 'wrong'])->getStatusCode());
        self::assertSame(403, $this->request([])->getStatusCode());
        self::assertSame(404, $this->request(['csrf_token' => 'clear-completed-test'], 'GET')->getStatusCode());
        $_SESSION = [];
        self::assertSame(401, $this->request(['csrf_token' => 'clear-completed-test'])->getStatusCode());
        self::assertSame(6, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    /** Repeated clearing of an already-empty completed list succeeds without affecting remaining tasks. */
    public function testEmptyClearIsSafe(): void
    {
        $this->request(['csrf_token' => 'clear-completed-test']);
        $response = $this->request(['csrf_token' => 'clear-completed-test']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, json_decode($response->getBody(), true)['deleted_count']);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    /** Renders localized, named controls and a native confirmation dialog with a safe initial action. */
    public function testClearControlAndConfirmationRenderInBothLanguages(): void
    {
        foreach (['english', 'persian'] as $language) {
            $translator = new Translator($language, dirname(__DIR__, 2) . '/resources/lang');
            $completedTasks = [(object) ['id' => 1, 'title' => '<Task>', 'completed_at' => '2026-10-29 00:00:00']];
            $activityTimezone = new DateTimeZone('Asia/Tehran');
            ob_start();
            include dirname(__DIR__, 2) . '/views/pages/activity.php';
            $markup = (string) ob_get_clean();
            self::assertStringContainsString('data-clear-completed-tasks', $markup);
            self::assertStringContainsString('<dialog', $markup);
            self::assertStringContainsString('autofocus', $markup);
            self::assertStringContainsString($translator->translate('activity.clear'), $markup);
            self::assertStringNotContainsString('<Task>', $markup);
        }
    }
}
