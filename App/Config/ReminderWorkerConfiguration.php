<?php

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\ReminderWorkerException;

final class ReminderWorkerConfiguration
{
    public readonly int $workers;
    public readonly int $runtimeSeconds;

    /** Validates resource limits before opening worker slots or reserving deliveries. */
    public function __construct(array $configuration)
    {
        $this->workers = $this->readInteger($configuration['workers'] ?? null, 16);
        $this->runtimeSeconds = $this->readInteger($configuration['runtime_seconds'] ?? null, 50);
    }

    /** Rejects malformed numbers instead of silently truncating environment values. */
    private function readInteger(mixed $value, int $maximum): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 1 || $integer > $maximum) {
            throw new ReminderWorkerException('Invalid reminder worker configuration.');
        }

        return $integer;
    }
}
