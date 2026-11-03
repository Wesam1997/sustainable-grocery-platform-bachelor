<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';
require_once __DIR__ . '/../Api/catalog.php';
require_once __DIR__ . '/../Api/climate_metrics.php';
require_once __DIR__ . '/../Api/instant_comparisons.php';
require_once __DIR__ . '/../src/Modules/Rewards/GreenPointsService.php';

function product_h(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$application = flashfood_application($conn);

$loggedInUser = currentUser();
$cartCount = $application->cart()->count();

/* ==========================================
   WISHLIST STATE
   ========================================== */

$savedFavorites = $application->favorites()->items();

$savedFavoriteIds = array_map(
    'intval',
    array_keys($savedFavorites)
);

/* ==========================================
   PRODUCT ID
   ========================================== */

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$product = null;
$climate = null;
$comparisonText = null;
$comparisonKey = null;
$loadError = false;

/* ==========================================
   LOAD PRODUCT
   ========================================== */

if ($id) {
    try {
        $product = get_catalog_product($conn, $id);

        if ($product !== null) {
            $signals = is_array($product['_sig'] ?? null)
                ? $product['_sig']
                : compute_signals($product);

            $rank = (int)($signals['co2']['rank'] ?? 0);

            $climate = climate_metrics(
                $product,
                $signals,
                $rank
            );
        }

    } catch (Throwable $error) {
        $loadError = true;

        error_log(
            'product.load_failed: ' . get_class($error)
        );
    }
}

/* ==========================================
   VALIDATE CLIMATE DATA
   ========================================== */

$climateAvailable = is_array($climate)
    && ($climate['available'] ?? false) === true
    && is_numeric($climate['value'] ?? null)
    && is_finite((float)$climate['value'])
    && (float)$climate['value'] >= 0;

$climateRank = $climateAvailable
    ? (int)($climate['rank'] ?? 0)
    : 0;

$climateClass = match ($climateRank) {
    1 => 'low',
    2 => 'medium',
    3 => 'high',
    default => 'unknown'
};

/* ==========================================
   PACKAGE CLIMATE ESTIMATE
   ========================================== */

$package = is_array($climate['package'] ?? null)
    ? $climate['package']
    : [];

$packageAvailable = $climateAvailable
    && ($package['available'] ?? false) === true
    && is_numeric($package['value'] ?? null)
    && is_finite((float)$package['value'])
    && (float)$package['value'] >= 0
    && is_numeric(
        $package['calculation_weight_g'] ?? null
    )
    && (float)$package['calculation_weight_g'] > 0;

$basis = (string)($climate['basis'] ?? '');

$showClimateValue = $climateAvailable
    && (
        $packageAvailable
        || in_array(
            $basis,
            ['per serving', 'per box'],
            true
        )
    );

$displayValue = $packageAvailable
    ? (float)$package['value']
    : (
        $showClimateValue
            ? (float)$climate['value']
            : null
    );

$displayBasis = $packageAvailable
    ? 'Estimated climate impact of this package'
    : (
        $basis === 'per serving'
            ? 'Estimated climate impact per serving'
            : 'Estimated climate impact of this box'
    );

/* ==========================================
   CLIMATE COMPARISON
   ========================================== */

// Uses the existing validated comparison.
// No live Ollama request.

if ($product !== null && $showClimateValue) {
    try {
        $comparisons = new FlashFoodInstantComparisons($conn);

        $context = $comparisons->context(
            $climate,
            (int)$product['id']
        );

        if (
            is_array(
                $context['selected_option'] ?? null
            )
        ) {
            $comparisonKey = (string)(
                $context['selected_option']['key'] ?? ''
            );

            $comparisonText = $comparisons
                ->read($context)['text'] ?? null;
        }

    } catch (Throwable $error) {
        error_log(
            'product.comparison_failed: '
            . get_class($error)
        );
    }
}

/* ==========================================
   GREEN POINTS
   ========================================== */

// Only products with climate rank 1 earn points.

$earnsGreenPoints =
    $climateAvailable && $climateRank === 1;

$pointsPerProduct =
    GreenPointsService::POINTS_PER_LOW_PRODUCT;

$rewardThreshold =
    GreenPointsService::REWARD_THRESHOLD;

$discountPercent =
    GreenPointsService::DISCOUNT_PERCENT;

/* ==========================================
   PRODUCT SIZE
   ========================================== */

$packSize = 'Not specified';

if (
    $product !== null
    && preg_match(
        '/(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l|pcs|stk)\b/ui',
        (string)($product['title'] ?? ''),
        $matches
    )
) {
    $packSize = $matches[1] . ' ' . $matches[2];

} elseif ($packageAvailable) {
    $packSize = number_format(
        (float)$package['calculation_weight_g'],
        0,
        ',',
        '.'
    ) . ' g';
}

/* ==========================================
   PRODUCT BADGE AND IMAGE
   ========================================== */

$isOrganicTitle = $product !== null
    && preg_match(
        '/\borganic\b/i',
        (string)($product['title'] ?? '')
    ) === 1;

$imageName = $product !== null
    ? basename(
        str_replace(
            '\\',
            '/',
            (string)($product['image'] ?? '')
        )
    )
    : '';

$imageUrl = $imageName !== ''
    ? '../Style/Images/products/'
        . rawurlencode($imageName)
    : '';

/* ==========================================
   IS THIS PRODUCT IN WISHLIST?
   ========================================== */

$isFavorite = $product !== null
    && isset($savedFavorites[(int)$product['id']]);

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= $product
            ? product_h($product['title']) . ' – Flash Food'
            : 'Product details – Flash Food'
        ?>
    </title>

    <!-- HOME HEADER STYLES -->
    <link
        rel="stylesheet"
        href="../Style/HomeStyle.css?v=20261103-7"
    >

    <!-- PRODUCT DETAILS STYLES -->
    <link
        rel="stylesheet"
        href="../Style/produktdetalej.css?v=20261103-7"
    >
    <script src="../Js/AddToFav.js?v=20261103-8" defer></script>

    <!-- SAVED HEART STYLES -->
    <style>
        .add-to-fav button.is-saved svg path {
            fill: #e84a67;
            stroke: #e84a67;
        }
    </style>

    <!-- SAVED PRODUCT IDS FROM PHP -->
    <script>
        window.flashFoodSavedIds = <?= json_encode(
            $savedFavoriteIds,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) ?>;
    </script>

    <!-- PRODUCT DETAILS -->
    <script
        src="../Js/productDetails.js?v=20261101-design2"
        defer
    ></script>

    <!-- ADD TO CART -->
    <script
        src="../Js/addToCartScript.js"
        defer
    ></script>

    <!-- WISHLIST: ONE SHARED HANDLER -->
    <script
        src="../Js/AddToFav.js?v=20261103-7"
        defer
    ></script>
</head>

<body>

<!-- ==========================================
     HEADER
     ========================================== -->

<header class="site-header">

    <div class="topbar">

        <!-- LOGO -->
        <a class="brand" href="home.php">
            <span
                class="brand-mark"
                aria-hidden="true"
            ></span>

            <span>Flash Food</span>
        </a>

        <!-- NAVIGATION -->
        <nav
            class="main-nav"
            aria-label="Main navigation"
        >
            <a
                class="nav-link active"
                href="home.php"
            >
                Home
            </a>

            <a
                class="nav-link"
                href="home.php#about"
            >
                About us
            </a>
        </nav>

        <!-- HEADER ACTIONS -->
        <div class="header-actions">

            <!-- WISHLIST -->
            <a
                class="header-link"
                href="fav.php"
                id="wishlist-link"
                aria-label="Wishlist"
            >

                <svg
                    id="wishlist-icon"
                    class="header-action-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path
                        d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"
                    />
                </svg>

                <span>Wishlist</span>
            </a>

            <!-- CART -->
            <a
                class="header-link"
                href="inkobLister.php"
                id="cart-link"
                aria-label="Cart"
            >

                <svg
                    id="cart-icon"
                    class="header-action-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M3 3h2l3 12h11l2-8H6"/>
                    <circle cx="9" cy="20" r="1"/>
                    <circle cx="18" cy="20" r="1"/>
                </svg>

                <span>Cart</span>

                <span class="cart-count">
                    <?= (int)$cartCount ?>
                </span>
            </a>

            <!-- PROFILE -->
            <a
                class="header-link"
                href="../profil/profil.php"
                id="profile-link"
                aria-label="Profile"
            >

                <svg
                    id="profile-icon"
                    class="header-action-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="8" r="4"/>

                    <path
                        d="M4 21v-2a8 8 0 0 1 16 0v2"
                    />
                </svg>

                <span>
                    <?php if ($loggedInUser !== null): ?>
                        Hi, <?= product_h(
                            $loggedInUser['name'] ?? ''
                        ) ?>
                    <?php else: ?>
                        Profile
                    <?php endif; ?>
                </span>
            </a>

        </div>
    </div>
</header>

<!-- ==========================================
     PRODUCT PAGE
     ========================================== -->

<main class="product-page">

<?php if ($product === null): ?>

    <p id="statusMessage" role="status">
        <?= $loadError
            ? 'Product could not be loaded. Please try again.'
            : 'Product not found.'
        ?>
    </p>

<?php else: ?>

    <!-- ======================================
         MAIN PRODUCT CARD
         ====================================== -->

    <article
        id="productDetails"
        class="product-details"
        data-product-id="<?= (int)$product['id'] ?>"
    >

        <!-- PRODUCT IMAGE -->
        <div class="product-image-area">

            <?php if ($isOrganicTitle): ?>

                <span class="organic-badge">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        aria-hidden="true"
                    >
                        <path
                            d="M20 4C10 4 5 8 5 15a5 5 0 0 0 5 5c7 0 11-6 10-16Z"
                        />

                        <path
                            d="M4 21c3-7 7-10 12-13"
                        />
                    </svg>

                    Organic
                </span>

            <?php endif; ?>

            <?php if ($imageUrl !== ''): ?>

                <img
                    id="productImage"
                    src="<?= product_h($imageUrl) ?>"
                    alt="<?= product_h($product['title']) ?>"
                >

                <p
                    id="imageUnavailable"
                    class="image-unavailable"
                    hidden
                >
                    Image not available
                </p>

            <?php else: ?>

                <p
                    id="imageUnavailable"
                    class="image-unavailable"
                >
                    Image not available
                </p>

            <?php endif; ?>

        </div>

        <!-- ==================================
             PRODUCT INFORMATION
             ================================== -->

        <div class="product-information">

            <!-- PRODUCT HEADING -->
            <div class="product-heading">

                <div>
                    <h1 id="productTitle">
                        <?= product_h($product['title']) ?>
                    </h1>

                    <p id="productMerchant">
                        <?= product_h(
                            $product['merchant'] ?? ''
                        ) ?>
                    </p>
                </div>

                <!-- WISHLIST BUTTON -->
                <form
                    class="add-to-fav"
                    action="../Api/AddFav.php"
                    method="post"
                >

                    <input
                        id="favoriteProductId"
                        type="hidden"
                        name="product_id"
                        value="<?= (int)$product['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= product_h(csrfToken()) ?>"
                    >

                    <input
                        type="hidden"
                        name="wishlist_action"
                        value="<?= $isFavorite ? 'remove' : 'add' ?>"
                    >

                    <button
                        class="favourite-button <?= $isFavorite ? 'is-saved' : '' ?>"
                        type="submit"
                        aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>"
                        aria-label="<?= $isFavorite ? 'Remove from wishlist' : 'Add to wishlist' ?>"
                    >

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"
                            />
                        </svg>

                    </button>
                </form>

            </div>

            <!-- PRODUCT PRICE -->
            <p
                id="productPrice"
                class="product-price"
            >
                <?= number_format(
                    (float)$product['price'],
                    2,
                    ',',
                    '.'
                ) ?> kr.
            </p>

            <!-- ==================================
                 CLIMATE INFORMATION
                 ================================== -->

            <section
                id="climateBox"
                class="climate-box <?= product_h(
                    $climateClass
                ) ?>"
                aria-labelledby="climateLabel"
            >

                <div
                    class="climate-icon"
                    aria-hidden="true"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M20 4C10 4 5 8 5 15a5 5 0 0 0 5 5c7 0 11-6 10-16Z"
                        />

                        <path
                            d="M4 21c3-7 7-10 12-13"
                        />
                    </svg>
                </div>

                <div class="climate-content">

                    <h2 id="climateLabel">
                        Climate impact
                    </h2>

                    <?php if ($showClimateValue): ?>

                        <!-- PACKAGE / SERVING BASIS -->
                        <p class="climate-basis">
                            <?= product_h($displayBasis) ?>
                        </p>

                        <!-- ONE CO2e VALUE -->
                        <p
                            id="climateValue"
                            class="climate-main-value"
                        >
                            <?= number_format(
                                (float)$displayValue,
                                2,
                                '.',
                                ''
                            ) ?> kg CO₂e
                        </p>

                        <!-- CLIMATE COMPARISON -->
                        <?php if (
                            is_string($comparisonText)
                            && trim($comparisonText) !== ''
                        ): ?>

                            <div class="climate-comparison">

                                <?php if (
                                    $comparisonKey === 'car'
                                ): ?>

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M5 11 7 6h10l2 5M4 11h16v7H4zM6 18v2M18 18v2"
                                        />
                                        <circle cx="7" cy="14" r="1"/>
                                        <circle cx="17" cy="14" r="1"/>
                                    </svg>

                                <?php else: ?>

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="M12 11v5M12 8h.01"/>
                                    </svg>

                                <?php endif; ?>

                                <p>
                                    <?= product_h(
                                        $comparisonText
                                    ) ?>
                                </p>
                            </div>

                        <?php endif; ?>

                        <!-- MORE INFORMATION -->
                        <details class="climate-more">

                            <summary
                                aria-label="About the climate estimate"
                            >
                                i
                            </summary>

                            <p>
                                This is an estimate based on
                                the available climate data<?= $packageAvailable
                                    ? ' and the recorded package weight'
                                    : ''
                                ?>.

                                Actual emissions may vary.
                            </p>

                        </details>

                    <?php else: ?>

                        <p
                            id="climateValue"
                            class="climate-unavailable"
                        >
                            Package climate estimate
                            not available
                        </p>

                        <p class="climate-basis">
                            A verified package or serving
                            estimate is needed.
                        </p>

                    <?php endif; ?>

                </div>
            </section>

            <!-- ==================================
                 GREEN CHOICE REWARD
                 ================================== -->

            <?php if ($earnsGreenPoints): ?>

                <aside
                    class="green-choice-reward"
                    aria-label="Green Choice Reward"
                >

                    <div
                        class="reward-icon"
                        aria-hidden="true"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="12" cy="9" r="7"/>

                            <path
                                d="m8 15-2 7 6-3 6 3-2-7"
                            />

                            <path
                                d="m12 5 1.2 2.6 2.8.3-2.1 1.9.6 2.8-2.5-1.4-2.5 1.4.6-2.8L8 7.9l2.8-.3Z"
                            />
                        </svg>
                    </div>

                    <div class="reward-copy">

                        <span class="reward-eyebrow">
                            GREEN CHOICE REWARD
                        </span>

                        <strong>
                            Earn +<?= (int)$pointsPerProduct ?>
                            Green Points
                        </strong>

                        <p>
                            Buy this lower-impact product
                            and earn
                            <?= (int)$pointsPerProduct ?>
                            points per item after checkout.

                            Collect
                            <?= (int)$rewardThreshold ?>
                            points to get

                            <b>
                                <?= (int)$discountPercent ?>%
                                off
                            </b>

                            a future order.
                        </p>

                    </div>

                </aside>

            <?php endif; ?>

            <!-- ==================================
                 PACK SIZE
                 ================================== -->

            <dl class="product-facts">

                <div>
                    <dt>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 8 12 4l8 4v10l-8 4-8-4zM4 8l8 4 8-4M12 12v10"
                            />
                        </svg>

                        Pack size
                    </dt>

                    <dd id="productSize">
                        <?= product_h($packSize) ?>
                    </dd>
                </div>

            </dl>

            <!-- ==================================
                 ADD TO CART
                 ================================== -->

            <form
                class="cart-form add-to-cart"
                action="../Api/add_to_cart.php"
                method="post"
            >

                <input
                    id="productId"
                    type="hidden"
                    name="product_id"
                    value="<?= (int)$product['id'] ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= product_h(csrfToken()) ?>"
                >

                <div class="quantity-picker">

                    <button
                        id="decreaseQuantity"
                        type="button"
                        aria-label="Decrease quantity"
                    >
                        −
                    </button>

                    <input
                        id="quantity"
                        type="number"
                        name="qty"
                        min="1"
                        max="99"
                        step="1"
                        value="1"
                        aria-label="Quantity"
                        required
                    >

                    <button
                        id="increaseQuantity"
                        type="button"
                        aria-label="Increase quantity"
                    >
                        +
                    </button>

                </div>

                <button
                    class="add-button"
                    type="submit"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M3 3h2l3 12h11l2-8H6"/>
                        <circle cx="9" cy="20" r="1"/>
                        <circle cx="18" cy="20" r="1"/>
                    </svg>

                    <span>Add to cart</span>
                </button>

            </form>

        </div>
    </article>

    <!-- ======================================
         RELATED PRODUCTS
         ====================================== -->

    <section
        id="relatedSection"
        class="related-section"
        data-csrf-token="<?= product_h(csrfToken()) ?>"
        hidden
    >

        <div class="related-heading">

            <h2>Related products</h2>

            <a href="home.php">
                See all
                <span aria-hidden="true">→</span>
            </a>

        </div>

        <div
            id="relatedProducts"
            class="related-products"
        ></div>

    </section>

<?php endif; ?>

</main>

</body>
</html>
