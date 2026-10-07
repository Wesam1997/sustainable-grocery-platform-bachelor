<?php
declare(strict_types=1);

interface LoggerInterface
{
    public function error(string $event, array $context = []): void;
}
