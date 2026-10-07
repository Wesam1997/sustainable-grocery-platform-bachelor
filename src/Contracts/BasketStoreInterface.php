<?php
declare(strict_types=1);

interface BasketStoreInterface
{
    public function load(string $key): array;
    public function save(string $key, array $items): void;
    public function clear(string $key): void;
}
