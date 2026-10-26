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

final class TaskDeletionTest extends TestCase
{
    private PDO $pdo;
    private Router $router;
    private array $previousSession;

    /** Builds real controller/repository dependencies using only a fresh in-memory database. */
    protected function setUp(): void
    {
        $this->previousSession = $_SESSION ?? [];
        $_SESSION = ['user' => ['id' => 10], 'csrf_token' => 'deletion-test-token'];
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, is_done INTEGER, completed_at TEXT)');
        $this->pdo->exec('CREATE TABLE task_reminders (id INTEGER PRIMARY KEY, task_id INTEGER REFERENCES tasks(id) ON DELETE CASCADE)');
        $this->pdo->exec('CREATE TABLE user_settings (user_id INTEGER, language TEXT, calendar_system TEXT, timezone TEXT)');
        $this->pdo->exec("INSERT INTO user_settings VALUES (10, 'english', 'gregorian', 'Asia/Tehran')");
        $this->pdo->exec('INSERT INTO tasks VALUES (1, 10, 0, NULL), (2, 20, 0, NULL)');
        $this->pdo->exec('INSERT INTO task_reminders VALUES (1, 1), (2, 2)');
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
        $routes = require dirname(__DIR__, 2) . '/routes/api.php';
        $routes($this->router);
    }

    /** Restores the surrounding test process session after each isolated request scenario. */
    protected function tearDown(): void
    {
        $_SESSION = $this->previousSession;
    }

    /** Sends the same authenticated AJAX request shape used by the browser. */
    private function request(array $post, string $method = 'POST'): App\Http\Response
    {
        return $this->router->dispatch(new Request([], $post, [
            'REQUEST_METHOD' => $method, 'REQUEST_URI' => '/api/tasks/delete',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]));
    }

    /** Deletes only the owned task and its dependent reminders, returning canonical UI state. */
    public function testOwnedDeletionRemovesOnlyItsReminderAndReturnsUpdatedCount(): void
    {
        $response = $this->request(['task_id' => '1', 'csrf_token' => 'deletion-test-token']);
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertArrayHasKey('deleted_task_id', $data);
        self::assertSame(1, $data['deleted_task_id']);
        self::assertSame(0, $data['completed_today_count']);
        self::assertSame([2], $this->pdo->query('SELECT id FROM tasks')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame([2], $this->pdo->query('SELECT id FROM task_reminders')->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Rejects a foreign task without changing either owner's tasks or reminders. */
    public function testForeignTaskCannotBeDeleted(): void
    {
        self::assertSame(404, $this->request(['task_id' => '2', 'csrf_token' => 'deletion-test-token'])->getStatusCode());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM task_reminders')->fetchColumn());
    }

    /** Recounts completed tasks using the saved user timezone, not the server's UTC date. */
    public function testCompletedCountRespectsTheUsersLocalDayAfterDeletion(): void
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran'));
        $inside = $today->modify('+1 minute')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $outside = $today->modify('-1 minute')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $insert = $this->pdo->prepare('INSERT INTO tasks VALUES (?, ?, 1, ?)');
        $insert->execute([3, 10, $inside]);
        $insert->execute([4, 10, $inside]);
        $insert->execute([5, 10, $outside]);
        $insert->execute([6, 20, $inside]);

        $response = $this->request(['task_id' => '3', 'csrf_token' => 'deletion-test-token']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)['completed_today_count']);
    }

    /** Requires both authentication and a valid CSRF token before mutating any rows. */
    public function testInvalidCsrfAndUnauthenticatedRequestsDoNotDelete(): void
    {
        self::assertSame(403, $this->request(['task_id' => '1', 'csrf_token' => 'wrong'])->getStatusCode());
        self::assertSame(403, $this->request(['task_id' => '1'])->getStatusCode());
        $_SESSION = [];
        self::assertSame(401, $this->request(['task_id' => '1'])->getStatusCode());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    /** Rejects malformed identifiers and GET navigation instead of executing a deletion. */
    public function testInvalidIdentifiersAndGetRequestsDoNotDelete(): void
    {
        foreach (['0', '-1', 'oops', '1 OR 1=1'] as $taskId) {
            self::assertSame(422, $this->request(['task_id' => $taskId, 'csrf_token' => 'deletion-test-token'])->getStatusCode());
        }
        self::assertSame(404, $this->request(['task_id' => '1', 'csrf_token' => 'deletion-test-token'], 'GET')->getStatusCode());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn());
    }

    /** Renders a semantic translated delete button without legacy links or inline handlers. */
    public function testDeleteControlIsAButtonInBothLanguages(): void
    {
        require_once dirname(__DIR__, 2) . '/views/components/task-items.php';
        foreach (['english', 'persian'] as $language) {
            $translator = new Translator($language, dirname(__DIR__, 2) . '/resources/lang');
            ob_start();
            renderSingleTaskItem((object) ['id' => 1, 'title' => '<Test "task">', 'is_done' => 0], 'home', false, false, new DateTimeZone('UTC'), $translator);
            $markup = ob_get_clean();
            self::assertStringContainsString('data-task-delete', $markup);
            self::assertStringContainsString('type="button"', $markup);
            self::assertStringNotContainsString('delete_task=', $markup);
            self::assertStringNotContainsString('onclick=', $markup);
            self::assertStringNotContainsString('<Test', $markup);
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $markup);
            $deleteButtons = (new DOMXPath($dom))->query('//button[@data-task-delete]');
            self::assertCount(1, $deleteButtons);
            self::assertSame($translator->translate('task.delete', ['title' => '<Test "task">']), $deleteButtons[0]->getAttribute('aria-label'));
        }
    }
}
