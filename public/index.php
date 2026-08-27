<?php

declare(strict_types=1);

use App\Database\Database;
use App\Http\Request;
use App\Http\Response;
use App\Http\SessionManager;

$rootPath = dirname(__DIR__);

require_once $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$appConfig = require_once $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
$databaseConfig = require_once $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

date_default_timezone_set($appConfig['timezone']);

SessionManager::start();
$pdo = Database::connect($databaseConfig);

$request = Request::createFromGlobals();
