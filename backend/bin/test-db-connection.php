<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

try {
    $connection = Database::getConnection();
    $result = $connection->query('SELECT 1 AS test');
    $row = $result->fetch_assoc();

    echo 'Database connection successful.' . PHP_EOL;
    echo 'Server info: ' . $connection->server_info . PHP_EOL;
    echo 'Charset: ' . $connection->character_set_name() . PHP_EOL;
    echo 'Test query result: ' . $row['test'] . PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Database connection failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
