<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RepeatRuleNotFoundException;
use App\Exceptions\RepeatRuleStateException;
use App\Exceptions\RepeatValidationException;
use App\Helpers\TimezoneHelper;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

final class RepeatService
{
    private const INVALID_TASK_TITLE = 'Task title is invalid.';

    private readonly RepeatRuleMapper $ruleMapper;
    private readonly RepeatGenerationService $generationService;

    public function __construct(
        private readonly RepeatRuleRepository $repeatRuleRepository,
        private readonly RepeatRuleValidator $validator,
        private readonly RepeatScheduleCalculator $scheduleCalculator,
        private readonly RepeatOccurrencePlanner $occurrencePlanner,
        private readonly TaskRepository $taskRepository,
        private readonly ReminderService $reminderService
    ) {
        $this->ruleMapper = new RepeatRuleMapper();
        $this->generationService = new RepeatGenerationService(
            $repeatRuleRepository,
            $scheduleCalculator,
            $occurrencePlanner,
            $taskRepository,
            $reminderService,
            $this->ruleMapper
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     frequency: string,
     *     interval: int,
     *     unit: string,
     *     week_days: array<int, int>,
     *     month_day: int|null,
     *     month_day_mode: string,
     *     ends: array{type: string, date: string|null, count: int|null}
     * }
     */
    public function prepareRule(array $payload, ?DateTimeInterface $startAt): array
    {
        return $this->validator->validate($payload, $startAt);
    }

    /**
     * @param array{
     *     frequency: string,
     *     interval: int,
     *     unit: string,
     *     week_days: array<int, int>,
     *     month_day: int|null,
     *     month_day_mode: string,
     *     ends: array{type: string, date: string|null, count: int|null}
     * } $rule
     * @param array<int, array{offset_value: int, offset_unit: string}> $reminders
     */
    public function createRule(
        int $userId,
        string $title,
        DateTimeInterface $startAt,
        bool $hasTime,
        array $rule,
        array $reminders
    ): int {
        $seriesStart = DateTimeImmutable::createFromInterface($startAt);
        $nextOccurrence = $this->scheduleCalculator->nextOccurrence($rule, $seriesStart, $seriesStart);

        $repeatRuleId = $this->repeatRuleRepository->create([
            'user_id' => $userId,
            'title' => trim($title),
            'start_at' => $this->ruleMapper->toDatabaseDateTime($seriesStart),
            'has_time' => $hasTime,
            'timezone' => $seriesStart->getTimezone()->getName(),
            'frequency' => $rule['frequency'],
            'interval' => $rule['interval'],
            'unit' => $rule['unit'],
            'week_days' => $rule['week_days'],
            'month_day' => $rule['month_day'],
            'month_day_mode' => $rule['month_day_mode'],
            'end_type' => $rule['ends']['type'],
            'end_date' => $rule['ends']['date'],
            'repeat_count' => $rule['ends']['count'],
            'next_occurrence_at' => $this->ruleMapper->toDatabaseDateTime($nextOccurrence),
        ]);

        $this->repeatRuleRepository->saveReminderTemplates($repeatRuleId, $reminders);

        return $repeatRuleId;
    }

    public function generateUntil(
        DateTimeImmutable $horizon,
        int $ruleLimit = 100,
        int $occurrenceLimit = 500
    ): int {
        return $this->generationService->generateUntil($horizon, $ruleLimit, $occurrenceLimit);
    }

    public function generateInitialWindow(
        int $repeatRuleId,
        DateTimeImmutable $horizon,
        int $occurrenceLimit = 500
    ): int {
        return $this->generationService->generateInitialWindow($repeatRuleId, $horizon, $occurrenceLimit);
    }

    /** @return array<int, object> */
    public function getRulesForUser(int $userId, string $status, DateTimeImmutable $now): array
    {
        $rules = $this->repeatRuleRepository->getForUser($userId, $status, $this->ruleMapper->toDatabaseDateTime($now));
        foreach ($rules as $rule) {
            $rule->reminders = $this->ruleMapper->normalizeReminderTemplates(
                $this->repeatRuleRepository->getReminderTemplates((int) $rule->id)
            );
        }

        return $rules;
    }

    /** @return array{status: string, deleted_task_ids: array<int, int>} */
    public function pauseRule(int $repeatRuleId, int $userId, DateTimeImmutable $now): array
    {
        return $this->runInTransaction(function () use ($repeatRuleId, $userId, $now): array {
            $storedRule = $this->requireOwnedRuleForUpdate($repeatRuleId, $userId);
            if ((string) $storedRule->status === 'paused') {
                return ['status' => 'paused', 'deleted_task_ids' => []];
            }
            if ((string) $storedRule->status !== 'active') {
                throw new RepeatRuleStateException('Only an active repeat rule can be paused.');
            }

            $deletedTaskIds = $this->taskRepository->deleteFutureIncompleteForRule(
                $repeatRuleId, $userId, $this->ruleMapper->toDatabaseDateTime($now)
            );
            $this->repeatRuleRepository->updateStatus($repeatRuleId, $userId, 'paused', null);

            return ['status' => 'paused', 'deleted_task_ids' => $deletedTaskIds];
        });
    }

    /** @return array{status: string, generated_count: int, next_occurrence_at: string|null} */
    public function resumeRule(int $repeatRuleId, int $userId, DateTimeImmutable $now): array
    {
        return $this->runInTransaction(function () use ($repeatRuleId, $userId, $now): array {
            $storedRule = $this->requireOwnedRuleForUpdate($repeatRuleId, $userId);
            if ((string) $storedRule->status === 'active') {
                return ['status' => 'active', 'generated_count' => 0, 'next_occurrence_at' => $storedRule->next_occurrence_at];
            }
            if ((string) $storedRule->status !== 'paused') {
                throw new RepeatRuleStateException('Only a paused repeat rule can be resumed.');
            }

            $candidate = $this->resolveResumeOccurrence($storedRule, $now);
            if ($candidate === null) {
                $this->repeatRuleRepository->updateGenerationState(
                    $repeatRuleId,
                    (int) $storedRule->user_id,
                    $this->taskRepository->countRepeatsForRule($repeatRuleId, $userId),
                    null,
                    'completed'
                );

                return ['status' => 'completed', 'generated_count' => 0, 'next_occurrence_at' => null];
            }

            $storedRule->status = 'active';
            $storedRule->next_occurrence_at = $this->ruleMapper->toDatabaseDateTime($candidate);
            $this->repeatRuleRepository->updateStatus($repeatRuleId, $userId, 'active', $storedRule->next_occurrence_at);
            $generatedCount = $this->generationService->generateInTransaction(
                $repeatRuleId,
                $now->modify('+30 days'),
                500,
                $storedRule,
                $now
            );
            $updatedRule = $this->requireOwnedRuleForUpdate($repeatRuleId, $userId);

            return [
                'status' => (string) $updatedRule->status,
                'generated_count' => $generatedCount,
                'next_occurrence_at' => $updatedRule->next_occurrence_at,
            ];
        });
    }

    /** @return array{status: string, deleted_task_ids: array<int, int>} */
    public function cancelRule(int $repeatRuleId, int $userId, DateTimeImmutable $now): array
    {
        return $this->runInTransaction(function () use ($repeatRuleId, $userId, $now): array {
            $storedRule = $this->requireOwnedRuleForUpdate($repeatRuleId, $userId);
            if ((string) $storedRule->status === 'cancelled') {
                return ['status' => 'cancelled', 'deleted_task_ids' => []];
            }
            if (!in_array((string) $storedRule->status, ['active', 'paused'], true)) {
                throw new RepeatRuleStateException('Only an active or paused repeat rule can be cancelled.');
            }

            $deletedTaskIds = $this->taskRepository->deleteFutureIncompleteForRule(
                $repeatRuleId, $userId, $this->ruleMapper->toDatabaseDateTime($now)
            );
            $this->repeatRuleRepository->updateStatus($repeatRuleId, $userId, 'cancelled', null);

            return ['status' => 'cancelled', 'deleted_task_ids' => $deletedTaskIds];
        });
    }

    /**
     * @param array<int, mixed> $reminders
     * @return array{task_id: int, repeat_rule_id: int, scope: string, task: array<string, mixed>, reminders: array<int, array{value: int, unit: string}>}
     */
    public function updateSingleOccurrence(
        int $taskId,
        int $userId,
        string $title,
        DateTimeInterface $dueAt,
        bool $hasTime,
        array $reminders
    ): array {
        return $this->runInTransaction(function () use ($taskId, $userId, $title, $dueAt, $hasTime, $reminders): array {
            $task = $this->taskRepository->findRecurringByIdForUpdate($taskId, $userId);
            if ($task === null) {
                throw new RepeatRuleNotFoundException('Recurring task not found.');
            }
            if ((bool) $task->is_done) {
                throw new RepeatRuleStateException('Completed recurring tasks cannot be edited.');
            }

            $repeatRuleId = (int) $task->repeat_rule_id;
            $rule = $this->requireOwnedRuleForUpdate($repeatRuleId, $userId);
            if (in_array((string) $rule->status, ['completed', 'cancelled'], true)) {
                throw new RepeatRuleStateException('This recurring task is no longer editable.');
            }

            $trimmedTitle = trim($title);
            if ($trimmedTitle === '' || mb_strlen($trimmedTitle) > 255) {
                throw new RepeatValidationException(self::INVALID_TASK_TITLE);
            }

            $preparedReminders = $this->reminderService->prepareTaskReminders($reminders, $dueAt, $hasTime);
            $databaseDueAt = $this->ruleMapper->toDatabaseDateTime($dueAt);
            $this->taskRepository->update($taskId, $userId, [
                'title' => $trimmedTitle,
                'due_at' => $databaseDueAt,
                'has_time' => $hasTime ? 1 : 0,
            ]);
            $this->reminderService->saveRemindersForTask($taskId, $preparedReminders);

            $updatedTask = $this->taskRepository->findById($taskId, $userId);
            $updatedReminders = array_map(
                static fn(array $reminder): array => [
                    'value' => (int) $reminder['offset_value'],
                    'unit' => (string) $reminder['offset_unit'],
                ],
                $preparedReminders
            );

            return [
                'task_id' => $taskId,
                'repeat_rule_id' => $repeatRuleId,
                'scope' => 'single',
                'task' => [
                    'id' => $taskId,
                    'title' => $trimmedTitle,
                    'due_at' => $updatedTask?->due_at ?? $databaseDueAt,
                    'has_time' => $updatedTask === null ? $hasTime : (bool) $updatedTask->has_time,
                    'repeat_rule_id' => $repeatRuleId,
                    'repeat_occurrence_number' => (int) $task->repeat_occurrence_number,
                ],
                'reminders' => $updatedReminders,
            ];
        });
    }

    /**
     * Split a recurring series at the selected occurrence. The selected task
     * becomes occurrence zero of a new rule; history and completed siblings
     * remain attached to the terminal old rule.
     *
     * @param array<string, mixed> $repeatConfig
     * @param array<int, mixed> $reminders
     * @return array<string, mixed>
     */
    public function updateThisAndFuture(
        int $taskId,
        int $userId,
        string $title,
        DateTimeInterface $dueAt,
        bool $hasTime,
        array $reminders,
        array $repeatConfig
    ): array {
        return $this->runInTransaction(function () use ($taskId, $userId, $title, $dueAt, $hasTime, $repeatConfig, $reminders): array {
            $task = $this->taskRepository->findRecurringByIdForUpdate($taskId, $userId);
            if ($task === null) {
                throw new RepeatRuleNotFoundException('Recurring task not found.');
            }
            if ((bool) $task->is_done) {
                throw new RepeatRuleStateException('Completed recurring tasks cannot be edited.');
            }

            $oldRuleId = (int) $task->repeat_rule_id;
            $oldRule = $this->requireOwnedRuleForUpdate($oldRuleId, $userId);
            if (in_array((string) $oldRule->status, ['completed', 'cancelled'], true)) {
                throw new RepeatRuleStateException('This recurring task is no longer editable.');
            }

            $trimmedTitle = trim($title);
            if ($trimmedTitle === '' || mb_strlen($trimmedTitle) > 255) {
                throw new RepeatValidationException(self::INVALID_TASK_TITLE);
            }
            $preparedRule = $this->validator->validate($repeatConfig, $dueAt);
            $preparedReminders = $this->reminderService->prepareTaskReminders($reminders, $dueAt, $hasTime);
            $this->repeatRuleRepository->completeForSplit($oldRuleId, $userId);
            $deletedTaskIds = $this->taskRepository->deleteIncompleteFromOccurrence(
                $oldRuleId,
                $userId,
                $this->ruleMapper->toDatabaseDateTime($dueAt),
                (int) $task->repeat_occurrence_number,
                $taskId
            );
            $newRuleId = $this->createRule(
                userId: $userId,
                title: $trimmedTitle,
                startAt: $dueAt,
                hasTime: $hasTime,
                rule: $preparedRule,
                reminders: $preparedReminders
            );
            $databaseDueAt = $this->ruleMapper->toDatabaseDateTime($dueAt);
            $this->taskRepository->update($taskId, $userId, [
                'title' => $trimmedTitle,
                'due_at' => $databaseDueAt,
                'has_time' => $hasTime ? 1 : 0,
            ]);
            $this->taskRepository->attachToRule($taskId, $userId, $newRuleId, 0);
            $this->reminderService->saveRemindersForTask($taskId, $preparedReminders);

            $generatedCount = $this->generateInitialWindow(
                $newRuleId,
                (new DateTimeImmutable('now', TimezoneHelper::getApplicationTimezone()))->modify('+30 days')
            );
            $updatedTask = $this->taskRepository->findById($taskId, $userId);

            return [
                'scope' => 'future',
                'task_id' => $taskId,
                'old_repeat_rule_id' => $oldRuleId,
                'repeat_rule_id' => $newRuleId,
                'new_repeat_rule_id' => $newRuleId,
                'deleted_task_ids' => $deletedTaskIds,
                'generated_count' => $generatedCount,
                'task' => [
                    'id' => $taskId,
                    'title' => $trimmedTitle,
                    'due_at' => $updatedTask?->due_at ?? $databaseDueAt,
                    'has_time' => $updatedTask === null ? $hasTime : (bool) $updatedTask->has_time,
                    'repeat_rule_id' => $newRuleId,
                    'repeat_occurrence_number' => 0,
                ],
            ];
        });
    }

    /**
     * Edit a rule from the recurring-rules page. Active rules split at their
     * first future incomplete occurrence; paused rules retain their identity.
     *
     * @param array<string, mixed> $repeatConfig
     * @param array<int, mixed> $reminders
     * @return array<string, mixed>
     */
    public function updateRule(RepeatRuleUpdateData $update): array
    {
        $now = $update->now ?? new DateTimeImmutable('now', TimezoneHelper::getApplicationTimezone());

        return $this->runInTransaction(function () use ($update, $now): array {
            $storedRule = $this->requireOwnedRuleForUpdate($update->repeatRuleId, $update->userId);
            $status = (string) $storedRule->status;
            if (in_array($status, ['completed', 'cancelled'], true)) {
                throw new RepeatRuleStateException('Completed or cancelled recurring rules cannot be edited.');
            }

            $trimmedTitle = trim($update->title);
            if ($trimmedTitle === '' || mb_strlen($trimmedTitle) > 255) {
                throw new RepeatValidationException(self::INVALID_TASK_TITLE);
            }
            $preparedRule = $this->validator->validate($update->repeatConfig, $update->dueAt);
            $preparedReminders = $this->reminderService->prepareTaskReminders($update->reminders, $update->dueAt, $update->hasTime);
            $ruleData = [
                'title' => $trimmedTitle,
                'start_at' => $this->ruleMapper->toDatabaseDateTime($update->dueAt),
                'has_time' => $update->hasTime,
                'timezone' => $update->dueAt->getTimezone()->getName(),
                'frequency' => $preparedRule['frequency'],
                'interval' => $preparedRule['interval'],
                'unit' => $preparedRule['unit'],
                'week_days' => $preparedRule['week_days'],
                'month_day' => $preparedRule['month_day'],
                'month_day_mode' => $preparedRule['month_day_mode'],
                'end_type' => $preparedRule['ends']['type'],
                'end_date' => $preparedRule['ends']['date'],
                'repeat_count' => $preparedRule['ends']['count'],
                'next_occurrence_at' => null,
            ];

            if ($status === 'paused') {
                $this->repeatRuleRepository->updatePausedRule($update->repeatRuleId, $update->userId, $ruleData);
                $this->repeatRuleRepository->replaceReminderTemplates($update->repeatRuleId, $update->userId, $preparedReminders);

                return [
                    'scope' => 'rule',
                    'repeat_rule_id' => $update->repeatRuleId,
                    'status' => 'paused',
                    'generated_count' => 0,
                ];
            }

            $selected = $this->taskRepository->findFirstFutureIncompleteForRule(
                $update->repeatRuleId,
                $update->userId,
                $this->ruleMapper->toDatabaseDateTime($now)
            );
            if ($selected === null) {
                if ($storedRule->next_occurrence_at === null) {
                    throw new RepeatRuleStateException('This recurring rule has no future occurrence to edit.');
                }
                $selectedDueAt = new DateTimeImmutable((string) $storedRule->next_occurrence_at, TimezoneHelper::getApplicationTimezone());
                $selectedId = $this->taskRepository->create(
                    userId: $update->userId,
                    title: (string) $storedRule->title,
                    dueAt: $this->ruleMapper->toDatabaseDateTime($selectedDueAt),
                    hasTime: (bool) $storedRule->has_time,
                    repeatRuleId: $update->repeatRuleId,
                    repeatOccurrenceNumber: $this->taskRepository->getMaxOccurrenceNumber($update->repeatRuleId, $update->userId) + 1
                );
            } else {
                $selectedId = (int) $selected->id;
            }

            return $this->updateThisAndFuture(
                $selectedId,
                $update->userId,
                $trimmedTitle,
                $update->dueAt,
                $update->hasTime,
                $update->reminders,
                $update->repeatConfig
            ) + ['scope' => 'rule'];
        });
    }

    /** @return array<string, mixed>|null */
    public function getTaskEditPayload(int $taskId, int $userId): ?array
    {
        $task = $this->taskRepository->findById($taskId, $userId);
        if ($task === null || (bool) $task->is_done || $task->repeat_rule_id === null) {
            return null;
        }

        $rule = $this->repeatRuleRepository->findByIdForUserForUpdate((int) $task->repeat_rule_id, $userId);
        if ($rule === null || in_array((string) $rule->status, ['completed', 'cancelled'], true)) {
            return null;
        }

        $reminders = array_map(
            static fn(object $reminder): array => [
                'value' => (int) $reminder->offset_value,
                'unit' => (string) $reminder->offset_unit,
            ],
            $this->reminderService->getRemindersForTask($taskId)
        );

        return [
            'task_id' => (int) $task->id,
            'title' => (string) $task->title,
            'due_at' => $task->due_at === null ? '' : (new DateTimeImmutable((string) $task->due_at, TimezoneHelper::getApplicationTimezone()))->format('Y-m-d\\TH:i'),
            'has_time' => (bool) $task->has_time,
            'repeat_rule_id' => (int) $task->repeat_rule_id,
            'repeat_occurrence_number' => (int) $task->repeat_occurrence_number,
            'reminders' => $reminders,
            'repeat_config' => $this->ruleMapper->toRuleArray($rule) + ['frequency' => (string) $rule->frequency],
        ];
    }

    private function requireOwnedRuleForUpdate(int $repeatRuleId, int $userId): object
    {
        $storedRule = $this->repeatRuleRepository->findByIdForUserForUpdate($repeatRuleId, $userId);
        if ($storedRule === null) {
            throw new RepeatRuleNotFoundException('Repeat rule not found.');
        }

        return $storedRule;
    }

    private function runInTransaction(callable $operation): mixed
    {
        $startedTransaction = !$this->repeatRuleRepository->inTransaction();
        if ($startedTransaction) {
            $this->repeatRuleRepository->beginTransaction();
        }

        try {
            $result = $operation();
            if ($startedTransaction) {
                $this->repeatRuleRepository->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->repeatRuleRepository->inTransaction()) {
                $this->repeatRuleRepository->rollBack();
            }

            throw $exception;
        }
    }

    private function resolveResumeOccurrence(object $storedRule, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $timezone = new DateTimeZone((string) $storedRule->timezone);
        $seriesStart = (new DateTimeImmutable((string) $storedRule->start_at, TimezoneHelper::getApplicationTimezone()))
            ->setTimezone($timezone);
        $localNow = $now->setTimezone($timezone);
        $candidate = $this->scheduleCalculator->nextOccurrence(
            $this->ruleMapper->toRuleArray($storedRule),
            $seriesStart,
            $localNow > $seriesStart ? $localNow : $seriesStart
        );
        $existingRepeatCount = $this->taskRepository->countRepeatsForRule((int) $storedRule->id, (int) $storedRule->user_id);

        return $this->isRuleExhausted($storedRule, $existingRepeatCount, $candidate) ? null : $candidate;
    }

    private function isRuleExhausted(object $storedRule, int $existingRepeatCount, DateTimeImmutable $candidate): bool
    {
        $seriesStart = (new DateTimeImmutable((string) $storedRule->start_at, TimezoneHelper::getApplicationTimezone()))
            ->setTimezone(new DateTimeZone((string) $storedRule->timezone));
        $plan = $this->occurrencePlanner->plan(
            $this->ruleMapper->toRuleArray($storedRule), $seriesStart, $candidate, $candidate, $existingRepeatCount, 1
        );

        return $plan['occurrences'] === [];
    }

}
