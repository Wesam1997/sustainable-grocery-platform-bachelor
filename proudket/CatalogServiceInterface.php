<?php
declare(strict_types=1);

interface CartServiceInterface
{
    public function add(int $id, int $quantity): int;
    public function items(): array;
    public function remove(int $id): void;
    public function update(int $id, int $quantity): void;
    public function clear(): void;
    public function count(): int;
    public function total(): float;
}
