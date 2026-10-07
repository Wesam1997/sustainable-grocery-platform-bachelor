<?php
declare(strict_types=1);

header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

session_start();
$token = $_POST['csrf_token'] ?? null;
$expected = $_SESSION['csrf_token'] ?? null;
if (!is_string($token) || !is_string($expected) || $expected === ''
    || !hash_equals($expected, $token)) {
    http_response_code(403);
    exit('Invalid request. Refresh the page and try again.');
}

$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $cookie['path'],
    'domain' => $cookie['domain'],
    'secure' => $cookie['secure'],
    'httponly' => $cookie['httponly'],
    'samesite' => $cookie['samesite'] ?: 'Lax',
]);
session_destroy();
header('Location: ../Login/login.php', true, 303);
exit;
