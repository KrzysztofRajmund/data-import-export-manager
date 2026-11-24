<?php

declare(strict_types=1);

use App\Security\Csrf;

$exportView ??= ['datasets' => [], 'dataset' => null, 'columns' => [], 'jobs' => []];
$datasets = $exportView['datasets'];
$dataset = $exportView['dataset'];
$columns = $exportView['columns'];
$jobs = $exportView['jobs'];
$selectedDatasetId = $dataset !== null ? $dataset->id : null;
$recordIds = [];
if ($selectedDatasetId !== null) {
    $recordIds = array_map('intval', is_array($_POST['record_ids'] ?? null) ? $_POST['record_ids'] : []);
}
?>
<section class="page-header">
    <h2>Excel and CSV export</h2>
    <p>Export a complete dataset or a filtered and selected subset.</p>
</section>

<section class="dataset-layout">
    <div>
        <form method="get" action="/">
            <input type="hidden" name="page" value="export-history">
            <label for="export-dataset-select">Dataset</label>
            <select id="export-dataset-select" name="dataset" onchange="this.form.submit()">
                <option value="">Select dataset</option>
                <?php foreach ($datasets as $item): ?>
                    <option value="<?= $item->id; ?>" <?= $selectedDatasetId === $item->id ? 'selected' : ''; ?>><?= htmlspecialchars($item->name, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($dataset !== null): ?>
            <h3>Export options</h3>
            <form method="post" action="/?page=export-history&amp;dataset=<?= $dataset->id; ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="dataset_id" value="<?= $dataset->id; ?>">

                <label for="export-format">Format</label>
                <select id="export-format" name="format">
                    <option value="xlsx">Excel (XLSX)</option>
                    <option value="csv">CSV</option>
                </select>

                <label for="csv-delimiter">CSV delimiter</label>
                <select id="csv-delimiter" name="delimiter">
                    <option value="comma">Comma (,)</option>
                    <option value="semicolon">Semicolon (;)</option>
                    <option value="tab">Tab</option>
                </select>

                <label for="export-search">Filter by first searchable column</label>
                <input id="export-search" name="search" value="<?= htmlspecialchars((string) ($_GET['search'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Leave empty for all records">

                <fieldset>
                    <legend>Columns to export</legend>
                    <?php foreach ($columns as $column): ?>
                        <label><input type="checkbox" name="column_ids[]" value="<?= $column->id; ?>" checked> <?= htmlspecialchars($column->label, ENT_QUOTES, 'UTF-8'); ?></label>
                    <?php endforeach; ?>
                    <?php if ($columns === []): ?><p class="empty-state">This dataset has no columns.</p><?php endif; ?>
                </fieldset>

                <fieldset>
                    <legend>Selected record IDs (optional)</legend>
                    <p>Enter comma-separated record IDs. Leave empty to export all records matching the filter.</p>
                    <input name="record_ids_text" value="<?= htmlspecialchars($recordIds === [] ? '' : implode(',', $recordIds), ENT_QUOTES, 'UTF-8'); ?>" oninput="">
                </fieldset>

                <?php
                $textIds = trim((string) ($_POST['record_ids_text'] ?? ($_GET['record_ids_text'] ?? '')));
                if ($textIds !== '') {
                    foreach (explode(',', $textIds) as $idText) {
                        $id = filter_var(trim($idText), FILTER_VALIDATE_INT);
                        if ($id !== false) echo '<input type="hidden" name="record_ids[]" value="' . (int) $id . '">';
                    }
                }
                ?>

                <button class="button button-primary" type="submit" <?= $columns === [] ? 'disabled' : ''; ?>>Generate download</button>
            </form>
        <?php else: ?>
            <p class="empty-state">Select a dataset to configure export options.</p>
        <?php endif; ?>
    </div>

    <aside class="dataset-panel">
        <h3>Export history</h3>
        <?php if ($jobs === []): ?>
            <p class="empty-state">No export operations yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Dataset</th><th>Format</th><th>Rows</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $job['dataset_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars(strtoupper((string) $job['format']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= (int) $job['total_rows']; ?></td>
                            <td><?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) $job['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </aside>
</section>
