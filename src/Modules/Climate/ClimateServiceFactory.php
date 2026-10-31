<?php
declare(strict_types=1);

require_once __DIR__ . '/ClimateCalculatorInterface.php';
require_once __DIR__ . '/ClimateCalculatorRegistry.php';
require_once __DIR__ . '/GroceryClimateCalculator.php';
require_once __DIR__ . '/RecipeClimateCalculator.php';

final class ClimateServiceFactory
{
    public static function create(
        array $restaurantMerchants = RESTAURANTS
    ): ClimateCalculatorRegistry {
        return new ClimateCalculatorRegistry(
            new RecipeClimateCalculator($restaurantMerchants),
            new GroceryClimateCalculator()
        );
    }
}
