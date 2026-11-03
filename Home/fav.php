<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';

/* ==========================================
   FLASH FOOD - WISHLIST
   ========================================== */

$app = flashfood_application($conn);

$favoriteService = $app->favorites();
$cartService = $app->cart();

function wishlistEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/* ==========================================
   HANDLE POST ACTIONS
   ========================================== */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    requireCsrf();

    $action = (string) (
        $_POST['wishlist_action'] ?? ''
    );

    $id = filter_input(
        INPUT_POST,
        'product_id',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    /* ======================================
       ADD TO CART
       ====================================== */

    if ($action === 'add_cart') {

        $isAjax = ($_POST['ajax'] ?? '') === '1';

        try {

            if (!is_int($id)) {
                throw new InvalidArgumentException(
                    'Invalid product.'
                );
            }

            // Use existing CartService
            $cartService->add($id, 1);

            // Get updated cart quantity
            $count = $cartService->count();

            $success = true;
            $message = '';

        } catch (Throwable $e) {

            $success = false;

            $message =
                'Unable to add this product to the cart.';
        }

        /* AJAX RESPONSE */

        if ($isAjax) {

            header(
                'Content-Type: application/json; charset=utf-8'
            );

            http_response_code(
                $success ? 200 : 400
            );

            echo json_encode(
                $success
                    ? [
                        'ok' => true,
                        'count' => $count
                    ]
                    : [
                        'ok' => false,
                        'error' => $message
                    ],
                JSON_UNESCAPED_UNICODE
            );

            exit;
        }

        /* FALLBACK WITHOUT JAVASCRIPT */

        if (!$success) {
            http_response_code(400);
            exit('Unable to add this product to the cart.');
        }
    }

    /* ======================================
       REMOVE PRODUCT
       ====================================== */

    elseif ($action === 'remove') {

        if (!is_int($id)) {
            http_response_code(400);
            exit('Invalid product.');
        }

        $savedItems = $favoriteService->items();

        if (isset($savedItems[$id])) {
            $favoriteService->remove($id);
        }
    }

    /* ======================================
       CLEAR WISHLIST
       ====================================== */

    elseif ($action === 'clear') {

        $favoriteService->clear();

    } else {

        http_response_code(400);
        exit('Invalid wishlist action.');
    }

    // Return to Wishlist without notifications
    header('Location: fav.php', true, 303);
    exit;
}

/* ==========================================
   LOAD USER AND PRODUCTS
   ========================================== */

$loggedInUser = currentUser();

$cartCount = $cartService->count();

$savedItems = $favoriteService->items();

$items = [];

/* ==========================================
   PREPARE PRODUCT DATA
   ========================================== */

foreach ($savedItems as $productId => $saved) {

    $id = (int) $productId;

    if ($id < 1) {
        continue;
    }

    // Get product from existing CatalogService
    $product = $app->catalog()->find($id);

    /* CLIMATE CLASSIFICATION */

    $rank = (int) (
        $product['_sig']['co2']['rank'] ?? 0
    );

    if (!in_array($rank, [1, 2, 3], true)) {
        $rank = 0;
    }

    /* PRODUCT IMAGE */

    $filename = basename(
        str_replace(
            '\\',
            '/',
            trim((string) ($product['image'] ?? ''))
        )
    );

    $imageUrl = (
        $filename !== '' &&
        $filename !== '.' &&
        $filename !== '..'
    )
        ? '../Style/Images/products/' .
            rawurlencode($filename)
        : null;

    /* PRODUCT INFORMATION */

    $items[] = [

        'id' => $id,

        'title' => (string) (
            $product['title'] ??
            $saved['title'] ??
            'Product'
        ),

        'price' => (float) (
            $product['price'] ??
            $saved['price'] ??
            0
        ),

        'image' => $imageUrl,

        'available' => $product !== null,

        'climate' => match ($rank) {

            1 => [
                'class' => 'low',
                'label' => 'Low climate impact'
            ],

            2 => [
                'class' => 'medium',
                'label' => 'Medium climate impact'
            ],

            3 => [
                'class' => 'high',
                'label' => 'High climate impact'
            ],

            default => [
                'class' => 'unknown',
                'label' => 'Climate data unavailable'
            ],

        },

    ];
}

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>My Wishlist | Flash Food</title>

    <!-- SAME DESIGN AS HOME -->
    <link
        rel="stylesheet"
        href="../Style/HomeStyle.css?v=20261101"
    >

    <!-- WISHLIST DESIGN -->
    <link
        rel="stylesheet"
        href="../Style/fav.css?v=20261101-5"
    >

    <!-- WISHLIST JAVASCRIPT -->
    <script
        src="../Js/wishlist.js?v=20261101-7"
        defer
    ></script>

