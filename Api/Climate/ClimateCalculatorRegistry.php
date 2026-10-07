<?php

declare(strict_types=1);

require_once __DIR__ . '/ClimateCalculatorInterface.php';
require_once __DIR__ . '/../../src/Contracts/ClimateCalculationProviderInterface.php';

final class ClimateCalculatorRegistry implements ClimateCalculationProviderInterface
{
    /**
     * @var ClimateCalculatorInterface[]
     */
    private array $calculators;

    /**
     * Receives all available climate calculation components.
     */
    public function __construct(
        ClimateCalculatorInterface ...$calculators
    ) {
        $this->calculators = $calculators;
    }

    /**
     * Finds the calculator that supports the product.
     */
    public function calculate(array $product): array
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($product)) {
                return $calculator->calculate($product);
            }
        }

        return $this->unavailableResult();
    }

    /**
     * Standard response when no calculator supports the product.
     */
    private function unavailableResult(): array
    {
        return [
            'available' => false,
            'value' => null,
            'formatted' => null,
            'unit' => null,
            'calculation_type' => null,
            'is_estimate' => false,
            'message' => 'Climate information is not available.'
        ];
    }
}
