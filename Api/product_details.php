<?php

require_once __DIR__ . '/connect.php';

header('Content-Type: application/json; charset=utf-8');

$product_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($product_id <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Produktets ID mangler.'
    ]);

    exit;
}


/* Hent det valgte produkt */

$stmt = mysqli_prepare(
    $conn,
    "
        SELECT *
        FROM flashfoodcart.proudkt
        WHERE id = ?
        LIMIT 1
    "
);

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $product_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$product = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$product) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Produktet blev ikke fundet.'
    ]);

    exit;
}


/* Hent relaterede varer fra samme butik */

$related_stmt = mysqli_prepare(
    $conn,
    "
        SELECT *
        FROM flashfoodcart.proudkt
        WHERE merchant = ?
        AND id != ?
        LIMIT 4
    "
);

mysqli_stmt_bind_param(
    $related_stmt,
    'si',
    $product['merchant'],
    $product_id
);

mysqli_stmt_execute($related_stmt);

$related_result = mysqli_stmt_get_result($related_stmt);

$related_products = [];

while ($row = mysqli_fetch_assoc($related_result)) {
    $related_products[] = $row;
}

mysqli_stmt_close($related_stmt);


/* Send data til JavaScript */

echo json_encode(
    [
        'success' => true,
        'product' => $product,
        'relatedProducts' => $related_products
    ],
    JSON_UNESCAPED_UNICODE
);