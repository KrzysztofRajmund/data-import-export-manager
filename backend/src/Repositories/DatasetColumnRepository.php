<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DatasetColumn;
use mysqli;

final class DatasetColumnRepository
{
    public function __construct(private readonly mysqli $connection)
    {
    }

    /** @return DatasetColumn[] */
    public function findByDataset(int $datasetId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, dataset_id, name, label, data_type, position, is_required, is_searchable, created_at
             FROM dataset_columns WHERE dataset_id = ? ORDER BY position ASC, id ASC'
        );
        $statement->bind_param('i', $datasetId);
        $statement->execute();
        $result = $statement->get_result();
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $this->hydrate($row);
        }
        return $columns;
    }

    public function findById(int $id): ?DatasetColumn
    {
        $statement = $this->connection->prepare(
            'SELECT id, dataset_id, name, label, data_type, position, is_required, is_searchable, created_at
             FROM dataset_columns WHERE id = ?'
        );
        $statement->bind_param('i', $id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        return $row === null ? null : $this->hydrate($row);
    }

    public function nameExists(int $datasetId, string $name, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $statement = $this->connection->prepare('SELECT id FROM dataset_columns WHERE dataset_id = ? AND name = ? AND id <> ? LIMIT 1');
            $statement->bind_param('isi', $datasetId, $name, $exceptId);
        } else {
            $statement = $this->connection->prepare('SELECT id FROM dataset_columns WHERE dataset_id = ? AND name = ? LIMIT 1');
            $statement->bind_param('is', $datasetId, $name);
        }
        $statement->execute();
        return $statement->get_result()->fetch_assoc() !== null;
    }

    public function create(int $datasetId, string $name, string $label, string $dataType, bool $required, bool $searchable): int
    {
        $position = $this->nextPosition($datasetId);
        $requiredInt = $required ? 1 : 0;
        $searchableInt = $searchable ? 1 : 0;
        $statement = $this->connection->prepare(
            'INSERT INTO dataset_columns (dataset_id, name, label, data_type, position, is_required, is_searchable)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->bind_param('isssiii', $datasetId, $name, $label, $dataType, $position, $requiredInt, $searchableInt);
        $statement->execute();
        $columnId = (int) $statement->insert_id;

        if ($searchable) {
            $this->createVirtualColumn($columnId, $name);
        }
        return $columnId;
    }

    public function delete(DatasetColumn $column): void
    {
        if ($column->isSearchable) {
            $this->dropVirtualColumn($column->id);
        }
        $statement = $this->connection->prepare('DELETE FROM dataset_columns WHERE id = ?');
        $statement->bind_param('i', $column->id);
        $statement->execute();
    }

    public function countRecords(int $datasetId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) AS count_value FROM dataset_records WHERE dataset_id = ?');
        $statement->bind_param('i', $datasetId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        return (int) ($row['count_value'] ?? 0);
    }

    public function createVirtualColumn(int $columnId, string $name): void
    {
        $identifier = 'col_' . $columnId;
        $jsonName = str_replace(['\\', '"'], ['\\\\', '\\"'], $name);
        $this->connection->query(
            "ALTER TABLE dataset_records
             ADD COLUMN `{$identifier}` VARCHAR(255)
             AS (JSON_UNQUOTE(JSON_EXTRACT(data, '\$.\"{$jsonName}\"'))) VIRTUAL,
             ADD INDEX `idx_{$identifier}` (`{$identifier}`)"
        );
    }

    public function dropVirtualColumn(int $columnId): void
    {
        $identifier = 'col_' . $columnId;
        $this->connection->query(
            "ALTER TABLE dataset_records
             DROP INDEX `idx_{$identifier}`,
             DROP COLUMN `{$identifier}`"
        );
    }

    private function nextPosition(int $datasetId): int
    {
        $statement = $this->connection->prepare('SELECT COALESCE(MAX(position), -1) + 1 AS next_position FROM dataset_columns WHERE dataset_id = ?');
        $statement->bind_param('i', $datasetId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        return (int) ($row['next_position'] ?? 0);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): DatasetColumn
    {
        return new DatasetColumn(
            (int) $row['id'], (int) $row['dataset_id'], (string) $row['name'],
            (string) $row['label'], (string) $row['data_type'], (int) $row['position'],
            (bool) $row['is_required'], (bool) $row['is_searchable'], (string) $row['created_at'],
        );
    }

    public function updateDataType(int $columnId, string $dataType): void
    {
        $statement = $this->connection->prepare('UPDATE dataset_columns SET data_type = ? WHERE id = ?');
        $statement->bind_param('si', $dataType, $columnId);
        $statement->execute();
    }
}
