<?php

declare(strict_types=1);

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$autoloaded = is_file($autoloadPath);

if ($autoloaded) {
    require $autoloadPath;
}

$appConfig = require __DIR__ . '/../config/app.php';

date_default_timezone_set($appConfig['timezone']);
ini_set('display_errors', $appConfig['display_errors'] ? '1' : '0');

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

$pages = [
    'dashboard' => [
        'title' => 'Dashboard',
        'lead' => 'Placeholder',
        'cards' => [
            'Total datasets: placeholder',
            'Total records: placeholder',
            'Recent imports: placeholder',
            'Recent exports: placeholder',
        ],
    ],
    'datasets' => [
        'title' => 'Datasets',
        'lead' => 'Placeholder',
        'cards' => [
            'Dataset table placeholder',
            'Create dataset form placeholder',
        ],
    ],
    'import' => [
        'title' => 'Import',
        'lead' => 'Placeholder',
        'cards' => [
            'Upload form placeholder',
            'Import jobs status placeholder',
        ],
    ],
    'export-history' => [
        'title' => 'Export History',
        'lead' => 'Placeholder',
        'cards' => [
            'Export jobs table placeholder',
            'Filters placeholder',
        ],
    ],
];

$view = $pages[$page];

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
                        <a
                            href="/?page=<?= urlencode($route); ?>"
                            class="<?= $route === $page ? 'active' : ''; ?>"
                        >
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <main class="app-main">
            <?php if (!$isValidPage): ?>
                <section class="notice notice-error">
                    <strong>Page not found.</strong> Showing dashboard instead.
                </section>
            <?php endif; ?>

            <section class="page-header">
                <h2><?= htmlspecialchars($view['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?= htmlspecialchars($view['lead'], ENT_QUOTES, 'UTF-8'); ?></p>
            </section>

            <section class="card-grid">
                <?php foreach ($view['cards'] as $card): ?>
                    <article class="card">
                        <?= htmlspecialchars($card, ENT_QUOTES, 'UTF-8'); ?>
                    </article>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
</body>
</html>
