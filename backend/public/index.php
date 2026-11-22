<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$autoloaded = is_file($autoloadPath);

if ($autoloaded) {
    require $autoloadPath;
}

$appConfig = require __DIR__ . '/../config/app.php';

date_default_timezone_set($appConfig['timezone']);
ini_set('display_errors', $appConfig['display_errors'] ? '1' : '0');

header('Content-Type: text/plain; charset=' . $appConfig['charset']);

echo 'Data Import & Export Manager backend is running.' . PHP_EOL;
echo 'PHP version: ' . PHP_VERSION . PHP_EOL;

if ($autoloaded && class_exists(\App\Services\SystemInfoService::class)) {
    $status = (new \App\Services\SystemInfoService())->getStatus();
    echo 'PSR-4 autoloading: OK (App\\Services\\SystemInfoService loaded)' . PHP_EOL;
    echo 'Server timestamp: ' . $status['timestamp'] . PHP_EOL;
} else {
    echo 'PSR-4 autoloading: not available yet, run "composer install" in backend/.' . PHP_EOL;
}
