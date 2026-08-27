<?php

$database_config = (object)[
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db' => 'mytodo',
    'port' => '3306'
];

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
    diepage('Connection failed :' . $e->getMessage());
}
