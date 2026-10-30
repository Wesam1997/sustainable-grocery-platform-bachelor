<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/bootstrap.php';

final class ArchitectureTest extends TestCase
{
    public function testClimateServiceDependsOnCalculatorInterface(): void
    {
        $constructor = new ReflectionMethod(
            ClimateService::class,
            '__construct'
        );

        $parameters = $constructor->getParameters();

        $this->assertNotEmpty($parameters);

        $type = $parameters[0]->getType();

        $this->assertInstanceOf(
            ReflectionNamedType::class,
            $type
        );

        $this->assertSame(
            ClimateCalculationProviderInterface::class,
            $type->getName()
        );
    }
    
public function testClimateServiceCanBeCreatedWithoutInfrastructure(): void
{
    $calculator = $this->createMock(
        ClimateCalculationProviderInterface::class
    );

    $service = new ClimateService($calculator);

    $this->assertInstanceOf(
        ClimateService::class,
        $service
    );
}


public function testClimateModulesDoNotDependOnInfrastructure(): void
{
    $directory = __DIR__ . '/../src/Modules/Climate';

    $this->assertDirectoryExists($directory);

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        $this->assertIsString($source);

        // Detect direct Infrastructure imports.
        $this->assertDoesNotMatchRegularExpression(
            '/\buse\s+[^;]*Infrastructure\\\\/i',
            $source,
            'Infrastructure import found in '
                . $file->getFilename()
        );

        // Detect direct references to concrete infrastructure classes.
        $this->assertDoesNotMatchRegularExpression(
            '/\bnew\s+(?:mysqli|PDO|OllamaLlmClient)\b/i',
            $source,
            'Concrete infrastructure dependency found in '
                . $file->getFilename()
        );
    }
}

public function testProductRepositoryImplementsInterface(): void
{
    $this->assertTrue(
        is_subclass_of(
            MysqliProductRepository::class,
            ProductRepositoryInterface::class
        )
    );
}

public function testProductRepositoryRequiresMysqliConnection(): void
{
    $constructor = new ReflectionMethod(
        MysqliProductRepository::class,
        '__construct'
    );

    $parameters = $constructor->getParameters();

    $this->assertCount(1, $parameters);

    $type = $parameters[0]->getType();

    $this->assertInstanceOf(
        ReflectionNamedType::class,
        $type
    );

    $this->assertSame(
        mysqli::class,
        $type->getName()
    );
}

public function testCatalogServiceDependsOnInterfaces(): void
{
    $constructor = new ReflectionMethod(
        CatalogService::class,
        '__construct'
    );

    $parameters = $constructor->getParameters();

    $this->assertCount(2, $parameters);

    $expectedTypes = [
        ProductRepositoryInterface::class,
        ProductSignalsInterface::class
    ];

    foreach ($parameters as $index => $parameter) {
        $type = $parameter->getType();

        $this->assertInstanceOf(
            ReflectionNamedType::class,
            $type
        );

        $this->assertSame(
            $expectedTypes[$index],
            $type->getName()
        );
    }
}

public function testCatalogServiceWorksWithoutDatabase(): void
{
    $repository = $this->createMock(
        ProductRepositoryInterface::class
    );

    $signals = $this->createMock(
        ProductSignalsInterface::class
    );

    $product = [
        'id' => 19,
        'title' => 'Organic Chickpeas'
    ];

    $repository->expects($this->once())
        ->method('find')
        ->with(19)
        ->willReturn($product);

    $signals->expects($this->once())
        ->method('compute')
        ->with($product)
        ->willReturn([
            'co2' => [
                'available' => true,
                'rank' => 2
            ]
        ]);

    $service = new CatalogService(
        $repository,
        $signals
    );

    $result = $service->find(19);

    $this->assertSame(19, $result['id']);
    $this->assertSame(2, $result['_sig']['co2']['rank']);
}

public function testCompositionRootCreatesClimateMetrics(): void
{
    $metrics = Application::createMetrics();

    $this->assertInstanceOf(
        ClimateMetricsInterface::class,
        $metrics
    );

    $this->assertInstanceOf(
        ClimateMetrics::class,
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

