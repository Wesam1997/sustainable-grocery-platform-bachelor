<?php
declare(strict_types=1);

// Run from PowerShell, never as a public web endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Api/connect.php';
require_once __DIR__ . '/../src/Infrastructure/Database/MysqliBasketStore.php';
require_once __DIR__ . '/../src/Modules/Cart/CartService.php';
require_once __DIR__ . '/../src/Modules/Favorites/FavoriteService.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$previousSession = $_SESSION ?? [];
$testUsers = [];
$testProducts = [];
$exitCode = 0;

function basketTestEqual(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': unexpected result.');
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

try {
    $marker = bin2hex(random_bytes(8));

    foreach (['a', 'b'] as $suffix) {
        $name = 'Basket persistence test';
        $email = 'basket-' . $marker . '-' . $suffix . '@example.invalid';
        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        try {
            $stmt->bind_param('sss', $name, $email, $hash);
            $stmt->execute();
            $testUsers[] = (int) $conn->insert_id;
        } finally {
            $stmt->close();
        }
    }

    foreach ([12.50, 20.00] as $price) {
        $title = 'TEST basket ' . $marker;
        $merchant = 'TEST';
        $stmt = $conn->prepare(
            'INSERT INTO proudkt (
                title, merchant, category, ingredients, price, expires_at
             ) VALUES (?, ?, 0, ?, ?, NULL)'
        );
        $description = 'Temporary persistence test fixture';
        try {
            $stmt->bind_param('sssd', $title, $merchant, $description, $price);
            $stmt->execute();
            $testProducts[] = (int) $conn->insert_id;
        } finally {
            $stmt->close();
        }
    }

    [$userA, $userB] = $testUsers;
    [$productA, $productB] = $testProducts;

    $_SESSION = [];
    $store = new MysqliBasketStore($conn);
    $products = new MysqliProductRepository($conn);
    $cart = new CartService($products, $store);
    $favorites = new FavoriteService($products, $store);

    $cart->add($productA, 1);
    basketTestEqual(1, $cart->count(), 'guest session cart works');

    // Same adapter instance, now with an authenticated session.
    $_SESSION = ['user_id' => $userA];
    basketTestEqual([], $cart->items(), 'guest data is not silently assigned to an account');

    $cart->add($productA, 2);
    $cart->add($productA, 1);
    $cart->add($productB, 1);
    $favorites->add($productA);
    $favorites->add($productA);
    basketTestEqual(4, $cart->count(), 'adding again increases quantity');
    basketTestEqual(57.50, $cart->total(), 'cart total uses current product prices');
    basketTestEqual(1, count($favorites->items()), 'favorites are not duplicated');

    $newStore = new MysqliBasketStore($conn);
    basketTestEqual(3, $newStore->load('cart')[$productA]['qty'], 'new adapter reads saved cart');

    $_SESSION = ['user_id' => $userB];
    basketTestEqual([], $cart->items(), 'another user has a separate cart');
    basketTestEqual([], $favorites->items(), 'another user has separate favorites');

    $_SESSION = ['user_id' => $userA];
    basketTestEqual(4, $cart->count(), 'returning to the account restores its cart');
    basketTestEqual(1, count($favorites->items()), 'returning restores its favorites');

    $cart->update($productA, 500);
    basketTestEqual(99, $cart->items()[$productA]['qty'], 'quantity is capped at 99');
    $cart->update($productA, 2);
    $cart->update($productB, 0);
    basketTestEqual(false, isset($cart->items()[$productB]), 'zero quantity removes an item');

    // Invalid foreign key must roll back the attempted full replacement.
    $before = $store->load('cart');
    try {
        $store->save('cart', [0 => ['qty' => 1]]);
        throw new RuntimeException('Invalid ID was accepted.');
    } catch (InvalidArgumentException $expected) {
        basketTestEqual($before, $store->load('cart'), 'invalid input leaves the cart unchanged');
    }

    // A deleted fixture ID is guaranteed not to reference a real product.
    $stmt = $conn->prepare('DELETE FROM proudkt WHERE id = ?');
    try {
        $stmt->bind_param('i', $productB);
        $stmt->execute();
    } finally {
        $stmt->close();
    }
    try {
        $store->save('cart', [$productB => ['qty' => 1]]);
        throw new RuntimeException('Missing product was accepted.');
    } catch (mysqli_sql_exception $expected) {
        basketTestEqual($before, $store->load('cart'), 'foreign-key failure rolls back removals');
    }

    $favorites->remove($productA);
    basketTestEqual([], $favorites->items(), 'favorite removal persists');
    $favorites->add($productA);
    $cart->clear();
    basketTestEqual([], $store->load('cart'), 'cart clear persists');
    basketTestEqual(1, count($favorites->items()), 'clearing cart preserves favorites');
    $favorites->clear();
    basketTestEqual([], $store->load('fav'), 'favorite clear persists');

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS n FROM carts WHERE user_id = ? AND status = 'active'"
    );
    try {
        $stmt->bind_param('i', $userA);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        basketTestEqual(1, (int) $row['n'], 'the existing active cart is reused');
    } finally {
        $stmt->close();
    }

    echo 'All persistence checks passed.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    // Delete only this run's fixture accounts and products.
    // Cascades remove their temporary favorites, carts and cart_items.
    foreach ($testUsers as $id) {
        try {
            $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $error) {
            fwrite(STDERR, 'Fixture cleanup failed for user ' . $id . PHP_EOL);
            $exitCode = 1;
        }
    }
    foreach ($testProducts as $id) {
        try {
            $stmt = $conn->prepare('DELETE FROM proudkt WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $error) {
            fwrite(STDERR, 'Fixture cleanup failed for product ' . $id . PHP_EOL);
            $exitCode = 1;
        }
    }
    $_SESSION = $previousSession;
}
exit($exitCode);
