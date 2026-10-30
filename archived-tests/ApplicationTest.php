<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/bootstrap.php';

final class ApplicationTest extends TestCase
{
    public function testCreateMetricsReturnsInterface(): void
    {
        $metrics = Application::createMetrics();

        $this->assertInstanceOf(
            ClimateMetricsInterface::class,
            $metrics
        );
    }
    
public function testProductRepositoryDoesNotDependOnClimateCalculators(): void
{
    $reflection = new ReflectionClass(
        MysqliProductRepository::class
    );

    $filename = $reflection->getFileName();

    $this->assertNotFalse($filename);

    $source = file_get_contents($filename);

    $this->assertNotFalse($source);

    $forbiddenDependencies = [
        'new RecipeClimateCalculator',
        'new GroceryClimateCalculator',
        'new ClimateService',
        'new ClimateMetrics'
    ];

    foreach ($forbiddenDependencies as $dependency) {
        $this->assertStringNotContainsString(
            $dependency,
            $source,
            "Repository must not instantiate: {$dependency}"
        );
    }
}

}
