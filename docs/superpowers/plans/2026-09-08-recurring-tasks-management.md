# Recurring Tasks Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a bilingual Recurring Tasks dashboard page and safe lifecycle/edit operations for repeat rules without changing completed or historical tasks.

**Architecture:** Keep schedule calculation in the existing repeat calculator/planner, place lifecycle orchestration and transactions in `RepeatService`, keep ownership-aware SQL in repositories, expose a dedicated `RepeatController`, and update the existing dashboard/task-modal JavaScript through small request and UI modules. The page remains a server-rendered MVC view that also supports the existing Fetch partial-navigation flow.

**Tech Stack:** PHP 8.2, PDO/MySQL, the existing MVC router and Translator, vanilla ES modules, Vite, Node's built-in test runner, project PHP test scripts, Font Awesome subset, CSS logical properties.

**Spec:** `docs/superpowers/specs/2026-09-07-recurring-tasks-management-design.md`

## Global Constraints

- Preserve the existing `.gitignore` changes and do not stage unrelated files.
- Every read/update/delete query for a rule or recurring task must include `user_id` in SQL.
- Pause, Resume, Cancel, single-occurrence edit, and series split must run in a transaction and lock the target rule with `FOR UPDATE`.
- Never rewrite or delete completed tasks or historical tasks.
- `cancelled` and `completed` are terminal states.
- Resume must retain the original `start_at` anchor and skip missed occurrences.
- The shared `views/components/task-toolbar.php` must remain the only toolbar markup; both Completed and Add New Task remain visible.
- Use one bilingual view. Add translations to both language files and use logical CSS properties for LTR/RTL.
- Successful lifecycle/edit requests update the DOM without `window.location.reload()`.
- Explain the finished implementation from private helpers and variables upward to public flows, per the project convention.

---

## Task 1: Lock down lifecycle rules with focused backend tests

**Files:**

- Modify: `tests/Backend/repeat-backend.test.php`
- Create: `App/Exceptions/RepeatRuleNotFoundException.php`
- Create: `App/Exceptions/RepeatRuleStateException.php`
- Modify: `App/Services/RepeatOccurrencePlanner.php`

- [ ] **Step 1: Add failing resume-anchor and exhausted-count tests**

Append scenarios that prove the planner starts from a supplied future occurrence while still calculating against the original series start:

```php
$resumeStart = new DateTimeImmutable('2026-09-16 12:00:00', $timezone);
$resumeCandidate = $calculator->nextOccurrence($weeklyRule, $start, $resumeStart);

assertSameValue(
    '2026-09-21 09:30:00',
    $resumeCandidate->format('Y-m-d H:i:s'),
    'Resume must skip missed weekly occurrences and retain the original weekday/time anchor.'
);

$exhaustedPlan = $planner->plan(
    $countRule,
    $start,
    new DateTimeImmutable('2026-09-08 09:30:00', $timezone),
    new DateTimeImmutable('2026-10-05 23:59:59', $timezone),
    2
);

assertSameValue([], $exhaustedPlan['occurrences'], 'An exhausted count rule must not create another task.');
assertSameValue('completed', $exhaustedPlan['status'], 'An exhausted count rule must be terminal.');
```

- [ ] **Step 2: Run the backend test and confirm the new exhausted-count assertion fails if the planner does not short-circuit**

Run: `php tests/Backend/repeat-backend.test.php`

Expected: the new exhausted-count scenario fails before the planner guard is added, while existing scheduling assertions still pass.

- [ ] **Step 3: Add an explicit exhausted-count guard to the planner**

At the beginning of `RepeatOccurrencePlanner::plan()`, after normalizing the limit, return a completed empty plan when the count limit is already reached:

```php
if (
    $rule['ends']['type'] === 'count'
    && $generatedRepeats >= (int) $rule['ends']['count']
) {
    return [
        'occurrences' => [],
        'generated_repeats' => $generatedRepeats,
        'next_occurrence' => null,
        'status' => 'completed',
    ];
}
```

- [ ] **Step 4: Add dedicated domain exceptions**

Both exception files use the existing project pattern:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class RepeatRuleNotFoundException extends RuntimeException {}
```

`RepeatRuleStateException.php` has the same structure and its matching class name.

- [ ] **Step 5: Verify syntax and tests**

Run:

```powershell
php -l App/Exceptions/RepeatRuleNotFoundException.php
php -l App/Exceptions/RepeatRuleStateException.php
php -l App/Services/RepeatOccurrencePlanner.php
php tests/Backend/repeat-backend.test.php
```

Expected: every lint prints `No syntax errors detected`; test ends with `repeat-backend tests passed`.

- [ ] **Step 6: Commit the scheduling contract**

```powershell
git add App/Exceptions/RepeatRuleNotFoundException.php App/Exceptions/RepeatRuleStateException.php App/Services/RepeatOccurrencePlanner.php tests/Backend/repeat-backend.test.php
git commit -m "Test repeat lifecycle scheduling rules"
```

---

## Task 2: Add ownership-safe repository primitives

**Files:**

- Modify: `App/Repositories/RepeatRuleRepository.php`
- Modify: `App/Repositories/TaskRepository.php`
- Modify: `App/Repositories/ReminderRepository.php`
- Create: `tests/Backend/repeat-repository-contract.test.php`

- [ ] **Step 1: Write a repository contract test before changing SQL**

The test reads repository source and asserts that all new mutation/select methods contain both rule/task identifiers and `user_id`. This fast contract test complements later database/browser verification without mutating the developer database:

```php
$repeatRepository = file_get_contents(dirname(__DIR__, 2) . '/App/Repositories/RepeatRuleRepository.php');
$taskRepository = file_get_contents(dirname(__DIR__, 2) . '/App/Repositories/TaskRepository.php');

