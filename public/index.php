<?php

declare(strict_types=1);

use App\Application;
use App\Config\EnvironmentLoader;
use App\Database\Database;
use App\Exceptions\ExceptionHandler;
use App\Http\Request;
use App\Http\SessionManager;

$rootPath = dirname(__DIR__);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

require_once $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$exceptionHandler = new ExceptionHandler($rootPath);
$exceptionHandler->register();

EnvironmentLoader::load($rootPath);

$appConfig = require_once $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
$databaseConfig = require_once $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

$debug = $appConfig['environment'] === 'local' && $appConfig['debug'];
$exceptionHandler->setDebug($debug);

date_default_timezone_set($appConfig['timezone']);

SessionManager::start();
$pdo = Database::connect($databaseConfig);

$app = new Application($pdo, $rootPath);
$request = Request::createFromGlobals();
$response = $app->handle($request);

$response->send();
