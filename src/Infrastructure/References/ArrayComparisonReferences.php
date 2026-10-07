<?php
declare(strict_types=1);

final class ArrayComparisonReferences implements ComparisonReferenceInterface
{
    public function __construct(private array $references) {}
    public function find(string $key): ?array { return $this->references[$key] ?? null; }
}
