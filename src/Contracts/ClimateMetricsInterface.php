<?php
declare(strict_types=1);

interface ClimateMetricsInterface
{
    public function format(array $row, array $signals, int $rank): array;
}
