<?php
declare(strict_types=1);

interface ComparisonSelectorInterface
{
    public function select(float $kgCo2e, string $basis, int $productId): ?array;
}
