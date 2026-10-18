<?php

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\ReminderWorkerException;

final class ReminderWorkerArguments
{
    private const INVALID_ARGUMENTS = 'Real delivery requires the explicit --send option and valid arguments. Use --help for usage.';

    public readonly ?int $workerSlot;
    public readonly ?int $reminderId;
    public readonly ?int $subscriptionId;

    /** Validates the entire command before environment, database or provider initialization. */
    public function __construct(array $arguments)
    {
        [$slot, $reminderId, $subscriptionId] = $this->parse($arguments);
        $this->workerSlot = $slot;
        $this->reminderId = $reminderId;
        $this->subscriptionId = $subscriptionId;
    }

    /**
     * Separates launcher, child and single-target modes without an unsafe fallback.
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private function parse(array $arguments): array
    {
        if (($arguments[0] ?? null) !== '--send') {
            throw new ReminderWorkerException(self::INVALID_ARGUMENTS);
        }
        if (count($arguments) === 1) {
            return [null, null, null];
        }
        if (count($arguments) === 2
            && preg_match('/\A--worker=([1-9]|1[0-6])\z/', $arguments[1], $matches) === 1) {
            return [(int) $matches[1], null, null];
        }
        if (count($arguments) === 3) {
            return $this->parseTarget(array_slice($arguments, 1));
        }

        throw new ReminderWorkerException(self::INVALID_ARGUMENTS);
    }

    /**
     * Requires two distinct positive IDs and rejects duplicate, malformed or overflowing values.
     * @return array{0: null, 1: int, 2: int}
     */
    private function parseTarget(array $arguments): array
    {
        $ids = [];
        foreach ($arguments as $argument) {
            if (preg_match('/\A--(reminder-id|subscription-id)=([1-9][0-9]*)\z/', $argument, $matches) !== 1
                || isset($ids[$matches[1]])) {
                throw new ReminderWorkerException(self::INVALID_ARGUMENTS);
            }
            $id = filter_var($matches[2], FILTER_VALIDATE_INT);
            if ($id === false) {
                throw new ReminderWorkerException(self::INVALID_ARGUMENTS);
            }
            $ids[$matches[1]] = $id;
        }

        return [null, $ids['reminder-id'], $ids['subscription-id']];
    }
}
