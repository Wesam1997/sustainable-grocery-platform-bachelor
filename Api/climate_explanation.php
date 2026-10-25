<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Produktets ID mangler.']);
    exit;
}
try {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/catalog.php';
    require_once __DIR__ . '/climate_metrics.php';
    require_once __DIR__ . '/instant_comparisons.php';
    $row = get_catalog_product($conn, $id);
    if ($row === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Produktet blev ikke fundet.']);
        exit;
    }
    $sig = $row['_sig'] ?? compute_signals($row);
    $metrics = climate_metrics($row, $sig, (int)($sig['co2']['rank'] ?? 0));
    $service = new FlashFoodInstantComparisons($conn);
    $context = $service->context($metrics, (int)$id);
    $result = $service->read($context);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    echo json_encode(['success' => true, 'product_id' => (int)$id, 'explanation' => $result['text'],
        'comparison_key' => $result['comparison_key'] ?? null, 'explanation_source' => $result['source']],
        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('climate.explanation_failed: ' . get_class($error) . ': ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Forklaringen kunne ikke hentes.']);
}
