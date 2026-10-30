<?php
declare(strict_types=1);

require_once __DIR__ . '/ClimateCalculatorInterface.php';
require_once __DIR__ . '/../../Contracts/ClimateCalculationProviderInterface.php';

final class ClimateCalculatorRegistry implements ClimateCalculationProviderInterface
{
    /**
     * @var ClimateCalculatorInterface[]
     */
    private array $calculators;

    public function __construct(
        ClimateCalculatorInterface ...$calculators
    ) {
        $this->calculators = $calculators;
    }

    public function calculate(array $product): array
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($product)) {
                return $calculator->calculate($product);
            }
        }

        return $this->unavailableResult();
    }

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
