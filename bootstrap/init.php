<?php
include "constant.php";

if (session_status() === PHP_SESSION_NONE) {
    if (!session_start()) {
        throw new RuntimeException('Unable to start the session using the configured PHP session path.');
    }
}

include BASE_PATH."bootstrap/config.php";

include BASE_PATH."libs/helpers.php";

include BASE_PATH."vendor/autoload.php";

try {
    $pdo = new PDO("mysql:dbname=$database_config->db; host={$database_config->host}", $database_config->user, $database_config->pass);
} catch (PDOException $e) {
    diepage('Connection failed :' . $e->getMessage()) ;
}
include BASE_PATH."libs/lib-auth.php";
include BASE_PATH."libs/lib-tasks.php";
