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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    loginResponse(405, false, 'Metoden er ikke tilladt.');
}

try {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/rate_limit.php';

    // Kontroller, at formularen har et gyldigt CSRF-token.
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

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($ip === '') {
        loginResponse(503, false, 'Prøv igen senere.');
    }

    // Højst 30 loginforsøg pr. IP på 15 minutter.
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

    $email = is_string($_POST['email'] ?? null)
        ? strtolower(trim($_POST['email'])) : '';

    // Adgangskoden må ikke trimmes.
    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password'] : '';

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

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, password_hash
         FROM users
         WHERE email = ?
         LIMIT 1'
    );

    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    // Brug kontoens ID, hvis den findes.
    // Det sikrer samme tæller for alternative stavemåder,
    // som databasens sammenligning accepterer.
    $accountKey = $user
        ? 'user:' . $user['id']
        : 'email:' . $email;

    // Højst 5 loginforsøg pr. konto på 15 minutter.
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

    // En dummy-hash bruges ved ukendt e-mail, så vi stadig
    // udfører password_verify(). Den giver aldrig adgang.
    $dummyHash =
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    $hash = $user ? $user['password_hash'] : $dummyHash;
    $passwordMatches = password_verify($password, $hash);

    if (!$user || !$passwordMatches) {
        loginResponse(
            401,
            false,
            'Forkert e-mail eller adgangskode.'
        );
    }

    // Nyt sessions-ID efter vellykket login.
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Session regeneration failed.');
    }

    $_SESSION['user_id'] = (int) $user['id'];

    // Nyt token efter login.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    session_write_close();

    loginResponse(200, true, 'Du er nu logget ind.');
} catch (Throwable $exception) {
    error_log(
        'Login error: '
        . get_class($exception)
        . ' code '
        . $exception->getCode()
    );

    loginResponse(
        500,
        false,
        'Der opstod en serverfejl. Prøv igen senere.'
    );
}