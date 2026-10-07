<?php
declare(strict_types=1);

interface RateLimiterInterface
{
    public function take(string $scope, string $identifier, int $limit = 5, int $windowSeconds = 900): int;
}
