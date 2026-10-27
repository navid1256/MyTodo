<?php

declare(strict_types=1);

use App\Exceptions\RepeatValidationException;
use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Services\ReminderService;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleMapper;
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

    /** Covers schedule shapes, nullable endings and timed/untimed persistence without new fixture infrastructure. */
    public static function persistenceCases(): iterable
    {
        yield 'daily endlessly without a time' => [['frequency' => 'daily', 'ends' => ['type' => 'endlessly']], false];
        yield 'weekly count' => [['frequency' => 'weekly', 'week_days' => [5, 1], 'ends' => ['type' => 'count', 'count' => 3]], true];
        yield 'monthly clamp with an end date' => [['frequency' => 'monthly', 'month_day' => 31, 'month_day_mode' => 'clamp', 'ends' => ['type' => 'date']], true];
        yield 'monthly last day' => [['frequency' => 'monthly', 'month_day_mode' => 'last_day', 'ends' => ['type' => 'endlessly']], false];
        yield 'custom days' => [['frequency' => 'custom', 'unit' => 'day', 'interval' => 2, 'ends' => ['type' => 'count', 'count' => 2]], true];
        yield 'custom weeks with an end date' => [['frequency' => 'custom', 'unit' => 'week', 'interval' => 3, 'week_days' => [6, 0], 'ends' => ['type' => 'date']], true];
        yield 'custom months on the last day' => [['frequency' => 'custom', 'unit' => 'month', 'interval' => 2, 'month_day_mode' => 'last_day', 'ends' => ['type' => 'count', 'count' => 4]], true];
    }

    /** Checks real Create and paused Edit persistence against the same normalized schedule contract. */
    #[DataProvider('persistenceCases')]
    public function testCreateAndPausedEditPersistEquivalentScheduleFields(array $payload, bool $hasTime): void
    {
        $start = $this->dueAt->setTimezone(new DateTimeZone('Asia/Tehran'))->setTime(0, 15);
        if ($payload['ends']['type'] === 'date') {
            $payload['ends']['date'] = $start->modify('+3 months')->format('Y-m-d');
        }
        $prepared = $this->service->prepareRule($payload, $start);
        $templates = $hasTime ? [['offset_value' => 30, 'offset_unit' => 'minute']] : [];
        $createdId = $this->service->createRule($this->userId, '  Mapped title  ', $start, $hasTime, $prepared, $templates);
        $created = $this->rules->findByIdForUserForUpdate($createdId, $this->userId);
        $expectedCursor = (new RepeatScheduleCalculator())->nextOccurrence($prepared, $start, $start);
        self::assertSame($this->utc($expectedCursor), $created->next_occurrence_at);
        self::assertSame('active', $created->status);

        $this->service->pauseRule($this->ruleId, $this->userId, $this->now);
        $result = $this->service->updateRule(new RepeatRuleUpdateData([
            'repeat_rule_id' => $this->ruleId, 'user_id' => $this->userId, 'title' => '  Mapped title  ',
            'due_at' => $start->setTimezone(new DateTimeZone('UTC')), 'has_time' => $hasTime,
            'repeat_config' => $payload, 'reminders' => $hasTime ? [['value' => 30, 'unit' => 'minute']] : [],
            'now' => $this->now,
        ]));
        $edited = $this->rules->findByIdForUserForUpdate($this->ruleId, $this->userId);
        self::assertSame($this->ruleId, $result['repeat_rule_id']);
        self::assertSame('paused', $edited->status);
        self::assertNull($edited->next_occurrence_at);
        $commonFields = array_fill_keys(['title', 'start_at', 'has_time', 'timezone', 'frequency', 'interval_value',
            'interval_unit', 'week_days', 'month_day', 'month_day_mode', 'end_type', 'end_date', 'repeat_count'], true);
        self::assertSame(array_intersect_key((array) $created, $commonFields), array_intersect_key((array) $edited, $commonFields));
        $mapper = new RepeatRuleMapper();
        foreach ([$created, $edited] as $stored) {
            self::assertSame('Mapped title', $stored->title);
            self::assertSame($this->utc($start), $stored->start_at);
            self::assertSame('Asia/Tehran', $stored->timezone);
            self::assertSame($hasTime ? 1 : 0, (int) $stored->has_time);
            self::assertSame($this->userId, (int) $stored->user_id);
            self::assertSame($prepared, $mapper->toRuleArray($stored));
            self::assertSame($prepared['week_days'] === [] ? null : json_encode($prepared['week_days']), $stored->week_days);
            self::assertSame($hasTime ? [['value' => 30, 'unit' => 'minute']] : [],
                $mapper->normalizeReminderTemplates($this->rules->getReminderTemplates((int) $stored->id)));
        }
        self::assertTrue(self::$pdo->inTransaction());
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
            self::assertSame(RepeatEditFailureStatement::FAILURE_MESSAGE, $exception->getMessage());
        } finally {
            self::$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, $defaultStatement);
        }
        self::assertSame($outer, self::$pdo->inTransaction());
        if ($outer) {
            self::$pdo->rollBack();
        }
        self::assertSame($before, $this->snapshotFixture());
    }

    /** Covers both ownership modes of the independent generation transaction wrapper. */
    public static function generationOwnershipCases(): iterable
    {
        yield 'service transaction' => [false];
        yield 'caller transaction' => [true];
    }

    /** Verifies that successful generation commits only its own transaction and remains rollbackable by a caller. */
    #[DataProvider('generationOwnershipCases')]
    public function testIndependentGenerationPreservesTransactionOwnership(bool $outer): void
    {
        $before = $this->preparePendingGeneration();
        if ($outer) {
            self::$pdo->beginTransaction();
        }

        self::assertSame(2, $this->service->generateUntil($this->dueAt->modify('+30 days')));
        self::assertSame($outer, self::$pdo->inTransaction());
        $after = $this->snapshotFixture();
        self::assertCount(3, $after['tasks']);
        self::assertCount(3, $after['task_reminders']);
        self::assertSame(2, (int) $after['task_repeat_rules'][0]['generated_repeats']);
        self::assertNull($after['task_repeat_rules'][0]['next_occurrence_at']);

        if ($outer) {
            self::$pdo->rollBack();
            self::assertSame($before, $this->snapshotFixture());
        } else {
            self::$pdo->beginTransaction();
            self::assertSame($after, $this->snapshotFixture());
        }
    }

    /** Fails after a generated task insert and checks service rollback versus the caller's retained partial writes. */
    #[DataProvider('generationOwnershipCases')]
    public function testIndependentGenerationFailureRollsBackOnlyItsOwnTransaction(bool $outer): void
    {
        $before = $this->preparePendingGeneration();
        if ($outer) {
            self::$pdo->beginTransaction();
        }
        $defaultStatement = self::$pdo->getAttribute(PDO::ATTR_STATEMENT_CLASS);
        self::$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RepeatEditFailureStatement::class, [self::$pdo->prefix . 'task_reminders']]);
        try {
            $this->service->generateUntil($this->dueAt->modify('+30 days'));
            self::fail('The generation persistence failure must escape the service.');
        } catch (RuntimeException $exception) {
            self::assertSame(RepeatEditFailureStatement::FAILURE_MESSAGE, $exception->getMessage());
        } finally {
            self::$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, $defaultStatement);
        }
        self::assertSame($outer, self::$pdo->inTransaction());
        if ($outer) {
            self::assertNotSame($before, $this->snapshotFixture());
            self::$pdo->rollBack();
        }
        self::assertSame($before, $this->snapshotFixture());
    }

    /** Restores the two-day fixture's first repeat and commits its baseline only in private fixture tables. */
    private function preparePendingGeneration(): array
    {
        $original = $this->tasks->findById($this->taskId, $this->userId);
        self::assertNotNull($original);
        $nextOccurrence = (new DateTimeImmutable((string) $original->due_at, new DateTimeZone('UTC')))->modify('+2 days');
        $this->tasks->deleteFutureIncompleteForRule($this->ruleId, $this->userId, $this->utc($nextOccurrence));
        $this->rules->updateGenerationState($this->ruleId, $this->userId, 0, $this->utc($nextOccurrence), 'active');
        self::$pdo->commit();

        return $this->snapshotFixture();
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
    public const FAILURE_MESSAGE = 'Simulated edit persistence failure.';

    /** Targets one private fixture table without replacing production repositories or transaction code. */
    protected function __construct(private readonly string $target) {}

    /** Fails only the selected insert, leaving earlier real writes for the service or caller to roll back. */
    public function execute(?array $params = null): bool
    {
        if (str_starts_with(ltrim($this->queryString), 'INSERT INTO') && str_contains($this->queryString, '`' . $this->target . '`')) {
            throw new RuntimeException(self::FAILURE_MESSAGE);
        }
        return parent::execute($params);
    }
}
