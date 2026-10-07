<?php

declare(strict_types=1);

require_once __DIR__ . '/ClimateCalculatorInterface.php';
require_once __DIR__ . '/GroceryClimateCalculator.php';
require_once __DIR__ . '/RecipeClimateCalculator.php';
require_once __DIR__ . '/ClimateCalculatorRegistry.php';

final class ClimateServiceFactory
{
    /**
     * @param string[] $restaurantMerchants
     */
    public static function create(
        array $restaurantMerchants
    ): ClimateCalculatorRegistry {
        return new ClimateCalculatorRegistry(
            /*
             * Recipe products must be checked first because
             * they may not have a direct agb_code.
             */
            new RecipeClimateCalculator($restaurantMerchants),

            /*
             * Products with one direct environmental reference.
             */
            new GroceryClimateCalculator()
        );
    }
}