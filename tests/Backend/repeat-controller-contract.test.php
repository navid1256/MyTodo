<?php

declare(strict_types=1);

use App\Controllers\RepeatController;
use App\Http\Request;
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
use App\Services\UserSettingsService;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

function assertRepeatControllerContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertRepeatControllerSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertRepeatControllerResponse(int $status, array $body, object $response, string $message): void
{
    assertRepeatControllerSame($status, $response->getStatusCode(), $message . ' status mismatch.');
    assertRepeatControllerSame(
        $body,
        json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        $message . ' body mismatch.'
    );
}

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/App/Controllers/RepeatController.php';
if (!is_file($controllerPath)) {
    throw new RuntimeException('RepeatController.php must exist.');
}

$controllerSource = (string) file_get_contents($controllerPath);
$routesSource = (string) file_get_contents($root . '/routes/api.php');
$applicationSource = (string) file_get_contents($root . '/App/Application.php');

$routes = [
    "/api/repeat-rules/pause', [RepeatController::class, 'pause'], [AuthMiddleware::class]",
    "/api/repeat-rules/resume', [RepeatController::class, 'resume'], [AuthMiddleware::class]",
    "/api/repeat-rules/cancel', [RepeatController::class, 'cancel'], [AuthMiddleware::class]",
];
foreach ($routes as $route) {
    assertRepeatControllerContains($route, $routesSource, 'Each repeat lifecycle route must use its controller action and AuthMiddleware.');
}

foreach (['pause', 'resume', 'cancel'] as $method) {
    assertRepeatControllerContains('public function ' . $method . '(Request $request): Response', $controllerSource, 'Missing repeat lifecycle controller action: ' . $method);
}

assertRepeatControllerContains('CsrfMiddleware::isValid(', $controllerSource, 'Repeat lifecycle requests must validate CSRF tokens.');
assertRepeatControllerContains('readPositiveId($request, \'repeat_rule_id\')', $controllerSource, 'Repeat lifecycle requests must parse a positive rule ID.');
assertRepeatControllerContains("new DateTimeImmutable('now', TimezoneHelper::getApplicationTimezone())", $controllerSource, 'Repeat lifecycle operations must use the application UTC clock.');
assertRepeatControllerContains('catch (RepeatRuleNotFoundException $exception)', $controllerSource, 'Repeat-rule not-found errors must be mapped.');
assertRepeatControllerContains('catch (RepeatValidationException | RepeatRuleStateException $exception)', $controllerSource, 'Repeat validation and state errors must be mapped.');
assertRepeatControllerContains("'The repeat rule could not be updated.'", $controllerSource, 'Unexpected errors must use a generic message.');
assertRepeatControllerContains('$this->router->bind(RepeatController::class, new RepeatController(', $applicationSource, 'Application must bind RepeatController.');
assertRepeatControllerContains('$repeatService,', $applicationSource, 'RepeatController must receive the shared RepeatService.');

final class RepeatControllerTestPdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace('FOR UPDATE', '', $query), $options);
    }
}

