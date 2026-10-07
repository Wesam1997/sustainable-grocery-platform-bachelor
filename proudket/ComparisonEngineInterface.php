<?php
declare(strict_types=1);

interface ClimateServiceInterface
{
    public function calculate(array $row): array;
}
