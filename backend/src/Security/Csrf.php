<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validate(?string $token): void
    {
        if ($token === null || !hash_equals(self::token(), $token)) {
            throw new RuntimeException('Invalid CSRF token. Refresh the page and try again.');
        }
    }
}
