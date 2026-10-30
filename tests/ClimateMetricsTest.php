<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/bootstrap.php';

final class ClimateMetricsTest extends TestCase
{
    public function testPackageClimateCalculation(): void
    {
        $comparisons = $this->createMock(
            ComparisonEngineInterface::class
        );

        $explanations = $this->createMock(
            ClimateExplanationInterface::class
        );

        $comparisons->expects($this->exactly(2))
            ->method('compare')
            ->willReturn(null);

        $explanations->method('explain')
            ->willReturn('Climate explanation');

        $metrics = new ClimateMetrics(
            $comparisons,
            $explanations
        );

        $product = [
            'id' => 19,
            'climate_weight_g' => 400
        ];

        $signals = [
            'co2' => [
                'available' => true,
                'value' => 2.20,
                'per_kg' => 2.20,
                'basis' => 'per kg',
                'rank' => 2
            ]
        ];

        $result = $metrics->format(
            $product,
            $signals,
            2
        );

        $this->assertTrue($result['available']);
        $this->assertSame(2.20, $result['value']);
       
$this->assertEqualsWithDelta(
    0.88,
    $result['package']['value'],
    0.000001
);

        $this->assertSame('Medium CO₂e emissions', $result['label']);
        $this->assertSame(2, $result['rank']);
    }
    
public function testPackageCalculationIsNotAppliedToServingValues(): void
{
    $comparisons = $this->createMock(
        ComparisonEngineInterface::class
    );

    $explanations = $this->createMock(
        ClimateExplanationInterface::class
    );

    $comparisons->expects($this->once())
        ->method('compare')
        ->with(2.20, 'per serving', 'car')
        ->willReturn(null);

    $explanations->method('explain')
        ->willReturn('Climate explanation');

    $metrics = new ClimateMetrics(
        $comparisons,
        $explanations
    );

    $result = $metrics->format(
        ['id' => 19, 'climate_weight_g' => 400],
        [
            'co2' => [
                'available' => true,
                'value' => 2.20,
                'basis' => 'per serving',
                'rank' => 2
            ]
        ],
        2
    );

    $this->assertFalse($result['package']['available']);
    $this->assertNull($result['package']['value']);
}

public function testPackageCalculationRequiresRecordedWeight(): void
{
    $comparisons = $this->createMock(
        ComparisonEngineInterface::class
    );

    $explanations = $this->createMock(
        ClimateExplanationInterface::class
    );

    $comparisons->expects($this->once())
        ->method('compare')
        ->with(2.20, 'per kg', 'car')
        ->willReturn(null);

    $explanations->method('explain')
        ->willReturn('Climate explanation');

    $metrics = new ClimateMetrics(
        $comparisons,
        $explanations
    );

    $result = $metrics->format(
        ['id' => 19],
        [
            'co2' => [
                'available' => true,
                'value' => 2.20,
                'basis' => 'per kg',
                'rank' => 2
            ]
        ],
        2
    );

    $this->assertFalse($result['package']['available']);
    $this->assertNull($result['package']['value']);
}

}
