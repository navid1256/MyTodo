<?php

declare(strict_types=1);

namespace App\Services;

use App\Notifications\ReminderDeliverySummary;
use InvalidArgumentException;

final class ReminderWorkerPool
{
    /** Starts a bounded set of PHP children before waiting for their sanitized summaries. */
    public function run(array $command, int $workers): array
    {
        if ($workers < 1 || $workers > 16 || $command === []) {
            throw new InvalidArgumentException('Invalid reminder worker pool.');
        }

        $children = $this->startWorkers($command, $workers);

        return $this->collectReports($children);
    }

    /** Starts every child before waiting for reports; null marks a failed process launch. */
    private function startWorkers(array $command, int $workers): array
    {
        $children = [];
        for ($slot = 1; $slot <= $workers; $slot++) {
            $childCommand = $command;
            $childCommand[] = '--worker=' . $slot;
            $outputChannels = [
                1 => ['pipe', 'w'], // The child writes its JSON report to stdout.
                2 => ['pipe', 'w'], // The child writes private errors to stderr.
            ];
            // Array commands bypass the shell, preserving paths with spaces on Windows.
            $process = @proc_open($childCommand, $outputChannels, $pipes);
            if (!is_resource($process)) {
                $children[] = null;
                continue;
            }
            $children[] = [
                'process' => $process,
                'stdout' => $pipes[1],
                'stderr' => $pipes[2],
            ];
        }

        return $children;
    }

    /** Combines validated child reports and counts failed launches or occupied worker slots. */
    private function collectReports(array $children): array
    {
        $summary = ReminderDeliverySummary::create();
        $summary['busy_workers'] = 0;
        $summary['failed_workers'] = 0;

        foreach ($children as $child) {
            if ($child === null) {
                $summary['failed_workers']++;
                continue;
            }

            $report = $this->readWorkerReport($child);
            if ($report === null) {
                $summary['failed_workers']++;
                continue;
            }
            if ($report['status'] === 'busy') {
                $summary['busy_workers']++;
                continue;
            }

            $summary = ReminderDeliverySummary::merge($summary, $report['summary']);
        }

        return $summary;
    }

    /** Closes a child's resources and returns its valid report, never its private error output. */
    private function readWorkerReport(array $child): ?array
    {
        $stdout = stream_get_contents($child['stdout']);
        // Raw child errors can contain credentials and must never be forwarded.
        stream_get_contents($child['stderr']);
        fclose($child['stdout']);
        fclose($child['stderr']);
        $exitCode = proc_close($child['process']);

        $report = json_decode((string) $stdout, true);
        if ($exitCode !== 0 || !is_array($report)) {
            return null;
        }
        if (($report['status'] ?? null) === 'busy') {
            return $report;
        }
        if (($report['status'] ?? null) !== 'completed'
            || !ReminderDeliverySummary::isValid($report['summary'] ?? null)) {
            return null;
        }

        return $report;
    }
}
