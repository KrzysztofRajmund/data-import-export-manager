<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dataset;
use App\Repositories\DatasetRepository;
use RuntimeException;

final class DatasetService
{
    public function __construct(private readonly DatasetRepository $repository)
    {
    }

    /** @return Dataset[] */
    public function list(): array
    {
        return $this->repository->findAll();
    }

    public function get(int $id): ?Dataset
    {
        return $this->repository->findById($id);
    }

    public function create(string $name, string $description): int
    {
        [$name, $description] = $this->validateAndNormalize($name, $description);

        if ($this->repository->nameExists($name)) {
            throw new RuntimeException('Dataset name already exists.');
        }

        return $this->repository->create($name, $description);
    }

    public function rename(int $id, string $name, string $description): void
    {
        if ($this->repository->findById($id) === null) {
            throw new RuntimeException('Dataset not found.');
        }

        [$name, $description] = $this->validateAndNormalize($name, $description);

        if ($this->repository->nameExists($name, $id)) {
            throw new RuntimeException('Dataset name already exists.');
        }

        $this->repository->update($id, $name, $description);
    }

    public function delete(int $id): void
    {
        if (!$this->repository->delete($id)) {
            throw new RuntimeException('Dataset not found or could not be deleted.');
        }
    }

    /** @return array{0:string,1:?string} */
    private function validateAndNormalize(string $name, string $description): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $description = trim($description);

        if ($name === '') {
            throw new RuntimeException('Dataset name is required.');
        }
        if (mb_strlen($name) > 255) {
            throw new RuntimeException('Dataset name must be at most 255 characters.');
        }
        if (mb_strlen($description) > 4000) {
            throw new RuntimeException('Dataset description must be at most 4000 characters.');
        }

        return [$name, $description === '' ? null : $description];
    }
}
