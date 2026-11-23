<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DatasetColumn;
use App\Repositories\DatasetColumnRepository;
use RuntimeException;

final class DatasetColumnService
{
    private const TYPES = ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime'];

    public function __construct(private readonly DatasetColumnRepository $repository)
    {
    }

    /** @return DatasetColumn[] */
    public function list(int $datasetId): array
    {
        return $this->repository->findByDataset($datasetId);
    }

    public function create(int $datasetId, string $name, string $label, string $dataType, bool $required, bool $searchable): int
    {
        $name = trim($name);
        $label = trim($label);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new RuntimeException('Column name must start with a letter or underscore and contain only letters, numbers and underscores.');
        }
        if ($label === '') throw new RuntimeException('Column label is required.');
        if (mb_strlen($label) > 255) throw new RuntimeException('Column label must be at most 255 characters.');
        if (!in_array($dataType, self::TYPES, true)) throw new RuntimeException('Unsupported column data type.');
        if ($this->repository->nameExists($datasetId, $name)) throw new RuntimeException('A column with this name already exists in the dataset.');

        return $this->repository->create($datasetId, $name, $label, $dataType, $required, $searchable);
    }

    public function delete(int $datasetId, int $columnId): void
    {
        $column = $this->repository->findById($columnId);
        if ($column === null || $column->datasetId !== $datasetId) throw new RuntimeException('Column not found.');
        if ($this->repository->countRecords($datasetId) > 0) {
            throw new RuntimeException('Column deletion is blocked once the dataset contains records. Full schema evolution is planned for a later phase.');
        }
        $this->repository->delete($column);
    }

    public function validateValue(DatasetColumn $column, mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            if ($column->isRequired) throw new RuntimeException('Field "' . $column->label . '" is required.');
            return null;
        }

        $value = is_string($value) ? trim($value) : $value;
        return match ($column->dataType) {
            'string' => $this->stringValue($column, $value),
            'integer' => $this->integerValue($column, $value),
            'decimal' => $this->decimalValue($column, $value),
            'boolean' => $this->booleanValue($column, $value),
            'date' => $this->dateValue($column, $value),
            'datetime' => $this->datetimeValue($column, $value),
            default => throw new RuntimeException('Unsupported column data type.'),
        };
    }

    private function stringValue(DatasetColumn $column, mixed $value): string
    {
        $text = (string) $value;
        if (mb_strlen($text) > 255) throw new RuntimeException('Field "' . $column->label . '" must be at most 255 characters.');
        return $text;
    }

    private function integerValue(DatasetColumn $column, mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) throw new RuntimeException('Field "' . $column->label . '" must be a valid integer.');
        return (int) $value;
    }

    private function decimalValue(DatasetColumn $column, mixed $value): float
    {
        if (!is_numeric($value)) throw new RuntimeException('Field "' . $column->label . '" must be a valid decimal number.');
        return (float) $value;
    }

    private function booleanValue(DatasetColumn $column, mixed $value): bool
    {
        if (is_bool($value)) return $value;
        $normalized = strtolower((string) $value);
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) return true;
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) return false;
        throw new RuntimeException('Field "' . $column->label . '" must be true or false.');
    }

    private function dateValue(DatasetColumn $column, mixed $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException('Field "' . $column->label . '" must use the YYYY-MM-DD date format.');
        }
        return $date->format('Y-m-d');
    }

    private function datetimeValue(DatasetColumn $column, mixed $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new RuntimeException('Field "' . $column->label . '" must use the YYYY-MM-DDTHH:MM datetime format.');
        }
        return $date->format('Y-m-d\TH:i:s');
    }
}
