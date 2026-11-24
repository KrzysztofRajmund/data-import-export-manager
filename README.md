# Data Import & Export Manager

Lightweight PHP + MySQL application for importing, managing and exporting dynamically structured datasets, with an optional Node.js Worker Threads service for CPU-intensive processing.

## Requirements

- PHP 8.3+
- Composer
- MySQL 8
- Node.js 18+ (starting from Phase 09)

## Installation

```bash
cd backend
composer install
```

## Run the PHP Application

Start the application from the backend directory:

```bash
cd backend
php -S localhost:8000 -t public
```

Open:

```text
http://localhost:8000
```

Start the static frontend in another terminal:

```bash
cd frontend
php -S localhost:8001
```

Open:

```text
http://localhost:8001
```

## Start MySQL

Start the local MySQL service:

```bash
brew services start mysql@8.4
brew services list
```

Verify the TCP connection:

```bash
mysqladmin -h 127.0.0.1 -P 3306 -u root ping
```

If MySQL requires a password:

```bash
mysqladmin -h 127.0.0.1 -P 3306 -u root -p ping
```

Create the application database:

```bash
mysql -h 127.0.0.1 -P 3306 -u root -e "CREATE DATABASE IF NOT EXISTS data_import_export_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
```

## phpMyAdmin

Start phpMyAdmin with the PHP built-in server:

```bash
cd "$(brew --prefix phpmyadmin)/share/phpmyadmin"
php -S 127.0.0.1:8081
```

Open phpMyAdmin:

```text
http://127.0.0.1:8081
```

### Login

- Server: `127.0.0.1:3306`
- Username: `root`
- Password: the local MySQL root password, or leave it empty only when `root` has no password

For password-based authentication, keep this setting in `config.inc.php`:

```php
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['host'] = '127.0.0.1';
$cfg['Servers'][$i]['port'] = '3306';
$cfg['Servers'][$i]['AllowNoPassword'] = false;
```

For a local account that has no password, `AllowNoPassword` may be set to `true`. Do not store MySQL passwords in application source or commit `config.inc.php` with private values.

## Database Configuration

Copy the example configuration and adjust local credentials:

```text
backend/config/config.example.php
backend/config/config.local.php
```

`backend/config/config.local.php` is ignored by Git.

## Stop the Services

Stop MySQL:

```bash
brew services stop mysql@8.4
```

## Node.js Worker Service

The Worker service is optional. PHP processes datasets with fewer than 500 rows locally and automatically falls back to PHP for larger batches if Node.js is unavailable, times out, or rejects a request.

Start it with environment variables:

```bash
cd worker-service
WORKER_HOST=127.0.0.1 \
WORKER_PORT=3001 \
WORKER_API_KEY=local-worker-secret \
WORKER_COUNT=2 \
WORKER_BATCH_SIZE=500 \
node src/server.js
```

The service binds only to `127.0.0.1` and requires `X-API-Key` with the configured shared secret. PHP is configured in `backend/config/config.local.php` under `worker`:

```php
'worker' => [
    'url' => 'http://127.0.0.1:3001/process-batch',
    'apiKey' => 'local-worker-secret',
    'timeoutMs' => 2000,
],
```

Test it directly:

```bash
curl -i -X POST http://127.0.0.1:3001/process-batch \
  -H 'Content-Type: application/json' \
  -H 'X-API-Key: local-worker-secret' \
  --data '{"headers":["Name"],"mapping":{"0":{"name":"Name","label":"Name","skip":false}},"rows":[["Ada"],["Ada"]],"headerRow":1,"columns":[{"name":"Name","label":"Name","dataType":"string","isRequired":false}],"batchOffset":0}'

# Missing key should return HTTP 401.
curl -i -X POST http://127.0.0.1:3001/process-batch -H 'Content-Type: application/json' --data '{}'
```

For fallback testing, stop Node.js and validate an import containing at least 500 rows. Validation should complete through PHP; persistence and database access remain in PHP. PHP cURL communication has a 2-second timeout and a 500 ms connection timeout by default.

## Storage Cleanup

Uploaded and generated files are stored outside the public directory. Remove files older than seven days (the default) with:

```bash
cd backend
php bin/cleanup.php
```

Configure another retention period with:

```bash
php bin/cleanup.php --days=30
```

The script deletes files in `backend/storage/imports/` and `backend/storage/exports/`. It can be scheduled with macOS `cron` or `launchd`, for example once daily:

```text
0 3 * * * cd /absolute/path/to/data-import-export-manager/backend && /usr/bin/php bin/cleanup.php --days=7
```
