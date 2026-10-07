<?php
declare(strict_types=1);

interface SearchServiceInterface
{
    public function parse(string $query, string $type): array;
}
