<?php

session_start();

require_once __DIR__ . '/connect.php';

header('Content-Type: application/json; charset=utf-8');


function fail($code, $msg)
{
    http_response_code($code);

    echo json_encode(
        [
            'ok' => false,
            'error' => $msg
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ==================================================
// HENT PRODUCT ID OG ANTAL
// ==================================================

$product_id = (int)($_POST['product_id'] ?? 0);

$qty = max(
    1,
    (int)($_POST['qty'] ?? 1)
);


// ==================================================
// KONTROLLER ID
// ==================================================

if ($product_id <= 0) {
    fail(400, 'bad_id');
}


// ==================================================
// FIND PRODUKT
// Samme tabel som catalog.php bruger
// ==================================================

$stmt = mysqli_prepare(
    $conn,
    "
        SELECT
            id,
            title,
            price
        FROM flashfoodcart.proudkt
        WHERE id = ?
        LIMIT 1
    "
);


if (!$stmt) {
    fail(500, 'stmt_prepare_failed');
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $product_id
);


mysqli_stmt_execute($stmt);


$res = mysqli_stmt_get_result($stmt);


$product = mysqli_fetch_assoc($res);


if (!$product) {
    fail(404, 'not_found');
}


// ==================================================
// OPRET KURV HVIS DEN IKKE FINDES
// ==================================================

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


// ==================================================
// TILFØJ PRODUKT TIL KURV
// ==================================================

if (!isset($_SESSION['cart'][$product_id])) {

    $_SESSION['cart'][$product_id] = [
        'qty' => 0,
        'title' => $product['title'],
        'price' => (float)$product['price']
    ];
}


$_SESSION['cart'][$product_id]['qty'] += $qty;


// ==================================================
// BEREGN ANTAL PRODUKTER I KURVEN
// ==================================================

$count = 0;


foreach ($_SESSION['cart'] as $item) {

    $count += (int)($item['qty'] ?? 0);
}


// ==================================================
// SEND SVAR TIL JAVASCRIPT
// ==================================================

echo json_encode(
    [
        'ok' => true,
        'count' => $count
    ],
    JSON_UNESCAPED_UNICODE
);

exit;