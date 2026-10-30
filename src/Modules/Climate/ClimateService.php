<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Contracts/ClimateServiceInterface.php';
require_once __DIR__ . '/../../Contracts/ClimateCalculationProviderInterface.php';

final class ClimateService implements ClimateServiceInterface
{
    private ClimateCalculationProviderInterface $calculators;

    public function __construct(
        ClimateCalculationProviderInterface $calculators
    ) {
        $this->calculators = $calculators;
    }

    /**
     * Calculate and classify climate impact for a product.
     *
     * The actual calculation is delegated to the
     * registered climate calculators.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function calculate(array $row): array
    {
        $climate = $this->calculators->calculate($row);

        // Default values for unavailable climate information.
        $climate = array_merge([
            'available' => false,
            'value' => null,
            'per_kg' => null,
            'formatted' => null,
            'basis' => null,
            'unit' => null,
            'calculation_type' => null,
            'is_estimate' => true,
            'is_example' => false,
            'note' => 'Climate information is not available.'
        ], $climate);

        /*
         * Climate classification always uses kg CO2e/kg,
         * even when the displayed value is per serving
         * or per box.
         */
        $classification = $this->classify(
            $climate['available'] === true
                ? $climate['per_kg']
                : null
        );

        $climate['label'] = $classification['label'];
        $climate['rank'] = $classification['rank'];

        /*
         * Explain that Low, Medium and High are
         * dataset-relative categories.
         */
        $climate['classification_note'] =
            'Dataset-relative climate categories (kg CO2e/kg): '
            . 'Low up to ' . CO2_LOW_MAX
            . ', Medium up to ' . CO2_MEDIUM_MAX
            . ', High above ' . CO2_MEDIUM_MAX . '.';

        return $climate;
    }

    /**
     * Classify emissions expressed in kg CO2e per kg.
     *
     * Low:    <= 1.80
     * Medium: <= 5.21
     * High:   > 5.21
     *
     * @param mixed $value
     * @return array{label: string, rank: int}
     */
    public function classify(mixed $value): array
    {
        if (
            !is_numeric($value)
            || !is_finite((float)$value)
            || (float)$value < 0
        ) {
            return [
                'label' => 'Unknown',
                'rank' => 0
            ];
        }

        $value = (float)$value;

        if ($value <= CO2_LOW_MAX) {
            return [
                'label' => 'Low',
                'rank' => 1
            ];
        }

        if ($value <= CO2_MEDIUM_MAX) {
            return [
                'label' => 'Medium',
                'rank' => 2
            ];
        }

        return [
            'label' => 'High',
            'rank' => 3
        ];
    }
}
