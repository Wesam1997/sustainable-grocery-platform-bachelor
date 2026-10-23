<?php
declare(strict_types=1);

final class ComparisonSelector implements ComparisonSelectorInterface
{
    // Display ranges are UI choices, not scientific classification thresholds.
    private const ACTIVITIES = [
        'car' => ['unit' => 'km', 'min' => 0.1, 'max' => 100.0],
        'phone' => ['unit' => 'charge', 'min' => 1.0, 'max' => 200.0],
        'shower' => ['unit' => 'min', 'min' => 0.5, 'max' => 60.0]
    ];

    public function __construct(private ComparisonEngineInterface $engine) {}

    public function select(float $kgCo2e, string $basis, int $productId): ?array
    {
        if (!is_finite($kgCo2e) || $kgCo2e <= 0 || $productId < 1) return null;
        $keys = array_keys(self::ACTIVITIES);
        // Rotate by product ID: the same product has a stable preferred activity.
        $offset = $productId % count($keys);
        $ordered = array_merge(array_slice($keys, $offset), array_slice($keys, 0, $offset));
        $fallback = null;
        foreach ($ordered as $key) {
            $comparison = $this->engine->compare($kgCo2e, $basis, $key);
            $rule = self::ACTIVITIES[$key];
            if ($comparison === null || ($comparison['unit'] ?? '') !== $rule['unit']) continue;
            $value = $comparison['value'] ?? null;
            if (!is_numeric($value) || !is_finite((float)$value) || (float)$value <= 0) continue;
            $fallback ??= $comparison;
            if ((float)$value >= $rule['min'] && (float)$value <= $rule['max']) {
                return $comparison;
            }
        }
        // If all usable references fall outside the preferred range, retain an honest number.
        return $fallback;
    }
}
