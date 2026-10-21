<?php

declare(strict_types=1);

use App\Exceptions\RepeatRuleNotFoundException;
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

final class RepeatResumeTest extends TestCase
{
    private static PDO $connection;
    private static ?array $applicationSnapshot = null;
    private RepeatService $service;
    private RepeatRuleRepository $rules;
    private TaskRepository $tasks;
    private ReminderRepository $reminders;
    private int $userId;

    /** Uses memory by default; MySQL requires explicit opt-in and the existing isolated-table guard. */
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

        self::$connection = new RepeatResumeSqliteConnection();
        self::$connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password TEXT)');
        self::$connection->exec('CREATE TABLE task_repeat_rules (
            id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, start_at TEXT, has_time INTEGER,
            timezone TEXT, frequency TEXT, interval_value INTEGER, interval_unit TEXT, week_days TEXT,
            month_day INTEGER, month_day_mode TEXT, end_type TEXT, end_date TEXT, repeat_count INTEGER,
            generated_repeats INTEGER DEFAULT 0, next_occurrence_at TEXT, status TEXT DEFAULT "active", created_at TEXT
        )');
        self::$connection->exec('CREATE TABLE tasks (
            id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, due_at TEXT, has_time INTEGER,
            repeat_rule_id INTEGER, repeat_occurrence_number INTEGER, is_done INTEGER DEFAULT 0,
            completed_at TEXT, UNIQUE (repeat_rule_id, repeat_occurrence_number)
        )');
        self::$connection->exec('CREATE TABLE task_repeat_reminder_templates (
            id INTEGER PRIMARY KEY, repeat_rule_id INTEGER, offset_value INTEGER, offset_unit TEXT
        )');
        self::$connection->exec('CREATE TABLE task_reminders (
            id INTEGER PRIMARY KEY, task_id INTEGER REFERENCES tasks(id) ON DELETE CASCADE,
            offset_value INTEGER, offset_unit TEXT, remind_at TEXT
        )');
    }

    /** Removes only this run's isolated tables and verifies the real application tables stayed unchanged. */
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$connection) && self::$connection instanceof IsolatedRepeatTestConnection) {
            if (self::$connection->inTransaction()) {
                self::$connection->rollBack();
            }
            self::$connection->dropFixtureTables();
            self::assertSame(self::$applicationSnapshot, self::$connection->snapshotApplicationTables(),
                'Original application row counts, checksums and schema hashes must remain unchanged.');
        }
    }

    /** Starts a rollback-only fixture and wires the real scheduling, repository and reminder implementations. */
    protected function setUp(): void
    {
        self::$connection->beginTransaction();
        $statement = self::$connection->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
        $suffix = bin2hex(random_bytes(8));
        $statement->execute(['resume_' . $suffix, $suffix . '@example.test', 'not-a-login']);
        $this->userId = (int) self::$connection->lastInsertId();
        $this->rules = new RepeatRuleRepository(self::$connection);
        $this->tasks = new TaskRepository(self::$connection);
        $this->reminders = new ReminderRepository(self::$connection);
        $calculator = new RepeatScheduleCalculator();
        $this->service = new RepeatService($this->rules, new RepeatRuleValidator(), $calculator,
            new RepeatOccurrencePlanner($calculator), $this->tasks, new ReminderService($this->reminders));
    }

    /** Rolls back each scenario, including failed assertions and service operations. */
    protected function tearDown(): void
    {
        if (self::$connection->inTransaction()) {
            self::$connection->rollBack();
        }
    }

    /** Covers the initial and later equality boundaries without changing the original schedule anchor. */
    public static function resumeCases(): iterable
    {
        foreach (['daily', 'weekly', 'monthly'] as $frequency) {
            foreach (['before', 'at', 'after', 'at-repeat'] as $timing) {
                foreach (['endlessly', 'count', 'date'] as $end) {
                    yield "$frequency/$timing/$end" => [$frequency, $timing, $end];
                }
            }
        }
    }

    /** Verifies real generated dates, occurrence-zero accounting, reminders and repeated lifecycle calls. */
    #[DataProvider('resumeCases')]
    public function testResumeKeepsFirstEligibleOccurrence(string $frequency, string $timing, string $end): void
    {
        $start = new DateTimeImmutable('2026-11-02 09:30:00', new DateTimeZone('Asia/Tehran'));
        $dates = $this->scheduleDates($frequency, $start);
        $now = match ($timing) {
            'before' => $start->modify('-1 hour'),
            'at' => $start,
            'after' => $start->modify('+1 microsecond'),
            'at-repeat' => $dates[1],
        };
        $ruleId = $this->createSeries($frequency, $start, $end);
        $originalId = $this->tasks->create($this->userId, 'Original', $this->utc($start), true, $ruleId, 0);
        $this->reminders->create($originalId, 30, 'minute', $this->utc($start->modify('-30 minutes')));
        $pauseAt = $start->modify('-2 hours');
        $this->service->generateInitialWindow($ruleId, $start->modify('+60 days'));
        $this->service->pauseRule($ruleId, $this->userId, $pauseAt);
        self::assertSame([], $this->rows($ruleId));
        self::assertSame([], $this->reminders->getByTaskId($originalId));

        $expectedDates = array_values(array_filter($dates, static function (DateTimeImmutable $date) use ($now, $start, $end, $dates): bool {
            return $date >= $now && $date <= $now->modify('+30 days')
                && ($end !== 'count' || $date <= $dates[2])
                && ($end !== 'date' || $date->format('Y-m-d') <= $start->modify('+8 days')->format('Y-m-d'));
        }));
        $result = $this->service->resumeRule($ruleId, $this->userId, $now);
        $rows = $this->rows($ruleId);
        self::assertSame(array_map($this->utc(...), $expectedDates), array_column($rows, 'due_at'));
        self::assertSame(count($rows), $result['generated_count']);
        $includesOriginal = $expectedDates !== [] && $expectedDates[0] == $start;
        $expectedNumbers = $rows === [] ? [] : range($includesOriginal ? 0 : 1, count($rows) - ($includesOriginal ? 1 : 0));
        self::assertSame($expectedNumbers, array_map('intval', array_column($rows, 'repeat_occurrence_number')));
        $storedRule = $this->rules->findByIdForUserForUpdate($ruleId, $this->userId);
        self::assertSame(count($rows) - ($includesOriginal ? 1 : 0), (int) $storedRule->generated_repeats);
        self::assertSame('active', $storedRule->status);
        foreach ($rows as $index => $row) {
            $reminderAt = $expectedDates[$index]->modify('-30 minutes');
            $reminders = $this->reminders->getByTaskId((int) $row['id']);
            self::assertCount($reminderAt > $now ? 1 : 0, $reminders);
            if ($reminders !== []) {
                self::assertSame($this->utc($reminderAt), $reminders[0]->remind_at);
            }
        }
        self::assertSame(0, $this->service->resumeRule($ruleId, $this->userId, $now)['generated_count']);
        $this->service->generateUntil($now->modify('+30 days'));
        self::assertSame($rows, $this->rows($ruleId));
        self::assertTrue(self::$connection->inTransaction(), 'The caller retains ownership of its transaction.');
    }

    /** Keeps retained completed identities, including an original task edited away from its anchor date. */
    public function testResumePreservesMovedOriginalAndCompletedRepeat(): void
    {
        $start = new DateTimeImmutable('2026-11-02 09:30:00', new DateTimeZone('Asia/Tehran'));
        $ruleId = $this->createSeries('weekly', $start, 'count');
        $originalId = $this->tasks->create($this->userId, 'Edited original', $this->utc($start->modify('+1 day')), true, $ruleId, 0);
        $repeatId = $this->tasks->create($this->userId, 'Completed repeat', $this->utc($start->modify('+7 days')), true, $ruleId, 8);
        foreach ([$originalId, $repeatId] as $id) {
            $this->tasks->updateStatus($id, $this->userId, true, $this->utc($start->modify('-3 days')));
        }
        self::assertTrue($this->tasks->hasInitialOccurrenceForRule($ruleId, $this->userId));
        self::assertFalse($this->tasks->hasInitialOccurrenceForRule($ruleId, $this->userId + 100));
        $before = $this->rows($ruleId);
        $now = $start->modify('-1 hour');
        $this->service->pauseRule($ruleId, $this->userId, $now);
        $result = $this->service->resumeRule($ruleId, $this->userId, $now);
        self::assertSame(1, $result['generated_count']);
        $rows = $this->rows($ruleId);
        self::assertSame($before, array_slice($rows, 0, 2));
        self::assertSame('2026-11-16 06:00:00', $rows[2]['due_at']);
        self::assertSame(9, (int) $rows[2]['repeat_occurrence_number']);
        self::assertSame(2, (int) $this->rules->findByIdForUserForUpdate($ruleId, $this->userId)->generated_repeats);
        self::assertNull($result['next_occurrence_at']);
    }

    /** Keeps a distant original cursor and restores it later without using a repeat slot or exceeding batch limits. */
    public function testDeferredOriginalIsRestoredByBoundedGeneration(): void
    {
        $start = new DateTimeImmutable('2026-11-02 09:30:00', new DateTimeZone('Asia/Tehran'));
        $now = $start->modify('-60 days');
        $ruleId = $this->createSeries('weekly', $start, 'count');
        $this->tasks->create($this->userId, 'Original', $this->utc($start), true, $ruleId, 0);
        $this->service->pauseRule($ruleId, $this->userId, $now);
        self::assertSame(['status' => 'active', 'generated_count' => 0, 'next_occurrence_at' => $this->utc($start)],
            $this->service->resumeRule($ruleId, $this->userId, $now));
        self::assertSame([], $this->rows($ruleId));

        $horizon = $start->modify('+30 days');
        foreach ([0, 1, 2] as $number) {
            self::assertSame(1, $this->service->generateUntil($horizon, 100, 1));
            $rows = $this->rows($ruleId);
            self::assertSame(range(0, $number), array_map('intval', array_column($rows, 'repeat_occurrence_number')));
            self::assertSame($number, (int) $this->rules->findByIdForUserForUpdate($ruleId, $this->userId)->generated_repeats);
        }
        self::assertSame(0, $this->service->generateUntil($horizon, 100, 1));
        self::assertNull($this->rules->findByIdForUserForUpdate($ruleId, $this->userId)->next_occurrence_at);
    }

    /** Rejects a foreign owner without changing the series or committing the caller's transaction. */
    public function testResumeRejectsAnotherOwner(): void
    {
        $now = new DateTimeImmutable('2026-11-02 08:30:00', new DateTimeZone('Asia/Tehran'));
        $ruleId = $this->createSeries('weekly', $now->modify('+1 hour'), 'count');
        $this->service->pauseRule($ruleId, $this->userId, $now);
        try {
            $this->service->resumeRule($ruleId, $this->userId + 100, $now);
            self::fail('A foreign user must not resume the rule.');
        } catch (RepeatRuleNotFoundException) {
            self::assertSame('paused', $this->rules->findByIdForUserForUpdate($ruleId, $this->userId)->status);
            self::assertTrue(self::$connection->inTransaction());
        }
    }

    /** Builds one bounded or endless series using the real input validator and template persistence. */
    private function createSeries(string $frequency, DateTimeImmutable $start, string $end): int
    {
        $payload = [
            'frequency' => $frequency, 'interval' => 1,
            'unit' => match ($frequency) { 'daily' => 'day', 'weekly' => 'week', 'monthly' => 'month' },
            'week_days' => $frequency === 'weekly' ? [1] : [],
            'month_day' => $frequency === 'monthly' ? 2 : null, 'month_day_mode' => 'clamp',
            'ends' => ['type' => $end, 'count' => $end === 'count' ? 2 : null,
                'date' => $end === 'date' ? $start->modify('+8 days')->format('Y-m-d') : ''],
        ];
        return $this->service->createRule($this->userId, 'Resume fixture', $start, true,
            $this->service->prepareRule($payload, $start), [['offset_value' => 30, 'offset_unit' => 'minute']]);
    }

    /** Supplies independently expected dates rather than using the calculator under test as an oracle. */
    private function scheduleDates(string $frequency, DateTimeImmutable $start): array
    {
        $offsets = match ($frequency) {
            'daily' => range(0, 90),
            'weekly' => range(0, 91, 7),
            'monthly' => [0, 30, 61, 92],
        };
        return array_map(static fn(int $offset): DateTimeImmutable => $start->modify("+$offset days"), $offsets);
    }

    /** Reads stable task fields to detect duplicate dates, identities or modifications to retained tasks. */
    private function rows(int $ruleId): array
    {
        $statement = self::$connection->prepare('SELECT id, title, due_at, repeat_occurrence_number, is_done, completed_at
            FROM tasks WHERE repeat_rule_id = :rule_id AND user_id = :user_id ORDER BY due_at, id');
        $statement->execute([':rule_id' => $ruleId, ':user_id' => $this->userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Formats fixture instants in the same UTC storage format as production. */
    private function utc(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}

final class RepeatResumeSqliteConnection extends PDO
{
    /** Creates a private memory database with cascading reminder deletion. */
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->exec('PRAGMA foreign_keys = ON');
    }

    /** Adapts only row-lock syntax; real MySQL execution is a separate explicitly enabled run. */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace('FOR UPDATE', '', $query), $options);
    }
}