assertContainsText('findByIdForUserForUpdate', $repeatRepository, 'A user-owned locking lookup is required.');
assertContainsText('user_id = :user_id', $repeatRepository, 'Repeat rule mutations must check ownership in SQL.');
assertContainsText('findRecurringByIdForUpdate', $taskRepository, 'Recurring task edits need a locking lookup.');
assertContainsText('t.user_id = :user_id', $taskRepository, 'Recurring task mutations must check ownership in SQL.');
```

Define the local `assertContainsText()` helper in the test and do not connect to the application database.

- [ ] **Step 2: Run the contract test and confirm it fails**

Run: `php tests/Backend/repeat-repository-contract.test.php`

Expected: failure because the ownership-aware methods do not exist yet.

- [ ] **Step 3: Add repeat-rule read and lifecycle methods**

Add these exact public interfaces to `RepeatRuleRepository`:

```php
/** @return array<int, object> */
public function getForUser(int $userId, string $status, string $cutoffAt): array;

public function findByIdForUserForUpdate(int $repeatRuleId, int $userId): ?object;

public function updateStatus(
    int $repeatRuleId,
    int $userId,
    string $status,
    ?string $nextOccurrenceAt
): bool;

/** @param array<string, mixed> $rule */
public function updateRule(int $repeatRuleId, int $userId, array $rule): bool;

/** @param array<int, array{offset_value: int, offset_unit: string}> $reminders */
public function replaceReminderTemplates(int $repeatRuleId, int $userId, array $reminders): void;
```

`getForUser()` accepts only `all|active|paused|completed|cancelled`; for `all`, omit the status predicate. It returns the rule plus counts using correlated aggregates:

```sql
SELECT r.*,
       (SELECT COUNT(*) FROM tasks t WHERE t.repeat_rule_id = r.id) AS task_count,
       (SELECT MIN(t.due_at)
          FROM tasks t
         WHERE t.repeat_rule_id = r.id
           AND t.user_id = r.user_id
           AND t.is_done = 0
           AND t.due_at >= :cutoff_at) AS first_future_task_at
FROM task_repeat_rules r
WHERE r.user_id = :user_id
```

The service computes `:cutoff_at` from its injected/current `DateTimeImmutable` in the application UTC timezone. Do not depend on the database server clock.

`findByIdForUserForUpdate()` must use:

```sql
SELECT *
FROM task_repeat_rules
WHERE id = :repeat_rule_id AND user_id = :user_id
LIMIT 1
FOR UPDATE
```

Keep the existing internal `findByIdForUpdate()` for the Cron generator, because Cron operates across users; lifecycle APIs must never call it.

- [ ] **Step 4: Add recurring-task locking, cleanup, and cursor methods**

Add these interfaces to `TaskRepository`:

```php
public function findRecurringByIdForUpdate(int $taskId, int $userId): ?object;

/** @return array<int, int> */
public function deleteFutureIncompleteForRule(
    int $repeatRuleId,
    int $userId,
    string $cutoffAt,
    ?int $exceptTaskId = null
): array;

/** @return array<int, int> */
public function deleteIncompleteFromOccurrence(
    int $repeatRuleId,
    int $userId,
    int $occurrenceNumber,
    int $exceptTaskId
): array;

public function countRepeatsForRule(int $repeatRuleId, int $userId): int;

public function getMaxOccurrenceNumber(int $repeatRuleId, int $userId): int;

public function findFirstFutureIncompleteForRule(
    int $repeatRuleId,
    int $userId,
    string $cutoffAt
): ?object;

