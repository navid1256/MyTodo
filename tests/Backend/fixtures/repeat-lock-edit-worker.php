<?php

declare(strict_types=1);

use App\Exceptions\RepeatRuleNotFoundException;
use App\Exceptions\RepeatRuleStateException;
use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Services\ReminderService;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleUpdateData;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatScheduleCalculator;
use App\Services\RepeatService;

require_once __DIR__ . '/isolated-repeat-test-connection.php';

final class RepeatRuleLockSignalStatement extends PDOStatement
{
    private static bool $signalled = false;

    /** Shares the coordinator socket and random namespace with every prepared statement in this worker. */
    protected function __construct(private readonly mixed $events, private readonly string $fixturePrefix) {}

    /** Signals before the first real rule-lock request; any earlier task lock has already executed. */
    public function execute(?array $params = null): bool
    {
        if (!self::$signalled && str_contains($this->queryString, 'task_repeat_rules')
            && str_contains($this->queryString, 'FOR UPDATE')) {
            self::$signalled = true;
            fwrite($this->events, json_encode(['event' => 'rule-lock', 'fixture' => $this->fixturePrefix]) . "\n");
        }
        return parent::execute($params);
    }
}

if (getenv('MYTODO_REPEAT_TEST_DATABASE') !== 'mytodo' || $argc !== 8
    || !in_array($argv[2], ['single', 'future', 'rule'], true)
    || filter_var($argv[6], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
    fwrite(STDERR, "Explicit isolated mytodo authorization and valid worker arguments are required.\n");
    exit(2);
}

try {
    $events = stream_socket_client('tcp://127.0.0.1:' . $argv[6], $errorCode, $errorMessage, 5);
    if ($events === false) {
        throw new RuntimeException('Could not connect to the local test coordinator.');
    }
    $pdo = new IsolatedRepeatTestConnection($argv[1]);
    $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RepeatRuleLockSignalStatement::class, [$events, $argv[1]]]);
    $calculator = new RepeatScheduleCalculator();
    $service = new RepeatService(new RepeatRuleRepository($pdo), new RepeatRuleValidator(), $calculator,
        new RepeatOccurrencePlanner($calculator), new TaskRepository($pdo), new ReminderService(new ReminderRepository($pdo)));
    $dueAt = new DateTimeImmutable($argv[7]);
    $rule = ['frequency' => 'daily', 'unit' => 'day', 'interval' => 1, 'week_days' => [],
        'month_day' => null, 'month_day_mode' => 'clamp', 'ends' => ['type' => 'count', 'count' => 2, 'date' => '']];
    $reminders = [['value' => 30, 'unit' => 'minute']];
    $result = match ($argv[2]) {
        'single' => $service->updateSingleOccurrence((int) $argv[3], (int) $argv[5], 'Concurrent edit', $dueAt, true, $reminders),
        'future' => $service->updateThisAndFuture((int) $argv[3], (int) $argv[5], 'Concurrent edit', $dueAt, true, $reminders, $rule),
        'rule' => $service->updateRule(new RepeatRuleUpdateData([
            'repeat_rule_id' => (int) $argv[4], 'user_id' => (int) $argv[5], 'title' => 'Concurrent edit',
            'due_at' => $dueAt, 'has_time' => true, 'reminders' => $reminders, 'repeat_config' => $rule,
        ])),
    };
    fwrite($events, json_encode(['event' => 'result', 'fixture' => $argv[1], 'status' => 'saved', 'scope' => $result['scope']]) . "\n");
} catch (RepeatRuleNotFoundException | RepeatRuleStateException $exception) {
    fwrite($events, json_encode(['event' => 'result', 'fixture' => $argv[1], 'status' => 'rejected', 'exception' => $exception::class]) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Worker failed: ' . $exception::class . ' (' . $exception->getCode() . ").\n");
    exit(1);
}
