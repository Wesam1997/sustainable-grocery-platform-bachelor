<?php

declare(strict_types=1);

/**
 * Calculates the estimated climate impact of a product package.
 *
 * Example:
 * 2.20 kg CO2e per kg
 * Package weight: 400 g
 *
 * Result:
 * 2.20 * (400 / 1000) = 0.88 kg CO2e
 */
final class PackageClimateCalculator
{
    /**
     * Calculate climate impact per package.
     *
     * @param mixed $value  Climate value
     * @param mixed $basis  Calculation basis
     * @param mixed $weightG Package weight in grams
     *
     * @return array<string, mixed>
     */
    public function calculate(
        mixed $value,
        mixed $basis,
        mixed $weightG
    ): array {
        $unavailable = [
            'available' => false,
            'value' => null,
            'formatted' => null,
            'weight_g' => null,
            'calculation_weight_g' => null,
            'unit' => 'kg CO₂e per package',
            'note' => 'Package climate impact is not available.'
        ];

        // Package calculation only applies to per-kg values.
        if ($basis !== 'per kg') {
            return $unavailable;
        }

        // Validate climate value.
        if (
            !is_numeric($value)
            || !is_finite((float)$value)
            || (float)$value < 0
        ) {
            return $unavailable;
        }

        // Validate package weight.
        if (
            !is_numeric($weightG)
            || !is_finite((float)$weightG)
            || (float)$weightG <= 0
        ) {
            return $unavailable;
        }

        $value = (float)$value;
        $weightG = (float)$weightG;

        // Convert grams to kilograms.
        $weightKg = $weightG / 1000;

        // Calculate total package emissions.
        $packageCo2e = $value * $weightKg;

        if (!is_finite($packageCo2e)) {
            return $unavailable;
        }

        return [
            'available' => true,
            'value' => $packageCo2e,
            'formatted' => number_format(
                $packageCo2e,
                2,
                '.',
                ''
            ),
            'weight_g' => $weightG,
            'calculation_weight_g' => $weightG,
            'unit' => 'kg CO₂e per package',
            'note' => 'Estimated from the product climate impact per kg and the package weight.'
        ];
    }
}
