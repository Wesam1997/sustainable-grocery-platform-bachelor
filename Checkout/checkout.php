<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';

$user = requireLogin();

$app = flashfood_application($conn);
$cartService = $app->cart();

$cart = $cartService->items();
$total = $cartService->total();
$count = $cartService->count();

function checkoutEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Checkout | Flash Food</title>

    <link rel="stylesheet" href="../Style/HomeStyle.css">

    <style>
        body {
            margin: 0;
            background: #f8fafc;
            color: #172b20;
            font-family: Arial, sans-serif;
        }

        .checkout-header {
            background: white;
            padding: 20px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
        }

        .brand {
            color: #16803c;
            font-size: 24px;
            font-weight: 800;
            text-decoration: none;
        }

        .back-link {
            color: #334155;
            text-decoration: none;
        }

        .checkout-container {
            max-width: 1050px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 25px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
        }

        .checkout-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .checkout-card h2 {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
        }

        .form-group input:focus {
            outline: 2px solid #22c55e;
            border-color: #22c55e;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .item-title {
            font-weight: 600;
        }

        .item-quantity {
            font-size: 14px;
            color: #64748b;
            margin-top: 5px;
        }

        .order-total {
            display: flex;
            justify-content: space-between;
            font-size: 20px;
            font-weight: 800;
            margin-top: 22px;
        }

        .checkout-button {
            width: 100%;
            background: #16a34a;
            color: white;
            border: none;
            padding: 16px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            margin-top: 24px;
        }

        .checkout-button:hover {
            background: #15803d;
        }

        .checkout-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        .notice {
            padding: 15px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            font-size: 14px;
            color: #166534;
            margin-top: 18px;
        }

        .error {
            color: #b91c1c;
            margin-top: 15px;
        }

        .success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 18px;
            padding: 30px;
            text-align: center;
        }

        .success h2 {
            color: #15803d;
        }

        .empty {
            text-align: center;
            padding: 35px;
        }

        .link-button {
            display: inline-block;
            background: #16a34a;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .checkout-container {
                margin: 20px auto;
            }
        }
    </style>
</head>

<body>

<header class="checkout-header">
    <a class="brand" href="../Home/home.php">
        Flash Food
    </a>

    <a class="back-link"
       href="../Home/inkobLister.php">
        Back to cart
    </a>
</header>

<main class="checkout-container">

    <h1>Checkout</h1>

    <p class="subtitle">
        Review your order and complete your purchase.
    </p>

    <div id="checkout-content">

        <?php if (empty($cart)): ?>

            <div class="checkout-card empty">
                <h2>Your cart is empty</h2>

                <p>
                    Add products to your cart before checking out.
                </p>

                <a class="link-button"
                   href="../Home/home.php">
                    Continue shopping
                </a>
            </div>

        <?php else: ?>

            <div class="checkout-grid">

                <section class="checkout-card">

                    <h2>Delivery information</h2>

                    <form
                        id="checkout-form"
                        method="post"
                        action="../Api/place_order.php"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= checkoutEscape(csrfToken()) ?>"
                        >

                        <div class="form-group">
                            <label for="delivery-name">
                                Full name
                            </label>

                            <input
                                id="delivery-name"
                                type="text"
                                value="<?= checkoutEscape(
                                    $user['name'] ?? ''
                                ) ?>"
                                readonly
                            >
                        </div>

                        <div class="form-group">
                            <label for="delivery-address">
                                Delivery address
                            </label>

                            <input
                                id="delivery-address"
                                type="text"
                                name="delivery_address"
                                placeholder="Street, postcode and city"
                                maxlength="255"
                                required
                            >
                        </div>

                        <div class="notice">
                            This is a simulated checkout
                            for the Flash Food prototype.
                            No real payment will be made.
                        </div>

                        <p
                            id="checkout-error"
                            class="error"
                            role="alert"
                            hidden
                        ></p>

                        <button
                            id="place-order-button"
                            class="checkout-button"
                            type="submit"
                        >
                            Place Order
                        </button>

                    </form>

                </section>

                <section class="checkout-card">

                    <h2>
                        Order summary
                        (<?= (int) $count ?> items)
                    </h2>

                    <?php foreach ($cart as $item): ?>

                        <div class="order-item">

                            <div>
                                <div class="item-title">
                                    <?= checkoutEscape(
                                        $item['title']
                                    ) ?>
                                </div>

                                <div class="item-quantity">
                                    Quantity:
                                    <?= (int) $item['qty'] ?>
                                </div>
                            </div>

                            <strong>
                                <?= number_format(
                                    (float) $item['price']
                                        * (int) $item['qty'],
                                    2,
                                    '.',
                                    ','
                                ) ?>
                                DKK
                            </strong>

                        </div>

                    <?php endforeach; ?>

                    <div class="order-total">
                        <span>Total</span>

                        <span>
                            <?= number_format(
                                $total,
                                2,
                                '.',
                                ','
                            ) ?>
                            DKK
                        </span>
                    </div>

                    <p class="subtitle">
                        Final prices are confirmed
                        when the order is placed.
                    </p>

                </section>

            </div>

        <?php endif; ?>

    </div>

</main>

<script>
const form = document.getElementById('checkout-form');

if (form) {
    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const button =
            document.getElementById('place-order-button');

        const errorElement =
            document.getElementById('checkout-error');

        button.disabled = true;
        button.textContent = 'Placing order...';

        errorElement.hidden = true;
        errorElement.textContent = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(
                    result.error || 'Checkout failed'
                );
            }

            const orderId = Number(result.order_id);

            if (!Number.isSafeInteger(orderId) || orderId <= 0) {
                throw new Error(
                    'Invalid order confirmation'
                );
            }

            document.getElementById('checkout-content')
                .innerHTML = `
                    <div class="success">
                        <h2>Order placed successfully!</h2>

                        <p>
                            Thank you for shopping
                            with Flash Food.
                        </p>

                        <p>
                            Your order number is
                            <strong>#${orderId}</strong>
                        </p>

                        <p>
                            No real payment was processed.
                        </p>

                        <a class="link-button"
                           href="../Home/home.php">
                            Continue shopping
                        </a>
                    </div>
                `;

        } catch (error) {
            errorElement.textContent =
                'Unable to place order: ' + error.message;

            errorElement.hidden = false;

            button.disabled = false;
            button.textContent = 'Place Order';
        }
    });
}
</script>

</body>
</html>
