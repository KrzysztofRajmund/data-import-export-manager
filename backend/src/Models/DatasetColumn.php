<?php

declare(strict_types=1);

namespace App\Models;

final class DatasetColumn
{
    public function __construct(
        public readonly int $id,
        public readonly int $datasetId,
        public readonly string $name,
        public readonly string $label,
        public readonly string $dataType,
        public readonly int $position,
        public readonly bool $isRequired,
        public readonly bool $isSearchable,
        public readonly string $createdAt,
    ) {
    }
}
