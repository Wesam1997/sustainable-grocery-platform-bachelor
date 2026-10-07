<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
function takeRateLimit(mysqli $conn, string $scope, string $identifier, int $limit = 5, int $windowSeconds = 900): int
{
    return flashfood_application($conn)->rateLimiter()->take($scope, $identifier, $limit, $windowSeconds);
}
