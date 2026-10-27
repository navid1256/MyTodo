<?php

declare(strict_types=1);

use App\Config\EnvironmentLoader;
use App\Database\Database;
use App\Helpers\TimezoneHelper;
use App\Repositories\ReminderRepository;
use App\Repositories\RepeatRuleRepository;
use App\Repositories\TaskRepository;
use App\Services\ReminderService;
use App\Services\RepeatOccurrencePlanner;
use App\Services\RepeatRuleValidator;
use App\Services\RepeatScheduleCalculator;
use App\Services\RepeatService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$rootPath = dirname(__DIR__);

require_once $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

EnvironmentLoader::load($rootPath);
$databaseConfig = require $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
$pdo = Database::connect($databaseConfig);

$taskRepository = new TaskRepository($pdo);
$reminderService = new ReminderService(new ReminderRepository($pdo));
$repeatScheduleCalculator = new RepeatScheduleCalculator();
$repeatService = new RepeatService(
    new RepeatRuleRepository($pdo),
    new RepeatRuleValidator(),
    $repeatScheduleCalculator,
    new RepeatOccurrencePlanner($repeatScheduleCalculator),
    $taskRepository,
    $reminderService
);
$now = new DateTimeImmutable('now', TimezoneHelper::getApplicationTimezone());
$generatedCount = $repeatService->generateUntil($now->modify('+30 days'));

fwrite(STDOUT, "Generated {$generatedCount} repeat task(s)." . PHP_EOL);
