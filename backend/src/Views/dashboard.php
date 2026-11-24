<?php
declare(strict_types=1);

$dashboardView ??= ['counts' => [], 'recentImports' => [], 'recentExports' => []];
$counts = $dashboardView['counts'];
?>
<section class="page-header">
    <h2>Dashboard</h2>
    <p>Overview of datasets and import/export operations.</p>
</section>

<section class="card-grid dashboard-cards">
    <article class="card"><strong><?= (int) ($counts['datasets'] ?? 0); ?></strong><span>Total datasets</span></article>
    <article class="card"><strong><?= (int) ($counts['records'] ?? 0); ?></strong><span>Total records</span></article>
    <article class="card"><strong><?= (int) ($counts['imports'] ?? 0); ?></strong><span>Total imports</span></article>
    <article class="card"><strong><?= (int) ($counts['exports'] ?? 0); ?></strong><span>Total exports</span></article>
</section>

<section class="dashboard-history">
    <h3>Recent imports</h3>
    <?php if ($dashboardView['recentImports'] === []): ?>
        <p class="empty-state">No import operations yet.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>File</th><th>Dataset</th><th>Status</th><th>Rows</th><th>Imported</th><th>Failed</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($dashboardView['recentImports'] as $job): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $job['filename'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) $job['dataset_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="status status-<?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><?= (int) $job['total_rows']; ?></td><td><?= (int) $job['imported_rows']; ?></td><td><?= (int) $job['failed_rows']; ?></td>
                    <td><?= htmlspecialchars((string) $job['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>

    <h3>Recent exports</h3>
    <?php if ($dashboardView['recentExports'] === []): ?>
        <p class="empty-state">No export operations yet.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Dataset</th><th>Format</th><th>Status</th><th>Rows</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($dashboardView['recentExports'] as $job): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $job['dataset_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars(strtoupper((string) $job['format']), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="status status-<?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><?= (int) $job['total_rows']; ?></td>
                    <td><?= htmlspecialchars((string) $job['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
