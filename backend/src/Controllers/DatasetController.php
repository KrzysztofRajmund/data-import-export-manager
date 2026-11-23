<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use App\Repositories\DatasetColumnRepository;
use App\Repositories\DatasetRecordRepository;
use App\Repositories\DatasetRepository;
use App\Security\Csrf;
use App\Services\DatasetColumnService;
use App\Services\DatasetRecordService;
use App\Services\DatasetService;
use RuntimeException;
use Throwable;

final class DatasetController
{
    public function __construct(private readonly DatasetService $service)
    {
    }

    /** @return array<string,mixed> */
    public function index(?int $datasetId): array
    {
        $datasets = $this->service->list();
        $dataset = $datasetId === null ? null : $this->service->get($datasetId);
        $dynamicView = null;
        $recordService = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($datasetId);
        }

        if ($dataset !== null) {
            try {
                $connection = Database::getConnection();
                $columnService = new DatasetColumnService(new DatasetColumnRepository($connection));
                $recordService = new DatasetRecordService(new DatasetRecordRepository($connection), $columnService);
                $dynamicController = new DynamicDatasetController($columnService, $recordService);
                $currentPage = filter_input(INPUT_GET, 'record_page', FILTER_VALIDATE_INT);
                $currentPage = $currentPage === false || $currentPage === null ? 1 : max(1, $currentPage);
                $search = (string) ($_GET['search'] ?? '');
                $dynamicView = $dynamicController->index($dataset->id, $currentPage, 20, $search);
                $dynamicView['currentPage'] = $currentPage;
                $dynamicView['search'] = $search;
            } catch (Throwable $exception) {
                $_SESSION['flash_error'] = 'Could not access dynamic records: ' . $exception->getMessage();
            }
        }

        return [
            'datasets' => $datasets,
            'dataset' => $dataset,
            'dynamic' => $dynamicView,
            'recordService' => $recordService,
        ];
    }

    private function handlePost(?int $datasetId): void
    {
        try {
            Csrf::validate(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
            $action = (string) ($_POST['action'] ?? '');

            if (in_array($action, ['add-column', 'delete-column', 'create-record', 'update-record', 'delete-record'], true)) {
                if ($datasetId === null) throw new RuntimeException('Please select a valid dataset.');
                $this->handleDynamicPost($datasetId, $action);
                return;
            }

            $name = (string) ($_POST['name'] ?? '');
            $description = (string) ($_POST['description'] ?? '');

            if ($action === 'create') {
                $createdId = $this->service->create($name, $description);
                $_SESSION['flash_success'] = 'Dataset created successfully.';
                header('Location: /?page=datasets&dataset=' . $createdId);
                exit;
            }

            $postedDatasetId = filter_input(INPUT_POST, 'dataset_id', FILTER_VALIDATE_INT);
            if ($postedDatasetId === false || $postedDatasetId === null) throw new RuntimeException('Invalid dataset ID.');

            if ($action === 'rename') {
                $this->service->rename($postedDatasetId, $name, $description);
                $_SESSION['flash_success'] = 'Dataset updated successfully.';
            } elseif ($action === 'delete') {
                $this->service->delete($postedDatasetId);
                $_SESSION['flash_success'] = 'Dataset deleted successfully.';
            } else {
                throw new RuntimeException('Unknown action.');
            }

            header('Location: /?page=datasets');
            exit;
        } catch (Throwable $exception) {
            $_SESSION['flash_error'] = $exception->getMessage();
            header('Location: /?page=datasets' . ($datasetId === null ? '' : '&dataset=' . $datasetId));
            exit;
        }
    }

    private function handleDynamicPost(int $datasetId, string $action): void
    {
        $connection = Database::getConnection();
        $columnService = new DatasetColumnService(new DatasetColumnRepository($connection));
        $recordService = new DatasetRecordService(new DatasetRecordRepository($connection), $columnService);

        if ($action === 'add-column') {
            $columnService->create($datasetId, (string) ($_POST['column_name'] ?? ''), (string) ($_POST['column_label'] ?? ''), (string) ($_POST['data_type'] ?? ''), isset($_POST['is_required']), isset($_POST['is_searchable']));
            $_SESSION['flash_success'] = 'Column added successfully.';
        } elseif ($action === 'delete-column') {
            $columnId = filter_input(INPUT_POST, 'column_id', FILTER_VALIDATE_INT);
            if ($columnId === false || $columnId === null) throw new RuntimeException('Invalid column ID.');
            $columnService->delete($datasetId, $columnId);
            $_SESSION['flash_success'] = 'Column deleted successfully.';
        } elseif ($action === 'create-record') {
            $recordService->create($datasetId, $_POST);
            $_SESSION['flash_success'] = 'Record added successfully.';
        } elseif ($action === 'update-record') {
            $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
            if ($recordId === false || $recordId === null) throw new RuntimeException('Invalid record ID.');
            $recordService->update($datasetId, $recordId, $_POST);
            $_SESSION['flash_success'] = 'Record updated successfully.';
        } elseif ($action === 'delete-record') {
            $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
            if ($recordId === false || $recordId === null) throw new RuntimeException('Invalid record ID.');
            $recordService->delete($datasetId, $recordId);
            $_SESSION['flash_success'] = 'Record deleted successfully.';
        }

        header('Location: /?page=datasets&dataset=' . $datasetId);
        exit;
    }
}
