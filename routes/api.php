<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\NotificationController;
use App\Controllers\ReminderController;
use App\Controllers\SettingsController;
use App\Controllers\TaskController;
use App\Http\Router;
use App\Middleware\AuthMiddleware;

return static function (Router $router): void {
    $router->post('/api/tasks', [TaskController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/api/tasks/create', [TaskController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/api/tasks/toggle', [TaskController::class, 'toggle'], [AuthMiddleware::class]);
    $router->post('/api/tasks/delete', [TaskController::class, 'delete'], [AuthMiddleware::class]);
    $router->post('/api/password/change', [AuthController::class, 'changePassword'], [AuthMiddleware::class]);
    $router->post('/api/notifications/update', [NotificationController::class, 'update'], [AuthMiddleware::class]);
    $router->post('/api/notifications/cancel', [NotificationController::class, 'cancel'], [AuthMiddleware::class]);
    $router->post('/api/reminders/preview', [ReminderController::class, 'preview'], [AuthMiddleware::class]);
    $router->post('/api/settings', [SettingsController::class, 'update'], [AuthMiddleware::class]);
};
