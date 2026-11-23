<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Import\ImportParser;
use App\Import\ImportPersistenceService;
use App\Import\ImportService;
use App\Repositories\DatasetColumnRepository;
use App\Repositories\DatasetRecordRepository;
use App\Repositories\DatasetRepository;
use App\Repositories\ImportJobRepository;
use App\Security\Csrf;
use App\Services\DatasetColumnService;
use App\Services\DatasetRecordService;
use App\Services\DatasetService;
use RuntimeException;
use Throwable;

final class ImportController
{
    public function index(): array
    {
        $view = [
            'headers' => [], 'rows' => [], 'worksheets' => [], 'worksheet' => '',
            'extension' => '', 'originalName' => '', 'headerRow' => 1, 'delimiter' => 'auto',
            'suggestedTypes' => [], 'datasets' => [],
            'validation' => is_array($_SESSION['import_validation'] ?? null) ? $_SESSION['import_validation'] : null,
        ];

        try {
            $connection = Database::getConnection();
            $datasetService = new DatasetService(new DatasetRepository($connection));
            $view['datasets'] = $datasetService->list();

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                Csrf::validate(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
                $action = (string) ($_POST['action'] ?? '');

                if ($action === 'import-upload') {
                    $this->upload($connection, $datasetService, $_FILES['import_file'] ?? []);
                } elseif ($action === 'import-refresh') {
                    $this->refresh();
                } elseif ($action === 'import-confirm') {
                    $this->validateImport($connection, $datasetService);
                } elseif ($action === 'import-persist') {
                    $this->persist($connection);
                } else {
                    throw new RuntimeException('Unknown import action.');
                }
            }

            $preview = is_array($_SESSION['import_preview'] ?? null) ? $_SESSION['import_preview'] : [];
            $view = array_merge($view, $preview);
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = $exception->getMessage();
        }

        return $view;
    }

    private function upload($connection, DatasetService $datasetService, array $file): void
    {
        $importService = new ImportService($connection);
        $stored = $importService->storeUpload($file);
        $headerRow = max(1, filter_input(INPUT_POST, 'header_row', FILTER_VALIDATE_INT) ?: 1);
        $delimiter = (string) ($_POST['delimiter'] ?? 'auto');
        $worksheet = (string) ($_POST['worksheet'] ?? '');
        $parsed = (new ImportParser())->parse(
            $stored['path'], $stored['extension'], $headerRow, $delimiter,
            $worksheet !== '' ? $worksheet : null
        );

        $suggestedTypes = [];
        foreach ($parsed['headers'] as $index => $header) {
            $suggestedTypes[] = $importService->suggestType(array_column($parsed['rows'], $index));
        }

        $_SESSION['import_preview'] = [
            'headers' => $parsed['headers'], 'rows' => $parsed['rows'],
            'worksheets' => $parsed['worksheets'], 'worksheet' => $parsed['worksheet'],
            'extension' => $stored['extension'], 'originalName' => $stored['filename'],
            'headerRow' => $headerRow, 'delimiter' => $delimiter,
            'suggestedTypes' => $suggestedTypes, 'path' => $stored['path'],
        ];
        unset($_SESSION['import_validation']);
        $_SESSION['flash_success'] = 'File uploaded and parsed. Review the preview and mapping before import.';
        header('Location: /?page=import');
        exit;
    }

    private function refresh(): void
    {
        $preview = is_array($_SESSION['import_preview'] ?? null) ? $_SESSION['import_preview'] : null;
        if ($preview === null || !is_file((string) ($preview['path'] ?? ''))) {
            throw new RuntimeException('Please upload a file first.');
        }

        $connection = Database::getConnection();
        $importService = new ImportService($connection);
        $headerRow = max(1, filter_input(INPUT_POST, 'header_row', FILTER_VALIDATE_INT) ?: 1);
        $delimiter = (string) ($_POST['delimiter'] ?? 'auto');
        $worksheet = (string) ($_POST['worksheet'] ?? '');
        $parsed = (new ImportParser())->parse(
            (string) $preview['path'], (string) $preview['extension'],
            $headerRow, $delimiter, $worksheet !== '' ? $worksheet : null
        );

        $suggestedTypes = [];
        foreach ($parsed['headers'] as $index => $header) {
            $suggestedTypes[] = $importService->suggestType(array_column($parsed['rows'], $index));
        }

        $preview = array_merge($preview, [
            'headers' => $parsed['headers'], 'rows' => $parsed['rows'],
            'worksheets' => $parsed['worksheets'], 'worksheet' => $parsed['worksheet'],
            'headerRow' => $headerRow, 'delimiter' => $delimiter,
            'suggestedTypes' => $suggestedTypes,
        ]);
        $_SESSION['import_preview'] = $preview;
        unset($_SESSION['import_validation']);
        header('Location: /?page=import');
        exit;
    }

