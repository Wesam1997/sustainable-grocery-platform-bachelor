<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/bootstrap.php';

final class PackageClimateCalculatorTest extends TestCase
{
    public function testCalculatesPackageEmissions(): void
    {
        $calculator = new PackageClimateCalculator();

        $result = $calculator->calculate(
            2.20,
            'per kg',
            400
        );

        $this->assertTrue($result['available']);

        $this->assertEqualsWithDelta(
            0.88,
            $result['value'],
            0.000001
        );

        $this->assertSame(
            400.0,
            $result['weight_g']
        );
    }

    public function testDoesNotRecalculateServingEmissions(): void
    {
        $calculator = new PackageClimateCalculator();

        $result = $calculator->calculate(
            2.20,
            'per serving',
            400
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['value']);
    }

    public function testMissingWeightProducesUnavailableResult(): void
    {
        $calculator = new PackageClimateCalculator();

        $result = $calculator->calculate(
            2.20,
            'per kg',
            null
        );

        $this->assertFalse($result['available']);
        $this->assertNull($result['value']);
    }
}
