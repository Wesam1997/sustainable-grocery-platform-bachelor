<?php
declare(strict_types=1);

/* ==========================================
   FLASH FOOD - WISHLIST API
   ADD / REMOVE FAVORITES
   ========================================== */

require_once __DIR__ . '/auth.php';

/* ==========================================
   JSON RESPONSE
   ========================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/* ==========================================
   ALLOW POST ONLY
   ========================================== */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    header('Allow: POST');

    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed.'
    ]);

    exit;
}

/* ==========================================
   PROCESS REQUEST
   ========================================== */

try {

    /* CSRF PROTECTION */

    requireCsrf();

    /* ======================================
       VALIDATE PRODUCT ID
       ====================================== */

    $productId = filter_var(
        $_POST['product_id'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    );

    if (!is_int($productId)) {

        http_response_code(400);

        echo json_encode([
            'ok' => false,
            'error' => 'Invalid product ID.'
        ]);

        exit;
    }

    /* ======================================
       VALIDATE ACTION
       ====================================== */

    $action = $_POST['wishlist_action'] ?? 'add';

    if (
        !is_string($action) ||
        !in_array(
            $action,
            ['add', 'remove'],
            true
        )
    ) {

        http_response_code(400);

        echo json_encode([
            'ok' => false,
            'error' => 'Invalid wishlist action.'
        ]);

        exit;
    }

    /* ======================================
       GET FAVORITE SERVICE
       ====================================== */

    $favoriteService = flashfood_application(
        $conn
    )->favorites();

    /* ======================================
       ADD PRODUCT
       ====================================== */

    if ($action === 'add') {

        $favoriteService->add(
            $productId
        );
    }

    /* ======================================
       REMOVE PRODUCT
       ====================================== */

    if ($action === 'remove') {

        $favoriteService->remove(
            $productId
        );
    }

    /* ======================================
       GET UPDATED WISHLIST
       ====================================== */

    $items = $favoriteService->items();

    $saved = isset(
        $items[$productId]
    );

    $count = count($items);

    /* ======================================
       SUCCESS RESPONSE
       ====================================== */

    http_response_code(200);

    echo json_encode([
        'ok' => true,
        'saved' => $saved,
        'count' => $count
    ]);

    exit;

} catch (InvalidArgumentException $error) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => 'Invalid product.'
    ]);

} catch (OutOfBoundsException $error) {

    http_response_code(404);

    echo json_encode([
        'ok' => false,
        'error' => 'Product not found.'
    ]);

} catch (Throwable $error) {

    error_log(
        'Wishlist API error: ' . $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Could not update wishlist.'
    ]);
}

exit;
