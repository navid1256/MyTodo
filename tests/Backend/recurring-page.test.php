<?php

declare(strict_types=1);

use App\Application;
use App\Http\Request;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Keep fixtures independent of application .env, session files, and live databases.
$_SERVER['APP_ENV'] = 'testing';
session_set_save_handler(
    static fn(): bool => true,
    static fn(): bool => true,
    static fn(): string => '',
    static fn(): bool => true,
    static fn(): bool => true,
    static fn(): int => 0
);
session_start();

function checkRecurringPage(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// A fresh process per scenario is intentional: the application renders views with require_once.
$language = $argv[1] ?? 'english';
$filter = $argv[2] ?? 'all';
$partial = ($argv[3] ?? 'full') === 'partial';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT)');
$pdo->exec('CREATE TABLE users_info (user_id INTEGER, firstname TEXT, lastname TEXT, job_title TEXT, date_of_birth TEXT, gender TEXT, country TEXT, avatar_url TEXT)');
$pdo->exec('CREATE TABLE user_settings (user_id INTEGER, language TEXT, calendar_system TEXT, timezone TEXT)');
$pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, user_id INTEGER, repeat_rule_id INTEGER, is_done INTEGER, due_at TEXT, completed_at TEXT)');
$pdo->exec('CREATE TABLE task_reminders (task_id INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE task_repeat_rules (
    id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, status TEXT, start_at TEXT,
    has_time INTEGER, timezone TEXT, frequency TEXT, interval_value INTEGER, interval_unit TEXT,
    week_days TEXT, month_day INTEGER, month_day_mode TEXT, end_type TEXT, end_date TEXT,
    repeat_count INTEGER, next_occurrence_at TEXT, created_at TEXT
)');
$pdo->exec("INSERT INTO users VALUES (10, 'Owner', 'owner@example.test')");
$statement = $pdo->prepare('INSERT INTO user_settings VALUES (10, ?, ?, ?)');
$statement->execute([$language, $language === 'persian' ? 'jalali' : 'gregorian', 'Asia/Tehran']);
$insertRule = $pdo->prepare('INSERT INTO task_repeat_rules VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$unsafeTitle = '<img src=x onerror=alert(1)> "quoted" & فارسی';
foreach (['active', 'paused', 'completed', 'cancelled'] as $index => $status) {
    $insertRule->execute([
        $index + 1, 10, $index === 0 ? $unsafeTitle : $status . ' owned rule', $status,
        '2099-01-01 06:00:00', 'Asia/Tehran', 'custom', 2,
        $index === 0 ? 'week' : 'month', '[1,3]', 31,
        $index === 2 ? 'last_day' : 'clamp', $index === 0 ? 'date' : ($index === 1 ? 'count' : 'endlessly'),
        $index === 0 ? '2099-12-31' : null, $index === 1 ? 5 : null,
        $status === 'active' ? '2099-05-01 06:00:00' : null, '2098-12-01 00:00:00',
    ]);
}
$pdo->exec("INSERT INTO task_repeat_rules (id, user_id, title, status, created_at) VALUES (99, 20, 'FOREIGN PRIVATE RULE', 'active', '2099-01-01')");
$pdo->exec("INSERT INTO tasks VALUES (1, 10, 1, 0, '2099-01-02 06:00:00', NULL)");
$pdo->exec("INSERT INTO tasks VALUES (2, 20, 99, 0, '2099-01-01 06:00:00', NULL)");
$pdo->exec("INSERT INTO task_reminders VALUES (1, 'sent')");
$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran'));
$statement = $pdo->prepare('INSERT INTO tasks VALUES (3, 10, NULL, 1, NULL, ?)');
$statement->execute([$today->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
if ($filter === 'empty') {
    $pdo->exec('DELETE FROM task_repeat_rules WHERE user_id = 10');
}
$_SESSION = $filter === 'unauthenticated' ? [] : ['user' => ['id' => 10, 'username' => 'Owner'], 'csrf_token' => 'page-test-token'];
$query = ['filter' => $filter === 'array' ? ['active'] : $filter, 'partial' => $partial ? '1' : '0'];
$response = (new Application($pdo, dirname(__DIR__, 2)))->handle(new Request($query, [], [
    'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/recurring-tasks',
]));
if ($filter === 'unauthenticated') {
    checkRecurringPage($response->getStatusCode() === 302 && $response->getHeaders()['Location'] === '/auth', 'GET page must require authentication.');
    echo "PASS recurring page unauthenticated redirect\n";
    exit;
}
checkRecurringPage($response->getStatusCode() === 200, 'Page must return 200.');
$html = $response->getBody();
if (($argv[4] ?? '') === '--html') {
    echo $html;
    exit;
}
if ($partial) {
    $data = json_decode($html, true, 512, JSON_THROW_ON_ERROR);
    checkRecurringPage($data['activeView'] === 'recurring-tasks', 'Partial view mapping must match.');
    checkRecurringPage($data['pageStylesheet'] === '/assets/css/pages/recurring-tasks.css', 'Partial page CSS must be available.');
    checkRecurringPage($data['taskModalStylesheet'] === '/assets/css/task-modal.css', 'Partial page must load task modal CSS.');
    checkRecurringPage($data['direction'] === ($language === 'persian' ? 'rtl' : 'ltr'), 'Partial direction must match language.');
    checkRecurringPage($data['timezoneIsPersisted'] === true, 'Saved timezone must remain authoritative.');
    $html = $data['html'];
} else {
    checkRecurringPage(str_contains($html, 'dir="' . ($language === 'persian' ? 'rtl' : 'ltr') . '"'), 'Document direction must match language.');
    checkRecurringPage(strpos($html, 'data-nav-id="manage-tasks"') < strpos($html, 'data-nav-id="recurring-tasks"'), 'Sidebar entry must follow Manage Tasks.');
}
$document = new DOMDocument();
@$document->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($document);
$expectedFilter = in_array($filter, ['all', 'active', 'paused', 'completed', 'cancelled'], true) ? $filter : 'all';
$rows = $xpath->query('//article[@data-repeat-rule-id]');
checkRecurringPage($rows->length === ($filter === 'empty' ? 0 : ($expectedFilter === 'all' ? 4 : 1)), 'Only owned, filtered rows must render.');
checkRecurringPage($xpath->query('//*[@data-i18n="recurring.empty"]')->length === ($filter === 'empty' ? 1 : 0), 'An empty result must show the translated empty state.');
checkRecurringPage(!str_contains($html, 'FOREIGN PRIVATE RULE'), 'Foreign title must never be exposed.');
checkRecurringPage(!str_contains($html, '<img src=x'), 'User title must be escaped.');
checkRecurringPage($xpath->query('//*[@class="content recurringTasksContent"]')->length === 1, 'Content wrapper must match dashboard layout.');
checkRecurringPage($xpath->query('//*[@id="openTaskModal"]')->length === 1, 'Shared Add New Task must remain visible.');
checkRecurringPage($xpath->query('//a[contains(@class,"completedButton") and @data-count="1"]')->length === 1, 'Shared Completed count must use the client day.');
checkRecurringPage($xpath->query('//*[@id="recurringTasksStatus" and @role="status" and @aria-live="polite" and @hidden]')->length === 1, 'Live status hook must exist.');
checkRecurringPage($xpath->query('//a[@data-repeat-filter="' . $expectedFilter . '" and @aria-current="page"]')->length === 1, 'Current filter must normalize and be accessible.');
foreach ($rows as $row) {
    $payload = json_decode($row->getAttribute('data-repeat-rule'), true, 512, JSON_THROW_ON_ERROR);
    $status = $row->getAttribute('data-repeat-status');
    $buttons = $xpath->query('.//button[@data-repeat-action]', $row);
    checkRecurringPage($buttons->length === (in_array($status, ['active', 'paused'], true) ? 3 : 0), 'Action availability must match lifecycle state.');
    if ($status === 'active') {
        checkRecurringPage($payload['title'] === $unsafeTitle && $payload['week_days'] === [1, 3], 'Row payload must safely round-trip title and schedule.');
        checkRecurringPage($xpath->query('.//button[@data-repeat-action="pause"]', $row)->length === 1, 'Active row needs Pause.');
        if ($language === 'english') {
            checkRecurringPage(str_contains($row->textContent, '2099/01/02 09:30'), 'Next date must prefer the generated task in the saved user timezone.');
            checkRecurringPage(str_contains($row->textContent, 'Mon / Wed'), 'Weekly summary must name the selected weekdays.');
        } else {
            checkRecurringPage(str_contains($row->textContent, '۱۴۷۷') || str_contains($row->textContent, '1477'), 'Persian saved Jalali calendar must render the converted year.');
        }
    }
    if ($status === 'paused') {
        checkRecurringPage($xpath->query('.//button[@data-repeat-action="resume"]', $row)->length === 1, 'Paused row needs Resume.');
    }
}
$en = require dirname(__DIR__, 2) . '/resources/lang/en.php';
$fa = require dirname(__DIR__, 2) . '/resources/lang/fa.php';
checkRecurringPage(array_diff_key($en, $fa) === [] && array_diff_key($fa, $en) === [], 'Translation key sets must match.');
echo "PASS recurring page $language $filter " . ($partial ? 'partial' : 'full') . "\n";
