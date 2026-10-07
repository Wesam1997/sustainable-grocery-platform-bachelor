<?php
declare(strict_types=1);

interface ComparisonReferenceInterface
{
    public function find(string $key): ?array;
}
