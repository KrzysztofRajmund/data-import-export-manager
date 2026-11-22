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
use App\Database\Database;
use App\Repositories\DatasetRepository;
use App\Security\Csrf;
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
                $_SESSION['flash_error'] = $exception->getMessage();
                header('Location: /?page=datasets');
                exit;
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
