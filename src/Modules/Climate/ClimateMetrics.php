<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Contracts/ClimateMetricsInterface.php';
require_once __DIR__ . '/../../Contracts/ComparisonEngineInterface.php';
require_once __DIR__ . '/../../Contracts/ClimateExplanationInterface.php';
require_once __DIR__ . '/PackageClimateCalculator.php';

final class ClimateMetrics implements ClimateMetricsInterface
{
    private PackageClimateCalculator $packageCalculator;

    public function __construct(
        private ComparisonEngineInterface $comparisons,
        private ClimateExplanationInterface $explanations,
        ?PackageClimateCalculator $packageCalculator = null
    ) {
        $this->packageCalculator =
            $packageCalculator ?? new PackageClimateCalculator();
    }

    public function format(
        array $row,
        array $signals = [],
        ?int $rank = null
    ): array {
        $climate = $signals['co2'] ?? [];

        $available = ($climate['available'] ?? false) === true;

        $value = $available && is_numeric($climate['value'] ?? null)
            ? (float)$climate['value']
            : null;

        $basis = $climate['basis'] ?? null;

        $rank ??= (int)($climate['rank'] ?? 0);

        $labels = [
            1 => 'Low CO₂e emissions',
            2 => 'Medium CO₂e emissions',
            3 => 'High CO₂e emissions'
        ];

        $label = $labels[$rank] ?? 'Unknown CO₂e emissions';

        $result = array_merge($climate, [
            'available' => $available,
            'value' => $value,
            'formatted' => $value !== null
                ? number_format($value, 2, '.', '')
                : null,
            'rank' => $rank,
            'label' => $label,
            'className' => match ($rank) {
                1 => 'low',
                2 => 'medium',
                3 => 'high',
                default => 'unknown'
            },
            'rating_score' => match ($rank) {
                1 => 5,
                2 => 3,
                3 => 1,
                default => null
            }
        ]);

        // Calculate package impact only from a per-kg value.
        $weight = $row['climate_weight_g'] ?? null;

        $result['package'] = $this->packageCalculator->calculate(
            $value,
            $basis,
            $weight
        );

        // Compare the displayed climate value with driving.
        $carComparison = $value !== null
            ? $this->comparisons->compare($value, $basis, 'car')
            : null;

        $result['comparison'] = $carComparison;
        $result['driving_km'] = $carComparison;

        // Package comparison is only possible when weight is known.
        if ($result['package']['available'] === true) {
            $packageComparison = $this->comparisons->compare(
                $result['package']['value'],
                'per package',
                'car'
            );

            $result['package']['driving_km'] = $packageComparison;
        } else {
            $result['package']['driving_km'] = null;
        }

        $result['explanation'] = null;

        return $result;
    }
}
