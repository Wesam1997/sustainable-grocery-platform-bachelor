<?php
declare(strict_types=1);

require_once __DIR__ . '/connect.php';
$query = trim((string)($_GET['q'] ?? ''));
if ($query === '') { header('Location: ../Home/home.php'); exit; }
$params = flashfood_application($conn)->search()->parse($query, (string)($_GET['type'] ?? 'all'));
header('Location: ../Home/home.php?' . http_build_query($params));
exit;
