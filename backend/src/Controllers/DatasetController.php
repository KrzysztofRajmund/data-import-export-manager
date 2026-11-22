<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DatasetService;

final class DatasetController
{
    public function __construct(private readonly DatasetService $service)
    {
    }

    /** @return array{datasets: array<int, \App\Models\Dataset>, dataset: ?\App\Models\Dataset} */
    public function index(?int $datasetId): array
    {
        return [
            'datasets' => $this->service->list(),
            'dataset' => $datasetId === null ? null : $this->service->get($datasetId),
        ];
    }
}
