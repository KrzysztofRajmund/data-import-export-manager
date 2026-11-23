<?php

declare(strict_types=1);

use App\Security\Csrf;

$selectedDataset = $datasetView['dataset'];
$dynamicDatasetView = $datasetView['dynamic'];
$recordService = $datasetView['recordService'];
?>
<section class="page-header">
    <h2>Datasets</h2>
    <p>Create, view, rename and delete datasets.</p>
</section>

<section class="dataset-layout<?= $selectedDataset !== null ? ' dataset-detail-layout' : ''; ?>">
    <?php if ($selectedDataset === null): ?>
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
    <?php endif; ?>

    <aside class="dataset-panel">
        <?php if ($selectedDataset !== null): ?>
            <a class="button button-secondary" href="/?page=datasets">← Back to dataset list</a>
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
                        <option value="string">String</option><option value="integer">Integer</option><option value="decimal">Decimal</option><option value="boolean">Boolean</option><option value="date">Date</option><option value="datetime">Datetime</option>
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
                        <?php if ($editRecord !== null): ?><input type="hidden" name="record_id" value="<?= $editRecord->id; ?>"><?php endif; ?>
                        <?php foreach ($dynamicDatasetView['columns'] as $column): ?>
                            <?php $current = $editRecord !== null ? ($editRecord->data[$column->name] ?? '') : ''; ?>
                            <label for="field-<?= $column->id; ?>"><?= htmlspecialchars($column->label, ENT_QUOTES, 'UTF-8'); ?><?= $column->isRequired ? ' *' : ''; ?></label>
                            <?php if ($column->dataType === 'boolean'): ?>
                                <select id="field-<?= $column->id; ?>" name="<?= htmlspecialchars($column->name, ENT_QUOTES, 'UTF-8'); ?>" <?= $column->isRequired ? 'required' : ''; ?>>
                                    <option value="">—</option><option value="true" <?= $current === true || $current === 'true' ? 'selected' : ''; ?>>True</option><option value="false" <?= $current === false || $current === 'false' ? 'selected' : ''; ?>>False</option>
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
                                            <input type="hidden" name="action" value="delete-record"><input type="hidden" name="record_id" value="<?= $record->id; ?>">
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
                <label for="create-name">Name</label><input id="create-name" name="name" maxlength="255" required>
                <label for="create-description">Description</label><textarea id="create-description" name="description" maxlength="4000" rows="4"></textarea>
                <button class="button button-primary" type="submit">Create dataset</button>
            </form>
        <?php endif; ?>
    </aside>
</section>
