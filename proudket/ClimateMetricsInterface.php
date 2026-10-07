<?php
declare(strict_types=1);

interface ClimateExplanationInterface
{
    public function explain(array $climate, ?array $comparison): string;
}
