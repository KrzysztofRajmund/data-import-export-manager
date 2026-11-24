<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;

final class ImportJobRepository
{
    public function __construct(private readonly mysqli $connection)
    {
    }

    public function create(int $datasetId, string $filename, int $totalRows): int
    {
        $statement = $this->connection->prepare(
            "INSERT INTO import_jobs (dataset_id, filename, status, total_rows, imported_rows, failed_rows)
             VALUES (?, ?, 'processing', ?, 0, 0)"
        );
        $statement->bind_param('isi', $datasetId, $filename, $totalRows);
        $statement->execute();
        return (int) $statement->insert_id;
    }

    public function addErrors(int $jobId, array $errors): void
    {
        if ($errors === []) return;
        $statement = $this->connection->prepare(
            'INSERT INTO import_errors (import_job_id, row_number, column_name, error_message) VALUES (?, ?, ?, ?)'
        );
        foreach ($errors as $error) {
            $rowNumber = (int) $error['rowNumber'];
            $columnName = $error['columnName'] === null ? null : (string) $error['columnName'];
            $message = (string) $error['message'];
            $statement->bind_param('iiss', $jobId, $rowNumber, $columnName, $message);
            $statement->execute();
        }
    }

    public function complete(int $jobId, int $importedRows, int $failedRows): void
    {
        $status = 'completed';
        $statement = $this->connection->prepare(
            "UPDATE import_jobs
             SET status = ?, imported_rows = ?, failed_rows = ?, completed_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $statement->bind_param('siii', $status, $importedRows, $failedRows, $jobId);
        $statement->execute();
    }

    public function fail(int $jobId): void
    {
        $status = 'failed';
        $statement = $this->connection->prepare(
            "UPDATE import_jobs
             SET status = ?, completed_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $statement->bind_param('si', $status, $jobId);
        $statement->execute();
    }

    /** @return array<int,array<string,mixed>> */
    public function findAll(): array
    {
        $result = $this->connection->query(
            'SELECT ij.id, ij.dataset_id, ij.filename, ij.status, ij.total_rows,
                    ij.imported_rows, ij.failed_rows, ij.created_at, ij.completed_at,
                    d.name AS dataset_name
             FROM import_jobs ij
             INNER JOIN datasets d ON d.id = ij.dataset_id
             ORDER BY ij.created_at DESC, ij.id DESC'
        );
        $jobs = [];
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
        return $jobs;
    }

    public function recordExistsExactly(int $datasetId, array $data): bool
    {
        $statement = $this->connection->prepare('SELECT data FROM dataset_records WHERE dataset_id = ?');
        $statement->bind_param('i', $datasetId);
        $statement->execute();
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $stored = json_decode((string) $row['data'], true);
            if (is_array($stored) && $stored == $data) return true;
        }
        return false;
    }
}
