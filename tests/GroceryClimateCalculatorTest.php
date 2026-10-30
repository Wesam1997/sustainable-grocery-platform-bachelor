<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../src/Modules/Climate/GroceryClimateCalculator.php';



final class GroceryClimateCalculatorTest extends TestCase
{
    private GroceryClimateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new GroceryClimateCalculator();
    }

    /**
     * Creates a valid grocery product for testing.
     */
    private function product(array $overrides = []): array
    {
        return array_merge([
            'recipe_count' => 0,
            'agb_code' => 'TEST-001',
            'co2e_kg_per_kg' => 1.50,
            'product_name_en' => 'Tomatoes'
        ], $overrides);
    }

    /**
     * Test 1: Grocery products with an environmental
     * reference are supported.
     */
    public function testSupportsProductWithEnvironmentalReference(): void
    {
        $this->assertTrue(
            $this->calculator->supports($this->product())
        );
    }

    /**
     * Test 2: Products without a valid agb_code
     * are not supported.
     */
    public function testRejectsMissingEnvironmentalReference(): void
    {
        foreach ([null, '', '   '] as $invalidCode) {
            $result = $this->calculator->supports(
                $this->product(['agb_code' => $invalidCode])
            );

            $this->assertFalse($result);
        }
    }

    /**
     * Test 3: Products containing recipe ingredients
     * must be handled by RecipeClimateCalculator.
     */
    public function testRejectsProductsWithRecipe(): void
    {
        $result = $this->calculator->supports(
            $this->product(['recipe_count' => 3])
        );

        $this->assertFalse($result);
    }

    /**
     * Test 4: CO2e per kg is calculated correctly.
     */
    public function testCalculatesCo2PerKg(): void
    {
        $result = $this->calculator->calculate(
            $this->product(['co2e_kg_per_kg' => 1.50])
        );

        $this->assertTrue($result['available']);

        $this->assertEqualsWithDelta(
            1.50,
            $result['value'],
            0.0001
        );

        $this->assertEqualsWithDelta(
            1.50,
            $result['per_kg'],
            0.0001
        );

        $this->assertSame('1.50', $result['formatted']);
        $this->assertSame('per kg', $result['basis']);
        $this->assertSame('kg CO₂e per kg', $result['unit']);
    }

    /**
     * Test 5: Zero CO2e is a valid numeric value.
     */
    public function testAcceptsZeroCo2(): void
    {
        $result = $this->calculator->calculate(
            $this->product(['co2e_kg_per_kg' => 0])
        );

        $this->assertTrue($result['available']);
        $this->assertSame(0.0, $result['value']);
        $this->assertSame('0.00', $result['formatted']);
    }

    /**
     * Test 6: Negative CO2e values are rejected.
     */
    public function testRejectsNegativeCo2(): void
    {
        $result = $this->calculator->calculate(
            $this->product(['co2e_kg_per_kg' => -2.5])
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['value']);
        $this->assertNull($result['per_kg']);
    }

    /**
     * Test 7: Non-numeric and non-finite CO2e
     * values are rejected.
     */
    public function testRejectsInvalidCo2(): void
    {
        foreach (['invalid', INF, -INF, NAN] as $invalidValue) {
            $result = $this->calculator->calculate(
                $this->product([
                    'co2e_kg_per_kg' => $invalidValue
                ])
            );

            $this->assertFalse($result['available']);
            $this->assertNull($result['value']);
        }
    }

    /**
     * Test 8: Missing CO2e produces an
     * unavailable climate result.
     */
    public function testHandlesMissingCo2(): void
    {
        $result = $this->calculator->calculate(
            $this->product(['co2e_kg_per_kg' => null])
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['formatted']);
        $this->assertSame(
            'Climate information is not available.',
            $result['note']
        );
    }

    /**
     * Test 9: Environmental reference information
     * is preserved in the result.
     */
    public function testReturnsEnvironmentalReference(): void
    {
        $result = $this->calculator->calculate(
            $this->product()
        );

        $this->assertSame('TEST-001', $result['agb_code']);
        $this->assertSame('Tomatoes', $result['reference_name']);
        $this->assertSame(
            'environmental_reference',
            $result['calculation_type']
        );
    }

    /**
     * Test 10: Climate values are marked as estimates,
     * not exact product measurements.
     */
    public function testMarksClimateValueAsEstimate(): void
    {
        $result = $this->calculator->calculate(
            $this->product()
        );

        $this->assertTrue($result['is_estimate']);
        $this->assertFalse($result['is_example']);

        $this->assertStringContainsString(
            'average climate data',
            $result['note']
        );
    }
}
