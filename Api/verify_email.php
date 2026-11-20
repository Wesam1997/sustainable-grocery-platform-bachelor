<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application\Services\EmailVerificationService;

$success = false;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        http_response_code(405);
        exit('Method not allowed.');
    }

    require_once __DIR__ . '/connect.php';

    $token = $_GET['token'] ?? '';

    if (is_string($token)) {
        $service = new EmailVerificationService($conn);
        $success = $service->verifyToken($token);
    }

    if (!$success) {
        http_response_code(400);
    }
} catch (Throwable $exception) {
    error_log(
        'Flash Food email verification failed: '
        . get_class($exception)
    );

    http_response_code(500);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Email Verification - Flash Food</title>
</head>
<body>
    <main>
        <?php if ($success): ?>
            <h1>Email verified successfully!</h1>
            <p>
                Your email address has been verified.
                You can now log in to Flash Food.
            </p>
        <?php else: ?>
            <h1>Email verification failed</h1>
            <p>
                The verification link is invalid,
                expired, or has already been used.
            </p>
        <?php endif; ?>
    </main>
</body>
</html>
