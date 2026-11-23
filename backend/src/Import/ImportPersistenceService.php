<?php

declare(strict_types=1);

namespace App\Import;

use App\Repositories\DatasetRecordRepository;
use App\Repositories\ImportJobRepository;
use App\Services\DatasetColumnService;
use App\Services\DatasetRecordService;
use mysqli;
use RuntimeException;

final class ImportPersistenceService
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly mysqli $connection,
        private readonly DatasetColumnService $columnService,
        private readonly DatasetRecordService $recordService,
        private readonly ImportJobRepository $jobRepository,
    ) {
    }

    /** @return array{validRows: array<int,array>, errors: array<int,array>, totalRows:int, importedRows:int, failedRows:int} */
    public function validate(int $datasetId, array $headers, array $mapping, array $rows, int $headerRow): array
    {
        $columns = $this->columnService->list($datasetId);
        $definitions = [];
        foreach ($columns as $column) {
            $definitions[$column->name] = $column;
        }

        $validRows = [];
        $errors = [];
        $seen = [];

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $headerRow + $rowIndex + 1;
            $input = [];
            $rowErrors = [];

            foreach ($headers as $index => $header) {
                $setting = is_array($mapping[$index] ?? null) ? $mapping[$index] : [];
                if (isset($setting['skip'])) continue;
                $label = trim((string) ($setting['label'] ?? $header));
                $name = (new ImportService($this->connection))->columnName($label, $index);
                $column = $definitions[$name] ?? null;

                if ($column === null) {
                    $rowErrors[] = ['rowNumber' => $rowNumber, 'columnName' => $name, 'message' => 'Field "' . $label . '" is not a valid dataset column.'];
                    continue;
                }

                try {
                    $input[$name] = $this->columnService->validateValue($column, $row[$index] ?? null);
                } catch (RuntimeException $exception) {
                    $rowErrors[] = ['rowNumber' => $rowNumber, 'columnName' => $name, 'message' => $exception->getMessage()];
                }
            }

            foreach ($columns as $column) {
                if (!array_key_exists($column->name, $input) && $column->isRequired) {
                    $rowErrors[] = ['rowNumber' => $rowNumber, 'columnName' => $column->name, 'message' => 'Field "' . $column->label . '" is required.'];
                } elseif (!array_key_exists($column->name, $input)) {
                    $input[$column->name] = null;
                }
            }

            $canonical = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            if (is_string($canonical) && isset($seen[$canonical])) {
                $rowErrors[] = ['rowNumber' => $rowNumber, 'columnName' => null, 'message' => 'Duplicate record: this row repeats a record in the import file.'];
            } elseif (is_string($canonical) && $this->jobRepository->recordExistsExactly($datasetId, $input)) {
                $rowErrors[] = ['rowNumber' => $rowNumber, 'columnName' => null, 'message' => 'Duplicate record: this record already exists in the dataset.'];
            } elseif (is_string($canonical)) {
                $seen[$canonical] = true;
            }

            if ($rowErrors === []) {
                $validRows[] = ['rowNumber' => $rowNumber, 'data' => $input];
            } else {
                $errors = array_merge($errors, $rowErrors);
            }
        }

        $totalRows = count($rows);
        return [
            'validRows' => $validRows,
            'errors' => $errors,
            'totalRows' => $totalRows,
            'importedRows' => count($validRows),
            'failedRows' => $totalRows - count($validRows),
        ];
    }

    public function persist(int $datasetId, string $filename, array $validRows, array $errors, ?int $totalRows = null): array
    {
        if ($totalRows === null) {
            $totalRows = count($validRows) + count(array_unique(array_column($errors, 'rowNumber')));
        }
        $jobId = $this->jobRepository->create($datasetId, $filename, $totalRows);

        try {
            $this->connection->begin_transaction();
            $this->jobRepository->addErrors($jobId, $errors);

            foreach (array_chunk($validRows, self::BATCH_SIZE) as $batch) {
                foreach ($batch as $row) {
                    $data = $row['data'];
                    $recordRepository = new DatasetRecordRepository($this->connection);
                    $recordRepository->create($datasetId, $data);
                }
            }

            $this->connection->commit();
            $this->jobRepository->complete($jobId, count($validRows), $totalRows - count($validRows));
            return ['jobId' => $jobId, 'importedRows' => count($validRows), 'failedRows' => $totalRows - count($validRows)];
        } catch (\Throwable $exception) {
            $this->connection->rollback();
            $this->jobRepository->fail($jobId);
            throw new RuntimeException('Import could not be persisted: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
