<?php

declare(strict_types=1);

use App\Config\ReminderWorkerConfiguration;
use App\Exceptions\ReminderWorkerException;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$configuration = new ReminderWorkerConfiguration(['workers' => '4', 'runtime_seconds' => '45']);
if ($configuration->workers !== 4 || $configuration->runtimeSeconds !== 45) {
    throw new RuntimeException('Worker settings must preserve validated values.');
}
foreach ([['workers' => '0'], ['workers' => '17'], ['workers' => '4oops'], ['runtime_seconds' => '0'], ['runtime_seconds' => '51']] as $invalid) {
    try {
        new ReminderWorkerConfiguration($invalid + ['workers' => '4', 'runtime_seconds' => '45']);
    } catch (ReminderWorkerException) {
        continue;
    }
    throw new RuntimeException('Invalid worker settings must fail before spawning or reserving.');
}
echo "PASS worker count and runtime configuration boundaries\n";
