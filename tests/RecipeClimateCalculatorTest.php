<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Api/Climate/RecipeClimateCalculator.php';

final class RecipeClimateCalculatorTest extends TestCase
{
    private RecipeClimateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new RecipeClimateCalculator([
            'Flash Food Restaurant'
        ]);
    }

    /**
     * Creates a test product with default recipe values.
     */
    private function product(array $overrides = []): array
    {
        return array_merge([
            'recipe_count' => 3,
            'recipe_weight_g' => 500,
            'recipe_co2e' => 2.5,
            'recipe_assumed' => false,
            'merchant' => 'Supermarket',
            'title' => 'Vegetable Meal'
        ], $overrides);
    }

    /**
     * Test 1: Products with recipe ingredients are supported.
     */
    public function testSupportsProductWithIngredients(): void
    {
        $this->assertTrue(
            $this->calculator->supports($this->product())
        );
    }

    /**
     * Test 2: Products without ingredients are not supported.
     */
    public function testDoesNotSupportProductWithoutIngredients(): void
    {
        $this->assertFalse(
            $this->calculator->supports(
                $this->product(['recipe_count' => 0])
            )
        );
    }

    /**
     * Test 3: CO2e per kilogram is calculated correctly.
     *
     * Formula:
     * 2.5 / (500 / 1000) = 5.0 kg CO2e/kg
     */
    public function testCalculatesCo2PerKg(): void
    {
        $result = $this->calculator->calculate(
            $this->product()
        );

        $this->assertTrue($result['available']);

        $this->assertEqualsWithDelta(
            5.0,
            $result['per_kg'],
            0.0001
        );

        $this->assertEqualsWithDelta(
            5.0,
            $result['value'],
            0.0001
        );

        $this->assertSame('per kg', $result['basis']);
        $this->assertSame('5.00', $result['formatted']);
    }

    /**
     * Test 4: Restaurant meals use CO2e per serving.
     */
    public function testRestaurantUsesPerServing(): void
    {
        $result = $this->calculator->calculate(
            $this->product([
                'merchant' => 'Flash Food Restaurant'
            ])
        );

        $this->assertTrue($result['available']);
        $this->assertTrue($result['is_restaurant']);
        $this->assertSame('per serving', $result['basis']);

        $this->assertEqualsWithDelta(
            2.5,
            $result['value'],
            0.0001
        );

        $this->assertEqualsWithDelta(
            5.0,
            $result['per_kg'],
            0.0001
        );
    }

    /**
     * Test 5: Restaurant boxes use CO2e per box.
     */
    public function testRestaurantBoxUsesPerBox(): void
    {
        $result = $this->calculator->calculate(
            $this->product([
                'merchant' => 'Flash Food Restaurant',
                'title' => 'Vegetable Box'
            ])
        );

        $this->assertTrue($result['available']);
        $this->assertSame('per box', $result['basis']);

        $this->assertEqualsWithDelta(
            2.5,
            $result['value'],
            0.0001
        );
    }

    /**
     * Test 6: Zero recipe weight produces an unavailable result.
     */
    public function testRejectsZeroWeight(): void
    {
        $result = $this->calculator->calculate(
            $this->product([
                'recipe_weight_g' => 0
            ])
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['value']);
        $this->assertNull($result['per_kg']);
    }

    /**
     * Test 7: Invalid CO2e values are rejected.
     */
    public function testRejectsInvalidCo2(): void
    {
        $invalidValues = [
            null,
            -1,
            'invalid',
            INF
        ];

        foreach ($invalidValues as $invalidCo2) {
            $result = $this->calculator->calculate(
                $this->product([
                    'recipe_co2e' => $invalidCo2
                ])
            );

            $this->assertFalse($result['available']);
            $this->assertNull($result['value']);
        }
    }

    /**
     * Test 8: Assumed recipe values are marked as examples.
     */
    public function testMarksAssumedRecipeAsExample(): void
    {
        $result = $this->calculator->calculate(
            $this->product([
                'recipe_assumed' => true
            ])
        );

        $this->assertTrue($result['available']);
        $this->assertTrue($result['is_estimate']);
        $this->assertTrue($result['is_example']);

        $this->assertStringContainsString(
            'example recipe',
            $result['note']
        );
    }

    /**
     * Test 9: Zero CO2e is accepted as a valid numeric value.
     */
    public function testAcceptsZeroCo2(): void
    {
        $result = $this->calculator->calculate(
            $this->product([
                'recipe_co2e' => 0
            ])
        );

        $this->assertTrue($result['available']);

        $this->assertEqualsWithDelta(
            0.0,
            $result['per_kg'],
            0.0001
        );

        $this->assertSame('0.00', $result['formatted']);
    }
}
