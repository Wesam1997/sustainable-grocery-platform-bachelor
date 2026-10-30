<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../src/Config/catalog.php';
require_once __DIR__ . '/../src/Contracts/ClimateServiceInterface.php';
require_once __DIR__ . '/../src/Contracts/ClimateCalculationProviderInterface.php';
require_once __DIR__ . '/../src/Modules/Climate/ClimateService.php';

final class ClimateServiceTest extends TestCase
{
    private ClimateService $service;

    protected function setUp(): void
    {
        $calculator = $this->createMock(
            ClimateCalculationProviderInterface::class
        );

        $this->service = new ClimateService($calculator);
    }

    public function testLowClassification(): void
    {
        $result = $this->service->classify(1.50);

        $this->assertSame('Low', $result['label']);
        $this->assertSame(1, $result['rank']);
    }

    public function testLowBoundary(): void
    {
        $result = $this->service->classify(1.80);

        $this->assertSame('Low', $result['label']);
    }

    public function testMediumClassification(): void
    {
        $result = $this->service->classify(1.81);

        $this->assertSame('Medium', $result['label']);
        $this->assertSame(2, $result['rank']);
    }

    public function testMediumBoundary(): void
    {
        $result = $this->service->classify(5.21);

        $this->assertSame('Medium', $result['label']);
    }

    public function testHighClassification(): void
    {
        $result = $this->service->classify(5.22);

        $this->assertSame('High', $result['label']);
        $this->assertSame(3, $result['rank']);
    }

    public function testNegativeValue(): void
    {
        $result = $this->service->classify(-1);

        $this->assertSame('Unknown', $result['label']);
        $this->assertSame(0, $result['rank']);
    }

    public function testMissingValue(): void
    {
        $result = $this->service->classify(null);

        $this->assertSame('Unknown', $result['label']);
        $this->assertSame(0, $result['rank']);
    }

public function testCalculateUsesInjectedCalculator(): void
{
    $product = [
        'id' => 19,
        'title' => 'Organic Chickpeas 400 g'
    ];

    $expectedClimate = [
        'available' => true,
        'value' => 0.88,
        'per_kg' => 2.20,
        'basis' => 'per package',
        'unit' => 'kg CO2e'
    ];

    $calculator = $this->createMock(
        ClimateCalculationProviderInterface::class
    );

    $calculator->expects($this->once())
        ->method('calculate')
        ->with($this->equalTo($product))
        ->willReturn($expectedClimate);

    $service = new ClimateService($calculator);

    $result = $service->calculate($product);

    $this->assertTrue($result['available']);
    $this->assertSame(0.88, $result['value']);
    $this->assertSame(2.20, $result['per_kg']);
    $this->assertSame('Medium', $result['label']);
    $this->assertSame(2, $result['rank']);
}

public function testUnavailableClimateCannotBeClassified(): void
{
    $calculator = $this->createMock(
        ClimateCalculationProviderInterface::class
    );

    $calculator->method('calculate')->willReturn([
        'available' => false,
        'value' => null,
        'per_kg' => 1.50
    ]);

    $service = new ClimateService($calculator);

    $result = $service->calculate(['id' => 19]);

    $this->assertFalse($result['available']);
    $this->assertSame('Unknown', $result['label']);
    $this->assertSame(0, $result['rank']);
}


}