<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DatasetRecordRepository;
use RuntimeException;

final class DatasetRecordService
{
    public function __construct(
        private readonly DatasetRecordRepository $repository,
        private readonly DatasetColumnService $columnService,
    ) {
    }

    /** @return array{records: array<int, \App\Models\DatasetRecord>, total: int, pages: int} */
    public function list(int $datasetId, int $page, int $perPage, string $search): array
    {
        $columns = $this->columnService->list($datasetId);
        return $this->repository->paginate($datasetId, $page, $perPage, trim($search), $columns);
    }

    public function get(int $datasetId, int $recordId): ?\App\Models\DatasetRecord
    {
        return $this->repository->findById($datasetId, $recordId);
    }

    public function create(int $datasetId, array $input): int
    {
        $data = $this->validateData($datasetId, $input);
        return $this->repository->create($datasetId, $data);
    }

    public function update(int $datasetId, int $recordId, array $input): void
    {
        if ($this->repository->findById($datasetId, $recordId) === null) throw new RuntimeException('Record not found.');
        $data = $this->validateData($datasetId, $input);
        $this->repository->update($datasetId, $recordId, $data);
    }

    public function delete(int $datasetId, int $recordId): void
    {
        if (!$this->repository->delete($datasetId, $recordId)) throw new RuntimeException('Record not found or could not be deleted.');
    }

    /** @return array<string, mixed> */
    private function validateData(int $datasetId, array $input): array
    {
        $data = [];
        foreach ($this->columnService->list($datasetId) as $column) {
            $data[$column->name] = $this->columnService->validateValue($column, $input[$column->name] ?? null);
        }
        return $data;
    }
}
