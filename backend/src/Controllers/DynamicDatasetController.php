<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DatasetColumnService;
use App\Services\DatasetRecordService;

final class DynamicDatasetController
{
    public function __construct(
        private readonly DatasetColumnService $columnService,
        private readonly DatasetRecordService $recordService,
    ) {
    }

    public function index(int $datasetId, int $page, int $perPage, string $search): array
    {
        $columns = $this->columnService->list($datasetId);
        $records = $this->recordService->list($datasetId, $page, $perPage, $search);
        return ['columns' => $columns, 'records' => $records['records'], 'total' => $records['total'], 'pages' => $records['pages']];
    }
}
