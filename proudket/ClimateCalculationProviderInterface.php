<?php
declare(strict_types=1);

interface CatalogServiceInterface
{
    public function find(int $id): ?array;
    public function items(array $params): array;
}
