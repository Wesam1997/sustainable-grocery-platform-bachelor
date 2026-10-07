<?php
declare(strict_types=1);

// Skal indlæses før HTML eller anden tekst sendes til browseren.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

require_once __DIR__ . '/connect.php';

// Opret et token, som beskytter formularer mod CSRF.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'];
}

function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }
}

// Hent den aktuelle bruger fra databasen.
// Rollen læses fra databasen, så ændrede rettigheder slår igennem.
function currentUser(): ?array
{
    global $conn;

    $id = $_SESSION['user_id'] ?? null;

    if (!is_int($id) || $id < 1) {
        return null;
    }

    return flashfood_application($conn)->users()->current($id);
}

function requireLogin(): array
{
    $user = currentUser();

    if ($user === null) {
        http_response_code(401);
        exit('You must log in first.');
    }

    return $user;
}

function requireAdmin(): array
{
    $user = requireLogin();

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }

    return $user;
}