<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ReminderController;
use App\Controllers\SettingsController;
use App\Controllers\TaskController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\NotificationRepository;
use App\Repositories\ReminderRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSettingsRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\ProfileService;
use App\Services\ReminderService;
use App\Services\TaskService;
use App\Services\UserSettingsService;
use PDO;

final class Application
{
    private Router $router;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $rootPath
    ) {
        $this->router = new Router();
        $this->registerDependencies();
        $this->loadRoutes();
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }

    private function registerDependencies(): void
    {
        $userRepository = new UserRepository($this->pdo);
        $taskRepository = new TaskRepository($this->pdo);
        $reminderRepository = new ReminderRepository($this->pdo);
        $notificationRepository = new NotificationRepository($this->pdo);
        $userSettingsRepository = new UserSettingsRepository($this->pdo);

        $authService = new AuthService($userRepository);
        $reminderService = new ReminderService($reminderRepository);
        $taskService = new TaskService($taskRepository, $reminderService);
        $notificationService = new NotificationService($notificationRepository, $reminderService);
        $profileService = new ProfileService($userRepository);
        $userSettingsService = new UserSettingsService($userSettingsRepository);

        $this->router->bind(AuthController::class, new AuthController($authService));
        $this->router->bind(HomeController::class, new HomeController(
            $taskService,
            $notificationService,
            $authService,
            $userRepository,
            $userSettingsService
        ));
        $this->router->bind(TaskController::class, new TaskController(
            $taskService,
            $authService,
            $userRepository,
            $notificationService,
            $userSettingsService
        ));
        $this->router->bind(ReminderController::class, new ReminderController(
            $reminderService,
            $authService,
            $userSettingsService
        ));
        $this->router->bind(NotificationController::class, new NotificationController(
            $notificationService,
            $authService,
            $userRepository,
            $userSettingsService
        ));
        $this->router->bind(ProfileController::class, new ProfileController(
            $profileService,
            $userRepository,
            $userSettingsService
        ));
        $this->router->bind(SettingsController::class, new SettingsController(
            $userSettingsService,
            $authService,
            $userRepository
        ));
    }

    private function loadRoutes(): void
    {
        $webRoutes = require_once $this->rootPath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $webRoutes($this->router);

        $apiFile = $this->rootPath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        if (is_file($apiFile)) {
            $apiRoutes = require_once $apiFile;
            $apiRoutes($this->router);
        }
    }
}
