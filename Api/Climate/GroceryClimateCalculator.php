<?php

declare(strict_types=1);

require_once __DIR__ . '/ClimateCalculatorInterface.php';

final class GroceryClimateCalculator implements ClimateCalculatorInterface
{
    /**
     * Grocery products have a direct environmental reference
     * and do not contain a restaurant recipe.
     */
    public function supports(array $product): bool
    {
        $hasRecipe = (int)($product['recipe_count'] ?? 0) > 0;

        $hasEnvironmentalReference =
            isset($product['agb_code'])
            && trim((string)$product['agb_code']) !== '';

        return !$hasRecipe && $hasEnvironmentalReference;
    }

    /**
     * Returns climate information from the environmental
     * reference already retrieved by catalog.php.
     */
    public function calculate(array $product): array
    {
        $value = $product['co2e_kg_per_kg'] ?? null;

        if (
            !is_numeric($value)
            || !is_finite((float)$value)
            || (float)$value < 0
        ) {
            return $this->unavailableResult();
        }

        $value = (float)$value;

        return [
            'available' => true,
            'value' => $value,
            'per_kg' => $value,
            'formatted' => number_format($value, 2, '.', ''),
            'basis' => 'per kg',
            'unit' => 'kg CO₂e per kg',
            'calculation_type' => 'environmental_reference',
            'agb_code' => $product['agb_code'],
            'reference_name' =>
                $product['product_name_en'] ?? null,

            /*
             * The value represents an average reference food,
             * not a measurement of the specific branded product.
             */
            'is_estimate' => true,
            'is_example' => false,

            'note' =>
                'Estimated from average climate data for the matched food.'
        ];
    }

    private function unavailableResult(): array
    {
        return [
            'available' => false,
            'value' => null,
            'per_kg' => null,
            'formatted' => null,
            'basis' => 'per kg',
            'unit' => 'kg CO₂e per kg',
            'calculation_type' => 'environmental_reference',
            'agb_code' => null,
            'reference_name' => null,
            'is_estimate' => true,
            'is_example' => false,
            'note' => 'Climate information is not available.'
        ];
    }
}