public function attachToRule(
    int $taskId,
    int $userId,
    int $repeatRuleId,
    int $occurrenceNumber
): bool;
```

`countRepeatsForRule()` counts only rows whose `repeat_occurrence_number > 0`; occurrence `0` is the original/anchor task and is excluded from the `Repeat Counts` total. `attachToRule()` must use an `EXISTS` ownership check for the destination rule as well as `tasks.user_id = :user_id`.

For delete methods, first select owned IDs using `FOR UPDATE`, then delete with both the selected IDs and `user_id`; return the selected IDs for the JSON response. `deleteIncompleteFromOccurrence()` must include `is_done = 0`, `repeat_occurrence_number >= :occurrence_number`, and `id <> :except_task_id`.

- [ ] **Step 5: Keep reminder replacement explicit**

Add `ReminderRepository::replaceForTask()` as a transaction-only composition of delete plus insert:

```php
/** @param array<int, array{offset_value: int, offset_unit: string, remind_at: string}> $reminders */
public function replaceForTask(int $taskId, array $reminders): void
{
    $this->deleteByTaskId($taskId);

    foreach ($reminders as $reminder) {
        $this->create(
            $taskId,
            $reminder['offset_value'],
            $reminder['offset_unit'],
            $reminder['remind_at']
        );
    }
}
```

- [ ] **Step 6: Run contract and syntax checks**

```powershell
php -l App/Repositories/RepeatRuleRepository.php
php -l App/Repositories/TaskRepository.php
php -l App/Repositories/ReminderRepository.php
php tests/Backend/repeat-repository-contract.test.php
```

Expected: lint succeeds and the contract test reports `repeat-repository-contract tests passed`.

- [ ] **Step 7: Commit repository support**

```powershell
git add App/Repositories/RepeatRuleRepository.php App/Repositories/TaskRepository.php App/Repositories/ReminderRepository.php tests/Backend/repeat-repository-contract.test.php
git commit -m "Add repeat lifecycle repository operations"
```

---

## Task 3: Implement Pause, Resume, Cancel, and safe generation cursors

**Files:**

- Modify: `App/Services/RepeatService.php`
- Modify: `App/Services/ReminderService.php`
- Modify: `tests/Backend/repeat-backend.test.php`
- Create: `tests/Backend/repeat-lifecycle.integration.php`

- [ ] **Step 1: Add a database integration test with an explicit test-only DSN**

The test must refuse to run unless `MYTODO_TEST_DSN`, `MYTODO_TEST_DB_USER`, and `MYTODO_TEST_DB_PASSWORD` are present. It must also reject a DSN whose database name does not end in `_test`. Seed two users, one active rule per user, historical/completed/future tasks, and reminders inside a transaction, then roll back in `finally`.

Test these outcomes:

```text
pauseRule(user A rule, user A) -> paused, next NULL, only A future incomplete IDs removed
pauseRule(user B rule, user A) -> RepeatRuleNotFoundException
resumeRule(paused weekly Monday, Wednesday) -> first next Monday, no missed tasks
cancelRule(active/paused) -> cancelled and terminal
resumeRule(cancelled) -> RepeatRuleStateException
```

- [ ] **Step 2: Run the integration test and confirm lifecycle methods are missing**

Run: `php tests/Backend/repeat-lifecycle.integration.php`

Expected: an actionable skip message if the test DSN is absent; otherwise failure because lifecycle methods are not implemented.

- [ ] **Step 3: Refactor occurrence numbering before lifecycle generation**

In `generateRuleOccurrencesInTransaction()`, separate count progress from the unique occurrence cursor:

```php
$existingRepeatCount = $this->taskRepository->countRepeatsForRule(
    $repeatRuleId,
    (int) $storedRule->user_id
);
$nextOccurrenceNumber = $this->taskRepository->getMaxOccurrenceNumber(
    $repeatRuleId,
    (int) $storedRule->user_id
) + 1;

$plan = $this->occurrencePlanner->plan(
    $rule,
    $seriesStart,
    $nextOccurrence,
    $horizon,
    $existingRepeatCount,
    $occurrenceLimit
);
```

Inside the create loop, use `$nextOccurrenceNumber + $index`. Save `$plan['generated_repeats']` as the current materialized repeat count; never reuse it as the unique occurrence number.

- [ ] **Step 4: Add public lifecycle methods above private helpers**

Add these exact interfaces:

```php
/** @return array<int, object> */
public function getRulesForUser(
    int $userId,
    string $status,
    DateTimeImmutable $now
): array;

/** @return array{status: string, deleted_task_ids: array<int, int>} */
public function pauseRule(int $repeatRuleId, int $userId, DateTimeImmutable $now): array;

/** @return array{status: string, generated_count: int, next_occurrence_at: string|null} */
public function resumeRule(int $repeatRuleId, int $userId, DateTimeImmutable $now): array;

/** @return array{status: string, deleted_task_ids: array<int, int>} */
public function cancelRule(int $repeatRuleId, int $userId, DateTimeImmutable $now): array;
```

`pauseRule()` behavior:

```php
if ((string) $storedRule->status === 'paused') {
    return ['status' => 'paused', 'deleted_task_ids' => []];
}

