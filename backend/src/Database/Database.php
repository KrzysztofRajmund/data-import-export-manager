<?php

declare(strict_types=1);

namespace App\Database;

use mysqli;
use mysqli_sql_exception;
use RuntimeException;

final class Database
{
    private static ?mysqli $connection = null;

    public static function getConnection(): mysqli
    {
        if (self::$connection === null) {
            self::$connection = self::connect();
        }

        return self::$connection;
    }

    private static function connect(): mysqli
    {
        $config = self::loadConfig();

        // PHP 8.1+ already reports mysqli errors as exceptions by default; set explicitly for clarity.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $mysqli = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database'],
                $config['port']
            );
            $mysqli->set_charset($config['charset']);
        } catch (mysqli_sql_exception $exception) {
            throw new RuntimeException(
                'Could not connect to the database: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        return $mysqli;
    }

    /**
     * @return array{host:string, port:int, username:string, password:string, database:string, charset:string}
     */
    private static function loadConfig(): array
    {
        $localConfigPath = __DIR__ . '/../../config/config.local.php';
        $fileConfig = [];

        if (is_file($localConfigPath)) {
            $fileConfig = (require $localConfigPath)['database'] ?? [];
        }

        return [
            'host' => $fileConfig['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1'),
            'port' => (int) ($fileConfig['port'] ?? (getenv('DB_PORT') ?: 3306)),
            'username' => $fileConfig['username'] ?? (getenv('DB_USERNAME') ?: 'root'),
            'password' => $fileConfig['password'] ?? (getenv('DB_PASSWORD') ?: ''),
            'database' => $fileConfig['database'] ?? (getenv('DB_DATABASE') ?: 'data_import_export_manager'),
            'charset' => $fileConfig['charset'] ?? (getenv('DB_CHARSET') ?: 'utf8mb4'),
        ];
    }
}
