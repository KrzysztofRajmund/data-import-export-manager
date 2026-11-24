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
use App\Controllers\ExportController;
use App\Controllers\ImportController;
use App\Database\Database;
use App\Repositories\DatasetRepository;
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

$importView = ['headers' => [], 'rows' => [], 'worksheets' => [], 'worksheet' => '', 'extension' => '', 'originalName' => '', 'headerRow' => 1, 'delimiter' => 'auto', 'suggestedTypes' => [], 'validation' => is_array($_SESSION['import_validation'] ?? null) ? $_SESSION['import_validation'] : null];
$importView = array_merge($importView, is_array($_SESSION['import_preview'] ?? null) ? $_SESSION['import_preview'] : []);

if ($page === 'import') {
    $importView = (new ImportController())->index();
}

$datasetController = null;
$datasetView = ['datasets' => [], 'dataset' => null, 'dynamic' => null, 'recordService' => null];
if ($page === 'datasets') {
    try {
        $datasetService = new DatasetService(new DatasetRepository(Database::getConnection()));
        $datasetController = new DatasetController($datasetService);
        $selectedDatasetId = filter_input(INPUT_GET, 'dataset', FILTER_VALIDATE_INT);
        $selectedDatasetId = $selectedDatasetId === false ? null : $selectedDatasetId;
        $datasetView = $datasetController->index($selectedDatasetId);
    } catch (Throwable $exception) {
        $flashError = 'Could not access datasets: ' . $exception->getMessage();
    }
}

$exportView = ['datasets' => [], 'dataset' => null, 'columns' => [], 'jobs' => []];
if ($page === 'export-history') {
    try {
        $exportView = (new ExportController())->index();
    } catch (Throwable $exception) {
        $flashError = 'Could not export dataset: ' . $exception->getMessage();
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
                <?php require __DIR__ . '/../src/Views/datasets.php'; ?>
            <?php elseif ($page === 'import'): ?>
                <?php require __DIR__ . '/../src/Views/import.php'; ?>
            <?php elseif ($page === 'export-history'): ?>
                <?php require __DIR__ . '/../src/Views/export.php'; ?>
            <?php else:
                $placeholders = match ($page) {
                    'dashboard' => ['Total datasets: placeholder', 'Total records: placeholder', 'Recent imports: placeholder', 'Recent exports: placeholder'],
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
