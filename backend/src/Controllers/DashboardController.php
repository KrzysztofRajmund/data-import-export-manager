<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;

final class DashboardController
{
    public function index(): array
    {
        $connection = Database::getConnection();

        $counts = [];
        foreach ([
            'datasets' => 'SELECT COUNT(*) AS value FROM datasets',
            'records' => 'SELECT COUNT(*) AS value FROM dataset_records',
            'imports' => 'SELECT COUNT(*) AS value FROM import_jobs',
            'exports' => 'SELECT COUNT(*) AS value FROM export_jobs',
        ] as $key => $sql) {
            $row = $connection->query($sql)->fetch_assoc();
            $counts[$key] = (int) ($row['value'] ?? 0);
        }

        $recentImports = $connection->query(
            'SELECT ij.id, ij.filename, ij.status, ij.total_rows, ij.imported_rows, ij.failed_rows,
                    ij.created_at, ij.completed_at, d.name AS dataset_name
             FROM import_jobs ij
             INNER JOIN datasets d ON d.id = ij.dataset_id
             ORDER BY ij.created_at DESC, ij.id DESC
             LIMIT 5'
        );

        $recentExports = $connection->query(
            'SELECT ej.id, ej.format, ej.status, ej.total_rows, ej.created_at, ej.completed_at,
                    d.name AS dataset_name
             FROM export_jobs ej
             INNER JOIN datasets d ON d.id = ej.dataset_id
             ORDER BY ej.created_at DESC, ej.id DESC
             LIMIT 5'
        );

        return [
            'counts' => $counts,
            'recentImports' => $this->rows($recentImports),
            'recentExports' => $this->rows($recentExports),
        ];
    }

    private function rows($result): array
    {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}
