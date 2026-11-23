<?php

declare(strict_types=1);

namespace App\Models;

final class DatasetRecord
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly int $id,
        public readonly int $datasetId,
        public readonly array $data,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }
}
