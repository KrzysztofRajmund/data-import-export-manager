<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoloadPath)) {
    require $autoloadPath;
}

$appConfig = require __DIR__ . '/../config/app.php';

date_default_timezone_set($appConfig['timezone']);
ini_set('display_errors', $appConfig['display_errors'] ? '1' : '0');

session_start();

use App\Controllers\DatasetController;
use App\Controllers\DynamicDatasetController;
use App\Database\Database;
use App\Repositories\DatasetColumnRepository;
use App\Repositories\DatasetRecordRepository;
use App\Repositories\DatasetRepository;
use App\Security\Csrf;
use App\Services\DatasetColumnService;
use App\Services\DatasetRecordService;
use App\Services\DatasetService;

$navigation = [
    'dashboard' => 'Dashboard',
    'datasets' => 'Datasets',
    'import' => 'Import',
    'export-history' => 'Export History',
];

$page = isset($_GET['page']) ? strtolower((string) $_GET['page']) : 'dashboard';
$isValidPage = isset($navigation[$page]);
if (!$isValidPage) {
    http_response_code(404);
    $page = 'dashboard';
}

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$datasetController = null;
if ($page === 'datasets') {
    try {
        $datasetService = new DatasetService(new DatasetRepository(Database::getConnection()));
        $datasetController = new DatasetController($datasetService);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                Csrf::validate(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);

                $action = (string) ($_POST['action'] ?? '');
                $dynamicActions = ['add-column', 'delete-column', 'create-record', 'update-record', 'delete-record'];
                if (in_array($action, $dynamicActions, true)) {
                    throw new RuntimeException('__DEFER_DYNAMIC_ACTION__');
                }

                $name = (string) ($_POST['name'] ?? '');
                $description = (string) ($_POST['description'] ?? '');

                if ($action === 'create') {
                    $datasetId = $datasetService->create($name, $description);
                    $_SESSION['flash_success'] = 'Dataset created successfully.';
                    header('Location: /?page=datasets&dataset=' . $datasetId);
                    exit;
                }

                $datasetId = filter_input(INPUT_POST, 'dataset_id', FILTER_VALIDATE_INT);
                if ($datasetId === false || $datasetId === null) {
                    throw new RuntimeException('Invalid dataset ID.');
                }

                if ($action === 'rename') {
                    $datasetService->rename($datasetId, $name, $description);
                    $_SESSION['flash_success'] = 'Dataset updated successfully.';
                } elseif ($action === 'delete') {
                    $datasetService->delete($datasetId);
                    $_SESSION['flash_success'] = 'Dataset deleted successfully.';
                } else {
                    throw new RuntimeException('Unknown action.');
                }

                header('Location: /?page=datasets');
                exit;
            } catch (Throwable $exception) {
                if ($exception->getMessage() === '__DEFER_DYNAMIC_ACTION__') {
                    // Continue to the dynamic column/record handler below.
                } else {
                    $_SESSION['flash_error'] = $exception->getMessage();
                    header('Location: /?page=datasets');
                    exit;
                }
            }
        }
    } catch (Throwable $exception) {
        $flashError = 'Could not access datasets: ' . $exception->getMessage();
    }
}

$datasetView = ['datasets' => [], 'dataset' => null];
if ($datasetController !== null) {
    $datasetId = filter_input(INPUT_GET, 'dataset', FILTER_VALIDATE_INT);
    $datasetId = $datasetId === false ? null : $datasetId;
    $datasetView = $datasetController->index($datasetId);
}

$dynamicDatasetController = null;
$dynamicDatasetView = null;
$recordService = null;

$datasetId = filter_input(INPUT_GET, 'dataset', FILTER_VALIDATE_INT);
$datasetId = $datasetId === false ? null : $datasetId;