</head>

<body>

<!-- ==========================================
     HEADER
     ========================================== -->

<header class="site-header">

    <div class="topbar">

        <!-- FLASH FOOD LOGO -->

        <a class="brand" href="./home.php">

            <span
                class="brand-mark"
                aria-hidden="true"
            ></span>

            <span>Flash Food</span>

        </a>

        <!-- MAIN NAVIGATION -->

        <nav
            class="main-nav"
            aria-label="Main navigation"
        >

            <a
                class="nav-link"
                href="./home.php"
            >
                Home
            </a>

            <a
                class="nav-link"
                href="./home.php#about"
            >
                About us
            </a>

        </nav>

        <!-- HEADER ACTIONS -->

        <div class="header-actions">

            <!-- WISHLIST -->

            <a
                class="header-link wishlist-current"
                href="./fav.php"
                id="wishlist-link"
                aria-label="Wishlist"
                aria-current="page"
            >

                <svg
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
                href="./inkobLister.php"
                id="cart-link"
                aria-label="Cart"
            >

                <svg
                    class="header-action-icon"
                    id="cart-icon"
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
                    <?= (int) $cartCount ?>
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

                    <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>

                </svg>

                <span>

                    <?php if ($loggedInUser !== null): ?>

                        Hi, <?= wishlistEscape(
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
     WISHLIST CONTENT
     ========================================== -->

<main class="wishlist-page">

    <!-- PAGE TITLE -->

    <div class="wishlist-heading">

        <h1>My Wishlist</h1>

    </div>

    <!-- MAIN PANEL -->

    <section
        class="wishlist-panel"
        aria-label="Saved products"
    >

        <?php if ($items !== []): ?>

            <!-- PANEL HEADER -->

            <div class="wishlist-panel-header">

                <h2>
                    <?= count($items) ?>

                    <?= count($items) === 1
                        ? 'item'
                        : 'items' ?>

                    in your wishlist
                </h2>

                <!-- CLEAR WISHLIST -->

                <form
                    method="post"
                    action="fav.php"
                    class="wishlist-confirm-form"
                    data-confirm-message="Remove all products from your wishlist?"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= wishlistEscape(
                            csrfToken()
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="wishlist_action"
                        value="clear"
                    >

                    <button
                        class="wishlist-clear"
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

                            <path
                                d="M4 7h16M10 4h4M6 7l1 14h10l1-14M10 11v6M14 11v6"
                            />

                        </svg>

                        Clear Wishlist

                    </button>

                </form>

            </div>

            <!-- ==================================
                 PRODUCT LIST
                 ================================== -->

            <div class="wishlist-items">

                <?php foreach ($items as $item): ?>

                    <article class="wishlist-item">

                        <!-- PRODUCT IMAGE -->

                        <a
                            class="wishlist-product-image"
                            href="product.php?id=<?= $item['id'] ?>"
                            aria-label="View <?= wishlistEscape(
                                $item['title']
                            ) ?>"
                        >

                            <?php if ($item['image'] !== null): ?>

                                <img
                                    src="<?= wishlistEscape(
                                        $item['image']
                                    ) ?>"
                                    alt="<?= wishlistEscape(
                                        $item['title']
                                    ) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <span
                                    class="wishlist-image-placeholder"
                                    aria-hidden="true"
                                >

                                    <?= wishlistEscape(
                                        mb_strtoupper(
                                            mb_substr(
                                                $item['title'],
                                                0,
                                                1,
                                                'UTF-8'
                                            ),
                                            'UTF-8'
                                        )
                                    ) ?>

                                </span>

                            <?php endif; ?>

                        </a>

                        <!-- PRODUCT INFORMATION -->

                        <div class="wishlist-product-info">

                            <h3>

                                <a
                                    href="product.php?id=<?= $item['id'] ?>"
                                >

                                    <?= wishlistEscape(
                                        $item['title']
                                    ) ?>

                                </a>

                            </h3>

                            <!-- PRICE -->

                            <strong class="wishlist-price">

                                <?= number_format(
                                    $item['price'],
                                    2,
                                    ',',
                                    '.'
                                ) ?> kr

                            </strong>

                            <!-- CLIMATE IMPACT -->

                            <span
                                class="wishlist-climate wishlist-climate-<?= $item['climate']['class'] ?>"
                            >

                                <span
                                    class="wishlist-climate-leaf"
                                    aria-hidden="true"
                                ></span>

                                <?= wishlistEscape(
                                    $item['climate']['label']
                                ) ?>

                            </span>

                        </div>

                        <!-- ==================================
                             PRODUCT ACTIONS
                             ================================== -->

                        <div class="wishlist-actions">

                            <!-- ADD TO CART -->

                            <form
                                class="wishlist-cart-form"
                                method="post"
                                action="fav.php"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= wishlistEscape(
                                        csrfToken()
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="wishlist_action"
                                    value="add_cart"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= $item['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="qty"
                                    value="1"
                                >

                                <button
                                    class="wishlist-add-button"
                                    type="submit"
                                    <?= $item['available']
                                        ? ''
                                        : 'disabled' ?>
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
                                            d="M3 3h2l3 12h11l2-8H6"
                                        />

                                        <circle
                                            cx="9"
                                            cy="20"
                                            r="1"
                                        />

                                        <circle
                                            cx="18"
                                            cy="20"
                                            r="1"
                                        />

                                    </svg>

                                    <span>
                                        <?= $item['available']
                                            ? 'Add to Cart'
                                            : 'Unavailable' ?>
                                    </span>

                                </button>

                            </form>

                            <!-- REMOVE PRODUCT -->

                            <form
                                method="post"
                                action="fav.php"
                                class="wishlist-confirm-form"
                                data-confirm-message="Remove <?= wishlistEscape(
                                    $item['title']
                                ) ?> from your wishlist?"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= wishlistEscape(
                                        csrfToken()
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="wishlist_action"
                                    value="remove"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= $item['id'] ?>"
                                >

                                <button
                                    class="wishlist-remove-button"
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

                                        <path
                                            d="M4 7h16M10 4h4M6 7l1 14h10l1-14M10 11v6M14 11v6"
                                        />

                                    </svg>

                                    Remove

                                </button>

                            </form>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <!-- ==================================
                 EMPTY WISHLIST
                 ================================== -->

            <div class="wishlist-empty">

                <span
                    class="wishlist-empty-icon"
                    aria-hidden="true"
                >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path
                            d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0 0-7.8Z"
                        />

                    </svg>

                </span>

                <h2>Your wishlist is empty</h2>

                <p>
                    Find products you love and save them for later.
                </p>

                <a
                    class="wishlist-shop-button"
                    href="home.php"
                >
                    Explore Products
                </a>

            </div>

        <?php endif; ?>

    </section>

</main>

<!-- ==========================================
     CONFIRMATION DIALOG
     ========================================== -->

<dialog
    id="wishlist-confirm"
    class="wishlist-dialog"
    aria-labelledby="wishlist-dialog-title"
    aria-describedby="wishlist-dialog-message"
>

    <h2 id="wishlist-dialog-title">
        Are you sure?
    </h2>

    <p id="wishlist-dialog-message"></p>

    <div class="wishlist-dialog-actions">

        <button
            type="button"
            class="wishlist-dialog-cancel"
            id="wishlist-cancel"
        >
            Cancel
        </button>

        <button
            type="button"
            class="wishlist-dialog-confirm"
            id="wishlist-confirm-yes"
        >
            Yes, remove
        </button>

    </div>

</dialog>

</body>
</html>
