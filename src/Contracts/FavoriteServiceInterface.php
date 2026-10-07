<?php
declare(strict_types=1);

interface FavoriteServiceInterface
{
    public function add(int $id): int;
    public function items(): array;
    public function remove(int $id): void;
    public function clear(): void;
}
