<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReminderValidationException;
use App\Helpers\TimezoneHelper;
use App\Repositories\ReminderRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

final class ReminderService
{
    private const MAX_REMINDERS = 5;
    private const UNIT_MINUTES = [
        'minute' => 1,
        'hour' => 60,
        'day' => 1440,
    ];

    public function __construct(private readonly ReminderRepository $reminderRepository) {}

    /**
     * @param array<int, mixed> $reminders
     * @return array<int, array{offset_value: int, offset_unit: string, offset_minutes: int, remind_at: DateTimeImmutable}>
     */
    public function prepareTaskReminders(
        array $reminders,
        ?DateTimeInterface $dueAt,
        bool $hasTime
    ): array {
        if ($reminders === []) {
            return [];
        }

        if (count($reminders) > self::MAX_REMINDERS) {
            throw new ReminderValidationException('You can set up to 5 reminders for each task.');
        }

        if ($dueAt === null || !$hasTime) {
            throw new ReminderValidationException('Please Set Date And Time before adding reminders.');
        }

        $dueDate = DateTimeImmutable::createFromInterface($dueAt);
        $now = new DateTimeImmutable('now', $dueDate->getTimezone());
        $preparedReminders = [];
        $usedOffsets = [];

        foreach (array_values($reminders) as $index => $reminder) {
            $preparedReminders[] = $this->parseSingleReminder(
                reminder: $reminder,
                reminderNumber: $index + 1,
                dueDate: $dueDate,
                now: $now,
                usedOffsets: $usedOffsets
            );
        }

        return $preparedReminders;
    }

    /**
     * @param array<int, array{offset_value: int, offset_unit: string, remind_at: DateTimeImmutable}> $preparedReminders
     */
    public function saveRemindersForTask(int $taskId, array $preparedReminders): void
    {
        $this->reminderRepository->deleteByTaskId($taskId);

        foreach ($preparedReminders as $reminder) {
            $this->reminderRepository->create(
                taskId: $taskId,
                offsetValue: $reminder['offset_value'],
                offsetUnit: $reminder['offset_unit'],
                remindAt: $reminder['remind_at']
                    ->setTimezone(TimezoneHelper::getApplicationTimezone())
                    ->format('Y-m-d H:i:s')
            );
        }
    }

    public function deleteRemindersForTask(int $taskId): bool
    {
        return $this->reminderRepository->deleteByTaskId($taskId);
    }

    /**
     * @return array<int, object>
     */
    public function getRemindersForTask(int $taskId): array
    {
        return $this->reminderRepository->getByTaskId($taskId);
    }

    /**
     * @param array<int, bool> $usedOffsets
     * @return array{offset_value: int, offset_unit: string, offset_minutes: int, remind_at: DateTimeImmutable}
     */
    private function parseSingleReminder(
        mixed $reminder,
        int $reminderNumber,
        DateTimeImmutable $dueDate,
        DateTimeImmutable $now,
        array &$usedOffsets
    ): array {
        [$value, $unit] = $this->extractReminderInputs($reminder, $reminderNumber);
        $offsetMinutes = $this->calculateOffsetMinutes($value, $unit, $reminderNumber);

        if (isset($usedOffsets[$offsetMinutes])) {
            throw new ReminderValidationException('Each reminder must use a different notification time.');
        }

        $remindAt = $dueDate->sub(new DateInterval("PT{$offsetMinutes}M"));
        if ($remindAt <= $now) {
            throw new ReminderValidationException(
                "Reminder {$reminderNumber} would be scheduled in the past. Choose a later due time or a shorter reminder."
            );
        }

        $usedOffsets[$offsetMinutes] = true;

        return [
            'offset_value' => $value,
            'offset_unit' => $unit,
            'offset_minutes' => $offsetMinutes,
            'remind_at' => $remindAt,
        ];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function extractReminderInputs(mixed $reminder, int $reminderNumber): array
    {
        if (!is_array($reminder)) {
            throw new ReminderValidationException("Reminder {$reminderNumber} is invalid.");
        }

        $value = $reminder['value'] ?? null;
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (!is_int($value) || $value < 0) {
            throw new ReminderValidationException("Reminder {$reminderNumber} has an invalid reminder time.");
        }

        $unit = isset($reminder['unit']) && is_string($reminder['unit'])
            ? strtolower(trim($reminder['unit']))
            : '';
        $unit = rtrim($unit, 's');

        if (!isset(self::UNIT_MINUTES[$unit])) {
            throw new ReminderValidationException("Reminder {$reminderNumber} has an invalid time unit.");
        }

        return [$value, $unit];
    }

    private function calculateOffsetMinutes(int $value, string $unit, int $reminderNumber): int
    {
        if ($value === 0 && $unit !== 'minute') {
            throw new ReminderValidationException("Reminder {$reminderNumber} must use minutes for On due time.");
        }

        $unitMultiplier = self::UNIT_MINUTES[$unit];
        $maximumValue = intdiv(525600, $unitMultiplier);

        if ($value > $maximumValue) {
            throw new ReminderValidationException("Reminder {$reminderNumber} cannot be more than one year before the due time.");
        }

        return $value * $unitMultiplier;
    }
}
