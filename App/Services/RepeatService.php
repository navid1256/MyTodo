<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\TimezoneHelper;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use LogicException;
use Throwable;

final class RepeatService
{
    private const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly RepeatRuleRepository $repeatRuleRepository,
        private readonly RepeatRuleValidator $validator,
        private readonly RepeatScheduleCalculator $scheduleCalculator,
        private readonly RepeatOccurrencePlanner $occurrencePlanner,
        private readonly TaskRepository $taskRepository,
        private readonly ReminderService $reminderService
    ) {}

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
        $databaseTimezone = TimezoneHelper::getApplicationTimezone();

        $repeatRuleId = $this->repeatRuleRepository->create([
            'user_id' => $userId,
            'title' => trim($title),
            'start_at' => $seriesStart->setTimezone($databaseTimezone)->format(self::DATABASE_DATETIME_FORMAT),
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
            'next_occurrence_at' => $nextOccurrence
                ->setTimezone($databaseTimezone)
                ->format(self::DATABASE_DATETIME_FORMAT),
        ]);

        $this->repeatRuleRepository->saveReminderTemplates($repeatRuleId, $reminders);

        return $repeatRuleId;
    }

    public function generateUntil(
        DateTimeImmutable $horizon,
        int $ruleLimit = 100,
        int $occurrenceLimit = 500
    ): int {
        $databaseTimezone = TimezoneHelper::getApplicationTimezone();
        $databaseHorizon = $horizon
            ->setTimezone($databaseTimezone)
            ->format(self::DATABASE_DATETIME_FORMAT);
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

    private function generateRuleOccurrences(
        int $repeatRuleId,
        DateTimeImmutable $horizon,
        int $occurrenceLimit
    ): int {
        $this->repeatRuleRepository->beginTransaction();

        try {
            $generatedCount = $this->generateRuleOccurrencesInTransaction(
                $repeatRuleId,
                $horizon,
                $occurrenceLimit
            );
            $this->repeatRuleRepository->commit();

            return $generatedCount;
        } catch (Throwable $exception) {
            if ($this->repeatRuleRepository->inTransaction()) {
                $this->repeatRuleRepository->rollBack();
            }

            throw $exception;
        }
    }

    private function generateRuleOccurrencesInTransaction(
        int $repeatRuleId,
        DateTimeImmutable $horizon,
        int $occurrenceLimit
    ): int {
        $storedRule = $this->repeatRuleRepository->findByIdForUpdate($repeatRuleId);
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
        $rule = $this->hydrateStoredRule($storedRule);
        $startingGeneratedRepeats = (int) $storedRule->generated_repeats;
        $plan = $this->occurrencePlanner->plan(
            $rule,
            $seriesStart,
            $nextOccurrence,
            $horizon,
            $startingGeneratedRepeats,
            $occurrenceLimit
        );
        $templates = $this->normalizeStoredReminderTemplates(
            $this->repeatRuleRepository->getReminderTemplates($repeatRuleId)
        );

        foreach ($plan['occurrences'] as $index => $occurrence) {
            $occurrenceNumber = $startingGeneratedRepeats + $index + 1;
            $taskId = $this->taskRepository->create(
                userId: (int) $storedRule->user_id,
                title: (string) $storedRule->title,
                dueAt: $occurrence
                    ->setTimezone($databaseTimezone)
                    ->format(self::DATABASE_DATETIME_FORMAT),
                hasTime: (bool) $storedRule->has_time,
                repeatRuleId: $repeatRuleId,
                repeatOccurrenceNumber: $occurrenceNumber
            );
            $preparedReminders = $this->reminderService->prepareGeneratedTaskReminders(
                $templates,
                $occurrence,
                (bool) $storedRule->has_time
            );

            if ($preparedReminders !== []) {
                $this->reminderService->saveRemindersForTask($taskId, $preparedReminders);
            }
        }

        $nextOccurrenceAt = $plan['next_occurrence'] instanceof DateTimeImmutable
            ? $plan['next_occurrence']
                ->setTimezone($databaseTimezone)
                ->format(self::DATABASE_DATETIME_FORMAT)
            : null;
        $this->repeatRuleRepository->updateGenerationState(
            $repeatRuleId,
            $plan['generated_repeats'],
            $nextOccurrenceAt,
            $plan['status']
        );

        return count($plan['occurrences']);
    }

    /**
     * @return array{
     *     interval: int,
     *     unit: string,
     *     week_days: array<int, int>,
     *     month_day: int|null,
     *     month_day_mode: string,
     *     ends: array{type: string, date: string|null, count: int|null}
     * }
     * @throws JsonException
     */
    private function hydrateStoredRule(object $storedRule): array
    {
        $weekDays = $storedRule->week_days === null
            ? []
            : json_decode((string) $storedRule->week_days, true, 512, JSON_THROW_ON_ERROR);

        return [
            'interval' => (int) $storedRule->interval_value,
            'unit' => (string) $storedRule->interval_unit,
            'week_days' => is_array($weekDays) ? array_map('intval', $weekDays) : [],
            'month_day' => $storedRule->month_day === null ? null : (int) $storedRule->month_day,
            'month_day_mode' => (string) $storedRule->month_day_mode,
            'ends' => [
                'type' => (string) $storedRule->end_type,
                'date' => $storedRule->end_date === null ? null : (string) $storedRule->end_date,
                'count' => $storedRule->repeat_count === null ? null : (int) $storedRule->repeat_count,
            ],
        ];
    }

    /**
     * @param array<int, object> $templates
     * @return array<int, array{value: int, unit: string}>
     */
    private function normalizeStoredReminderTemplates(array $templates): array
    {
        return array_map(
            static fn(object $template): array => [
                'value' => (int) $template->offset_value,
                'unit' => (string) $template->offset_unit,
            ],
            $templates
        );
    }
}
