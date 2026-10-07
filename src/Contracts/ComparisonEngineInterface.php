<?php
declare(strict_types=1);

interface ComparisonEngineInterface
{
    public function compare(float $kgCo2e, string $basis, string $key): ?array;
}
