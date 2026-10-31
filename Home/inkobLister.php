<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';
require_once __DIR__ . '/../src/Modules/Cart/CartClimateSummaryService.php';

$app = flashfood_application($conn);
$cartService = $app->cart();

/*
 * Handle cart actions.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrf();

    if (isset($_POST['remove_id'])) {
        $cartService->remove((int) $_POST['remove_id']);

    } elseif (isset($_POST['update_id'], $_POST['qty'])) {
        $cartService->update(
            (int) $_POST['update_id'],
            (int) $_POST['qty']
        );

    } elseif (($_POST['action'] ?? '') === 'clear') {
        $cartService->clear();
    }

    header('Location: inkobLister.php', true, 303);
    exit;
}

$loggedInUser = currentUser();

$cart = $cartService->items();
$count = $cartService->count();
$total = $cartService->total();

/*
 * Calculate cart climate data.
 */
$summary = (
    new CartClimateSummaryService(
        $app->catalog(),
        $app->metrics()
    )
)->summarize($cart);

/*
 * Helper functions.
 */
function cartEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function cartMoney(float $amount): string
{
    return number_format(
        $amount,
        2,
        '.',
        ','
    ) . ' DKK';
}

function cartImageUrl(string $filename): ?string
{
    $filename = basename(
        str_replace('\\', '/', trim($filename))
    );

    if (
        $filename === ''
        || $filename === '.'
        || $filename === '..'
    ) {
        return null;
    }

    return '../Style/Images/products/'
        . rawurlencode($filename);
}

/*
 * Overall cart climate classification.
 */
$cartClimateClass = (string) (
    $summary['cart_climate_class'] ?? 'unknown'
);

if (!in_array(
    $cartClimateClass,
    ['low', 'medium', 'high', 'unknown'],
    true
)) {
    $cartClimateClass = 'unknown';
}

$cartClimateLabel = match ($cartClimateClass) {
    'low' => 'Low climate impact',
    'medium' => 'Medium climate impact',
    'high' => 'High climate impact',
    default => 'Climate category unavailable'
};

$averagePerKg = $summary['average_per_kg'] ?? null;

$classificationComplete = (
    $summary['classification_complete'] ?? false
) === true;

$car = $summary['car_comparison'] ?? null;

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Shopping Cart | Flash Food</title>

    <link rel="stylesheet"
          href="../Style/CartStyle.css?v=20261031-3">

    <script src="../Js/cart.js" defer></script>
</head>

<body>

<!-- HEADER -->

<header class="site-header">

    <div class="topbar">

        <!-- Flash Food brand -->

        <a class="brand" href="./home.php">
            <span class="brand-mark"
                  aria-hidden="true"></span>

            <span>Flash Food</span>
        </a>

        <!-- Main navigation -->

        <nav class="main-nav"
             aria-label="Main navigation">

            <a class="nav-link"
               href="./home.php">
                Home
            </a>

            <a class="nav-link"
               href="./home.php#about">
                About us
            </a>

        </nav>

        <!-- Header actions -->

        <div class="header-actions">

            <!-- Wishlist -->

            <a class="header-link"
               href="./fav.php"
               id="wishlist-link"
               aria-label="Wishlist">

                <svg class="header-action-icon"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">

                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>
                </svg>

                <span>Wishlist</span>
            </a>

            <!-- Cart -->

            <a class="header-link"
               href="./inkobLister.php"
               id="cart-link"
               aria-label="Cart">

                <svg class="header-action-icon"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">

                    <path d="M3 3h2l3 12h11l2-8H6"/>
                    <circle cx="9" cy="20" r="1"/>
                    <circle cx="18" cy="20" r="1"/>
                </svg>

                <span>Cart</span>

                <span class="cart-count">
                    <?= (int) $count ?>
                </span>
            </a>

            <!-- Profile -->

            <a class="header-link"
               href="../profil/profil.php"
               id="profile-link"
               aria-label="Profile">

                <svg class="header-action-icon"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">

                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                </svg>

                <span>
                    <?php if ($loggedInUser !== null): ?>
                        Hi, <?= cartEscape(
                            $loggedInUser['name']
                        ) ?>
                    <?php else: ?>
                        Profile
                    <?php endif; ?>
                </span>

            </a>

        </div>

    </div>

</header>


<!-- SHOPPING CART -->

