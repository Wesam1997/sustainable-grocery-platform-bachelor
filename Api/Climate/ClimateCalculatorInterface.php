<?php

declare(strict_types=1);

interface ClimateCalculatorInterface
{
    /**
     * Checks whether this calculator can handle the product.
     */
    public function supports(array $product): bool;

    /**
     * Calculates and returns the product's climate information.
     */
    public function calculate(array $product): array;
}