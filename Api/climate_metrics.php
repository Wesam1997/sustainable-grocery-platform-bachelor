<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
function climate_metrics(array $row, array $sig, int $rank): array
{
    static $metrics = null;
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        return flashfood_application($conn)->metrics()->format($row, $sig, $rank);
    }
    $metrics ??= Application::createMetrics();
    return $metrics->format($row, $sig, $rank);
}
