<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class ReminderWorkerPool
{
    /** Starts a bounded set of PHP children before waiting for their sanitized summaries. */
    public function run(array $command, int $workers): array
    {
        if ($workers < 1 || $workers > 16 || $command === []) {
            throw new InvalidArgumentException('Invalid reminder worker pool.');
        }
        $summary = [
            'claimed' => 0, 'accepted' => 0, 'retryable' => 0, 'expired' => 0,
            'failed' => 0, 'unknown' => 0, 'skipped' => 0,
            'busy_workers' => 0, 'failed_workers' => 0,
        ];
        $children = [];
        for ($slot = 1; $slot <= $workers; $slot++) {
            // Array commands bypass the shell, preserving paths with spaces on Windows.
            $process = @proc_open([...$command, '--worker=' . $slot], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                $summary['failed_workers']++;
                continue;
            }
            $children[] = [$process, $pipes];
        }
        foreach ($children as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            // Raw child errors can contain credentials and must never be forwarded.
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            $report = json_decode((string) $stdout, true);
            if ($status !== 0 || !is_array($report)) {
                $summary['failed_workers']++;
                continue;
            }
            if (($report['status'] ?? null) === 'busy') {
                $summary['busy_workers']++;
                continue;
            }
            if (!$this->isValidReport($report)) {
                $summary['failed_workers']++;
                continue;
            }
            foreach ($report['summary'] as $outcome => $count) {
                $summary[$outcome] += $count;
            }
        }

        return $summary;
    }

    /** Accepts only known integer counters from a child, excluding arbitrary output. */
    private function isValidReport(array $report): bool
    {
        $outcomes = ['claimed', 'accepted', 'retryable', 'expired', 'failed', 'unknown', 'skipped'];
        if (($report['status'] ?? null) !== 'completed' || !is_array($report['summary'] ?? null)
            || count($report['summary']) !== count($outcomes)) {
            return false;
        }
        foreach ($outcomes as $outcome) {
            $count = $report['summary'][$outcome] ?? null;
            if (!is_int($count) || $count < 0) {
                return false;
            }
        }

        return true;
    }
}