if ((string) $storedRule->status !== 'active') {
    throw new RepeatRuleStateException('Only an active repeat rule can be paused.');
}
```

Then delete future incomplete tasks using the UTC `$now`, set status `paused`, and set next occurrence to `null`.

`resumeRule()` must hydrate the stored rule, convert `$now` to the rule timezone, calculate the first matching occurrence with the original `start_at`, reject terminal states, set `completed` when the end rule is exhausted, otherwise set `active`, save the next occurrence, and call `generateRuleOccurrencesInTransaction()` with `$now->modify('+30 days')` before commit.

If `resumeRule()` receives an already-active owned rule, return idempotent success with its existing state and `generated_count=0`; do not generate a second window in that repeated request.

`cancelRule()` returns idempotent success for an already-cancelled rule, rejects `completed`, deletes future incomplete tasks, and persists `cancelled` plus `NULL` next occurrence.

- [ ] **Step 5: Add small private transaction/state helpers below public methods**

Use these responsibilities:

```php
private function requireOwnedRuleForUpdate(int $repeatRuleId, int $userId): object;
private function runInTransaction(callable $operation): mixed;
private function resolveResumeOccurrence(object $storedRule, DateTimeImmutable $now): ?DateTimeImmutable;
private function isRuleExhausted(object $storedRule, int $existingRepeatCount, DateTimeImmutable $candidate): bool;
private function toDatabaseDateTime(DateTimeInterface $dateTime): string;
```

`runInTransaction()` begins only when no transaction exists, commits only what it started, and rolls back only what it started. This preserves `TaskService`'s existing outer transaction.

- [ ] **Step 6: Verify the lifecycle behavior**

Run:

```powershell
php -l App/Services/RepeatService.php
php -l App/Services/ReminderService.php
php tests/Backend/repeat-backend.test.php
php tests/Backend/repeat-lifecycle.integration.php
```

Expected: pure tests pass; integration test either passes against the isolated `_test` database or exits with its explicit configuration message—never touches the development database.

- [ ] **Step 7: Commit lifecycle behavior**

```powershell
git add App/Services/RepeatService.php App/Services/ReminderService.php tests/Backend/repeat-backend.test.php tests/Backend/repeat-lifecycle.integration.php
git commit -m "Implement repeat pause resume and cancel"
```

---

## Task 4: Expose lifecycle APIs through a dedicated controller

**Files:**

- Create: `App/Controllers/RepeatController.php`
- Modify: `App/Application.php`
- Modify: `routes/api.php`
- Create: `tests/Backend/repeat-controller-contract.test.php`

- [ ] **Step 1: Add failing route/controller contract assertions**

Assert these lifecycle route strings and controller methods exist:

```text
/api/repeat-rules/pause -> pause
/api/repeat-rules/resume -> resume
/api/repeat-rules/cancel -> cancel
```

Also assert every route includes `[AuthMiddleware::class]` and the controller calls `CsrfMiddleware::isValid()`.

- [ ] **Step 2: Run the contract test and confirm failure**

Run: `php tests/Backend/repeat-controller-contract.test.php`

Expected: failure because `RepeatController` and routes are absent.

- [ ] **Step 3: Create `RepeatController` with common guards and error mapping**

Constructor:

```php
public function __construct(
    private readonly RepeatService $repeatService,
    private readonly AuthService $authService,
    private readonly UserRepository $userRepository,
    private readonly NotificationService $notificationService,
    private readonly UserSettingsService $settingsService
) {}
```

Lifecycle actions use `readPositiveId($request, 'repeat_rule_id')`, `guardJsonRequest()`, and a common operation wrapper. Map exceptions as follows:

```php
} catch (RepeatRuleNotFoundException $exception) {
    return Response::json(['success' => false, 'message' => $exception->getMessage()], 404);
} catch (RepeatValidationException | RepeatRuleStateException $exception) {
    return Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable) {
    return Response::json(['success' => false, 'message' => 'The repeat rule could not be updated.'], 500);
}
```

Successful lifecycle responses merge `['success' => true]` with the service result.

- [ ] **Step 4: Register dependencies and routes**

Bind the controller in `Application::registerDependencies()` using the already-created `$repeatService`, and add authenticated routes to `routes/api.php`:

```php
$router->post('/api/repeat-rules/pause', [RepeatController::class, 'pause'], [AuthMiddleware::class]);
$router->post('/api/repeat-rules/resume', [RepeatController::class, 'resume'], [AuthMiddleware::class]);
$router->post('/api/repeat-rules/cancel', [RepeatController::class, 'cancel'], [AuthMiddleware::class]);
```

- [ ] **Step 5: Verify and commit the lifecycle API slice**

```powershell
php -l App/Controllers/RepeatController.php
php -l App/Application.php
php -l routes/api.php
php tests/Backend/repeat-controller-contract.test.php
git add App/Controllers/RepeatController.php App/Application.php routes/api.php tests/Backend/repeat-controller-contract.test.php
git commit -m "Expose repeat lifecycle API"
```

Expected: lint and contract test pass.

---

## Task 5: Add the Recurring Tasks MVC page and bilingual compact UI

**Files:**

- Modify: `App/Controllers/RepeatController.php`
- Modify: `routes/web.php`
- Modify: `views/layouts/dashboard.php`
- Modify: `views/components/sidebar.php`
- Create: `views/pages/recurring-tasks.php`
- Create: `public/assets/css/pages/recurring-tasks.css`
- Modify: `resources/lang/en.php`
- Modify: `resources/lang/fa.php`
- Modify: `public/assets/js/modules/navigation.js`
- Create: `tests/Frontend/recurring-navigation.test.mjs`

- [ ] **Step 1: Write failing navigation mapping tests**

Import `getDashboardNavigationView()` and assert:

```js
assert.equal(
    getDashboardNavigationView('https://mytodo.php/recurring-tasks', 'https://mytodo.php/'),
    'recurring-tasks'
);
assert.equal(
    getDashboardNavigationView('https://example.com/recurring-tasks', 'https://mytodo.php/'),
    null
);
```

- [ ] **Step 2: Run the frontend test and confirm failure**

Run: `node --test tests/Frontend/recurring-navigation.test.mjs`

Expected: the same-origin Recurring Tasks URL currently resolves to `null`.

- [ ] **Step 3: Add the GET route and page action**

Add:

```php
$router->get('/recurring-tasks', [RepeatController::class, 'index'], [AuthMiddleware::class]);
```

`RepeatController::index()` normalizes `filter` to `all|active|paused|completed|cancelled`, gets owned rules through `RepeatService::getRulesForUser()`, resolves the same shared dashboard data currently assembled by `TaskController`, and renders `layouts/dashboard` with:

```php
[
    'activeView' => 'recurring-tasks',
    'repeatRules' => $rules,
    'repeatFilter' => $filter,
    'completedTasksToday' => $completedTodayCount,
    'sentNotificationCount' => $sentCount,
    'csrfToken' => CsrfMiddleware::getToken(),
    'renderTimezone' => $clientTimezone->getName(),
    'effectiveLanguage' => $settings['effective_language'],
    'calendarSystem' => $settings['calendar_system'],
]
```

- [ ] **Step 4: Wire the layout without copying toolbar markup**

In `views/layouts/dashboard.php`:

- add the new page stylesheet mapping;
- include `recurring-tasks` in `$usesTaskModals`;
- add `recurringTasksView` to `$viewClassMap`;
- include it in the toolbar allow-list;
- render the page inside `<div class="content recurringTasksContent">`.

Do not alter `views/components/task-toolbar.php`.

- [ ] **Step 5: Add the sidebar item after Manage Tasks**

Use this item:

```php
'recurring-tasks' => [
    'href' => '/recurring-tasks',
    'icon' => 'fa-solid fa-arrows-rotate',
    'translationKey' => 'navigation.recurring_tasks',
    'label' => $translator->translate('navigation.recurring_tasks'),
],
```

- [ ] **Step 6: Render the compact list and accessible states**

`views/pages/recurring-tasks.php` must render:

- page heading plus status filter links using `data-dashboard-link`;
- one `<article class="recurringRule" data-repeat-rule-id="..." data-repeat-status="...">` per rule;
- title, translated schedule summary, next occurrence, end rule, task count, text status badge;
- Edit plus Pause/Resume plus Cancel buttons according to state;
- a hidden `role="status" aria-live="polite"` message container;
- a translated empty state when no rows match.

Escape all database text with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Put IDs/status in data attributes only after integer/allow-list normalization.

- [ ] **Step 7: Add symmetric English and Persian translations**

Add matching keys under these groups:

```text
navigation.recurring_tasks
recurring.title
recurring.filter.*
recurring.status.*
recurring.action.*
recurring.summary.*
recurring.confirm.*
recurring.message.*
recurring.empty
```

After editing, verify equal key sets with:

```powershell
php -r "$en=require 'resources/lang/en.php'; $fa=require 'resources/lang/fa.php'; $missingFa=array_diff_key($en,$fa); $missingEn=array_diff_key($fa,$en); exit(($missingFa||$missingEn)?1:0);"
```

- [ ] **Step 8: Add page CSS using logical properties**

The CSS must define compact desktop rows and a wrapping mobile layout, including visible focus and non-color status text. Use `padding-inline`, `margin-inline`, `inset-inline`, and `border-inline`; do not add physical left/right spacing. Preserve the existing toolbar margins by styling only descendants of `.recurringTasksContent`.

- [ ] **Step 9: Enable partial navigation**

Add `recurring-tasks` to `DASHBOARD_AJAX_VIEWS` and to `pathToView` in `navigation.js`.

- [ ] **Step 10: Verify page delivery and commit**

```powershell
php -l App/Controllers/RepeatController.php
php -l routes/web.php
php -l views/layouts/dashboard.php
php -l views/components/sidebar.php
php -l views/pages/recurring-tasks.php
php -l resources/lang/en.php
php -l resources/lang/fa.php
node --test tests/Frontend/recurring-navigation.test.mjs
git add App/Controllers/RepeatController.php routes/web.php views/layouts/dashboard.php views/components/sidebar.php views/pages/recurring-tasks.php public/assets/css/pages/recurring-tasks.css resources/lang/en.php resources/lang/fa.php public/assets/js/modules/navigation.js tests/Frontend/recurring-navigation.test.mjs
git commit -m "Add recurring tasks dashboard page"
```

---

## Task 6: Add reload-free lifecycle actions to the page

**Files:**

- Create: `public/assets/js/services/repeat-service.js`
- Create: `public/assets/js/modules/recurring-tasks.js`
- Modify: `public/assets/js/app.js`
- Create: `tests/Frontend/repeat-service.test.mjs`
- Create: `tests/Frontend/recurring-tasks.test.mjs`

- [ ] **Step 1: Write failing request-shape tests**

Stub `fetch`, call each service method, and assert the endpoint and form fields:

```js
await pauseRepeatRule(14, 'csrf-value');
assert.equal(request.url, '/api/repeat-rules/pause');
assert.equal(request.body.get('repeat_rule_id'), '14');
assert.equal(request.body.get('csrf_token'), 'csrf-value');
```

Repeat for Resume and Cancel.

- [ ] **Step 2: Implement the request-only service**

Expose:

```js
export function pauseRepeatRule(repeatRuleId, csrfToken) {
    return sendRepeatAction('/api/repeat-rules/pause', repeatRuleId, csrfToken);
}

