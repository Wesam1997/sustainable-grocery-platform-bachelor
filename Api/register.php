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

    // Validate CSRF token
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

    // Rate limit registration attempts
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

    // Read registration fields
    $name = is_string($_POST['name'] ?? null)
        ? trim($_POST['name']) : '';

    $email = is_string($_POST['email'] ?? null)
        ? strtolower(trim($_POST['email'])) : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password'] : '';

    $confirm = is_string($_POST['confirm_password'] ?? null)
        ? $_POST['confirm_password'] : '';

    // Validate name
    if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
        respond(
            422,
            false,
            'Enter a name between 1 and 100 characters.'
        );
    }

    // Validate email
    if (
        strlen($email) > 254
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        respond(
            422,
            false,
            'Enter a valid email address.'
        );
    }

    // Validate password
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
        respond(
            422,
            false,
            'The passwords do not match.'
        );
    }

    // Validate APP_URL before creating the user.
    // Allow HTTPS for deployed environments.
    // Allow HTTP only for local development.
    $appUrl = rtrim(trim($_ENV['APP_URL'] ?? ''), '/');

    $scheme = parse_url($appUrl, PHP_URL_SCHEME);
    $host = parse_url($appUrl, PHP_URL_HOST);

    $isLocalHttp = $scheme === 'http'
        && in_array(
            $host,
            ['localhost', '127.0.0.1'],
            true
        );

    if (
        !filter_var($appUrl, FILTER_VALIDATE_URL)
        || ($scheme !== 'https' && !$isLocalHttp)
    ) {
        throw new RuntimeException(
            'APP_URL is not configured.'
        );
    }

    // Create user with email_verified_at = NULL
    $userId = flashfood_application($conn)
        ->users()
        ->register($name, $email, $password);

    // Create verification token
    $verificationService =
        new \App\Application\Services\EmailVerificationService($conn);

    $verificationToken =
        $verificationService->createToken($userId);

    // Build verification link
    $verificationUrl = $appUrl
        . '/Api/verify_email.php?token='
        . urlencode($verificationToken);

    // Prepare email
    $subject = 'Verify your Flash Food email';

    $body = "Hello {$name},\n\n"
        . "Welcome to Flash Food!\n\n"
        . "Please verify your email address by opening this link:\n\n"
        . $verificationUrl . "\n\n"
        . "This link expires after 24 hours.\n\n"
        . "If you did not create this account, ignore this email.\n\n"
        . "Flash Food Team";

    // Send verification email
    $mailService =
        new \App\Application\Services\MailService();

    try {
        $mailService->send(
            $email,
            $subject,
            $body
        );
    } catch (Throwable $mailException) {
        (new SystemLogger())->error(
            'user.verification_email_failed',
            [
                'type' => get_class($mailException),
                'user_id' => $userId,
            ]
        );

        respond(
            503,
            false,
            'Your account was created, but the verification email '
            . 'could not be sent. Please contact support.'
        );
    }

    respond(
        201,
        true,
        'Account created! Please check your email to verify your account.'
    );

} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() === 1062) {
        respond(
            409,
            false,
            'An account could not be created with this email.'
        );
    }

    (new SystemLogger())->error(
        'user.registration_failed',
        [
            'type' => get_class($exception),
        ]
    );

    respond(
        500,
        false,
        'Something went wrong. Please try again later.'
    );

} catch (Throwable $exception) {
    (new SystemLogger())->error(
        'user.registration_failed',
        [
            'type' => get_class($exception),
        ]
    );

    respond(
        500,
        false,
        'Something went wrong. Please try again later.'
    );
}
