<?php
declare(strict_types=1);

interface OrderRepositoryInterface
{
    public function save(
        int $userId,
        string $address,
        array $items,
        float $total
    ): int;
}
