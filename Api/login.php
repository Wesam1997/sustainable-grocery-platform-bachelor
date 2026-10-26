<?php
declare(strict_types=1);

ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function loginResponse(
    int $status,
    bool $ok,
    string $message
): void {
    http_response_code($status);

    echo json_encode([
        'ok' => $ok,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

function rejectLimitedLogin(int $seconds): void
{
    header('Retry-After: ' . $seconds);

    $minutes = (int) ceil($seconds / 60);

    loginResponse(
        429,
        false,
        "For mange loginforsøg. Prøv igen om {$minutes} minut(ter)."
    );
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    loginResponse(
        405,
        false,
        'Metoden er ikke tilladt.'
    );
}

try {

    // Authentication, database and session setup
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/rate_limit.php';

    // ==========================================
    // CSRF VALIDATION
    // ==========================================

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals(csrfToken(), $token)
    ) {
        loginResponse(
            403,
            false,
            'Genindlæs login-siden, og prøv igen.'
        );
    }

    // ==========================================
    // IP RATE LIMITING
    // ==========================================

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!is_string($ip) || $ip === '') {
        loginResponse(
            503,
            false,
            'Prøv igen senere.'
        );
    }

    // Maximum 30 login attempts per IP in 15 minutes
    $ipWait = takeRateLimit(
        $conn,
        'login-ip',
        $ip,
        30,
        15 * 60
    );

    if ($ipWait > 0) {
        rejectLimitedLogin($ipWait);
    }

    // ==========================================
    // READ LOGIN INPUT
    // ==========================================

    $email = is_string($_POST['email'] ?? null)
        ? strtolower(trim($_POST['email']))
        : '';

    // Do not trim passwords
    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    // ==========================================
    // INPUT VALIDATION
    // ==========================================

    if (
        strlen($email) > 254
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $password === ''
        || strlen($password) > 72
        || strpos($password, "\0") !== false
    ) {
        loginResponse(
            401,
            false,
            'Forkert e-mail eller adgangskode.'
        );
    }

    // ==========================================
    // FIND USER
    // ==========================================

    $users = flashfood_application($conn)->users();

    $user = $users->findForLogin($email);

    // Use user ID when available to prevent
    // alternative email spelling from bypassing limits
    $accountKey = $user
        ? 'user:' . $user['id']
        : 'email:' . $email;

    // ==========================================
    // ACCOUNT RATE LIMITING
    // ==========================================

    // Maximum 5 attempts per account in 15 minutes
    $accountWait = takeRateLimit(
        $conn,
        'login-account',
        $accountKey,
        5,
        15 * 60
    );

    if ($accountWait > 0) {
        rejectLimitedLogin($accountWait);
    }

    // ==========================================
    // VERIFY PASSWORD
    // ==========================================

    $passwordMatches = $users->passwordMatches(
        $user,
        $password
    );

    if (!$user || !$passwordMatches) {
        loginResponse(
            401,
            false,
            'Forkert e-mail eller adgangskode.'
        );
    }

    // ==========================================
    // CREATE LOGIN SESSION
    // ==========================================

    if (!session_regenerate_id(true)) {
        throw new RuntimeException(
            'Session regeneration failed.'
        );
    }

    $_SESSION['user_id'] = (int) $user['id'];

    // Generate a new CSRF token after login
    $_SESSION['csrf_token'] = bin2hex(
        random_bytes(32)
    );

    session_write_close();

    // ==========================================
    // LOGIN SUCCESS
    // ==========================================

    loginResponse(
        200,
        true,
        'Du er nu logget ind.'
    );

} catch (Throwable $exception) {

    // Log server errors without exposing details
    if (class_exists('SystemLogger')) {
        (new SystemLogger())->error(
            'user.login_failed',
            ['type' => get_class($exception)]
        );
    }

    loginResponse(
        500,
        false,
        'Der opstod en serverfejl. Prøv igen senere.'
    );
}
