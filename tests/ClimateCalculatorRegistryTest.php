<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Modules/Climate/ClimateCalculatorInterface.php';
require_once __DIR__ . '/../src/Modules/Climate/ClimateCalculatorRegistry.php';
require_once __DIR__ . '/../src/Modules/Climate/GroceryClimateCalculator.php';
require_once __DIR__ . '/../src/Modules/Climate/RecipeClimateCalculator.php';

final class ClimateCalculatorRegistryTest extends TestCase
{
    /**
     * Creates a grocery product with environmental data.
     */
    private function groceryProduct(): array
    {
        return [
            'recipe_count' => 0,
            'agb_code' => 'TEST-001',
            'co2e_kg_per_kg' => 1.5,
            'product_name_en' => 'Tomatoes',
            'merchant' => 'Supermarket',
            'title' => 'Tomatoes'
        ];
    }

    /**
     * Creates a product with recipe ingredients.
     */
    private function recipeProduct(): array
    {
        return [
            'recipe_count' => 3,
            'recipe_weight_g' => 500,
            'recipe_co2e' => 2.5,
            'recipe_assumed' => false,
            'merchant' => 'Flash Food Restaurant',
            'title' => 'Vegetable Meal'
        ];
    }

    /**
     * Creates a registry with the real calculators.
     */
    private function createRegistry(): ClimateCalculatorRegistry
    {
        return new ClimateCalculatorRegistry(
            new RecipeClimateCalculator(['Flash Food Restaurant']),
            new GroceryClimateCalculator()
        );
    }

    /**
     * Test 1: Grocery products use GroceryClimateCalculator.
     */
    public function testSelectsGroceryCalculator(): void
    {
        $result = $this->createRegistry()->calculate(
            $this->groceryProduct()
        );

        $this->assertTrue($result['available']);

        $this->assertSame(
            'environmental_reference',
            $result['calculation_type']
        );

        $this->assertEqualsWithDelta(
            1.5,
            $result['value'],
            0.0001
        );
    }

    /**
     * Test 2: Recipe products use RecipeClimateCalculator.
     */
    public function testSelectsRecipeCalculator(): void
    {
        $result = $this->createRegistry()->calculate(
            $this->recipeProduct()
        );

        $this->assertTrue($result['available']);

        $this->assertSame(
            'recipe_estimate',
            $result['calculation_type']
        );

        $this->assertSame(
            'per serving',
            $result['basis']
        );

        $this->assertEqualsWithDelta(
            2.5,
            $result['value'],
            0.0001
        );
    }

    /**
     * Test 3: Unsupported products return unavailable.
     */
    public function testReturnsUnavailableForUnsupportedProduct(): void
    {
        $result = $this->createRegistry()->calculate([
            'recipe_count' => 0,
            'agb_code' => null
        ]);

        $this->assertFalse($result['available']);
        $this->assertNull($result['value']);
        $this->assertNull($result['calculation_type']);

        $this->assertSame(
            'Climate information is not available.',
            $result['message']
        );
    }

    /**
     * Test 4: An empty registry returns unavailable.
     */
    public function testReturnsUnavailableWhenRegistryIsEmpty(): void
    {
        $registry = new ClimateCalculatorRegistry();

        $result = $registry->calculate(
            $this->groceryProduct()
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['value']);
        $this->assertNull($result['formatted']);
        $this->assertNull($result['unit']);
        $this->assertFalse($result['is_estimate']);
    }

    /**
     * Test 5: The first supporting calculator is selected.
     */
    public function testSelectsFirstSupportingCalculator(): void
    {
        $first = $this->createMock(ClimateCalculatorInterface::class);
        $second = $this->createMock(ClimateCalculatorInterface::class);

        $first->method('supports')->willReturn(true);
        $first->method('calculate')->willReturn([
            'source' => 'first'
        ]);

        $second->method('supports')->willReturn(true);
        $second->method('calculate')->willReturn([
            'source' => 'second'
        ]);

        $registry = new ClimateCalculatorRegistry(
            $first,
            $second
        );

        $result = $registry->calculate([]);

        $this->assertSame('first', $result['source']);
    }

    /**
     * Test 6: Unsupported calculators are skipped.
     */
    public function testSkipsUnsupportedCalculator(): void
    {
        $first = $this->createMock(ClimateCalculatorInterface::class);
        $second = $this->createMock(ClimateCalculatorInterface::class);

        $first->expects($this->once())
            ->method('supports')
            ->willReturn(false);

        $first->expects($this->never())
            ->method('calculate');

        $second->expects($this->once())
            ->method('supports')
            ->willReturn(true);

        $second->expects($this->once())
            ->method('calculate')
            ->willReturn(['source' => 'second']);

        $registry = new ClimateCalculatorRegistry(
            $first,
            $second
        );

        $result = $registry->calculate([]);

        $this->assertSame('second', $result['source']);
    }

    /**
     * Test 7: Calculator results are returned unchanged.
     */
    public function testReturnsCalculatorResultUnchanged(): void
    {
        $expected = [
            'available' => true,
            'value' => 4.2,
            'formatted' => '4.20',
            'custom_field' => 'test'
        ];

        $calculator = $this->createMock(
            ClimateCalculatorInterface::class
        );

        $calculator->method('supports')->willReturn(true);
        $calculator->method('calculate')->willReturn($expected);

        $registry = new ClimateCalculatorRegistry($calculator);

        $this->assertSame(
            $expected,
            $registry->calculate([])
        );
    }

    /**
     * Test 8: Registry stops after finding a calculator.
     */
    public function testStopsAfterFirstSupportingCalculator(): void
    {
        $first = $this->createMock(ClimateCalculatorInterface::class);
        $second = $this->createMock(ClimateCalculatorInterface::class);

        $first->expects($this->once())
            ->method('supports')
            ->willReturn(true);

        $first->expects($this->once())
            ->method('calculate')
            ->willReturn(['available' => true]);

        $second->expects($this->never())
            ->method('supports');

        $second->expects($this->never())
            ->method('calculate');

        $registry = new ClimateCalculatorRegistry(
            $first,
            $second
        );

        $result = $registry->calculate([]);

        $this->assertTrue($result['available']);
    }
}
