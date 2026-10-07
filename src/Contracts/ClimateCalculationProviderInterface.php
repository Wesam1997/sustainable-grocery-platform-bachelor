<?php
declare(strict_types=1);

interface ClimateCalculationProviderInterface
{
        public function calculate(array $product): array;
}
