<?php
declare(strict_types=1);

interface ProductAdministrationInterface
{
    public function sellers(): array;
    public function climateFoods(): array;
    public function create(array $product, array $recipe, int $isAssumed): int;
}
