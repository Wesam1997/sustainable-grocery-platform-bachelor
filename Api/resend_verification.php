<?php
declare(strict_types=1);

ini_set('display_errors', '0');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function resendResponse(int $status, bool $ok, string $message): void
{
    http_response_code($status);

    echo json_encode([
        'ok' => $ok,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    resendResponse(405, false, 'Method not allowed.');
}

try {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/rate_limit.php';

    // Validate CSRF
    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf)
        || !hash_equals(csrfToken(), $csrf)
    ) {
        resendResponse(
            403,
            false,
            'Invalid request. Refresh the page and try again.'
        );
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!is_string($ip) || $ip === '') {
        resendResponse(503, false, 'Please try again later.');
    }

    // Limit requests per IP
    $retryAfter = takeRateLimit(
        $conn,
        'resend-verification-ip',
        $ip,
        5,
        15 * 60
    );

    if ($retryAfter > 0) {
        header('Retry-After: ' . $retryAfter);
        resendResponse(429, false, 'Too many requests. Try again later.');
    }

    $email = is_string($_POST['email'] ?? null)
        ? strtolower(trim($_POST['email']))
        : '';

    if (
        strlen($email) > 254
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        resendResponse(422, false, 'Enter a valid email address.');
    }

    $genericMessage =
        'If an unverified account exists with this email, '
        . 'a new verification link will be sent.';

    // Per-address rate limit without revealing account existence
    $emailWait = takeRateLimit(
        $conn,
        'resend-verification-email',
        hash('sha256', $email),
        3,
        60 * 60
    );

    if ($emailWait > 0) {
        resendResponse(200, true, $genericMessage);
    }

    // Find an unverified account
    $stmt = $conn->prepare(
        'SELECT id, name
         FROM users
         WHERE email = ?
           AND email_verified_at IS NULL
         LIMIT 1'
    );

    try {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }

    if (!$user) {
        resendResponse(200, true, $genericMessage);
    }

    $appUrl = rtrim(trim($_ENV['APP_URL'] ?? ''), '/');

    if (
        !filter_var($appUrl, FILTER_VALIDATE_URL)
        || parse_url($appUrl, PHP_URL_SCHEME) !== 'https'
    ) {
        throw new RuntimeException('APP_URL is not configured.');
    }

    $verificationService =
        new \App\Application\Services\EmailVerificationService($conn);

    $token = $verificationService->createToken((int) $user['id']);

    $verificationUrl = $appUrl
        . '/Api/verify_email.php?token='
        . urlencode($token);

    $name = $user['name'];

    $subject = 'Verify your Flash Food email';

    $body = "Hello {$name},\n\n"
        . "You requested a new verification link for Flash Food.\n\n"
        . "Open this link to verify your email:\n\n"
        . $verificationUrl . "\n\n"
        . "This link expires after 24 hours.\n\n"
        . "If you did not request this, ignore this email.\n\n"
        . "Flash Food Team";

    $mailService = new \App\Application\Services\MailService();
    $mailService->send($email, $subject, $body);

    resendResponse(200, true, $genericMessage);

} catch (Throwable $exception) {
    if (class_exists('SystemLogger')) {
        (new SystemLogger())->error(
            'user.verification_resend_failed',
            ['type' => get_class($exception)]
        );
    }

    // Do not reveal account existence through error messages
    resendResponse(
        200,
        true,
        'If an unverified account exists with this email, '
        . 'a new verification link will be sent.'
    );
}
