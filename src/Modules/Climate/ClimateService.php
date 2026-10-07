<?php
declare(strict_types=1);

final class ClimateService implements ClimateServiceInterface
{
    public function __construct(private ClimateCalculationProviderInterface $calculators) {}
    public function calculate(array $row): array
    {
        $climate = $this->calculators->calculate($row);

        $climate = array_merge(
            [
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
            ],
            $climate
        );

        /*
         * Low, Medium and High are always calculated using
         * the comparable per-kilogram value.
         */
        $classification = $this->classify(
            $climate['per_kg']
        );

        $climate['label'] = $classification['label'];
        $climate['rank'] = $classification['rank'];

        $climate['classification_note'] =
            'Prototype categories are based on emissions per kg: '
            . 'Low up to 2; Medium above 2 and up to 5; '
            . 'High above 5.';

        return $climate;
    }
    public function classify($value): array
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