/** @return array{RepeatControllerTestPdo, RepeatController} */
function repeatControllerFixture(): array
{
    $pdo = new RepeatControllerTestPdo();
    $pdo->exec('CREATE TABLE task_repeat_rules (
        id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, start_at TEXT, has_time INTEGER,
        timezone TEXT, frequency TEXT, interval_value INTEGER, interval_unit TEXT, week_days TEXT,
        month_day INTEGER, month_day_mode TEXT, end_type TEXT, end_date TEXT, repeat_count INTEGER,
        generated_repeats INTEGER, next_occurrence_at TEXT, status TEXT, created_at TEXT
    )');
    $pdo->exec('CREATE TABLE tasks (
        id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, due_at TEXT, has_time INTEGER,
        repeat_rule_id INTEGER, repeat_occurrence_number INTEGER, is_done INTEGER DEFAULT 0,
        completed_at TEXT
    )');
    $pdo->exec("INSERT INTO task_repeat_rules (
        id, user_id, title, start_at, has_time, timezone, frequency, interval_value,
        interval_unit, week_days, month_day_mode, end_type, generated_repeats,
        next_occurrence_at, status, created_at
    ) VALUES
        (1, 10, 'Pause', '2026-09-07 06:00:00', 1, 'UTC', 'daily', 1, 'day', '[]', 'clamp', 'endlessly', 0, '2026-09-14 06:00:00', 'active', '2026-09-01 00:00:00'),
        (2, 10, 'Resume', '2026-09-07 06:00:00', 1, 'UTC', 'daily', 1, 'day', '[]', 'clamp', 'endlessly', 0, '2026-09-14 06:00:00', 'active', '2026-09-01 00:00:00'),
        (3, 10, 'Cancel', '2026-09-07 06:00:00', 1, 'UTC', 'daily', 1, 'day', '[]', 'clamp', 'endlessly', 0, '2026-09-14 06:00:00', 'active', '2026-09-01 00:00:00'),
        (4, 10, 'Completed', '2026-09-07 06:00:00', 1, 'UTC', 'daily', 1, 'day', '[]', 'clamp', 'endlessly', 0, NULL, 'completed', '2026-09-01 00:00:00'),
        (5, 10, 'Database failure', '2026-09-07 06:00:00', 1, 'UTC', 'daily', 1, 'day', '[]', 'clamp', 'endlessly', 0, '2026-09-14 06:00:00', 'active', '2026-09-01 00:00:00')");

    $ruleRepository = new RepeatRuleRepository($pdo);
    $taskRepository = new TaskRepository($pdo);
    $reminderRepository = new ReminderRepository($pdo);
    $calculator = new RepeatScheduleCalculator();
    $reminderService = new ReminderService($reminderRepository);
    $repeatService = new RepeatService(
        $ruleRepository,
        new RepeatRuleValidator(),
        $calculator,
        new RepeatOccurrencePlanner($calculator),
        $taskRepository,
        $reminderService
    );
    $authService = new AuthService(new UserRepository($pdo));
    $notificationService = new NotificationService(new NotificationRepository($pdo), $reminderService);
    $settingsService = new UserSettingsService(new UserSettingsRepository($pdo));

    return [$pdo, new RepeatController(
        $repeatService,
        $authService,
        new UserRepository($pdo),
        $notificationService,
        $settingsService
    )];
}

[$pdo, $controller] = repeatControllerFixture();

$_SESSION = [];
assertRepeatControllerResponse(
    401,
    ['success' => false, 'message' => 'Authentication required.'],
    $controller->pause(new Request(post: ['repeat_rule_id' => '1'])),
    'Unauthenticated lifecycle request'
);

$_SESSION = ['user' => ['id' => 10], 'csrf_token' => 'controller-contract-token'];
assertRepeatControllerResponse(
    403,
    ['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.'],
    $controller->pause(new Request(post: ['repeat_rule_id' => '1', 'csrf_token' => 'wrong-token'])),
    'Invalid-CSRF lifecycle request'
);

foreach (['pause', 'resume', 'cancel'] as $method) {
    assertRepeatControllerResponse(
        422,
        ['success' => false, 'message' => 'Invalid repeat rule ID.'],
        $controller->$method(new Request(post: ['repeat_rule_id' => '0', 'csrf_token' => 'controller-contract-token'])),
        ucfirst($method) . ' invalid-ID request'
    );
}

assertRepeatControllerResponse(
    200,
    ['success' => true, 'status' => 'paused', 'deleted_task_ids' => []],
    $controller->pause(new Request(post: ['repeat_rule_id' => '1', 'csrf_token' => 'controller-contract-token'])),
    'Pause success'
);
assertRepeatControllerResponse(
    200,
    ['success' => true, 'status' => 'active', 'generated_count' => 0, 'next_occurrence_at' => '2026-09-14 06:00:00'],
    $controller->resume(new Request(post: ['repeat_rule_id' => '2', 'csrf_token' => 'controller-contract-token'])),
    'Resume success'
);
assertRepeatControllerResponse(
    200,
    ['success' => true, 'status' => 'cancelled', 'deleted_task_ids' => []],
    $controller->cancel(new Request(post: ['repeat_rule_id' => '3', 'csrf_token' => 'controller-contract-token'])),
    'Cancel success'
);
assertRepeatControllerResponse(
    404,
    ['success' => false, 'message' => 'Repeat rule not found.'],
    $controller->pause(new Request(post: ['repeat_rule_id' => '999', 'csrf_token' => 'controller-contract-token'])),
    'Missing repeat rule'
);
assertRepeatControllerResponse(
    422,
    ['success' => false, 'message' => 'Only an active repeat rule can be paused.'],
    $controller->pause(new Request(post: ['repeat_rule_id' => '4', 'csrf_token' => 'controller-contract-token'])),
    'Invalid repeat-rule state'
);

$pdo->exec('DROP TABLE tasks');
assertRepeatControllerResponse(
    500,
    ['success' => false, 'message' => 'The repeat rule could not be updated.'],
    $controller->pause(new Request(post: ['repeat_rule_id' => '5', 'csrf_token' => 'controller-contract-token'])),
    'Unexpected persistence error'
);

echo "repeat-controller-contract tests passed\n";
