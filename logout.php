<?php
include "bootstrap/init.php";

use App\Controllers\AuthController;
use App\Services\AuthService;

$controller = new AuthController(new AuthService());
$response = $controller->logout(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_POST['csrf_token'] ?? null
);
$response->send();
