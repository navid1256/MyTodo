<?php

declare(strict_types=1);

use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Services\ReminderService;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatScheduleCalculator;
use App\Services\RepeatService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepeatLockingTest extends TestCase
{
    private static IsolatedRepeatTestConnection $pdo;
    private static array $snapshot;
    private RepeatService $service;
    private RepeatRuleRepository $rules;
    private TaskRepository $tasks;
    private int $userId;
    private int $ruleId;
    private int $taskId;
    private int $foreignTaskId;
    private int $foreignUserId;
    private DateTimeImmutable $now;
    private DateTimeImmutable $start;
    private mixed $worker = null;
    private array $pipes = [];
    private mixed $server = null;
    private mixed $events = null;

    /** Requires explicit MySQL opt-in; never substitutes SQLite for a real concurrency test. */
    public static function setUpBeforeClass(): void
    {
        $database = getenv('MYTODO_REPEAT_TEST_DATABASE');
        if ($database === false || $database === '') {
            self::markTestSkipped('Real locking requires MYTODO_REPEAT_TEST_DATABASE=mytodo and isolated tables.');
        }
        if ($database !== 'mytodo') {
            throw new InvalidArgumentException('Only mytodo is authorized for isolated Repeat tests.');
        }
        require_once __DIR__ . '/fixtures/isolated-repeat-test-connection.php';
        self::$pdo = new IsolatedRepeatTestConnection();
        self::$snapshot = self::$pdo->snapshotApplicationTables();
        try {
            self::$pdo->createFixtureTables();
        } catch (Throwable $exception) {
            self::$pdo->dropFixtureTables();
            throw $exception;
        }
    }

    /** Drops only owned fixture tables and checks original application data and schemas. */
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$pdo)) {
            self::$pdo->dropFixtureTables();
            self::assertSame(self::$snapshot, self::$pdo->snapshotApplicationTables());
        }
    }

    /** Commits private fixture rows so the independently connected edit process can observe them. */
    protected function setUp(): void
    {
        $this->rules = new RepeatRuleRepository(self::$pdo);
        $this->tasks = new TaskRepository(self::$pdo);
        $calculator = new RepeatScheduleCalculator();
        $this->service = new RepeatService($this->rules, new RepeatRuleValidator(), $calculator,
            new RepeatOccurrencePlanner($calculator), $this->tasks, new ReminderService(new ReminderRepository(self::$pdo)));
        $this->now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->start = $this->now->modify('+2 days')->setTime(9, 0);
        self::$pdo->beginTransaction();
        $users = self::$pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
        foreach (['owner', 'foreign'] as $label) {
            $suffix = bin2hex(random_bytes(8));
            $users->execute([$label . '_' . $suffix, $suffix . '@example.test', 'not-a-login']);
            $userId = (int) self::$pdo->lastInsertId();
            $rule = $this->service->prepareRule(['frequency' => 'daily', 'unit' => 'day', 'interval' => 1,
                'week_days' => [], 'month_day' => null, 'month_day_mode' => 'clamp',
                'ends' => ['type' => 'count', 'count' => 2, 'date' => '']], $this->start);
            $ruleId = $this->service->createRule($userId, 'Lock fixture', $this->start, true, $rule,
                [['offset_value' => 30, 'offset_unit' => 'minute']]);
            $original = $this->tasks->create($userId, 'Original', $this->date($this->start), true, $ruleId, 0);
            if ($label === 'foreign') {
                $this->foreignTaskId = $original;
                $this->foreignUserId = $userId;
                continue;
            }
            $this->userId = $userId;
            $this->ruleId = $ruleId;
            $next = $this->start->modify('+1 day');
            $this->taskId = $this->tasks->create($userId, 'Selected', $this->date($next), true, $ruleId, 1);
            $this->rules->updateGenerationState($ruleId, $userId, 1, $this->date($next->modify('+1 day')), 'active');
            $reminders = new ReminderRepository(self::$pdo);
            $reminders->create($original, 30, 'minute', $this->date($this->start->modify('-30 minutes')));
            $reminders->create($this->taskId, 30, 'minute', $this->date($next->modify('-30 minutes')));
        }
        self::$pdo->commit();
    }

    /** Releases parent locks, closes only this test's child process, then clears its private namespace. */
    protected function tearDown(): void
    {
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        if (is_resource($this->worker)) {
            if (proc_get_status($this->worker)['running']) {
                proc_terminate($this->worker);
            }
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($this->worker);
        }
        foreach ([$this->events, $this->server] as $socket) {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
        foreach (array_reverse(IsolatedRepeatTestConnection::TABLES) as $table) {
            self::$pdo->exec('DELETE FROM ' . $table);
        }
    }

    /** Covers all edit entrypoints against each competing rule-first operation. */
    public static function competingOperations(): iterable
    {
        foreach (['single', 'future', 'rule'] as $edit) {
            foreach (['pause', 'cancel', 'generate'] as $other) {
                yield "$edit/$other" => [$edit, $other];
            }
        }
    }

    /** Proves a waiting editor holds no task lock, then lets both real service operations finish. */
    #[DataProvider('competingOperations')]
    public function testEditsUseRuleBeforeTask(string $edit, string $other): void
    {
        $foreignBefore = $this->tasks->findById($this->foreignTaskId, $this->foreignUserId);
        self::$pdo->beginTransaction();
        $this->rules->findByIdForUserForUpdate($this->ruleId, $this->userId);
        $this->startEditor($edit);
        self::assertSame('rule-lock', $this->readEvent()['event']);
        $probe = self::$pdo->prepare('SELECT id FROM tasks WHERE id = :id FOR UPDATE');
        $probe->execute([':id' => $this->taskId]);
        self::assertSame($this->taskId, (int) $probe->fetchColumn(), 'A waiting editor must not already hold the task lock.');

        match ($other) {
            'pause' => $this->service->pauseRule($this->ruleId, $this->userId, $this->now),
            'cancel' => $this->service->cancelRule($this->ruleId, $this->userId, $this->now),
            'generate' => $this->service->generateInitialWindow($this->ruleId, $this->start->modify('+10 days')),
        };
        self::assertTrue(self::$pdo->inTransaction(), 'The service must leave the outer transaction open.');
        self::$pdo->commit();
        $result = $this->readEvent();
        // Editing a paused rule is intentionally supported; editing a deleted occurrence is not.
        $saved = $other === 'generate' || ($edit === 'rule' && $other === 'pause');
        self::assertSame($saved ? 'saved' : 'rejected', $result['status']);
        self::assertEquals($foreignBefore, $this->tasks->findById($this->foreignTaskId, $this->foreignUserId));
        self::assertSame(0, (int) self::$pdo->query('SELECT COUNT(*) FROM task_reminders AS r
            LEFT JOIN tasks AS t ON t.id = r.task_id WHERE t.id IS NULL')->fetchColumn());
        $duplicates = self::$pdo->query('SELECT repeat_rule_id, repeat_occurrence_number FROM tasks
            GROUP BY repeat_rule_id, repeat_occurrence_number HAVING COUNT(*) > 1')->fetchAll();
        self::assertSame([], $duplicates);
        self::assertSame([], self::$pdo->query('SELECT repeat_rule_id, due_at FROM tasks
            GROUP BY repeat_rule_id, due_at HAVING COUNT(*) > 1')->fetchAll());
        self::assertSame([], self::$pdo->query('SELECT task_id, offset_value, offset_unit FROM task_reminders
            GROUP BY task_id, offset_value, offset_unit HAVING COUNT(*) > 1')->fetchAll());
        if ($other !== 'generate') {
            self::assertNull($this->tasks->findById($this->taskId, $this->userId));
        } else {
            self::assertSame($edit, $result['scope']);
        }
    }

    /** Covers task changes committed after the editor's unlocked lookup but before its rule lock is granted. */
    public static function changedTasks(): iterable
    {
        foreach (['single', 'future'] as $edit) {
            foreach (['series', 'completed', 'owner'] as $change) {
                yield "$edit/$change" => [$edit, $change];
            }
        }
    }

    /** Rejects stale identities, ownership and completion state without writing through the old task snapshot. */
    #[DataProvider('changedTasks')]
    public function testTaskIsRevalidatedAfterWaiting(string $edit, string $change): void
    {
        self::$pdo->beginTransaction();
        $this->rules->findByIdForUserForUpdate($this->ruleId, $this->userId);
        $this->startEditor($edit);
        self::assertSame('rule-lock', $this->readEvent()['event']);
        if ($change === 'completed') {
            $this->tasks->updateStatus($this->taskId, $this->userId, true, $this->date($this->now));
        } elseif ($change === 'series') {
            $prepared = $this->service->prepareRule(['frequency' => 'daily', 'unit' => 'day', 'interval' => 1,
                'week_days' => [], 'month_day' => null, 'month_day_mode' => 'clamp',
                'ends' => ['type' => 'count', 'count' => 2, 'date' => '']], $this->start);
            $newRuleId = $this->service->createRule($this->userId, 'Other owned series', $this->start, true, $prepared, []);
            $this->tasks->attachToRule($this->taskId, $this->userId, $newRuleId, 0);
        } else {
            $foreign = $this->tasks->findById($this->foreignTaskId, $this->foreignUserId);
            $move = self::$pdo->prepare('UPDATE tasks SET user_id = :user_id, repeat_rule_id = :rule_id WHERE id = :id');
            $move->execute([':user_id' => $foreign->user_id, ':rule_id' => $foreign->repeat_rule_id, ':id' => $this->taskId]);
        }
        $before = self::$pdo->query('SELECT id, user_id, title, due_at, repeat_rule_id, repeat_occurrence_number, is_done, completed_at
            FROM tasks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $ruleCount = (int) self::$pdo->query('SELECT COUNT(*) FROM task_repeat_rules')->fetchColumn();
        self::$pdo->commit();
        self::assertSame('rejected', $this->readEvent()['status']);
        self::assertSame($before, self::$pdo->query('SELECT id, user_id, title, due_at, repeat_rule_id, repeat_occurrence_number, is_done, completed_at
            FROM tasks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($ruleCount, (int) self::$pdo->query('SELECT COUNT(*) FROM task_repeat_rules')->fetchColumn());
    }

    /** Launches an explicitly isolated editor; only fixture identifiers and dates cross the command line. */
    private function startEditor(string $edit): void
    {
        $this->server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        self::assertIsResource($this->server);
        $port = substr(strrchr(stream_socket_get_name($this->server, false), ':'), 1);
        $this->worker = proc_open([PHP_BINARY, __DIR__ . '/fixtures/repeat-lock-edit-worker.php',
            self::$pdo->prefix, $edit, (string) $this->taskId, (string) $this->ruleId,
            (string) $this->userId, $port, $this->start->modify('+1 day +1 hour')->format('c')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes);
        self::assertIsResource($this->worker);
        fclose($this->pipes[0]);
        unset($this->pipes[0]);
        $this->events = stream_socket_accept($this->server, 5);
        self::assertIsResource($this->events);
        stream_set_timeout($this->events, 5);
    }

    /** Waits for a bounded JSON event rather than assuming overlap from a fixed sleep. */
    private function readEvent(): array
    {
        $line = fgets($this->events);
        if ($line === false) {
            $details = proc_get_status($this->worker)['running'] ? '' : stream_get_contents($this->pipes[2]);
            self::fail('The isolated editor must send its event within five seconds. ' . $details);
        }
        $event = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(self::$pdo->prefix, $event['fixture']);
        if ($event['event'] === 'result') {
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }
            $this->pipes = [];
            $exitCode = proc_close($this->worker);
            $this->worker = null;
            self::assertSame(0, $exitCode, 'The isolated editor must exit cleanly.');
        }
        return $event;
    }

    /** Formats explicit UTC fixture instants for the repository. */
    private function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