if ($datasetController !== null) {
    $datasetView = $datasetController->index($datasetId);

    if ($datasetId !== null && $datasetView['dataset'] !== null) {
        try {
            $connection = Database::getConnection();
            $columnService = new DatasetColumnService(new DatasetColumnRepository($connection));
            $recordService = new DatasetRecordService(new DatasetRecordRepository($connection), $columnService);
            $dynamicDatasetController = new DynamicDatasetController($columnService, $recordService);

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    Csrf::validate(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null);
                    $action = (string) ($_POST['action'] ?? '');

                    if ($action === 'add-column') {
                        $columnService->create(
                            $datasetId,
                            (string) ($_POST['column_name'] ?? ''),
                            (string) ($_POST['column_label'] ?? ''),
                            (string) ($_POST['data_type'] ?? ''),
                            isset($_POST['is_required']),
                            isset($_POST['is_searchable']),
                        );
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
                    } else {
                        throw new RuntimeException('Unknown action.');
                    }

                    header('Location: /?page=datasets&dataset=' . $datasetId);
                    exit;
                } catch (Throwable $exception) {
                    $_SESSION['flash_error'] = $exception->getMessage();
                    header('Location: /?page=datasets&dataset=' . $datasetId);
                    exit;
                }
            }

            $currentPage = filter_input(INPUT_GET, 'record_page', FILTER_VALIDATE_INT);
            $currentPage = $currentPage === false || $currentPage === null ? 1 : max(1, $currentPage);
            $search = (string) ($_GET['search'] ?? '');
            $dynamicDatasetView = $dynamicDatasetController->index($datasetId, $currentPage, 20, $search);
            $dynamicDatasetView['currentPage'] = $currentPage;
            $dynamicDatasetView['search'] = $search;
        } catch (Throwable $exception) {
            $flashError = 'Could not access dynamic records: ' . $exception->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=' . $appConfig['charset']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="<?= htmlspecialchars($appConfig['charset'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Import &amp; Export Manager</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="app-header">
        <div class="header-inner">
            <h1>Data Import &amp; Export Manager</h1>
        </div>
    </header>

    <div class="layout">
        <nav class="app-nav" aria-label="Main navigation">
            <ul>
                <?php foreach ($navigation as $route => $label): ?>
                    <li>
                        <a href="/?page=<?= urlencode($route); ?>" class="<?= $route === $page ? 'active' : ''; ?>">
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <main class="app-main">
            <?php if (!$isValidPage): ?>
                <section class="notice notice-error"><strong>Page not found.</strong></section>
            <?php endif; ?>

            <?php if ($flashSuccess !== null): ?>
                <section class="notice notice-success" role="status"><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></section>
            <?php endif; ?>
            <?php if ($flashError !== null): ?>
                <section class="notice notice-error" role="alert"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></section>
            <?php endif; ?>

            <?php if ($page === 'datasets'): ?>
                <?php $selectedDataset = $datasetView['dataset']; ?>
                <section class="page-header">
                    <h2>Datasets</h2>
                    <p>Create, view, rename and delete datasets.</p>
                </section>

                <section class="dataset-layout">
                    <div>
                        <h3>Dataset list</h3>
                        <?php if ($datasetView['datasets'] === []): ?>
                            <p class="empty-state">No datasets yet. Create the first dataset below.</p>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Name</th><th>Description</th><th>Actions</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($datasetView['datasets'] as $dataset): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($dataset->name, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?= htmlspecialchars($dataset->description ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <a class="button button-secondary" href="/?page=datasets&amp;dataset=<?= $dataset->id; ?>">View</a>
                                                <form class="inline-form" method="post" action="/?page=datasets">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="dataset_id" value="<?= $dataset->id; ?>">
                                                    <button class="button button-danger" type="submit" onclick="return confirm('Delete this dataset and all related data?')">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="dataset-panel">
                        <?php if ($selectedDataset !== null): ?>
                            <h3>Dataset details</h3>
                            <dl>
                                <dt>Name</dt><dd><?= htmlspecialchars($selectedDataset->name, ENT_QUOTES, 'UTF-8'); ?></dd>
                                <dt>Description</dt><dd><?= htmlspecialchars($selectedDataset->description ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                                <dt>Created</dt><dd><?= htmlspecialchars($selectedDataset->createdAt, ENT_QUOTES, 'UTF-8'); ?></dd>
                                <dt>Updated</dt><dd><?= htmlspecialchars($selectedDataset->updatedAt, ENT_QUOTES, 'UTF-8'); ?></dd>
                            </dl>

                            <h3>Rename dataset</h3>
                            <form method="post" action="/?page=datasets">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="dataset_id" value="<?= $selectedDataset->id; ?>">
                                <label for="rename-name">Name</label>
                                <input id="rename-name" name="name" maxlength="255" required value="<?= htmlspecialchars($selectedDataset->name, ENT_QUOTES, 'UTF-8'); ?>">
                                <label for="rename-description">Description</label>
                                <textarea id="rename-description" name="description" maxlength="4000" rows="4"><?= htmlspecialchars($selectedDataset->description ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <button class="button button-primary" type="submit">Save changes</button>
                            </form>

                            <h3>Columns</h3>
                            <?php if ($dynamicDatasetView !== null): ?>
                                <?php if ($dynamicDatasetView['columns'] === []): ?>
                                    <p class="empty-state">No columns yet. Add the first column below.</p>
                                <?php else: ?>
                                    <div class="table-wrap">
                                        <table>
                                            <thead><tr><th>Name</th><th>Label</th><th>Type</th><th>Required</th><th>Searchable</th><th>Actions</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($dynamicDatasetView['columns'] as $column): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($column->name, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?= htmlspecialchars($column->label, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?= htmlspecialchars($column->dataType, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?= $column->isRequired ? 'Yes' : 'No'; ?></td>
                                                    <td><?= $column->isSearchable ? 'Yes' : 'No'; ?></td>
                                                    <td>
                                                        <form class="inline-form" method="post" action="/?page=datasets&amp;dataset=<?= $selectedDataset->id; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="action" value="delete-column">
                                                            <input type="hidden" name="column_id" value="<?= $column->id; ?>">
                                                            <button class="button button-danger" type="submit" onclick="return confirm('Delete this column?')">Delete</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <h3>Add column</h3>
                                <form method="post" action="/?page=datasets&amp;dataset=<?= $selectedDataset->id; ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="add-column">
                                    <label for="column-name">Name</label>
                                    <input id="column-name" name="column_name" maxlength="255" required pattern="[A-Za-z_][A-Za-z0-9_]*" placeholder="customer_name">
                                    <label for="column-label">Label</label>
                                    <input id="column-label" name="column_label" maxlength="255" required placeholder="Customer name">
                                    <label for="column-type">Data type</label>
                                    <select id="column-type" name="data_type" required>
                                        <option value="string">String</option>
                                        <option value="integer">Integer</option>
                                        <option value="decimal">Decimal</option>
                                        <option value="boolean">Boolean</option>
                                        <option value="date">Date</option>
                                        <option value="datetime">Datetime</option>
                                    </select>
                                    <label><input type="checkbox" name="is_required" value="1"> Required</label>
                                    <label><input type="checkbox" name="is_searchable" value="1"> Searchable</label>
                                    <button class="button button-primary" type="submit">Add column</button>
                                </form>

                                <h3>Records</h3>
                                <form method="get" action="/" class="search-form">
                                    <input type="hidden" name="page" value="datasets">
                                    <input type="hidden" name="dataset" value="<?= $selectedDataset->id; ?>">
                                    <label for="record-search">Search searchable columns</label>
                                    <input id="record-search" name="search" value="<?= htmlspecialchars($dynamicDatasetView['search'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="button button-secondary" type="submit">Search</button>
                                </form>

                                <?php if ($dynamicDatasetView['columns'] !== []): ?>
                                    <?php $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT); ?>
                                    <?php $editRecord = $editId !== false && $editId !== null ? $recordService->get($selectedDataset->id, $editId) : null; ?>
                                    <h4><?= $editRecord === null ? 'Add record' : 'Edit record'; ?></h4>
                                    <form method="post" action="/?page=datasets&amp;dataset=<?= $selectedDataset->id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="<?= $editRecord === null ? 'create-record' : 'update-record'; ?>">
                                        <?php if ($editRecord !== null): ?>
                                            <input type="hidden" name="record_id" value="<?= $editRecord->id; ?>">
                                        <?php endif; ?>
                                        <?php foreach ($dynamicDatasetView['columns'] as $column): ?>
                                            <?php $current = $editRecord !== null ? ($editRecord->data[$column->name] ?? '') : ''; ?>
                                            <label for="field-<?= $column->id; ?>"><?= htmlspecialchars($column->label, ENT_QUOTES, 'UTF-8'); ?><?= $column->isRequired ? ' *' : ''; ?></label>
                                            <?php if ($column->dataType === 'boolean'): ?>
                                                <select id="field-<?= $column->id; ?>" name="<?= htmlspecialchars($column->name, ENT_QUOTES, 'UTF-8'); ?>" <?= $column->isRequired ? 'required' : ''; ?>>
                                                    <option value="">—</option>
                                                    <option value="true" <?= $current === true || $current === 'true' ? 'selected' : ''; ?>>True</option>
                                                    <option value="false" <?= $current === false || $current === 'false' ? 'selected' : ''; ?>>False</option>
                                                </select>
                                            <?php else: ?>
                                                <input id="field-<?= $column->id; ?>" name="<?= htmlspecialchars($column->name, ENT_QUOTES, 'UTF-8'); ?>" type="<?= $column->dataType === 'date' ? 'date' : ($column->dataType === 'datetime' ? 'datetime-local' : ($column->dataType === 'integer' || $column->dataType === 'decimal' ? 'number' : 'text')); ?>" <?= $column->isRequired ? 'required' : ''; ?> value="<?= htmlspecialchars((string) $current, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <button class="button button-primary" type="submit"><?= $editRecord === null ? 'Add record' : 'Save record'; ?></button>
                                    </form>
                                <?php endif; ?>

                                <div class="table-wrap">
                                    <table>
                                        <thead><tr><th>Actions</th><?php foreach ($dynamicDatasetView['columns'] as $column): ?><th><?= htmlspecialchars($column->label, ENT_QUOTES, 'UTF-8'); ?></th><?php endforeach; ?></tr></thead>
                                        <tbody>
                                        <?php if ($dynamicDatasetView['records'] === []): ?>
                                            <tr><td colspan="<?= count($dynamicDatasetView['columns']) + 1; ?>">No records found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($dynamicDatasetView['records'] as $record): ?>
                                                <tr>
                                                    <td>
                                                        <a class="button button-secondary" href="/?page=datasets&amp;dataset=<?= $selectedDataset->id; ?>&amp;edit=<?= $record->id; ?>">Edit</a>
                                                        <form class="inline-form" method="post" action="/?page=datasets&amp;dataset=<?= $selectedDataset->id; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="action" value="delete-record">
                                                            <input type="hidden" name="record_id" value="<?= $record->id; ?>">
                                                            <button class="button button-danger" type="submit" onclick="return confirm('Delete this record?')">Delete</button>
                                                        </form>
                                                    </td>
                                                    <?php foreach ($dynamicDatasetView['columns'] as $column): ?>
                                                        <?php $value = $record->data[$column->name] ?? null; ?>
                                                        <td><?= $value === null ? '—' : htmlspecialchars(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <p>Showing <?= $dynamicDatasetView['total']; ?> record(s)</p>
                                <?php if ($dynamicDatasetView['pages'] > 1): ?>
                                    <div class="pagination">
                                        <?php for ($pageNumber = 1; $pageNumber <= $dynamicDatasetView['pages']; $pageNumber++): ?>
                                            <a class="button button-secondary <?= $pageNumber === $dynamicDatasetView['currentPage'] ? 'active' : ''; ?>" href="/?page=datasets&amp;dataset=<?= $selectedDataset->id; ?>&amp;search=<?= urlencode($dynamicDatasetView['search']); ?>&amp;record_page=<?= $pageNumber; ?>"><?= $pageNumber; ?></a>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <h3>Create dataset</h3>
                            <form method="post" action="/?page=datasets">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="create">
                                <label for="create-name">Name</label>
                                <input id="create-name" name="name" maxlength="255" required>
                                <label for="create-description">Description</label>
                                <textarea id="create-description" name="description" maxlength="4000" rows="4"></textarea>
                                <button class="button button-primary" type="submit">Create dataset</button>
                            </form>
                        <?php endif; ?>
                    </aside>
                </section>
            <?php elseif ($page === 'dynamic-dataset' && $dynamicDatasetView !== null): ?>
                <?php $records = $dynamicDatasetView['records']; ?>
                <section class="page-header">
                    <h2>Dynamic Dataset View</h2>
                    <p>View and manage records for the dataset.</p>
                </section>

                <section class="record-layout">
                    <div>
                        <h3>Record list</h3>
                        <?php if ($records === []): ?>
                            <p class="empty-state">No records found. Add the first record below.</p>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>Actions</th>
                                        <?php foreach ($records[0] as $columnName => $value): ?>
                                            <th><?= htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8'); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($records as $record): ?>
                                        <tr>
                                            <td>
                                                <form class="inline-form" method="post" action="/?page=datasets&dataset=<?= $datasetId; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="record_id" value="<?= $record['id']; ?>">
                                                    <button class="button button-danger" type="submit" onclick="return confirm('Delete this record?')">Delete</button>
                                                </form>
                                            </td>
                                            <?php foreach ($record as $columnName => $value): ?>
                                                <td>
                                                    <form method="post" action="/?page=datasets&dataset=<?= $datasetId; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="record_id" value="<?= $record['id']; ?>">
                                                        <input type="hidden" name="column_name" value="<?= htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="text" name="value" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" onchange="this.form.submit()">
                                                    </form>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php else:
                $placeholders = match ($page) {
                    'dashboard' => ['Total datasets: placeholder', 'Total records: placeholder', 'Recent imports: placeholder', 'Recent exports: placeholder'],
                    'import' => ['Upload form placeholder', 'Import jobs status placeholder'],
                    'export-history' => ['Export jobs table placeholder', 'Filters placeholder'],
                    default => [],
                };
            ?>
                <section class="page-header">
                    <h2><?= htmlspecialchars($navigation[$page], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p>Placeholder</p>
                </section>
                <section class="card-grid">
                    <?php foreach ($placeholders as $card): ?><article class="card"><?= htmlspecialchars($card, ENT_QUOTES, 'UTF-8'); ?></article><?php endforeach; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
