<?php

declare(strict_types=1);

use App\Exceptions\RepeatValidationException;
use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Services\ReminderService;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleUpdateData;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatScheduleCalculator;
use App\Services\RepeatService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepeatEditTest extends TestCase
{
    private static IsolatedRepeatTestConnection $pdo;
    private static array $originalSnapshot;
    private RepeatService $service;
    private RepeatRuleRepository $rules;
    private TaskRepository $tasks;
    private int $userId;
    private int $ruleId;
    private int $taskId;
    private DateTimeImmutable $now;
    private DateTimeImmutable $dueAt;
    private array $config;

    /** Reuses the existing isolated MySQL guard; the default suite never opens a database connection. */
    public static function setUpBeforeClass(): void
    {
        $database = getenv('MYTODO_REPEAT_TEST_DATABASE');
        if ($database === false || $database === '') {
            self::markTestSkipped('Edit persistence tests require explicit isolated mytodo authorization.');
        }
        if ($database !== 'mytodo') {
            throw new InvalidArgumentException('Only mytodo is authorized for isolated Repeat tests.');
        }
        require_once __DIR__ . '/fixtures/isolated-repeat-test-connection.php';
        self::$pdo = new IsolatedRepeatTestConnection();
        self::$originalSnapshot = self::$pdo->snapshotApplicationTables();
        try {
            self::$pdo->createFixtureTables();
        } catch (Throwable $exception) {
            self::$pdo->dropFixtureTables();
            throw $exception;
        }
    }

    /** Removes owned fixture schemas and verifies that the application's tables were not modified. */
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$pdo)) {
            self::$pdo->dropFixtureTables();
            self::assertSame(self::$originalSnapshot, self::$pdo->snapshotApplicationTables());
        }
    }

    /** Seeds a future series inside the caller's transaction using real services and repositories. */
    protected function setUp(): void
    {
        self::$pdo->beginTransaction();
        $suffix = bin2hex(random_bytes(8));
        $insert = self::$pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
        $insert->execute(['edit_' . $suffix, $suffix . '@example.test', 'not-a-login']);
        $this->userId = (int) self::$pdo->lastInsertId();
        $this->rules = new RepeatRuleRepository(self::$pdo);
        $this->tasks = new TaskRepository(self::$pdo);
        $reminders = new ReminderRepository(self::$pdo);
        $calculator = new RepeatScheduleCalculator();
        $this->service = new RepeatService($this->rules, new RepeatRuleValidator(), $calculator,
            new RepeatOccurrencePlanner($calculator), $this->tasks, new ReminderService($reminders));
        $this->now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $start = $this->now->setTimezone(new DateTimeZone('Asia/Tehran'))->modify('+2 days')->setTime(9, 0);
        $this->dueAt = $start->modify('+1 hour')->setTimezone(new DateTimeZone('UTC'));
        $this->config = ['frequency' => 'custom', 'unit' => 'day', 'interval' => 2, 'week_days' => [],
            'month_day' => null, 'month_day_mode' => 'clamp', 'ends' => ['type' => 'count', 'count' => 2, 'date' => '']];
        $this->ruleId = $this->service->createRule($this->userId, 'Original rule', $start, true,
            $this->service->prepareRule($this->config, $start), [['offset_value' => 30, 'offset_unit' => 'minute']]);
        $this->taskId = $this->tasks->create($this->userId, 'Original task', $this->utc($start), true, $this->ruleId, 0);
        $reminders->create($this->taskId, 30, 'minute', $this->utc($start->modify('-30 minutes')));
        $this->service->generateInitialWindow($this->ruleId, $start->modify('+30 days'));
    }

    /** Releases caller transactions and clears only this suite's private tables, including committed rollback fixtures. */
    protected function tearDown(): void
    {
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        foreach (array_reverse(IsolatedRepeatTestConnection::TABLES) as $table) {
            self::$pdo->exec('DELETE FROM ' . $table);
        }
    }

    /** Enumerates the existing public edit contracts without depending on private helper names. */
    public static function editScopes(): iterable
    {
        foreach (['single', 'future', 'active', 'paused'] as $scope) {
            yield $scope => [$scope];
        }
    }

    /** Preserves response shapes, title normalization, stored timezone and rollback ownership. */
    #[DataProvider('editScopes')]
    public function testSuccessfulEditKeepsItsContract(string $scope): void
    {
        $this->prepareScope($scope);
        $result = $this->edit($scope);
        self::assertTrue(self::$pdo->inTransaction());
        self::assertSame(in_array($scope, ['active', 'paused'], true) ? 'rule' : $scope, $result['scope']);
        $storedRule = $this->rules->findByIdForUserForUpdate((int) $result['repeat_rule_id'], $this->userId);
        self::assertSame('Asia/Tehran', $storedRule->timezone);
        if ($scope === 'paused') {
            self::assertSame(['scope', 'repeat_rule_id', 'status', 'generated_count'], array_keys($result));
            self::assertSame('paused', $storedRule->status);
            self::assertSame('Edited title', $storedRule->title);
            self::assertNull($storedRule->next_occurrence_at);
        } else {
            self::assertSame($this->taskId, $result['task_id']);
            self::assertSame('Edited title', $result['task']['title']);
            self::assertSame($this->utc($this->dueAt), $result['task']['due_at']);
            if ($scope === 'single') {
                self::assertSame(['task_id', 'repeat_rule_id', 'scope', 'task', 'reminders'], array_keys($result));
            } else {
                self::assertSame(2, $result['generated_count']);
                self::assertSame(['scope', 'task_id', 'old_repeat_rule_id', 'repeat_rule_id', 'new_repeat_rule_id',
                    'deleted_task_ids', 'generated_count', 'task'], array_keys($result));
            }
            $reminders = (new ReminderRepository(self::$pdo))->getByTaskId($this->taskId);
            self::assertCount(1, $reminders);
            self::assertSame(45, (int) $reminders[0]->offset_value);
        }
        self::$pdo->rollBack();
        self::assertSame([], self::$pdo->query('SELECT id FROM task_repeat_rules')->fetchAll());
        self::assertSame([], self::$pdo->query('SELECT id FROM task_reminders')->fetchAll());
    }

    /** Preserves active-rule editing when generation has a cursor but no future task has been materialized. */
    public function testRuleEditMaterializesItsSelectedOccurrenceFromTheCursor(): void
    {
        $this->tasks->deleteFutureIncompleteForRule($this->ruleId, $this->userId, $this->utc($this->now));
        $this->rules->updateGenerationState($this->ruleId, $this->userId, 0, $this->utc($this->dueAt), 'active');

        $result = $this->edit('active');

        self::assertTrue(self::$pdo->inTransaction());
        self::assertSame('rule', $result['scope']);
        self::assertNotSame($this->taskId, $result['task_id']);
        self::assertSame(0, $result['task']['repeat_occurrence_number']);
        self::assertSame('Edited title', $result['task']['title']);
        self::assertSame($this->utc($this->dueAt), $result['task']['due_at']);
        self::assertSame(2, $result['generated_count']);
        self::assertSame('completed', $this->rules->findByIdForUserForUpdate($this->ruleId, $this->userId)->status);
    }

    /** Keeps invalid-title error precedence and guarantees validation does not write partial edits. */
    #[DataProvider('editScopes')]
    public function testValidationLeavesTheCallerTransactionAndDataIntact(string $scope): void
    {
        $this->prepareScope($scope);
        $before = $this->snapshotFixture();
        $this->config['unit'] = 'invalid';
        try {
            $this->edit($scope, '   ');
            self::fail('An empty title must be rejected.');
        } catch (RepeatValidationException $exception) {
            self::assertSame('Task title is invalid.', $exception->getMessage());
        }
        self::assertTrue(self::$pdo->inTransaction());
        self::assertSame($before, $this->snapshotFixture());
    }

    /** Covers service-owned and caller-owned rollback boundaries for every edit path. */
    public static function rollbackCases(): iterable
    {
        foreach (self::editScopes() as [$scope]) {
            foreach ([false, true] as $outer) {
                yield $scope . ($outer ? '/caller transaction' : '/service transaction') => [$scope, $outer];
            }
        }
    }

    /** Injects a persistence failure after editing has begun, then verifies complete rollback of real SQL writes. */
    #[DataProvider('rollbackCases')]
    public function testPersistenceFailureRespectsTransactionOwnership(string $scope, bool $outer): void
    {
        $this->prepareScope($scope);
        self::$pdo->commit();
        $before = $this->snapshotFixture();
        if ($outer) {
            self::$pdo->beginTransaction();
        }
        $defaultStatement = self::$pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS);
        $target = self::$pdo->prefix . ($scope === 'single' ? 'task_reminders' : 'task_repeat_reminder_templates');
        self::$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RepeatEditFailureStatement::class, [$target]]);
        try {
            $this->edit($scope);
            self::fail('The injected persistence failure must escape the service.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated edit persistence failure.', $exception->getMessage());
        } finally {
            self::$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, $defaultStatement);
        }
        self::assertSame($outer, self::$pdo->inTransaction());
        if ($outer) {
            self::$pdo->rollBack();
        }
        self::assertSame($before, $this->snapshotFixture());
    }

    /** Establishes the paused branch using the same lifecycle operation as the application. */
    private function prepareScope(string $scope): void
    {
        if ($scope === 'paused') {
            $this->service->pauseRule($this->ruleId, $this->userId, $this->now);
        }
    }

    /** Calls only public entrypoints with the same edited values, including a differently zoned form instant. */
    private function edit(string $scope, string $title = '  Edited title  '): array
    {
        $reminders = [['value' => 45, 'unit' => 'minute']];
        return match ($scope) {
            'single' => $this->service->updateSingleOccurrence($this->taskId, $this->userId, $title, $this->dueAt, true, $reminders),
            'future' => $this->service->updateThisAndFuture($this->taskId, $this->userId, $title, $this->dueAt, true, $reminders, $this->config),
            default => $this->service->updateRule(new RepeatRuleUpdateData([
                'repeat_rule_id' => $this->ruleId, 'user_id' => $this->userId, 'title' => $title,
                'due_at' => $this->dueAt, 'has_time' => true, 'reminders' => $reminders,
                'repeat_config' => $this->config, 'now' => $this->now,
            ])),
        };
    }

    /** Captures only fixture rows so failed writes cannot be hidden by checking the final status alone. */
    private function snapshotFixture(): array
    {
        $snapshot = [];
        foreach (IsolatedRepeatTestConnection::TABLES as $table) {
            $snapshot[$table] = self::$pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        }
        return $snapshot;
    }

    /** Formats fixture instants using the application's UTC storage contract. */
    private function utc(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}

final class RepeatEditFailureStatement extends PDOStatement
{
    /** Targets one private fixture table without replacing production repositories or transaction code. */
    protected function __construct(private readonly string $target) {}

    /** Fails only the selected insert, leaving earlier real writes for the service or caller to roll back. */
    public function execute(?array $params = null): bool
    {
        if (str_starts_with(ltrim($this->queryString), 'INSERT INTO') && str_contains($this->queryString, '`' . $this->target . '`')) {
            throw new RuntimeException('Simulated edit persistence failure.');
        }
        return parent::execute($params);
    }
}
