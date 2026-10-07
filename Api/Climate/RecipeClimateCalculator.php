<?php

declare(strict_types=1);

require_once __DIR__ . '/ClimateCalculatorInterface.php';

final class RecipeClimateCalculator implements ClimateCalculatorInterface
{
    /**
     * @var string[]
     */
    private array $restaurantMerchants;

    /**
     * @param string[] $restaurantMerchants
     */
    public function __construct(array $restaurantMerchants)
    {
        $this->restaurantMerchants = $restaurantMerchants;
    }

    /**
     * Supports every product that contains recipe ingredients.
     */
    public function supports(array $product): bool
    {
        return (int)($product['recipe_count'] ?? 0) > 0;
    }

    /**
     * Calculates climate information from the recipe values
     * already retrieved by catalog.php.
     */
    public function calculate(array $product): array
    {
        $ingredientCount =
            (int)($product['recipe_count'] ?? 0);

        $totalWeight =
            (float)($product['recipe_weight_g'] ?? 0);

        $totalCo2 =
            $product['recipe_co2e'] ?? null;

        $usesAssumedValues =
            !empty($product['recipe_assumed']);

        if (
            $ingredientCount <= 0
            || $totalWeight <= 0
            || !is_numeric($totalCo2)
            || !is_finite((float)$totalCo2)
            || (float)$totalCo2 < 0
        ) {
            return $this->unavailableResult(
                $ingredientCount,
                $totalWeight,
                $usesAssumedValues
            );
        }

        $totalCo2 = (float)$totalCo2;

        /*
         * The per-kilogram value is used for a consistent
         * Low, Medium, or High classification.
         */
        $perKg = $totalCo2 / ($totalWeight / 1000);

        $isRestaurant = in_array(
            $product['merchant'] ?? '',
            $this->restaurantMerchants,
            true
        );

        /*
         * Restaurant meals are displayed per serving or per box.
         * Composite supermarket products are displayed per kg.
         */
        if ($isRestaurant) {
            $isBox = stripos(
                (string)($product['title'] ?? ''),
                'box'
            ) !== false;

            $basis = $isBox ? 'per box' : 'per serving';
            $displayValue = $totalCo2;
        } else {
            $basis = 'per kg';
            $displayValue = $perKg;
        }

        $note = $usesAssumedValues
            ? 'Estimated from an example recipe and average ingredient data. Actual ingredients and quantities may vary.'
            : 'Estimated from ingredient weights and average climate data.';

        return [
            'available' => true,
            'value' => $displayValue,
            'per_kg' => $perKg,
            'formatted' => number_format(
                $displayValue,
                2,
                '.',
                ''
            ),
            'basis' => $basis,
            'unit' => 'kg CO₂e ' . $basis,
            'calculation_type' => 'recipe_estimate',
            'ingredient_count' => $ingredientCount,
            'total_weight_g' => $totalWeight,
            'total_recipe_co2e' => $totalCo2,
            'is_restaurant' => $isRestaurant,
            'is_estimate' => true,
            'is_example' => $usesAssumedValues,
            'note' => $note
        ];
    }

    private function unavailableResult(
        int $ingredientCount = 0,
        float $totalWeight = 0,
        bool $usesAssumedValues = false
    ): array {
        return [
            'available' => false,
            'value' => null,
            'per_kg' => null,
            'formatted' => null,
            'basis' => null,
            'unit' => null,
            'calculation_type' => 'recipe_estimate',
            'ingredient_count' => $ingredientCount,
            'total_weight_g' => $totalWeight,
            'total_recipe_co2e' => null,
            'is_restaurant' => false,
            'is_estimate' => true,
            'is_example' => $usesAssumedValues,
            'note' =>
                'A complete climate estimate could not be calculated.'
        ];
    }
}