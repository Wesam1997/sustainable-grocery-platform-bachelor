<?php
session_start();
require_once __DIR__ . '/connect.php';

header('Content-Type: application/json; charset=utf-8');

function fail($code, $msg) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
if ($product_id <= 0) fail(400, 'bad_id');

/* VIGTIGT: fjern/ret expires_at hvis kolonnen ikke findes */
$sql = "SELECT id, title, price
        FROM `flashfoodcart`.`proudkt`
        WHERE id = ?"; // <-- ingen expires_at her

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) fail(500, 'stmt_prepare_failed: ' . mysqli_error($conn));

mysqli_stmt_bind_param($stmt, "i", $product_id);
if (!mysqli_stmt_execute($stmt)) {
  fail(500, 'sql_execute_failed: ' . mysqli_error($conn));
}
$res = mysqli_stmt_get_result($stmt);
$product = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$product) fail(404, 'not_found');

$_SESSION['fav'] = $_SESSION['fav'] ?? [];

/* Tilføj kun hvis den ikke findes i forvejen */
if (!isset($_SESSION['fav'][$product_id])) {
  $_SESSION['fav'][$product_id] = [
    'title' => $product['title'],
    'price' => (float)$product['price'],
  ];
}

$count = count($_SESSION['fav']);
echo json_encode(['ok' => true, 'count' => $count], JSON_UNESCAPED_UNICODE);
