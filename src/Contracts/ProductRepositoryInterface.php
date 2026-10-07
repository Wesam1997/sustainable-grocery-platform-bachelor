<?php
declare(strict_types=1);

interface ProductRepositoryInterface
{
    public function find(int $id): ?array;
    public function findBasic(int $id): ?array;
    public function listFiltered(array $filters): array;
    public function related(string $merchant, int $id): array;
    public function vocabularyRows(): array;
}
