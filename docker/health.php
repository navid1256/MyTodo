<?php

declare(strict_types=1);

// Confirm that Apache executes PHP without connecting to a database or sending notifications.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo "ok\n";
