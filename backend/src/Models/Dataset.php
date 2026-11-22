<?php

declare(strict_types=1);

namespace App\Models;

final class Dataset
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }
}
