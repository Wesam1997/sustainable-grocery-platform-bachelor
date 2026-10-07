<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/bootstrap.php';
try {
    require_once __DIR__ . '/connect.php';
    $count = flashfood_application($conn)->cart()->add((int)($_POST['product_id'] ?? 0), (int)($_POST['qty'] ?? 1));
    echo json_encode(['ok' => true, 'count' => $count], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $error) {
    http_response_code(400); echo json_encode(['ok' => false, 'error' => $error->getMessage()]);
} catch (OutOfBoundsException $error) {
    http_response_code(404); echo json_encode(['ok' => false, 'error' => 'not_found']);
} catch (Throwable $error) {
    (new SystemLogger())->error('basket.request_failed', ['type' => get_class($error)]);
    http_response_code(500); echo json_encode(['ok' => false, 'error' => 'server_error']);
}
