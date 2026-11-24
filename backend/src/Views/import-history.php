<?php
declare(strict_types=1);

$importHistory ??= [];
?>
<section class="page-header">
    <h2>Import History</h2>
    <p>Review completed, processing and failed import operations.</p>
</section>

<?php if ($importHistory === []): ?>
    <p class="empty-state">No import operations yet.</p>
<?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Filename</th><th>Dataset</th><th>Date</th><th>Status</th><th>Total</th><th>Imported</th><th>Failed</th></tr></thead>
        <tbody>
        <?php foreach ($importHistory as $job): ?>
            <tr>
                <td><?= htmlspecialchars((string) $job['filename'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) $job['dataset_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) $job['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><span class="status status-<?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $job['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?= (int) $job['total_rows']; ?></td><td><?= (int) $job['imported_rows']; ?></td><td><?= (int) $job['failed_rows']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>
