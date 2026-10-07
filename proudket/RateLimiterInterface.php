<?php
declare(strict_types=1);

interface ProductSignalsInterface
{
    public function compute(array $row): array;
}
