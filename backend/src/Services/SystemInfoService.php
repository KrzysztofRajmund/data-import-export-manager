<?php

declare(strict_types=1);

namespace App\Services;

final class SystemInfoService
{
    /**
     * @return array{phpVersion: string, timestamp: string}
     */
    public function getStatus(): array
    {
        return [
            'phpVersion' => PHP_VERSION,
            'timestamp' => date('c'),
        ];
    }
}
