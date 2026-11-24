<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Export\ExportService;
use App\Repositories\DatasetColumnRepository;
use App\Repositories\DatasetRecordRepository;
use App\Repositories\DatasetRepository;
use App\Repositories\ExportJobRepository;
use App\Security\Csrf;
use App\Services\DatasetColumnService;
use RuntimeException;

final class ExportController
{
    public function index(): array
    {
        $connection = Database::getConnection();
        $datasetRepository = new DatasetRepository($connection);
        $jobRepository = new ExportJobRepository($connection);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handleExport($connection, $datasetRepository, $jobRepository);
        }

        $datasetId = filter_input(INPUT_GET, 'dataset', FILTER_VALIDATE_INT);
        $datasetId = $datasetId === false || $datasetId === null ? null : $datasetId;
        $datasets = $datasetRepository->findAll();
        $columns = [];
        $dataset = null;
        if ($datasetId !== null) {
            $dataset = $datasetRepository->findById($datasetId);
            if ($dataset !== null) {
                $columns = (new DatasetColumnService(new DatasetColumnRepository($connection)))->list($datasetId);
            }
        }

        return ['datasets' => $datasets, 'dataset' => $dataset, 'columns' => $columns, 'jobs' => $jobRepository->findAll()];
    }

    private function handleExport($connection, DatasetRepository $datasetRepository, ExportJobRepository $jobRepository): void
    {
        Csrf::validate(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
        $datasetId = filter_input(INPUT_POST, 'dataset_id', FILTER_VALIDATE_INT);
        if ($datasetId === false || $datasetId === null) throw new RuntimeException('Please select a valid dataset.');
        $dataset = $datasetRepository->findById($datasetId);
        if ($dataset === null) throw new RuntimeException('Dataset not found.');

        $format = strtolower((string) ($_POST['format'] ?? ''));
        if (!in_array($format, ['csv', 'xlsx', 'pdf'], true)) throw new RuntimeException('Unsupported export format.');

        $columnService = new DatasetColumnService(new DatasetColumnRepository($connection));
        $allColumns = $columnService->list($datasetId);
        $selectedColumnIds = is_array($_POST['column_ids'] ?? null) ? array_map('intval', $_POST['column_ids']) : [];
        $columns = $selectedColumnIds === []
            ? $allColumns
            : array_values(array_filter($allColumns, static fn ($column): bool => in_array($column->id, $selectedColumnIds, true)));
        if ($columns === []) throw new RuntimeException('Please select at least one column.');

        $recordIds = is_array($_POST['record_ids'] ?? null)
            ? array_map('intval', $_POST['record_ids'])
            : [];
        if ($recordIds === [] && trim((string) ($_POST['record_ids_text'] ?? '')) !== '') {
            $recordIds = array_map('intval', array_map('trim', explode(',', (string) $_POST['record_ids_text'])));
        }
        $search = trim((string) ($_POST['search'] ?? ''));
        $recordRepository = new DatasetRecordRepository($connection);
        $records = $recordRepository->findForExport($datasetId, $search, $allColumns, $recordIds);

        $jobId = $jobRepository->create($datasetId, $format, count($records));
        try {
            $exportDirectory = dirname(__DIR__, 2) . '/storage/exports';
            $exportService = new ExportService($exportDirectory);
            $path = match ($format) {
                'csv' => $exportService->exportCsv($columns, $records, $dataset->name, (string) ($_POST['delimiter'] ?? 'comma')),
                'xlsx' => $exportService->exportXlsx($columns, $records, $dataset->name),
                'pdf' => $exportService->exportPdf($columns, $records, $dataset->name),
            };
            $jobRepository->complete($jobId);
        } catch (\Throwable $exception) {
            $jobRepository->fail($jobId);
            throw $exception;
        }

        $downloadName = basename($path);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . match ($format) {
            'csv' => 'text/csv; charset=UTF-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
        });
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
    }
}
