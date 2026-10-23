<?php
declare(strict_types=1);

final class ClimateMetrics implements ClimateMetricsInterface
{
    public function __construct(
        private ComparisonEngineInterface $comparisons,
        private ClimateExplanationInterface $explanations
    ) {}

    public function format(
        array $row,
        array $sig,
        int $rank
    ): array {
        $co2 = is_array($sig['co2'] ?? null)
            ? $sig['co2']
            : [];

        $rawValue = $co2['value'] ?? null;

        $available =
            ($co2['available'] ?? true) !== false
            && is_numeric($rawValue)
            && is_finite((float) $rawValue)
            && (float) $rawValue >= 0;

        $value = $available
            ? (float) $rawValue
            : null;

        $rank = $available
            ? (int) ($co2['rank'] ?? $rank)
            : 0;

        $className = match ($rank) {
            1 => 'low',
            2 => 'medium',
            3 => 'high',
            default => 'unknown'
        };

        $label = match ($rank) {
            1 => 'Low CO₂e emissions',
            2 => 'Medium CO₂e emissions',
            3 => 'High CO₂e emissions',
            default => 'CO₂e emissions'
        };

        $basis = (string) ($co2['basis'] ?? 'per kg');

        $note = $available
            ? (string) ($co2['note'] ?? '')
            : 'Climate impact information is not available.';

        /*
         * The existing comparison uses the same basis as value:
         * per kg, per serving or per box.
         */
        $comparison = $available
            ? $this->comparisons->compare($value, $basis, 'car')
            : null;

        /*
         * Additional package estimate.
         * Use a recorded calculation weight that matches the
         * environmental reference, rather than guessing from the title.
         */
        $rawWeight = $row['climate_weight_g'] ?? null;

        $weightAvailable =
            is_numeric($rawWeight)
            && is_finite((float) $rawWeight)
            && (float) $rawWeight > 0;

        $weightGrams = $weightAvailable
            ? (float) $rawWeight
            : null;

        $packageValue = null;

        /*
         * A value already expressed per serving or per box must
         * not be multiplied by the weight again.
         */
        if ($available && $basis === 'per kg' && $weightAvailable) {
            $calculatedValue = $value * ($weightGrams / 1000);

            if (is_finite($calculatedValue)) {
                $packageValue = $calculatedValue;
            }
        }

        $packageAvailable = $packageValue !== null;

        $packageComparison = $packageAvailable
            ? $this->comparisons->compare(
                $packageValue,
                'per package',
                'car'
            )
            : null;

        return [
            'product_id' => (int) ($row['id'] ?? 0),
            'available' => $available,
            'is_estimate' => true,
            'is_example' => (bool) ($co2['is_example'] ?? false),

            // Existing value and basis remain available to the UI.
            'value' => $value,
            'formatted' => $available
                ? number_format($value, 2, '.', '')
                : '',

            'unit' => $basis,
            'full_unit' => 'kg CO₂e ' . $basis,

            // Classification is not recalculated from package size.
            'rank' => $rank,
            'label' => $label,
            'className' => $className,

            'note' => $note,
            'comparison' => $available
                ? $this->explanations->explain($co2, $comparison)
                : $note,

            'comparison_data' => $comparison,

            'classification_note' =>
                $co2['classification_note'] ?? '',

            'driving_km' => $comparison !== null
                ? (int) round($comparison['value'])
                : null,

            // New package-specific result.
            'package' => [
                'available' => $packageAvailable,
                'calculation_weight_g' => $weightGrams,
                'value' => $packageValue,

                'formatted' => $packageAvailable
                    ? number_format($packageValue, 2, '.', '')
                    : '',

                'unit' => 'per package',
                'full_unit' => 'kg CO₂e per package',
                'is_estimate' => true,
                'is_example' => (bool) ($co2['is_example'] ?? false),

                'comparison_data' => $packageComparison,

                'driving_km' => $packageComparison !== null
                    ? round((float) $packageComparison['value'], 1)
                    : null,

                'note' => $packageAvailable
                    ? 'Estimated using the recorded weight matching the food reference.'
                    : 'A package estimate requires a per-kg climate value and a matching recorded weight.'
            ],

            'rating_score' => match ($rank) {
                1 => 5,
                2 => 3,
                3 => 1,
                default => 0
            }
        ];
    }
}