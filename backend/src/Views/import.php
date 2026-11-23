<?php

declare(strict_types=1);

use App\Security\Csrf;
?>
<section class="page-header">
    <h2>Import</h2>
    <p>Upload an XLSX or CSV file, review the preview and configure column mapping.</p>
</section>

<h3>Upload file</h3>
<form method="post" action="/?page=import" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="import-upload">
    <label for="import-file">XLSX or CSV file (maximum 10 MB)</label>
    <input id="import-file" type="file" name="import_file" accept=".xlsx,.csv" required>
    <label for="header-row">Header row</label>
    <input id="header-row" type="number" name="header_row" min="1" value="<?= (int) $importView['headerRow']; ?>">
    <label for="csv-delimiter">CSV delimiter</label>
    <select id="csv-delimiter" name="delimiter">
        <?php foreach (['auto' => 'Detect automatically', 'comma' => 'Comma (,)', 'semicolon' => 'Semicolon (;)', 'tab' => 'Tab'] as $value => $label): ?>
            <option value="<?= $value; ?>" <?= $importView['delimiter'] === $value ? 'selected' : ''; ?>><?= $label; ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button button-primary" type="submit">Upload and preview</button>
</form>

<?php if ($importView['headers'] !== []): ?>
    <h3>Preview: <?= htmlspecialchars($importView['originalName'], ENT_QUOTES, 'UTF-8'); ?></h3>

    <form method="post" action="/?page=import">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="import-refresh">
        <label for="refresh-header-row">Header row</label>
        <input id="refresh-header-row" type="number" name="header_row" min="1" value="<?= (int) $importView['headerRow']; ?>">

        <?php if ($importView['extension'] === 'csv'): ?>
            <label for="refresh-delimiter">CSV delimiter</label>
            <select id="refresh-delimiter" name="delimiter">
                <?php foreach (['auto' => 'Detect automatically', 'comma' => 'Comma (,)', 'semicolon' => 'Semicolon (;)', 'tab' => 'Tab'] as $value => $label): ?>
                    <option value="<?= $value; ?>" <?= $importView['delimiter'] === $value ? 'selected' : ''; ?>><?= $label; ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <label for="worksheet">Worksheet</label>
            <select id="worksheet" name="worksheet">
                <?php foreach ($importView['worksheets'] as $sheet): ?>
                    <option value="<?= htmlspecialchars($sheet, ENT_QUOTES, 'UTF-8'); ?>" <?= $importView['worksheet'] === $sheet ? 'selected' : ''; ?>><?= htmlspecialchars($sheet, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button class="button button-secondary" type="submit">Refresh preview</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><?php foreach ($importView['headers'] as $header): ?><th><?= htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8'); ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php if ($importView['rows'] === []): ?>
                <tr><td colspan="<?= count($importView['headers']); ?>">No data rows found.</td></tr>
            <?php else: ?>
                <?php foreach ($importView['rows'] as $row): ?>
                    <tr><?php foreach ($row as $value): ?><td><?= $value === null ? '—' : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3>Column mapping and dataset</h3>
    <form method="post" action="/?page=import">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="import-confirm">

        <div class="table-wrap">
            <table>
                <thead><tr><th>Source column</th><th>Label</th><th>Type</th><th>Required</th><th>Searchable</th><th>Skip</th></tr></thead>
                <tbody>
                <?php foreach ($importView['headers'] as $index => $header): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><input name="mapping[<?= $index; ?>][label]" value="<?= htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" required></td>
                        <td>
                            <select name="mapping[<?= $index; ?>][type]">
                                <?php foreach (['string', 'integer', 'decimal', 'boolean', 'date', 'datetime'] as $type): ?>
                                    <option value="<?= $type; ?>" <?= $importView['suggestedTypes'][$index] === $type ? 'selected' : ''; ?>><?= ucfirst($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="checkbox" name="mapping[<?= $index; ?>][required]" value="1"></td>
                        <td><input type="checkbox" name="mapping[<?= $index; ?>][searchable]" value="1"></td>
                        <td><input type="checkbox" name="mapping[<?= $index; ?>][skip]" value="1"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <fieldset>
            <legend>Destination dataset</legend>
            <label><input type="radio" name="dataset_mode" value="existing" checked> Use existing dataset</label>
            <select name="dataset_id">
                <option value="">Select dataset</option>
                <?php foreach ($importView['datasets'] as $dataset): ?>
                    <option value="<?= $dataset->id; ?>"><?= htmlspecialchars($dataset->name, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <label><input type="radio" name="dataset_mode" value="new"> Create new dataset</label>
            <input name="new_dataset_name" maxlength="255" placeholder="New dataset name">
            <textarea name="new_dataset_description" maxlength="4000" rows="3" placeholder="Description"></textarea>
        </fieldset>
        <button class="button button-primary" type="submit">Validate import</button>
    </form>

    <?php if ($importView['validation'] !== null): ?>
        <?php $validation = $importView['validation']; ?>
        <h3>Validation results</h3>
        <p><strong>Total:</strong> <?= (int) $validation['totalRows']; ?> · <strong>Valid:</strong> <?= (int) $validation['importedRows']; ?> · <strong>Failed:</strong> <?= (int) $validation['failedRows']; ?></p>

        <?php if ($validation['errors'] !== []): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Row</th><th>Column</th><th>Error</th></tr></thead>
                    <tbody>
                    <?php foreach ($validation['errors'] as $error): ?>
                        <tr>
                            <td><?= (int) $error['rowNumber']; ?></td>
                            <td><?= htmlspecialchars((string) ($error['columnName'] ?? 'Record'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) $error['message'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No validation errors found.</p>
        <?php endif; ?>

        <form method="post" action="/?page=import">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="import-persist">
            <button class="button button-primary" type="submit" onclick="return confirm('Save all valid rows and record failed rows?')">Confirm and save valid rows</button>
        </form>
    <?php endif; ?>
<?php endif; ?>
