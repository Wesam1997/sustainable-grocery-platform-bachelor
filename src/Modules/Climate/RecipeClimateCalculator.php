<?php
declare(strict_types=1);

require_once __DIR__ . '/ClimateCalculatorInterface.php';

final class RecipeClimateCalculator implements ClimateCalculatorInterface
{
    public function __construct(
        private array $restaurantMerchants = []
    ) {}

    private function isRestaurant(array $product): bool
    {
        $merchant = strtolower(trim((string)($product['merchant'] ?? '')));

        foreach ($this->restaurantMerchants as $restaurant) {
            if ($merchant === strtolower(trim((string)$restaurant))) {
                return true;
            }
        }

        return false;
    }

    public function supports(array $product): bool
    {
        return (int)($product['recipe_count'] ?? 0) > 0
            || $this->isRestaurant($product);
    }

    public function calculate(array $product): array
    {
        $co2e = $product['recipe_co2e'] ?? null;
        $weight = $product['recipe_weight_g'] ?? null;

        if (
            !is_numeric($co2e) ||
            !is_numeric($weight) ||
            !is_finite((float)$co2e) ||
            !is_finite((float)$weight) ||
            (float)$co2e < 0 ||
            (float)$weight <= 0
        ) {
            return $this->unavailableResult();
        }

        $co2e = (float)$co2e;
        $weight = (float)$weight;
        $perKg = $co2e / ($weight / 1000);

        $isRestaurant = $this->isRestaurant($product);
        $isBox = $isRestaurant
            && str_contains(
                strtolower((string)($product['title'] ?? '')),
                'box'
            );

        $basis = $isRestaurant
            ? ($isBox ? 'per box' : 'per serving')
            : 'per kg';

        $value = $isRestaurant ? $co2e : $perKg;

        $unit = match ($basis) {
            'per box' => 'kg CO₂e per box',
            'per serving' => 'kg CO₂e per serving',
            default => 'kg CO₂e per kg'
        };

        $isExample = (bool)($product['recipe_assumed'] ?? false);

        return [
            'available' => true,
            'value' => $value,
            'per_kg' => $perKg,
            'formatted' => number_format($value, 2, '.', ''),
            'basis' => $basis,
            'unit' => $unit,
            'calculation_type' => 'recipe_estimate',
            'is_restaurant' => $isRestaurant,
            'agb_code' => null,
            'reference_name' => null,
            'is_estimate' => true,
            'is_example' => $isExample,
            'note' => $isExample
                ? 'Estimated using an example recipe and average climate data.'
                : 'Estimated from the ingredients and their climate reference values.'
        ];
    }

    private function unavailableResult(): array
    {
        return [
            'available' => false,
            'value' => null,
            'per_kg' => null,
            'formatted' => null,
            'basis' => null,
            'unit' => null,
            'calculation_type' => 'recipe_estimate',
            'is_restaurant' => false,
            'agb_code' => null,
            'reference_name' => null,
            'is_estimate' => true,
            'is_example' => false,
            'note' => 'Recipe climate information is not available.'
        ];
    }
}
