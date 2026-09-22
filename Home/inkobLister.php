<?php
session_start();

$cart = $_SESSION['cart'] ?? [];

// Remove an item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $rid = (int) $_POST['remove_id'];

    if (isset($cart[$rid])) {
        unset($cart[$rid]);
        $_SESSION['cart'] = $cart;
    }

    header('Location: inkobLister.php');
    exit;
}

// Update quantity
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_id'], $_POST['qty'])
) {
    $uid = (int) $_POST['update_id'];
    $qty = max(0, (int) $_POST['qty']);

    if ($qty === 0) {
        unset($cart[$uid]);
    } elseif (isset($cart[$uid])) {
        $cart[$uid]['qty'] = $qty;
    }

    $_SESSION['cart'] = $cart;

    header('Location: inkobLister.php');
    exit;
}

// Clear the cart
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'clear'
) {
    unset($_SESSION['cart']);

    header('Location: inkobLister.php');
    exit;
}

// Calculate totals
$cart = $_SESSION['cart'] ?? [];
$total = 0.0;
$count = 0;

foreach ($cart as $item) {
    $total += $item['price'] * $item['qty'];
    $count += (int) $item['qty'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Flash Food · Shopping Cart</title>

    <link rel="stylesheet" href="../Style/HomeStyle.css">

    <style>
        body {
            background: #f8fafc;
        }

        .container {
            max-width: 960px;
            margin: 16px auto;
            padding: 0 12px;
        }

        .cart {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
        }

        .title {
            font-weight: 700;
        }

        .muted {
            color: #64748b;
            font-size: .9rem;
        }

        .qty-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .qty {
            width: 64px;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
        }

        .btn {
            cursor: pointer;
            border: none;
            border-radius: 10px;
            padding: 8px 10px;
            font-weight: 700;
            background: #22c55e;
            color: #fff;
            text-decoration: none;
            display: inline-block;
        }

        .btn-sm {
            padding: 6px 8px;
        }

        .btn-outline {
            background: #fff;
            color: #0f172a;
            border: 1px solid #e2e8f0;
        }

        .btn-danger {
            background: #ef4444;
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            width: 100%;
        }

        .total {
            margin-top: 8px;
            font-weight: 800;
            font-size: 1.05rem;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .empty {
            padding: 16px;
            border: 1px dashed #e2e8f0;
            border-radius: 16px;
            text-align: center;
            color: #64748b;
        }

        .topbar,
        .bottombar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .bottombar {
            position: sticky;
            bottom: 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: none;
            flex-wrap: wrap;
        }

        .brand {
            font-weight: 800;
        }

        .wishlist-pill {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            text-decoration: none;
            color: #0f172a;
            background: #fff;
            display: inline-block;
        }

        .home-btn,
        .profile-link {
            text-decoration: none;
        }

        a {
            color: inherit;
        }
    </style>
</head>

<body>
<header>
    <div class="topbar">
        <div class="brand">Flash Food</div>
        <a class="profile-link" href="../profil/profil.php">
            My profile
        </a>
    </div>
</header>

<main class="container">
    <h1>Your shopping cart</h1>

    <?php if (empty($cart)): ?>
        <p class="empty">Your cart is empty.</p>
    <?php else: ?>
        <div class="cart">
            <?php foreach ($cart as $pid => $item): ?>
                <div class="item">
                    <div class="row">
                        <div>
                            <div class="title">
                                <?= htmlspecialchars(
                                    $item['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>

                            <div class="muted">
                                Price per item:
                                <?= number_format($item['price'], 2, '.', ',') ?> DKK
                                · Subtotal:
                                <?= number_format(
                                    $item['price'] * $item['qty'],
                                    2,
                                    '.',
                                    ','
                                ) ?> DKK
                            </div>
                        </div>

                        <form
                            class="qty-wrap"
                            method="post"
                            action="inkobLister.php"
                        >
                            <input
                                type="hidden"
                                name="update_id"
                                value="<?= (int) $pid ?>"
                            >

                            <input
                                class="qty"
                                name="qty"
                                type="number"
                                min="1"
                                value="<?= (int) $item['qty'] ?>"
                                aria-label="Quantity"
                            >

                            <button
                                class="btn btn-sm btn-outline"
                                type="submit"
                            >
                                Update
                            </button>
                        </form>

                        <form method="post" action="inkobLister.php">
                            <input
                                type="hidden"
                                name="remove_id"
                                value="<?= (int) $pid ?>"
                            >

                            <button
                                class="btn btn-sm btn-danger"
                                type="submit"
                            >
                                Remove
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="total">
            Total (<?= $count ?> <?= $count === 1 ? 'item' : 'items' ?>):
            <?= number_format($total, 2, '.', ',') ?> DKK
        </div>

        <div class="actions">
            <form method="post" action="inkobLister.php">
                <input type="hidden" name="action" value="clear">

                <button class="btn btn-outline" type="submit">
                    Clear cart
                </button>
            </form>
        </div>
    <?php endif; ?>
</main>

<footer>
    <nav class="bottombar" aria-label="Main navigation">
        <div class="slot left">
            <a class="home-btn" href="home.php">
                Home
            </a>
        </div>

        <div class="slot center">
            <a class="wishlist-pill" href="./fav.php">
                My wishlist
            </a>
        </div>

        <div class="slot right">
            <a
                class="wishlist-pill"
                href="inkobLister.php"
                aria-current="page"
            >
                Cart <b><?= (int) $count ?></b>
            </a>
        </div>
    </nav>
</footer>
</body>
</html>