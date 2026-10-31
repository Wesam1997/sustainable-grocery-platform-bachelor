<?php
declare(strict_types=1);

/**
 * Simulated checkout endpoint.
 *
 * Creates an order without processing real payments.
 * Requires an authenticated user and a valid CSRF token.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';

function checkoutResponse(int $status, array $data): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

// Only POST requests are allowed.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkoutResponse(405, [
        'ok' => false,
        'error' => 'method_not_allowed'
    ]);
}

// Verify the logged-in user.
$user = currentUser();

if ($user === null) {
    checkoutResponse(401, [
        'ok' => false,
        'error' => 'login_required'
    ]);
}

// Verify CSRF token.
$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token)
    || !hash_equals(csrfToken(), $token)
) {
    checkoutResponse(403, [
        'ok' => false,
        'error' => 'invalid_csrf_token'
    ]);
}

// Read delivery address.
$address = $_POST['delivery_address'] ?? '';

if (
    !is_string($address)
    || trim($address) === ''
    || strlen(trim($address)) > 255
) {
    checkoutResponse(422, [
        'ok' => false,
        'error' => 'invalid_delivery_address'
    ]);
}

try {
    $app = flashfood_application($conn);

    // Use the authenticated session identity,
    // never a user ID supplied by the browser.
    $userId = (int) $_SESSION['user_id'];

    $orderId = $app->orders()->checkout(
        $userId,
        trim($address)
    );

    checkoutResponse(201, [
        'ok' => true,
        'order_id' => $orderId,
        'message' => 'Order placed successfully',
        'payment_required' => false
    ]);

} catch (InvalidArgumentException $error) {
    $reason = $error->getMessage();

    $status = in_array(
        $reason,
        ['cart_empty', 'user_not_found'],
        true
    ) ? 409 : 422;

    checkoutResponse($status, [
        'ok' => false,
        'error' => $reason
    ]);

} catch (Throwable $error) {
    (new SystemLogger())->error(
        'checkout.request_failed',
        ['type' => get_class($error)]
    );

    checkoutResponse(500, [
        'ok' => false,
        'error' => 'server_error'
    ]);
}
