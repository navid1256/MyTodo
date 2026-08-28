<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ReminderController;
use App\Controllers\TaskController;
use App\Http\Router;
use App\Middleware\AuthMiddleware;

return static function (Router $router): void {
    // Authentication Routes
    $router->get('/auth', [AuthController::class, 'showLogin']);
    $router->post('/auth/login', [AuthController::class, 'login']);
    $router->post('/auth/register', [AuthController::class, 'register']);
    $router->post('/auth/change-password', [AuthController::class, 'changePassword'], [AuthMiddleware::class]);
    $router->post('/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

    // Dashboard & Home Routes
    $router->get('/', [HomeController::class, 'index'], [AuthMiddleware::class]);

    // Tasks Routes
    $router->get('/manage-tasks', [TaskController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/activity', [TaskController::class, 'showActivity'], [AuthMiddleware::class]);
    $router->post('/tasks/create', [TaskController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/tasks/toggle', [TaskController::class, 'toggle'], [AuthMiddleware::class]);
    $router->post('/tasks/delete', [TaskController::class, 'delete'], [AuthMiddleware::class]);

    // Reminders Routes
    $router->post('/reminders/preview', [ReminderController::class, 'preview'], [AuthMiddleware::class]);

    // Notifications Routes
    $router->get('/notifications', [NotificationController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/notifications/update', [NotificationController::class, 'update'], [AuthMiddleware::class]);
    $router->post('/notifications/cancel', [NotificationController::class, 'cancel'], [AuthMiddleware::class]);

    // Profile & Account Settings Routes
    $router->get('/profile', [ProfileController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/profile', [ProfileController::class, 'update'], [AuthMiddleware::class]);
    $router->get('/change-password', [ProfileController::class, 'changePassword'], [AuthMiddleware::class]);
    $router->get('/account-settings', [ProfileController::class, 'accountSettings'], [AuthMiddleware::class]);
};
