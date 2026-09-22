<?php
declare(strict_types=1);

ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, bool $ok, string $message): void
{
    http_response_code($status);

    echo json_encode([
        'ok' => $ok,
        'message' => $message,
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Method not allowed.');
}

try {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/rate_limit.php';

    // Kontroller CSRF her for at returnere JSON ved fejl.
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals(csrfToken(), $token)
    ) {
        respond(
            403,
            false,
            'Invalid request. Refresh the page and try again.'
        );
    }
    // Brug IP-adressen fra serverforbindelsen.
// Stol ikke på en IP-adresse sendt fra JavaScript.
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($ip === '') {
    respond(503, false, 'Please try again later.');
}

$retryAfter = takeRateLimit(
    $conn,
    'register-ip',
    $ip,
    5,
    15 * 60
);

if ($retryAfter > 0) {
    header('Retry-After: ' . $retryAfter);

    $minutes = (int) ceil($retryAfter / 60);

    respond(
        429,
        false,
        "Too many attempts. Try again in {$minutes} minute(s)."
    );
}

    $name = is_string($_POST['name'] ?? null)
        ? trim($_POST['name']) : '';

    $email = is_string($_POST['email'] ?? null)
        ? strtolower(trim($_POST['email'])) : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password'] : '';

    $confirm = is_string($_POST['confirm_password'] ?? null)
        ? $_POST['confirm_password'] : '';

    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        respond(422, false, 'Enter a name between 1 and 100 characters.');
    }

    if (
        strlen($email) > 254
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        respond(422, false, 'Enter a valid email address.');
    }

    if (
        mb_strlen($password, 'UTF-8') < 15
        || strlen($password) > 72
        || strpos($password, "\0") !== false
    ) {
        respond(
            422,
            false,
            'Use at least 15 characters and no more than 72 bytes.'
        );
    }

    if ($password !== $confirm) {
        respond(422, false, 'The passwords do not match.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO users (name, email, password_hash, role)
         VALUES (?, ?, ?, 'user')"
    );

    mysqli_stmt_bind_param($stmt, 'sss', $name, $email, $hash);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    respond(201, true, 'Your account has been created.');
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() === 1062) {
        respond(
            409,
            false,
            'An account could not be created with this email.'
        );
    }

    error_log('Registration database error: ' . $exception->getCode());
    respond(500, false, 'Something went wrong. Please try again later.');
} catch (Throwable $exception) {
    error_log('Registration error type: ' . get_class($exception));
    respond(500, false, 'Something went wrong. Please try again later.');
}