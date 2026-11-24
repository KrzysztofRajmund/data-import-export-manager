<?php

declare(strict_types=1);

// Copy this file to config.local.php and adjust the values for your local environment.
return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'username' => 'root',
        'password' => '',
        'database' => 'data_import_export_manager',
        'charset' => 'utf8mb4',
    ],
    'worker' => [
        'url' => 'http://127.0.0.1:3001/process-batch',
        'apiKey' => 'local-worker-secret',
        'timeoutMs' => 2000,
    ],
];
