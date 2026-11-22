<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Dataset;
use mysqli;

final class DatasetRepository
{
    public function __construct(private readonly mysqli $connection)
    {
    }

    /** @return Dataset[] */
    public function findAll(): array
    {
        $result = $this->connection->query(
            'SELECT id, name, description, created_at, updated_at
             FROM datasets
             ORDER BY name ASC, id ASC'
        );

        $datasets = [];
        while ($row = $result->fetch_assoc()) {
            $datasets[] = $this->hydrate($row);
        }

        return $datasets;
    }

    public function findById(int $id): ?Dataset
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, description, created_at, updated_at
             FROM datasets
             WHERE id = ?'
        );
        $statement->bind_param('i', $id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();

        return $row === null ? null : $this->hydrate($row);
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $statement = $this->connection->prepare(
                'SELECT id FROM datasets WHERE name = ? AND id <> ? LIMIT 1'
            );
            $statement->bind_param('si', $name, $exceptId);
        } else {
            $statement = $this->connection->prepare(
                'SELECT id FROM datasets WHERE name = ? LIMIT 1'
            );
            $statement->bind_param('s', $name);
        }

        $statement->execute();
        return $statement->get_result()->fetch_assoc() !== null;
    }

    public function create(string $name, ?string $description): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO datasets (name, description) VALUES (?, ?)'
        );
        $statement->bind_param('ss', $name, $description);
        $statement->execute();

        return (int) $statement->insert_id;
    }

    public function update(int $id, string $name, ?string $description): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE datasets SET name = ?, description = ? WHERE id = ?'
        );
        $statement->bind_param('ssi', $name, $description, $id);
        $statement->execute();

        return $statement->affected_rows >= 0;
    }

    public function delete(int $id): bool
    {
        $statement = $this->connection->prepare('DELETE FROM datasets WHERE id = ?');
        $statement->bind_param('i', $id);
        $statement->execute();

        return $statement->affected_rows > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Dataset
    {
        return new Dataset(
            (int) $row['id'],
            (string) $row['name'],
            $row['description'] !== null ? (string) $row['description'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
