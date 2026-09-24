<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\TimezoneHelper;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Throwable;

final class RepeatGenerationService
{
    public function __construct(
        private readonly RepeatRuleRepository $repeatRuleRepository,
        private readonly RepeatScheduleCalculator $scheduleCalculator,
        private readonly RepeatOccurrencePlanner $occurrencePlanner,
        private readonly TaskRepository $taskRepository,
        private readonly ReminderService $reminderService,
        private readonly RepeatRuleMapper $ruleMapper
    ) {}

    public function generateUntil(
        DateTimeImmutable $horizon,
        int $ruleLimit = 100,
        int $occurrenceLimit = 500
    ): int {
        $databaseHorizon = $this->ruleMapper->toDatabaseDateTime($horizon);
        $ruleIds = $this->repeatRuleRepository->findDueRuleIds($databaseHorizon, $ruleLimit);
        $generatedTotal = 0;

        foreach ($ruleIds as $ruleId) {
            if ($generatedTotal >= $occurrenceLimit) {
                break;
            }

            $generatedTotal += $this->generateRuleOccurrences(
                $ruleId,
                $horizon,
                $occurrenceLimit - $generatedTotal
            );
        }

        return $generatedTotal;
    }

    public function generateInitialWindow(
        int $repeatRuleId,
        DateTimeImmutable $horizon,
        int $occurrenceLimit = 500
    ): int {
        if (!$this->repeatRuleRepository->inTransaction()) {
            throw new LogicException('The initial repeat window must be generated inside the task transaction.');
        }

        return $this->generateRuleOccurrencesInTransaction(
            $repeatRuleId,
            $horizon,
            max(1, $occurrenceLimit)
        );
    }

    public function generateInTransaction(
        int $repeatRuleId,
        DateTimeImmutable $horizon,
        int $occurrenceLimit,
        ?object $storedRule = null,
        ?DateTimeImmutable $now = null
    ): int {
        return $this->generateRuleOccurrencesInTransaction(
            $repeatRuleId,
            $horizon,
            max(1, $occurrenceLimit),
            $storedRule,
            $now
        );
    }

    private function generateRuleOccurrences(
        int $repeatRuleId,
        DateTimeImmutable $horizon,
        int $occurrenceLimit
    ): int {
        return $this->runInTransaction(fn(): int => $this->generateRuleOccurrencesInTransaction(
            $repeatRuleId,
            $horizon,
            $occurrenceLimit
        ));
    }

    private function generateRuleOccurrencesInTransaction(
        int $repeatRuleId,
        DateTimeImmutable $horizon,
        int $occurrenceLimit,
        ?object $storedRule = null,
        ?DateTimeImmutable $now = null
    ): int {
        // Lifecycle calls supply their ownership-checked, locked row; Cron locks by ID.
        $storedRule ??= $this->repeatRuleRepository->findByIdForUpdate($repeatRuleId);
        if (
            $storedRule === null
            || (string) $storedRule->status !== 'active'
            || $storedRule->next_occurrence_at === null
        ) {
            return 0;
        }

        $timezone = new DateTimeZone((string) $storedRule->timezone);
        $databaseTimezone = TimezoneHelper::getApplicationTimezone();
        $seriesStart = (new DateTimeImmutable((string) $storedRule->start_at, $databaseTimezone))
            ->setTimezone($timezone);
        $nextOccurrence = (new DateTimeImmutable((string) $storedRule->next_occurrence_at, $databaseTimezone))
            ->setTimezone($timezone);
        $rule = $this->ruleMapper->toRuleArray($storedRule);
        $existingRepeatCount = $this->taskRepository->countRepeatsForRule($repeatRuleId, (int) $storedRule->user_id);
        $nextOccurrenceNumber = $this->taskRepository->getMaxOccurrenceNumber($repeatRuleId, (int) $storedRule->user_id) + 1;
        $existingDates = array_fill_keys($this->taskRepository->getOccurrenceDatesForRule(
            $repeatRuleId,
            (int) $storedRule->user_id,
            $this->ruleMapper->toDatabaseDateTime($nextOccurrence),
            $this->ruleMapper->toDatabaseDateTime($horizon)
        ), true);
        $templates = $this->ruleMapper->normalizeReminderTemplates(
            $this->repeatRuleRepository->getReminderTemplates($repeatRuleId)
        );

        $generatedCount = 0;
        while (true) {
            // A retained completed task is already in the materialized count. Plan one
            // candidate at a time so skipping its date never spends that count again.
            $plan = $this->occurrencePlanner->plan(
                $rule, $seriesStart, $nextOccurrence, $horizon, $existingRepeatCount, 1
            );
            if ($plan['occurrences'] === []) {
                break;
            }

            $occurrence = $plan['occurrences'][0];
            $dueAt = $this->ruleMapper->toDatabaseDateTime($occurrence);
            if (isset($existingDates[$dueAt])) {
                $nextOccurrence = $this->scheduleCalculator->nextOccurrence($rule, $seriesStart, $occurrence);
                continue;
            }

            $taskId = $this->taskRepository->create(
                userId: (int) $storedRule->user_id,
                title: (string) $storedRule->title,
                dueAt: $dueAt,
                hasTime: (bool) $storedRule->has_time,
                repeatRuleId: $repeatRuleId,
                repeatOccurrenceNumber: $nextOccurrenceNumber + $generatedCount
            );
            $preparedReminders = $this->reminderService->prepareGeneratedTaskReminders(
                $templates,
                $occurrence,
                (bool) $storedRule->has_time,
                $now
            );

            if ($preparedReminders !== []) {
                $this->reminderService->saveRemindersForTask($taskId, $preparedReminders);
            }

            $generatedCount++;
            $existingRepeatCount = $plan['generated_repeats'];
            if ($plan['next_occurrence'] === null || $generatedCount >= max(1, $occurrenceLimit)) {
                break;
            }
            $nextOccurrence = $plan['next_occurrence'];
        }

        $nextOccurrenceAt = $plan['next_occurrence'] instanceof DateTimeImmutable
            ? $plan['next_occurrence']
                ->setTimezone($databaseTimezone)
                ->format('Y-m-d H:i:s')
            : null;
        $this->repeatRuleRepository->updateGenerationState(
            $repeatRuleId,
            (int) $storedRule->user_id,
            $plan['generated_repeats'],
            $nextOccurrenceAt,
            $plan['status']
        );

        return $generatedCount;
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
}
