<?php
declare(strict_types=1);

final class ComparisonEngine implements ComparisonEngineInterface
{
    public function __construct(private ComparisonReferenceInterface $references) {}
    public function compare(float $kgCo2e, string $basis, string $key): ?array
    {
        if (!is_finite($kgCo2e) || $kgCo2e < 0) return null;
        $reference = $this->references->find($key);
        $factor = $reference['kg_co2e_per_unit'] ?? null;
        if (!is_numeric($factor) || !is_finite((float)$factor) || (float)$factor <= 0) return null;
        return [
            'key' => $key, 'value' => $kgCo2e / (float)$factor,
            'unit' => $reference['unit'], 'basis' => $basis,
            'factor' => (float)$factor, 'source' => $reference['source'] ?? null,
            'is_example' => (bool)($reference['is_example'] ?? true)
        ];
    }
}
