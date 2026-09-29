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

    $related_stmt = mysqli_prepare(
        $conn,
        catalog_select_sql()
        . ' WHERE p.merchant = ?'
        . ' AND p.id != ?'
        . ' ORDER BY p.id'
        . ' LIMIT 4'
    );

    if (!$related_stmt) {
        throw new RuntimeException(
            'Related product query could not be prepared.'
        );
    }

    mysqli_stmt_bind_param(
        $related_stmt,
        'si',
        $product['merchant'],
        $product_id
    );

    if (!mysqli_stmt_execute($related_stmt)) {
        mysqli_stmt_close($related_stmt);

        throw new RuntimeException(
            'Related product query failed.'
        );
    }

    $related_result = mysqli_stmt_get_result($related_stmt);
    $related_products = [];

    while ($row = mysqli_fetch_assoc($related_result)) {
        $related_products[] = product_details_payload($row);
    }

    mysqli_stmt_close($related_stmt);

    echo json_encode([
        'success' => true,
        'product' => product_details_payload($product),
        'relatedProducts' => $related_products
    ],
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $error) {
    error_log('Product details failed: ' . $error->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Produktdata kunne ikke hentes.'
    ], JSON_UNESCAPED_UNICODE);
}