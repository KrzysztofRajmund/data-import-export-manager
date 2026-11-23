<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DatasetColumn;
use App\Models\DatasetRecord;
use mysqli;

final class DatasetRecordRepository
{
    public function __construct(private readonly mysqli $connection)
    {
    }

    /** @return array{records: DatasetRecord[], total: int, pages: int} */
    public function paginate(int $datasetId, int $page, int $perPage, string $search, array $searchableColumns): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $countSql = 'SELECT COUNT(*) AS count_value FROM dataset_records WHERE dataset_id = ?';
        $listSql = 'SELECT id, dataset_id, data, created_at, updated_at FROM dataset_records WHERE dataset_id = ?';
        $params = [$datasetId];
        $types = 'i';

        if ($search !== '' && $searchableColumns !== []) {
            $searchable = array_values(array_filter($searchableColumns, static fn (DatasetColumn $column): bool => $column->isSearchable));
            $first = $searchable[0] ?? $searchableColumns[0];
            $like = '%' . $search . '%';
            if ($first->isSearchable) {
                $virtual = 'col_' . $first->id;
                $condition = " AND `$virtual` LIKE ?";
                $countSql .= $condition;
                $listSql .= $condition;
                $types .= 's';
                $params[] = $like;
            } else {
                $jsonName = str_replace(['\\', '"'], ['\\\\', '\\"'], $first->name);
                $condition = " AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.\"{$jsonName}\"')) LIKE ?";
                $countSql .= $condition;
                $listSql .= $condition;
                $types .= 's';
                $params[] = $like;
            }
        }

        $countStatement = $this->connection->prepare($countSql);
        $countBindings = [$types];
        foreach ($params as &$param) {
            $countBindings[] = &$param;
        }
        call_user_func_array([$countStatement, 'bind_param'], $countBindings);
        $countStatement->execute();
        $total = (int) $countStatement->get_result()->fetch_assoc()['count_value'];
        $pages = max(1, (int) ceil($total / $perPage));

        $listSql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $perPage;
        $params[] = $offset;
        $statement = $this->connection->prepare($listSql);
        $listBindings = [$types];
        foreach ($params as &$param) {
            $listBindings[] = &$param;
        }
        call_user_func_array([$statement, 'bind_param'], $listBindings);
        $statement->execute();
        $result = $statement->get_result();

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $data = json_decode((string) $row['data'], true);
            $records[] = new DatasetRecord(
                (int) $row['id'], (int) $row['dataset_id'], is_array($data) ? $data : [],
                (string) $row['created_at'], (string) $row['updated_at'],
            );
        }
        return ['records' => $records, 'total' => $total, 'pages' => $pages];
    }

    public function findById(int $datasetId, int $recordId): ?DatasetRecord
    {
        $statement = $this->connection->prepare('SELECT id, dataset_id, data, created_at, updated_at FROM dataset_records WHERE dataset_id = ? AND id = ?');
        $statement->bind_param('ii', $datasetId, $recordId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        if ($row === null) return null;
        $data = json_decode((string) $row['data'], true);
        return new DatasetRecord((int) $row['id'], (int) $row['dataset_id'], is_array($data) ? $data : [], (string) $row['created_at'], (string) $row['updated_at']);
    }

    public function create(int $datasetId, array $data): int
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($json)) throw new \RuntimeException('Could not encode record data.');
        $statement = $this->connection->prepare('INSERT INTO dataset_records (dataset_id, data) VALUES (?, ?)');
        $statement->bind_param('is', $datasetId, $json);
        $statement->execute();
        return (int) $statement->insert_id;
    }

    public function update(int $datasetId, int $recordId, array $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if (!is_string($json)) throw new \RuntimeException('Could not encode record data.');
        $statement = $this->connection->prepare('UPDATE dataset_records SET data = ? WHERE dataset_id = ? AND id = ?');
        $statement->bind_param('sii', $json, $datasetId, $recordId);
        $statement->execute();
        return $statement->affected_rows >= 0;
    }

    public function delete(int $datasetId, int $recordId): bool
    {
        $statement = $this->connection->prepare('DELETE FROM dataset_records WHERE dataset_id = ? AND id = ?');
        $statement->bind_param('ii', $datasetId, $recordId);
        $statement->execute();
        return $statement->affected_rows > 0;
    }
}
