<?php

declare(strict_types=1);

if ($argc !== 2 || $argv[1] !== '--database=mytodo') {
    fwrite(STDERR, "REFUSED isolated Repeat integration: explicitly pass --database=mytodo to authorize fixture tables in the configured mytodo database.\n");
    exit(2);
}

require_once __DIR__ . '/fixtures/isolated-repeat-test-connection.php';

$connection = null;
$originalSnapshot = null;
try {
    $connection = new IsolatedRepeatTestConnection();
    $originalSnapshot = $connection->snapshotApplicationTables();
    echo 'Isolated Repeat fixture namespace: ' . $connection->prefix . "\n";
    $connection->createFixtureTables();
    $isolatedRepeatConnection = $connection;
    require __DIR__ . '/repeat-lifecycle.integration.php';
} finally {
    if ($connection !== null) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        $connection->dropFixtureTables();
        if ($originalSnapshot !== null && $connection->snapshotApplicationTables() !== $originalSnapshot) {
            throw new RuntimeException('Application table snapshot changed during the test; investigate concurrent activity before continuing.');
        }
        echo "Fixture tables removed; original row counts, checksums and schema hashes unchanged.\n";
    }
}

echo "PASS isolated Repeat integration on mytodo; application rows were never fixture targets.\n";
