<?php
include "constant.php";

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mytodo-sessions';

    if (!is_dir($sessionPath) && !mkdir($sessionPath, 0775, true) && !is_dir($sessionPath)) {
        throw new RuntimeException('Unable to create the session storage directory.');
    }

    session_save_path($sessionPath);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Unable to start the session.');
    }
}

include BASE_PATH."bootstrap/config.php";

include BASE_PATH."libs/helpers.php";

include BASE_PATH."vendor/autoload.php";

try {
    $pdo = new PDO(
        "mysql:dbname=$database_config->db; host={$database_config->host}",
        $database_config->user,
        $database_config->pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    diepage('Connection failed :' . $e->getMessage()) ;
}
include BASE_PATH."libs/lib-auth.php";
include BASE_PATH."libs/lib-tasks.php";
