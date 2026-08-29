<?php

declare(strict_types=1);

use App\Application;
use App\Database\Database;
use App\Http\Request;
use App\Http\SessionManager;
use App\Config\EnvironmentLoader;

$rootPath = dirname(__DIR__);

require_once $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

EnvironmentLoader::load($rootPath);

$appConfig = require_once $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
$databaseConfig = require_once $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';



date_default_timezone_set($appConfig['timezone']);

SessionManager::start();
$pdo = Database::connect($databaseConfig);

$app = new Application($pdo, $rootPath);
$request = Request::createFromGlobals();
$response = $app->handle($request);

$response->send();