<main class="page">

    <a class="back" href="./home.php">
        ← Continue shopping
    </a>

    <div class="page-heading">

        <div>
            <h1>Your shopping cart</h1>

            <p>
                Review your items and their climate impact.
            </p>
        </div>

        <span class="item-count">
            <?= (int) $count ?>
            <?= $count === 1 ? 'item' : 'items' ?>
        </span>

    </div>


    <?php if ($cart === []): ?>

        <!-- EMPTY CART -->

        <section class="empty-state">

            <div class="empty-icon"
                 aria-hidden="true">
                🛒
            </div>

            <h2>Your cart is empty</h2>

            <p>
                Discover products and start shopping.
            </p>

            <a class="primary-button"
               href="./home.php">
                Explore products
            </a>

        </section>

    <?php else: ?>

        <div class="cart-layout">

            <!-- PRODUCT LIST -->

            <section class="cart-items"
                     aria-label="Cart products">

                <?php foreach ($summary['lines'] as $item): ?>

                    <?php
                    $imageUrl = cartImageUrl(
                        $item['image']
                    );

                    $itemClass = match (
                        (int) $item['rank']
                    ) {
                        1 => 'low',
                        2 => 'medium',
                        3 => 'high',
                        default => 'unknown'
                    };
                    ?>

                    <article class="product-card">

                        <!-- Product image -->

                        <a class="product-visual"
                           href="product.php?id=<?= (int) $item['id'] ?>"
                           aria-label="View <?= cartEscape(
                               $item['title']
                           ) ?>">

                            <?php if ($imageUrl !== null): ?>

                                <img
                                    src="<?= cartEscape($imageUrl) ?>"
                                    alt="<?= cartEscape(
                                        $item['title']
                                    ) ?>"
                                    loading="lazy">

                            <?php else: ?>

                                <span class="product-placeholder"
                                      aria-hidden="true">
                                    ✳
                                </span>

                            <?php endif; ?>

                        </a>


                        <!-- Product information -->

                        <div class="product-info">

                            <h2>
                                <?= cartEscape($item['title']) ?>
                            </h2>

                            <p class="unit-price">
                                <?= cartEscape(
                                    cartMoney(
                                        (float) $item['price']
                                    )
                                ) ?>
                                / item
                            </p>


                            <!-- Individual climate category -->

                            <?php if ($item['rank'] > 0): ?>

                                <span class="climate-tag climate-<?= $itemClass ?>">

                                    <svg viewBox="0 0 24 24"
                                         fill="currentColor"
                                         aria-hidden="true">

                                        <path d="M20.5 3.5C12.3 3 6 6.1 4.2 12c-1.2 3.9.8 7.5 4.4 8.3 5.6 1.2 11.5-3.4 11.9-16.8ZM6.3 18.8c2.3-3.9 5.4-6.7 9.5-9.1-3.3 3.2-5.7 6.3-7.3 10.1l-2.2-1Z"/>
                                    </svg>

                                    <?= cartEscape($item['label']) ?>
                                    climate impact

                                </span>

                            <?php else: ?>

                                <span class="climate-tag climate-unknown">
                                    Climate category unavailable
                                </span>

                            <?php endif; ?>


                            <!-- CO2e for selected quantity -->

                            <p class="climate-detail">

                                <?php if (
                                    $item['co2e_line'] !== null
                                ): ?>

                                    <?= number_format(
                                        (float) $item['co2e_line'],
                                        2,
                                        '.',
                                        ''
                                    ) ?>

                                    kg CO₂e for
                                    <?= (int) $item['qty'] ?>

                                    <?= (int) $item['qty'] === 1
                                        ? 'item'
                                        : 'items' ?>

                                <?php else: ?>

                                    Climate total unavailable
                                    for this item

                                <?php endif; ?>

                            </p>

                        </div>


                        <!-- Product actions -->

                        <div class="product-actions">

                            <strong class="line-price">
                                <?= cartEscape(
                                    cartMoney(
                                        (float) $item['price']
                                        * (int) $item['qty']
                                    )
                                ) ?>
                            </strong>


                            <!-- Update quantity -->

                            <form class="quantity-form"
                                  method="post"
                                  action="inkobLister.php">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= cartEscape(
                                           csrfToken()
                                       ) ?>">

                                <input type="hidden"
                                       name="update_id"
                                       value="<?= (int) $item['id'] ?>">

                                <label class="visually-hidden"
                                       for="qty-<?= (int) $item['id'] ?>">

                                    Quantity for
                                    <?= cartEscape(
                                        $item['title']
                                    ) ?>

                                </label>

                                <input
                                    id="qty-<?= (int) $item['id'] ?>"
                                    name="qty"
                                    type="number"
                                    min="1"
                                    max="99"
                                    value="<?= (int) $item['qty'] ?>"
                                    required>

                                <button type="submit"
                                        class="small-button">
                                    Update
                                </button>

                            </form>


                            <!-- Remove product -->

                            <form method="post"
                                  action="inkobLister.php">

                                <input type="hidden"
                                       name="csrf_token"
                                       value="<?= cartEscape(
                                           csrfToken()
                                       ) ?>">

                                <input type="hidden"
                                       name="remove_id"
                                       value="<?= (int) $item['id'] ?>">

                                <button type="submit"
                                        class="remove-button">
                                    Remove
                                </button>

                            </form>

                        </div>

                    </article>

                <?php endforeach; ?>


                <!-- Clear cart -->

                <form method="post"
                      action="inkobLister.php"
                      class="clear-form">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= cartEscape(
                               csrfToken()
                           ) ?>">

                    <input type="hidden"
                           name="action"
                           value="clear">

                    <button type="submit"
                            class="clear-button"
                            data-confirm-clear>
                        Clear cart
                    </button>

                </form>

            </section>


            <!-- RIGHT SIDE -->

            <aside class="summary-column">

                <!-- ORDER SUMMARY -->

                <section class="summary-card">

                    <h2>Order summary</h2>

                    <div class="summary-row">

                        <span>
                            Items (<?= (int) $count ?>)
                        </span>

                        <span>
                            <?= cartEscape(
                                cartMoney($total)
                            ) ?>
                        </span>

                    </div>

                    <div class="summary-total">

                        <span>Total</span>

                        <strong>
                            <?= cartEscape(
                                cartMoney($total)
                            ) ?>
                        </strong>

                    </div>

                    <a class="primary-button checkout-button"
                       href="../Checkout/checkout.php">

                        Proceed to Checkout

                        <span aria-hidden="true">→</span>

                    </a>

                    <p class="checkout-note">
                        Simulated checkout — no real payment.
                    </p>

                </section>


                <!-- CLIMATE FOOTPRINT -->

                <section class="summary-card climate-card">

                    <div class="climate-heading">

                        <svg class="climate-leaf"
                             viewBox="0 0 24 24"
                             fill="currentColor"
                             aria-hidden="true">

                            <path d="M20.5 3.5C12.3 3 6 6.1 4.2 12c-1.2 3.9.8 7.5 4.4 8.3 5.6 1.2 11.5-3.4 11.9-16.8ZM6.3 18.8c2.3-3.9 5.4-6.7 9.5-9.1-3.3 3.2-5.7 6.3-7.3 10.1l-2.2-1Z"/>
                        </svg>

                        <h2>
                            Your cart's climate footprint
                        </h2>

                    </div>


                    <!-- TOTAL CO2e -->

                    <?php if ($summary['known_count'] > 0): ?>

                        <div class="footprint-number">

                            <?= number_format(
                                (float) $summary['known_total'],
                                2,
                                '.',
                                ''
                            ) ?>

                            <small>kg CO₂e</small>

                        </div>

                        <?php if (!$summary['complete']): ?>

                            <p class="footprint-note">
                                Partial estimate — some items
                                have no climate total.
                            </p>

                        <?php endif; ?>

                    <?php else: ?>

                        <p class="footprint-note">
                            Climate total is unavailable
                            for these items.
                        </p>

                    <?php endif; ?>


                    <!-- DYNAMIC CAR COMPARISON -->

                    <?php if ($car !== null): ?>

                        <div class="car-comparison climate-<?= cartEscape(
                            $cartClimateClass
                        ) ?>">

                            <!-- Car icon -->

                            <svg class="car-icon"
                                 viewBox="0 0 48 32"
                                 fill="none"
                                 aria-hidden="true">

                                <path
                                    d="M8 13 12 5h23l5 8 4 3v10H4V16l4-3Z"
                                    stroke="currentColor"
                                    stroke-width="2.6"
                                    stroke-linejoin="round"/>

                                <path
                                    d="M13 13h22l-3-6H16l-3 6Z"
                                    fill="currentColor"
                                    opacity=".25"/>

                                <circle cx="12"
                                        cy="26"
                                        r="4"
                                        fill="currentColor"/>

                                <circle cx="36"
                                        cy="26"
                                        r="4"
                                        fill="currentColor"/>

                            </svg>


                            <!-- Driving distance -->

                            <div class="comparison-copy">

                                <span>
                                    Equivalent to driving about
                                </span>

                                <strong>
                                    <?= number_format(
                                        (float) $car['value'],
                                        1,
                                        '.',
                                        ''
                                    ) ?>
                                    km
                                </strong>

                                <span>
                                    in a car<?= ($car['is_example'] ?? false)
                                        ? ' (illustrative)'
                                        : '' ?>.
                                </span>

                            </div>


                            <!-- Comparison information -->

                            <details class="comparison-details">

                                <summary
                                    aria-label="How is the driving comparison calculated?">
                                    ⓘ
                                </summary>

                                <div class="comparison-popover">

                                    <?php if (
                                        isset($car['factor'])
                                        && is_numeric($car['factor'])
                                    ): ?>

                                        Calculated using

                                        <?= number_format(
                                            (float) $car['factor'],
                                            5,
                                            '.',
                                            ''
                                        ) ?>

                                        kg CO₂e per km.

                                    <?php endif; ?>

                                    <?php if (
                                        is_array($car['source'] ?? null)
                                        && !empty(
                                            $car['source']['title']
                                        )
                                    ): ?>

                                        Source:
                                        <?= cartEscape(
                                            $car['source']['title']
                                        ) ?>.

                                    <?php endif; ?>

                                    <?php if (
                                        !$summary['complete']
                                    ): ?>

                                        Only items with available
                                        climate totals are included.

                                    <?php endif; ?>

                                </div>

                            </details>

                        </div>

                    <?php endif; ?>


                    <!-- AVERAGE CLIMATE IMPACT PER KG -->

                    <div class="cart-average-impact">

                        <div class="cart-average-heading">

                            <span>
                                Average climate impact per kg
                            </span>

                            <span class="climate-tag climate-<?= cartEscape(
                                $cartClimateClass
                            ) ?>">

                                <?= cartEscape(
                                    $cartClimateLabel
                                ) ?>

                            </span>

                        </div>

                        <?php if (
                            $averagePerKg !== null
                        ): ?>

                            <div class="cart-average-value">

                                <strong>
                                    <?= number_format(
                                        (float) $averagePerKg,
                                        2,
                                        '.',
                                        ''
                                    ) ?>
                                </strong>

                                <span>
                                    kg CO₂e/kg
                                </span>

                            </div>

                            <?php if (
                                !$classificationComplete
                            ): ?>

                                <p class="cart-average-note">
                                    Partial classification —
                                    only products with known
                                    climate impact and weight
                                    are included.
                                </p>

                            <?php endif; ?>

                        <?php else: ?>

                            <p class="cart-average-note">
                                Average climate impact
                                is unavailable because
                                product weight or climate
                                data is missing.
                            </p>

                        <?php endif; ?>

                    </div>


                    <!-- CLIMATE BREAKDOWN -->

                    <div class="breakdown"
                         aria-label="Number of cart items by climate category">

                        <?php foreach (
                            [
                                'low' => 'Low-impact items',
                                'medium' => 'Medium-impact items',
                                'high' => 'High-impact items'
                            ] as $key => $label
                        ): ?>

                            <div class="breakdown-row">

                                <span class="dot <?= $key ?>"
                                      aria-hidden="true"></span>

                                <span>
                                    <?= cartEscape($label) ?>
                                </span>

                                <strong>
                                    <?= (int) (
                                        $summary['breakdown'][$key] ?? 0
                                    ) ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>


                        <?php if (
                            ($summary['breakdown']['unknown'] ?? 0) > 0
                        ): ?>

                            <div class="breakdown-row">

                                <span class="dot unknown"
                                      aria-hidden="true"></span>

                                <span>
                                    Unclassified items
                                </span>

                                <strong>
                                    <?= (int) $summary['breakdown']['unknown'] ?>
                                </strong>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- DATA COMPLETENESS -->

                    <?php if (
                        $summary['unknown_count'] > 0
                    ): ?>

                        <p class="data-note">

                            <?= (int) $summary['unknown_count'] ?>

                            item(s) excluded from the CO₂e
                            total because a valid per-item
                            estimate is unavailable.

                        </p>

                    <?php endif; ?>

                </section>

            </aside>

        </div>

    <?php endif; ?>

</main>

</body>
</html>