export function resumeRepeatRule(repeatRuleId, csrfToken) {
    return sendRepeatAction('/api/repeat-rules/resume', repeatRuleId, csrfToken);
}

export function cancelRepeatRule(repeatRuleId, csrfToken) {
    return sendRepeatAction('/api/repeat-rules/cancel', repeatRuleId, csrfToken);
}
```

`sendRepeatAction()` uses `sendJsonFormRequest(url, formData, { errorMessage })`; it performs no DOM work.

- [ ] **Step 3: Write DOM tests for state changes**

Cover:

- clicked button becomes disabled during the request;
- Pause swaps the action to Resume and updates text status;
- Resume swaps back to Pause;
- Cancel disables terminal actions and leaves Edit unavailable;
- API error restores buttons and writes the translated error into the live region;
- a second `initRecurringTasks()` call does not duplicate handlers because `app.js` aborts the prior dashboard controller.

- [ ] **Step 4: Implement delegated UI events**

`initRecurringTasks(signal)` queries `.recurringTasksContent`, listens once on the container, resolves `[data-repeat-action]`, asks for confirmation for Pause/Cancel, reads CSRF from `<meta name="csrf-token">`, and calls the service. Keep the DOM update in small private functions:

```js
function setRowBusy(row, isBusy) {}
function renderRuleState(row, result) {}
function setPageMessage(container, message, isError) {}
function getCsrfToken() {}
```

Use `signal` in `addEventListener(..., { signal })` so partial navigation cleans up listeners.

- [ ] **Step 5: Register the module in the shared dashboard lifecycle**

In `app.js`, import `initRecurringTasks` and call `initRecurringTasks(signal)` inside `initDashboardView()`.

- [ ] **Step 6: Run frontend tests and commit**

```powershell
node --test tests/Frontend/repeat-service.test.mjs
node --test tests/Frontend/recurring-tasks.test.mjs
node --test tests/Frontend/recurring-navigation.test.mjs
git add public/assets/js/services/repeat-service.js public/assets/js/modules/recurring-tasks.js public/assets/js/app.js tests/Frontend/repeat-service.test.mjs tests/Frontend/recurring-tasks.test.mjs
git commit -m "Add recurring task lifecycle interactions"
```

Expected: all Node test commands pass and no test performs a real network request.

---

## Task 7: Implement `Only this task` editing

**Files:**

- Modify: `App/Services/RepeatService.php`
- Modify: `App/Services/ReminderService.php`
- Modify: `App/Services/TaskService.php`
- Modify: `App/Controllers/RepeatController.php`
- Modify: `routes/api.php`
- Modify: `views/components/task-items.php`
- Modify: `views/modals/task.php`
- Modify: `public/assets/js/modules/task-modal.js`
- Modify: `public/assets/js/modules/date-time/date-time-picker.js`
- Modify: `public/assets/js/modules/reminder/reminder-picker.js`
- Modify: `public/assets/js/modules/repeat/repeat-picker.js`
- Modify: `public/assets/js/services/repeat-service.js`
- Modify: `resources/lang/en.php`
- Modify: `resources/lang/fa.php`
- Modify: `tests/Backend/repeat-lifecycle.integration.php`
- Create: `tests/Frontend/repeat-task-edit.test.mjs`

- [ ] **Step 1: Add failing integration assertions for a single occurrence**

Seed a recurring task with two sibling occurrences and reminders. Call the new service method and assert:

```text
selected task title/due_at/has_time changed
selected task repeat_rule_id and repeat_occurrence_number unchanged
selected reminders replaced
rule row unchanged
sibling task and reminders unchanged
foreign-user task rejected
```

- [ ] **Step 2: Add the service method and task payload validation**

Add:

```php
/**
 * @param array<int, mixed> $reminders
 * @return array{task_id: int, repeat_rule_id: int, scope: string}
 */