    private function validateImport($connection, DatasetService $datasetService): void
    {
        $preview = is_array($_SESSION['import_preview'] ?? null) ? $_SESSION['import_preview'] : null;
        if ($preview === null || !is_file((string) ($preview['path'] ?? ''))) {
            throw new RuntimeException('Please upload a file first.');
        }

        $datasetMode = (string) ($_POST['dataset_mode'] ?? 'existing');
        if ($datasetMode === 'new') {
            $datasetId = $datasetService->create(
                (string) ($_POST['new_dataset_name'] ?? ''),
                (string) ($_POST['new_dataset_description'] ?? '')
            );
        } else {
            $datasetId = filter_input(INPUT_POST, 'dataset_id', FILTER_VALIDATE_INT);
            if ($datasetId === false || $datasetId === null || $datasetService->get($datasetId) === null) {
                throw new RuntimeException('Please select a valid existing dataset.');
            }
        }

        $columnService = new DatasetColumnService(new DatasetColumnRepository($connection));
        $recordService = new DatasetRecordService(new DatasetRecordRepository($connection), $columnService);
        $importService = new ImportService($connection);
        $mapping = is_array($_POST['mapping'] ?? null) ? $_POST['mapping'] : [];

        foreach ($preview['headers'] as $index => $header) {
            $setting = is_array($mapping[$index] ?? null) ? $mapping[$index] : [];
            if (isset($setting['skip'])) continue;
            $label = trim((string) ($setting['label'] ?? $header));
            $name = $importService->columnName($label, $index);
            $exists = false;
            foreach ($columnService->list($datasetId) as $existingColumn) {
                if ($existingColumn->name === $name) { $exists = true; break; }
            }
            if (!$exists) {
                $columnService->create(
                    $datasetId, $name,
                    $label !== '' ? $label : (string) $header,
                    (string) ($setting['type'] ?? 'string'),
                    isset($setting['required']), isset($setting['searchable'])
                );
            }
        }

        $parsed = (new ImportParser())->parse(
            (string) $preview['path'], (string) $preview['extension'],
            (int) $preview['headerRow'], (string) $preview['delimiter'],
            $preview['worksheet'] !== '' ? (string) $preview['worksheet'] : null, false
        );
        $persistence = new ImportPersistenceService(
            $connection, $columnService, $recordService,
            new ImportJobRepository($connection)
        );
        $validation = $persistence->validate(
            $datasetId, $parsed['headers'], $mapping, $parsed['rows'], (int) $preview['headerRow']
        );

        $_SESSION['import_validation'] = [
            'datasetId' => $datasetId, 'mapping' => $mapping,
            'validRows' => $validation['validRows'], 'errors' => $validation['errors'],
            'totalRows' => $validation['totalRows'], 'importedRows' => $validation['importedRows'],
            'failedRows' => $validation['failedRows'],
        ];
        $_SESSION['flash_success'] = 'Validation completed: ' . $validation['importedRows']
            . ' valid row(s), ' . $validation['failedRows'] . ' failed row(s).';
        header('Location: /?page=import');
        exit;
    }

    private function persist($connection): void
    {
        $preview = is_array($_SESSION['import_preview'] ?? null) ? $_SESSION['import_preview'] : null;
        $validation = is_array($_SESSION['import_validation'] ?? null) ? $_SESSION['import_validation'] : null;
        if ($preview === null || $validation === null) {
            throw new RuntimeException('Please validate the import first.');
        }

        $datasetId = (int) $validation['datasetId'];
        $columnService = new DatasetColumnService(new DatasetColumnRepository($connection));
        $recordService = new DatasetRecordService(new DatasetRecordRepository($connection), $columnService);
        $persistence = new ImportPersistenceService(
            $connection, $columnService, $recordService,
            new ImportJobRepository($connection)
        );
        $result = $persistence->persist(
            $datasetId, (string) $preview['originalName'],
            (array) $validation['validRows'], (array) $validation['errors'],
            (int) $validation['totalRows']
        );

        if (is_file((string) $preview['path'])) unlink((string) $preview['path']);
        unset($_SESSION['import_preview'], $_SESSION['import_validation']);
        $_SESSION['flash_success'] = 'Import completed: ' . $result['importedRows']
            . ' record(s) imported, ' . $result['failedRows'] . ' row(s) failed.';
        header('Location: /?page=datasets&dataset=' . $datasetId);
        exit;
    }
}
