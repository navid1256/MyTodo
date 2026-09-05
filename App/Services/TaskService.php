<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TaskNotFoundException;
use App\Exceptions\TaskValidationException;
use App\Helpers\TimezoneHelper;
use App\Repositories\TaskRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final class TaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ReminderService $reminderService,
        private readonly RepeatService $repeatService
    ) {}

    public function validateTitle(string $title): void
    {
        $trimmed = trim($title);

        if ($trimmed === '') {
            throw new TaskValidationException('Task title cannot be empty.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new TaskValidationException('Task title cannot exceed 255 characters.');
        }
    }

    /**
     * @param array<int, mixed> $reminders
     * @param array<string, mixed>|null $repeatConfig
     */
    public function createTask(
        int $userId,
        string $title,
        ?DateTimeInterface $dueAt,
        bool $hasTime = false,
        array $reminders = [],
        ?array $repeatConfig = null
    ): int {
        $this->validateTitle($title);
        $preparedReminders = $this->reminderService->prepareTaskReminders($reminders, $dueAt, $hasTime);
        $preparedRepeatRule = $repeatConfig === null
            ? null
            : $this->repeatService->prepareRule($repeatConfig, $dueAt);

        $this->taskRepository->beginTransaction();

        try {
            $repeatRuleId = null;
            if ($preparedRepeatRule !== null && $dueAt !== null) {
                $repeatRuleId = $this->repeatService->createRule(
                    userId: $userId,
                    title: $title,
                    startAt: $dueAt,
                    hasTime: $hasTime,
                    rule: $preparedRepeatRule,
                    reminders: $preparedReminders
                );
            }

            $formattedDueAt = $dueAt === null
                ? null
                : DateTimeImmutable::createFromInterface($dueAt)
                    ->setTimezone(TimezoneHelper::getApplicationTimezone())
                    ->format('Y-m-d H:i:s');
            $taskId = $this->taskRepository->create(
                userId: $userId,
                title: trim($title),
                dueAt: $formattedDueAt,
                hasTime: $dueAt !== null && $hasTime,
                repeatRuleId: $repeatRuleId,
                repeatOccurrenceNumber: $repeatRuleId === null ? null : 0
            );

            if ($preparedReminders !== []) {
                $this->reminderService->saveRemindersForTask($taskId, $preparedReminders);
            }

            if ($repeatRuleId !== null) {
                $now = new DateTimeImmutable('now', TimezoneHelper::getApplicationTimezone());
                $this->repeatService->generateInitialWindow(
                    $repeatRuleId,
                    $now->modify('+30 days')
                );
            }

            $this->taskRepository->commit();

            return $taskId;
        } catch (Throwable $exception) {
            if ($this->taskRepository->inTransaction()) {
                $this->taskRepository->rollBack();
            }

            throw $exception;
        }
    }

    public function toggleTask(int $taskId, int $userId): object
    {
        if ($taskId <= 0) {
            throw new TaskNotFoundException('Invalid task.');
        }

        $task = $this->taskRepository->findById($taskId, $userId);
        if ($task === null) {
            throw new TaskNotFoundException('Task not found.');
        }

        $isDone = !(bool) $task->is_done;
        $completedAt = $isDone
            ? (new DateTimeImmutable('now', TimezoneHelper::getApplicationTimezone()))->format('Y-m-d H:i:s')
            : null;

        $this->taskRepository->updateStatus($taskId, $userId, $isDone, $completedAt);

        return (object) [
            'id' => $taskId,
            'is_done' => $isDone,
            'completed_at' => $completedAt,
        ];
    }

    public function deleteTask(int $taskId, int $userId): bool
    {
        if ($taskId <= 0) {
            throw new TaskNotFoundException('Invalid task.');
        }

        if (!$this->taskRepository->delete($taskId, $userId)) {
            throw new TaskNotFoundException('Task not found.');
        }

        return true;
    }

    /**
     * @return array<int, object>
     */
    public function getTasksForUser(int $userId): array
    {
        return $this->taskRepository->getTasksForUser($userId);
    }

    /**
     * @return array<int, object>
     */
    public function getTasksForDate(
        int $userId,
        DateTimeInterface $date,
        ?DateTimeInterface $showCompletedSince = null
    ): array {
        return $this->taskRepository->getTasksForDate($userId, $date, $showCompletedSince);
    }

    /**
     * @return array<int, object>
     */
    public function getTasksWithoutDueDate(int $userId, ?DateTimeInterface $showCompletedSince = null): array
    {
        return $this->taskRepository->getTasksWithoutDueDate($userId, $showCompletedSince);
    }

    /**
     * @return array<int, object>
     */
    public function getCompletedTasks(int $userId): array
    {
        return $this->taskRepository->getCompletedTasksForUser($userId);
    }

    public function countCompletedTasksForDate(int $userId, DateTimeInterface $date): int
    {
        return $this->taskRepository->countCompletedTasksForDate($userId, $date);
    }
}
