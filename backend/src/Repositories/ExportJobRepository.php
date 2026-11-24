<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;

final class ExportJobRepository
{
    public function __construct(private readonly mysqli $connection)
    {
    }

    public function create(int $datasetId, string $format, int $totalRows): int
    {
        $statement = $this->connection->prepare(
            "INSERT INTO export_jobs (dataset_id, format, status, total_rows)
             VALUES (?, ?, 'processing', ?)"
        );
        $statement->bind_param('isi', $datasetId, $format, $totalRows);
        $statement->execute();
        return (int) $statement->insert_id;
    }

    public function complete(int $jobId): void
    {
        $statement = $this->connection->prepare(
            "UPDATE export_jobs SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $statement->bind_param('i', $jobId);
        $statement->execute();
    }

    public function fail(int $jobId): void
    {
        $statement = $this->connection->prepare(
            "UPDATE export_jobs SET status = 'failed', completed_at = CURRENT_TIMESTAMP WHERE id = ?"
        );
        $statement->bind_param('i', $jobId);
        $statement->execute();
    }

    /** @return array<int,array<string,mixed>> */
    public function findAll(): array
    {
        $result = $this->connection->query(
            'SELECT ej.id, ej.dataset_id, ej.format, ej.status, ej.total_rows,
                    ej.created_at, ej.completed_at, d.name AS dataset_name
             FROM export_jobs ej
             INNER JOIN datasets d ON d.id = ej.dataset_id
             ORDER BY ej.created_at DESC, ej.id DESC'
        );
        $jobs = [];
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
        return $jobs;
    }
}
