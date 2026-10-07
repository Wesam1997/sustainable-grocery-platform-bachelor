<?php

header('Content-Type: application/json; charset=utf-8');

function product_details_payload(array $row): array
{
    $row['_sig'] = compute_signals($row);

    $row['climate'] = climate_metrics(
        $row,
        $row['_sig'],
        (int)$row['_sig']['co2']['rank']
    );

    return $row;
}

$product_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$product_id) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Produktets ID mangler.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    require_once __DIR__ . '/connect.php';
    require_once __DIR__ . '/catalog.php';
    require_once __DIR__ . '/climate_metrics.php';

    $product = get_catalog_product($conn, $product_id);

    if (!$product) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Produktet blev ikke fundet.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $related_products = array_map(
        'product_details_payload',
        flashfood_application($conn)->products()->related((string)$product['merchant'], $product_id)
    );

    echo json_encode([
        'success' => true,
        'product' => product_details_payload($product),
        'relatedProducts' => $related_products
    ],
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $error) {
    (new SystemLogger())->error('products.details_failed', ['type' => get_class($error)]);

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Produktdata kunne ikke hentes.'
    ], JSON_UNESCAPED_UNICODE);
}