public function updateSingleOccurrence(
    int $taskId,
    int $userId,
    string $title,
    DateTimeInterface $dueAt,
    bool $hasTime,
    array $reminders
): array;
```

Inside the transaction: lock the owned recurring task, lock its owned rule, validate title and reminders through the existing services, update only `title`, `due_at`, and `has_time`, replace reminders, and return IDs. Do not update repeat columns or the rule.

- [ ] **Step 3: Implement the controller's `updateTask()` single scope and register its route**

Accept only `scope=single|future`. For `single`, parse the same canonical task fields as task creation and call `updateSingleOccurrence()`. Return `422` when Repeat is supplied for `single`.

Register the endpoint only after the action works:

```php
$router->post('/api/repeat-tasks/update', [RepeatController::class, 'updateTask'], [AuthMiddleware::class]);
```

- [ ] **Step 4: Add edit metadata to recurring task rows**

In `task-items.php`, add an Edit button only for incomplete recurring tasks, with data attributes for task ID, title, canonical due time, has-time, repeat rule ID, and occurrence number. Do not place raw JSON in an unescaped HTML attribute; use a sibling `<script type="application/json" data-task-edit-payload>` encoded with JSON hex flags.

Extend `TaskService`'s list-returning methods to enrich only incomplete recurring tasks through a new `RepeatService::getTaskEditPayload($taskId, $userId)`. That payload contains the task's current reminders plus its normalized repeat configuration. `RepeatService` reads reminders through this new `ReminderService` method:

```php
/** @return array<int, object> */
public function getRemindersForTask(int $taskId): array
{
    return $this->reminderRepository->getByTaskId($taskId);
}
```

The edit-payload method must use the ownership-aware task/rule reads from Task 2 and return `null` for non-recurring, completed, missing, or foreign-owned tasks. This prevents the view from assembling domain data itself.

- [ ] **Step 5: Reuse the task modal in edit mode**

Add hidden fields:

```html
<input id="taskFormMode" name="mode" type="hidden" value="create">
<input id="taskEditId" name="task_id" type="hidden" value="">
<input id="taskEditScope" name="scope" type="hidden" value="">
```

For recurring task edit, show a translated scope dialog/select with exactly `Only this task` and `This and future tasks`. When `single` is selected, hide/disable the Repeat button and clear no stored rule data until submission. Create mode remains unchanged.

Expose explicit hydration/reset methods from the three existing picker modules so task-modal code does not mutate their private state:

```js
// date-time-picker.js
load(value, hasTime);
reset();

// reminder-picker.js
load(reminders);
reset();

