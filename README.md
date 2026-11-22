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
