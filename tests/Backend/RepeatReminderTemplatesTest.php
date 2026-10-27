<?php

declare(strict_types=1);

use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Services\ReminderService;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleMapper;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatScheduleCalculator;
use App\Services\RepeatService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepeatReminderTemplatesTest extends TestCase
{
    private static PDO $connection;
    private static ?array $applicationSnapshot = null;
    private RepeatRuleRepository $rules;
    private RepeatService $service;
    private stdClass $counter;
    private int $userId;
    private int $foreignUserId;

    /** Uses SQLite by default and the existing isolated schema guard for explicitly authorized MySQL runs. */
    public static function setUpBeforeClass(): void
    {
        $database = getenv('MYTODO_REPEAT_TEST_DATABASE');
        if ($database !== false && $database !== '') {
            if ($database !== 'mytodo') {
                throw new InvalidArgumentException('Only mytodo is authorized for isolated Repeat tests.');
            }
            require_once __DIR__ . '/fixtures/isolated-repeat-test-connection.php';
            self::$connection = new IsolatedRepeatTestConnection();
            self::$applicationSnapshot = self::$connection->snapshotApplicationTables();
            try {
                self::$connection->createFixtureTables();
            } catch (Throwable $exception) {
                self::$connection->dropFixtureTables();
                throw $exception;
            }
            return;
        }

        self::$connection = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::$connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password TEXT)');
        self::$connection->exec("CREATE TABLE task_repeat_rules (
            id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, start_at TEXT, has_time INTEGER,
            timezone TEXT, frequency TEXT, interval_value INTEGER, interval_unit TEXT, week_days TEXT,
            month_day INTEGER, month_day_mode TEXT, end_type TEXT, end_date TEXT, repeat_count INTEGER,
            next_occurrence_at TEXT, status TEXT DEFAULT 'active', created_at TEXT DEFAULT '2099-01-01 00:00:00'
        )");
        self::$connection->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, repeat_rule_id INTEGER, is_done INTEGER, due_at TEXT)');
        self::$connection->exec('CREATE TABLE task_repeat_reminder_templates (id INTEGER PRIMARY KEY, repeat_rule_id INTEGER, offset_value INTEGER, offset_unit TEXT)');
    }

    /** Removes only owned fixture tables and verifies that application data and schema stayed unchanged. */
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$connection) && self::$connection instanceof IsolatedRepeatTestConnection) {
            self::$connection->dropFixtureTables();
            self::assertSame(self::$applicationSnapshot, self::$connection->snapshotApplicationTables());
        }
    }

    /** Seeds two isolated owners and instruments real SELECT executions, without replacing repository behavior. */
    protected function setUp(): void
    {
        self::$connection->beginTransaction();
        $users = [];
        foreach (['owner', 'foreign'] as $name) {
            $suffix = bin2hex(random_bytes(8));
            $statement = self::$connection->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
            $statement->execute([$name . '_' . $suffix, $suffix . '@example.test', 'not-a-login']);
            $users[] = (int) self::$connection->lastInsertId();
        }
        [$this->userId, $this->foreignUserId] = $users;
        $this->counter = (object) ['selects' => 0];
        self::$connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RepeatTemplateCountingStatement::class, [$this->counter]]);
        $this->rules = new RepeatRuleRepository(self::$connection);
        $calculator = new RepeatScheduleCalculator();
        $this->service = new RepeatService($this->rules, new RepeatRuleValidator(), $calculator,
            new RepeatOccurrencePlanner($calculator), new TaskRepository(self::$connection),
            new ReminderService(new ReminderRepository(self::$connection)));
    }

    /** Rolls back each fixture and restores the connection's ordinary statement class. */
    protected function tearDown(): void
    {
        if (self::$connection->inTransaction()) {
            self::$connection->rollBack();
        }
        self::$connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
    }

    /** Compares empty, single and larger result sets to expose per-rule SELECT growth. */
    public static function listSizes(): iterable
    {
        foreach ([0, 1, 25] as $size) {
            yield (string) $size => [$size];
        }
    }

    /** Verifies bounded query count, ownership, rule ordering and the unchanged reminder payload. */
    #[DataProvider('listSizes')]
    public function testListQueryCountDoesNotGrowWithRuleCount(int $size): void
    {
        $expected = [];
        for ($index = 0; $index < $size; $index++) {
            $id = $this->createRule($this->userId, $index % 2 === 0);
            $expected[$id] = $index % 2 === 0 ? [['value' => 1, 'unit' => 'hour'], ['value' => 15, 'unit' => 'minute']] : [];
        }
        $this->createRule($this->foreignUserId, true);
        $this->counter->selects = 0;

        $rules = $this->service->getRulesForUser($this->userId, 'all', new DateTimeImmutable('2099-01-01', new DateTimeZone('UTC')));

        self::assertSame($size === 0 ? 1 : 2, $this->counter->selects);
        self::assertSame(array_reverse(array_keys($expected)), array_map(static fn(object $rule): int => (int) $rule->id, $rules));
        foreach ($rules as $rule) {
            self::assertSame($this->userId, (int) $rule->user_id);
            self::assertSame($expected[(int) $rule->id], $rule->reminders);
        }
    }

    /** Rejects foreign and unrequested rules even when their identifiers are explicitly passed to the batch method. */
    public function testBatchReturnsOnlyRequestedOwnedTemplatesInOriginalOrder(): void
    {
        $requested = $this->createRule($this->userId, true);
        $this->createRule($this->userId, true);
        $foreign = $this->createRule($this->foreignUserId, true);
        $this->counter->selects = 0;

        $grouped = $this->rules->getReminderTemplatesForRules($this->userId, [$requested, $foreign, $requested]);

        self::assertSame(1, $this->counter->selects);
        self::assertSame([$requested], array_keys($grouped));
        $mapper = new RepeatRuleMapper();
        self::assertSame([['value' => 1, 'unit' => 'hour'], ['value' => 15, 'unit' => 'minute']],
            $mapper->normalizeReminderTemplates($grouped[$requested]));
        self::assertSame($mapper->normalizeReminderTemplates($this->rules->getReminderTemplates($requested)),
            $mapper->normalizeReminderTemplates($grouped[$requested]));
    }

    /** Avoids invalid empty IN clauses and unnecessary queries for an empty identifier list. */
    public function testEmptyBatchDoesNotQuery(): void
    {
        self::assertSame([], $this->rules->getReminderTemplatesForRules($this->userId, []));
        self::assertSame(0, $this->counter->selects);
    }

    /** Keeps status filtering intact while loading reminders only for the selected result set. */
    public function testFilteredListKeepsItsReminderPayload(): void
    {
        $paused = $this->createRule($this->userId, true);
        $this->createRule($this->userId, false);
        $this->rules->updateStatus($paused, $this->userId, 'paused', null);
        $this->counter->selects = 0;

        $rules = $this->service->getRulesForUser($this->userId, 'paused', new DateTimeImmutable('2099-01-01', new DateTimeZone('UTC')));

        self::assertSame(2, $this->counter->selects);
        self::assertCount(1, $rules);
        self::assertSame($paused, (int) $rules[0]->id);
        self::assertSame('paused', $rules[0]->status);
        self::assertSame([['value' => 1, 'unit' => 'hour'], ['value' => 15, 'unit' => 'minute']], $rules[0]->reminders);
    }

    /** Inserts valid persistence data and deliberately orders templates differently from their offset values. */
    private function createRule(int $userId, bool $withReminders): int
    {
        $id = $this->rules->create([
            'user_id' => $userId, 'title' => 'Template fixture', 'start_at' => '2099-01-01 09:00:00',
            'has_time' => true, 'timezone' => 'UTC', 'frequency' => 'daily', 'interval' => 1, 'unit' => 'day',
            'week_days' => [], 'month_day' => null, 'month_day_mode' => 'clamp',
            'end_type' => 'endlessly', 'end_date' => null, 'repeat_count' => null, 'next_occurrence_at' => null,
        ]);
        if ($withReminders) {
            $this->rules->saveReminderTemplates($id, [
                ['offset_value' => 1, 'offset_unit' => 'hour'], ['offset_value' => 15, 'offset_unit' => 'minute'],
            ]);
        }
        return $id;
    }
}

final class RepeatTemplateCountingStatement extends PDOStatement
{
    /** Shares a counter only within this test's connection; no SQL parameters or row contents are logged. */
    protected function __construct(private readonly stdClass $counter) {}

    /** Counts actual SELECT executions rather than source-code text or mocked repository calls. */
    public function execute(?array $params = null): bool
    {
        if (preg_match('/\A\s*SELECT\b/i', $this->queryString) === 1) {
            $this->counter->selects++;
        }
        return parent::execute($params);
    }
}