// repeat-picker.js
load(rule);
reset();
setEnabled(isEnabled);
```

Each `load()` clones the supplied value, updates its hidden form input and visible summary, and re-renders its own controls. Each `reset()` restores the same empty state used by Add New Task.

- [ ] **Step 6: Submit single edits without reload**

Add `updateRecurringTask(formData)` to `repeat-service.js`, post to `/api/repeat-tasks/update`, and update the selected task row from the JSON response. Reset all modal state on close so the next Add New Task opens in create mode.

- [ ] **Step 7: Run tests and commit**

```powershell
php -l App/Services/RepeatService.php
php -l App/Controllers/RepeatController.php
php -l views/components/task-items.php
php -l views/modals/task.php
php tests/Backend/repeat-lifecycle.integration.php
node --test tests/Frontend/repeat-task-edit.test.mjs
git add App/Services/RepeatService.php App/Services/ReminderService.php App/Services/TaskService.php App/Controllers/RepeatController.php routes/api.php views/components/task-items.php views/modals/task.php public/assets/js/modules/task-modal.js public/assets/js/modules/date-time/date-time-picker.js public/assets/js/modules/reminder/reminder-picker.js public/assets/js/modules/repeat/repeat-picker.js public/assets/js/services/repeat-service.js resources/lang/en.php resources/lang/fa.php tests/Backend/repeat-lifecycle.integration.php tests/Frontend/repeat-task-edit.test.mjs
git commit -m "Edit one recurring task occurrence"
```

---

## Task 8: Implement `This and future tasks` and rule editing

**Files:**

- Modify: `App/Repositories/RepeatRuleRepository.php`
- Modify: `App/Repositories/TaskRepository.php`
- Modify: `App/Services/RepeatService.php`
- Modify: `App/Controllers/RepeatController.php`
- Modify: `routes/api.php`
- Modify: `views/pages/recurring-tasks.php`
- Modify: `public/assets/js/modules/recurring-tasks.js`
- Modify: `public/assets/js/modules/task-modal.js`
- Modify: `public/assets/js/services/repeat-service.js`
- Modify: `tests/Backend/repeat-lifecycle.integration.php`
- Modify: `tests/Frontend/repeat-task-edit.test.mjs`

- [ ] **Step 1: Add failing series-split integration scenarios**

Assert:

```text
old rule becomes completed with next_occurrence_at NULL
past/completed tasks remain attached to old rule
future incomplete siblings at/after selected occurrence are deleted
selected task becomes occurrence 0 of a new active rule
new rule starts at the edited due date/time
new reminder templates and generated-task reminders use the edited values
30-day window is generated immediately
unique occurrence numbers do not collide after earlier deletions
```

Add paused-rule edit assertions: it updates the same rule/templates, stays paused, and keeps `next_occurrence_at=NULL` until Resume.

- [ ] **Step 2: Add rule cloning/update helpers**

Repository support must include:

```php
public function completeForSplit(int $repeatRuleId, int $userId): bool;
public function updatePausedRule(int $repeatRuleId, int $userId, array $rule): bool;
```

`completeForSplit()` sets `status='completed'` and `next_occurrence_at=NULL` with both ID and user predicates.

- [ ] **Step 3: Add the service's future-scope method**

```php
/**
 * @param array<string, mixed> $repeatPayload
 * @param array<int, mixed> $reminders
 * @return array{
 *   task_id: int,
 *   old_repeat_rule_id: int,
 *   new_repeat_rule_id: int,
 *   deleted_task_ids: array<int, int>,
 *   generated_count: int,
 *   scope: string
 * }
 */
public function updateThisAndFuture(
    int $taskId,
    int $userId,
    string $title,
    DateTimeInterface $dueAt,
    bool $hasTime,
    array $reminders,
    array $repeatPayload
): array;
```

Transaction order:

1. lock the owned task and old rule;
2. validate task/reminders/new repeat rule;
3. mark old rule completed;
4. delete owned future incomplete sibling tasks from selected occurrence number, excluding selected task;
5. create a new rule whose `start_at` is the edited due time;
6. update the selected task fields and attach it to the new rule as occurrence `0`;
7. replace selected reminders and save new rule reminder templates;
8. generate the new 30-day window;
9. commit and return affected IDs.

- [ ] **Step 4: Add edit-from-Recurring-Tasks behavior**

`RepeatService::updateRule()`:

- rejects `cancelled|completed`;
- for `active`, locks the first future incomplete task and delegates to `updateThisAndFuture()`; if no future task exists, creates a new occurrence at `next_occurrence_at` and then delegates;
- for `paused`, validates and updates the same rule/template rows, keeps `paused`, and keeps next occurrence `NULL`.

`RepeatController::updateRule()` parses `repeat_rule_id`, title, canonical start, has-time, reminders, and repeat config, then returns JSON. Register the route only now that the action is complete:

```php
$router->post('/api/repeat-rules/update', [RepeatController::class, 'updateRule'], [AuthMiddleware::class]);
```

- [ ] **Step 5: Connect both edit entry points**

- Task modal `scope=future` includes Repeat and posts to `/api/repeat-tasks/update`.
- Recurring page Edit button opens the same modal prefilled with rule data and no scope choice; it posts to `/api/repeat-rules/update`.
- On success, update/remove the current rule row and show the live-region success message without reloading.

- [ ] **Step 6: Verify the complete edit behavior**

```powershell
php -l App/Repositories/RepeatRuleRepository.php
php -l App/Repositories/TaskRepository.php
php -l App/Services/RepeatService.php
php -l App/Controllers/RepeatController.php
php tests/Backend/repeat-backend.test.php
php tests/Backend/repeat-lifecycle.integration.php
node --test tests/Frontend/repeat-task-edit.test.mjs
node --test tests/Frontend/recurring-tasks.test.mjs
```

Expected: all configured tests pass; both update endpoints are fully implemented and authenticated.

- [ ] **Step 7: Commit scoped series editing**

```powershell
git add App/Repositories/RepeatRuleRepository.php App/Repositories/TaskRepository.php App/Services/RepeatService.php App/Controllers/RepeatController.php routes/api.php views/pages/recurring-tasks.php public/assets/js/modules/recurring-tasks.js public/assets/js/modules/task-modal.js public/assets/js/services/repeat-service.js tests/Backend/repeat-lifecycle.integration.php tests/Frontend/repeat-task-edit.test.mjs
git commit -m "Edit current and future recurring tasks"
```

---

## Task 9: Verify Font Awesome, browser behavior, regression safety, and roadmap state

**Files:**

- Modify: `public/assets/css/fontawesome-subset.css`
- Modify: `public/assets/font/fa-solid-900-subset.woff2`
- Read only: `public/assets/font/fa-solid-900.woff2`
- Modify: `todo.md`

- [ ] **Step 1: Verify the navigation glyph exists in the shipped subset**

The official project stylesheet maps `fa-arrows-rotate` to `U+F021`; add the matching subset rule:

```css
.fa-arrows-rotate { --fa: "\f021"; }
```

Inspect the current subset cmap and regenerate `fa-solid-900-subset.woff2` from the full local source font using the union of the existing codepoints plus `U+F021`:

```powershell
$subsetCodes = python -c "from fontTools.ttLib import TTFont; f=TTFont(r'public/assets/font/fa-solid-900-subset.woff2'); print(','.join('U+%04X' % c for c in sorted(f.getBestCmap())))"
pyftsubset public/assets/font/fa-solid-900.woff2 --unicodes="$subsetCodes,U+F021" --flavor=woff2 --output-file=public/assets/font/fa-solid-900-subset.woff2
```

Then verify the codepoint is present:

```powershell
python -c "from fontTools.ttLib import TTFont; f=TTFont(r'public/assets/font/fa-solid-900-subset.woff2'); assert 0xF021 in f.getBestCmap(); print('fa-arrows-rotate present')"
```

Do not replace the subset with the full Font Awesome font.

- [ ] **Step 2: Run the full focused automated suite**

```powershell
php tests/Backend/repeat-backend.test.php
php tests/Backend/repeat-repository-contract.test.php
php tests/Backend/repeat-controller-contract.test.php
php tests/Backend/repeat-lifecycle.integration.php
node --test tests/Frontend/*.test.mjs
```

Expected: all non-integration tests pass; the integration test passes on the isolated test database or explicitly reports that its test-only DSN is not configured.

- [ ] **Step 3: Run syntax and whitespace checks**

```powershell
$changedPhp = git diff --name-only HEAD~8 -- '*.php'
foreach ($file in $changedPhp) { php -l $file }
git diff --check
```

Expected: all PHP files lint cleanly and `git diff --check` prints nothing.

- [ ] **Step 4: Perform real HTTPS browser QA**

With Apache serving `https://mytodo.php` and Vite running on `5173`, verify:

- direct GET and Fetch navigation to `/recurring-tasks`;
- Toolbar has both Completed and Add New Task;
- filter query strings and browser Back/Forward;
- Pause, Resume, Cancel, Only this, This and future, and recurring-page Edit without reload;
- a weekly Monday rule resumes on the first valid Monday;
- reminders disappear/reappear with generated tasks correctly;
- English/LTR and Persian/RTL at 320, 768, 1024, and 1440 px;
- keyboard focus, Escape/close behavior, live messages, Network responses, and Console.

- [ ] **Step 5: Update the roadmap only after verified behavior**

In `todo.md`, update the review date and mark both Priority 3 remaining items complete. Add concise sub-bullets documenting the standalone Recurring Tasks page, lifecycle semantics, two edit scopes, 30-day regeneration, ownership checks, and bilingual AJAX UI.

- [ ] **Step 6: Final review and commit**

```powershell
git status --short
git diff --check
git add todo.md public/assets/css/fontawesome-subset.css public/assets/font/fa-solid-900-subset.woff2
git commit -m "Verify recurring tasks management"
```

Do not stage `.gitignore`, `.superpowers`, or any unrelated user file.

---

## Final Acceptance Checklist

- [ ] Each user sees only their own recurring rules and can mutate only their own rules/tasks.
- [ ] Pause removes only future incomplete tasks/reminders and stops Cron selection.
- [ ] Resume preserves the original anchor, skips missed dates, and fills the next 30 days.
- [ ] Cancel is permanent and retains history.
- [ ] Single edit preserves the original rule association and occurrence number.
- [ ] Future edit splits the series at the occurrence number and preserves history/completed tasks.
- [ ] Active and paused rule editing follows the approved distinct behavior.
- [ ] Count-limited rules and occurrence numbers remain correct after deletion/regeneration.
- [ ] Recurring Tasks uses the shared toolbar with both buttons and works through direct/AJAX navigation.
- [ ] English/LTR and Persian/RTL render from the same view.
- [ ] No successful action reloads the page.
- [ ] PHP lint, backend tests, frontend tests, browser QA, and `git diff --check` pass.
