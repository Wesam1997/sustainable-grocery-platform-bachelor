<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';
require_once __DIR__ . '/../Api/catalog.php';
require_once __DIR__ . '/../Api/climate_metrics.php';
require_once __DIR__ . '/../Api/instant_comparisons.php';

$application = flashfood_application($conn);
$instantComparisons = new FlashFoodInstantComparisons($conn);

$loggedInUser = currentUser();
$filters = parse_filters($_GET);
$items = get_catalog_items($conn, $_GET);

$cart_count = $application->cart()->count();

/* ==========================================
   SAVED WISHLIST PRODUCTS
   ========================================== */

$savedFavorites = $application->favorites()->items();

$savedFavoriteIds = array_map(
    'intval',
    array_keys($savedFavorites)
);

/* ==========================================
   FILTER VALUES
   ========================================== */

$type = $filters['type'];
$query = $filters['q'] ?? '';
$pmin = $filters['pmin'];
$pmax = $filters['pmax'];
$wmin = $filters['wmin'];
$wmax = $filters['wmax'];
$onlyVeg = $filters['onlyVeg'];
$onlyMeat = $filters['onlyMeat'];
$lowCO2 = $filters['lowCO2'];
$midCO2 = $filters['midCO2'];
$highCO2 = $filters['highCO2'];
$sort = $filters['sort'];

function h(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function product_initial(string $title): string
{
    $title = trim($title);

    if ($title === '') {
        return '?';
    }

    return mb_strtoupper(
        mb_substr($title, 0, 1, 'UTF-8'),
        'UTF-8'
    );
}

function is_restaurant_item(array $row): bool
{
    $typeValue = mb_strtolower(
        (string)($row['type'] ?? $row['category'] ?? ''),
        'UTF-8'
    );

    return str_contains($typeValue, 'restaurant')
        || str_contains($typeValue, 'meal')
        || str_contains($typeValue, 'dish');
}

/* ==========================================
   PRODUCT SECTIONS
   ========================================== */

$popularItems = array_slice($items, 0, 8);

$supermarketItems = array_values(array_filter(
    $items,
    static fn(array $row): bool => !is_restaurant_item($row)
));

$restaurantItems = array_values(array_filter(
    $items,
    static fn(array $row): bool => is_restaurant_item($row)
));

$productSections = [
    [
        'id' => 'popular-products',
        'title' => 'Featured products',
        'description' => 'Explore a selection of products and meals.',
        'items' => $popularItems,
    ],
    [
        'id' => 'supermarket-products',
        'title' => 'Supermarket products',
        'description' => 'Explore groceries with climate information.',
        'items' => $supermarketItems,
    ],
    [
        'id' => 'restaurant-meals',
        'title' => 'Restaurant meals',
        'description' => 'Meals with clear climate information per serving.',
        'items' => $restaurantItems,
    ],
];
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Flash Food</title>

    <link rel="stylesheet"
          href="../Style/HomeStyle.css?v=20261103-9">

    <style>
        .add-to-fav button.is-saved svg path {
            fill: #e84a67;
            stroke: #e84a67;
        }
    </style>

    <script>
        window.flashFoodSavedIds = <?= json_encode(
            $savedFavoriteIds,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) ?>;
    </script>

    <script src="../Js/Home.js?v=20261103-8"
            defer></script>

    <script src="../Js/AddToFav.js?v=20261103-8"
            defer></script>
</head>

<body>

<!-- ==========================================
     HEADER
     ========================================== -->

<header class="site-header">
    <div class="topbar">

        <a class="brand"
           href="./home.php">

            <span class="brand-mark"
                  aria-hidden="true"></span>

            <span>Flash Food</span>
        </a>

        <nav class="main-nav"
             aria-label="Main navigation">

            <a class="nav-link active"
               href="./home.php">
                Home
            </a>

            <a class="nav-link"
               href="./about.php">
                About us
            </a>
        </nav>

        <div class="header-actions">

            <!-- WISHLIST -->
            <a class="header-link"
               href="./fav.php"
               id="wishlist-link"
               aria-label="Wishlist">

                <svg id="wishlist-icon"
                     class="header-action-icon"
                     xmlns="http://www.w3.org/2000/svg"
                     width="22"
                     height="22"
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

            <!-- CART -->
            <a class="header-link"
               href="./inkobLister.php"
               id="cart-link"
               aria-label="Cart">

                <svg id="cart-icon"
                     class="header-action-icon"
                     xmlns="http://www.w3.org/2000/svg"
                     width="22"
                     height="22"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">

                    <path d="M3 3h2l3 12h11l2-8H6"/>
                    <circle cx="9"
                            cy="20"
                            r="1"/>
                    <circle cx="18"
                            cy="20"
                            r="1"/>
                </svg>

                <span>Cart</span>

                <span class="cart-count">
                    <?= (int)$cart_count ?>
                </span>
            </a>

            <!-- PROFILE -->
            <a class="header-link"
               href="../profil/profil.php"
               id="profile-link"
               aria-label="Profile">

                <svg id="profile-icon"
                     class="header-action-icon"
                     xmlns="http://www.w3.org/2000/svg"
                     width="22"
                     height="22"
                     viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     aria-hidden="true">

                    <circle cx="12"
                            cy="8"
                            r="4"/>

                    <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                </svg>

                <span>
                    <?php if ($loggedInUser !== null): ?>

                        Hi, <?= h($loggedInUser['name']) ?>

                    <?php else: ?>

                        Profile

                    <?php endif; ?>
                </span>
            </a>

        </div>
    </div>
</header>

<!-- ==========================================
     HERO
     ========================================== -->

<section class="hero">

    <div class="hero-content">

        <h1>
            Shop smarter.
            <br>
            <span>Choose greener.</span>
        </h1>

    </div>
</section>

<div id="climate-anchor"></div>

<!-- ==========================================
     BROWSE TOOLBAR
     ========================================== -->

<div class="browse-toolbar">

    <button type="button"
            class="browse-filter-button"
            id="home-filter-toggle"
            aria-label="Open filters"
            aria-controls="product-filters"
            aria-expanded="false">

        <svg viewBox="0 0 24 24"
             width="19"
             height="19"
             fill="none"
             stroke="currentColor"
             stroke-width="1.8"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true">

            <path d="M4 7h16M7 12h10M10 17h4"/>
        </svg>

        <span>Filters</span>
    </button>

    <nav class="tabs"
         aria-label="Browse by type">

        <a class="pill <?= $type === 'all' ? 'is-active' : '' ?>"
           href="?type=all">
            All
        </a>

        <a class="pill <?= $type === 'restaurant' ? 'is-active' : '' ?>"
           href="?type=restaurant">
            Restaurants
        </a>

        <a class="pill <?= $type === 'market' ? 'is-active' : '' ?>"
           href="?type=market">
            Supermarket
        </a>
    </nav>

    <form class="search"
          method="get"
          action="../Api/smart_search.php">

        <input type="hidden"
               name="type"
               value="<?= h($type) ?>">

        <input type="text"
               name="q"
               aria-label="Search products and meals"
               placeholder="Search for products or meals..."
               value="<?= h($query) ?>"
               autocomplete="off">

        <button type="submit">
            Search
        </button>
    </form>
</div>

<!-- ==========================================
     FILTERS AND PRODUCTS
     ========================================== -->

<div class="catalog-layout">

    <button type="button"
            class="filter-backdrop"
            id="filter-backdrop"
            aria-label="Close filters"
            hidden>
    </button>

    <!-- FILTER SIDEBAR -->
    <aside class="catalog-sidebar"
           role="dialog"
           aria-modal="true"
           aria-label="Filter products"
           hidden>

        <details class="filter"
                 id="product-filters">

            <summary class="filter-btn">

                <span class="filter-button-mark"></span>

                Filters
            </summary>

            <div class="filter-panel">

                <form method="get"
                      action="home.php">

                    <input type="hidden"
                           name="type"
                           value="<?= h($type) ?>">

                    <?php if ($query !== ''): ?>

                        <input type="hidden"
                               name="q"
                               value="<?= h($query) ?>">

                    <?php endif; ?>

                    <!-- FILTER HEADER -->
                    <div class="filter-title">

                        <div>
                            <h3>Filter products</h3>

                            <p>
                                Choose the filters that suit you.
                            </p>
                        </div>

                        <button type="button"
                                class="filter-close"
                                id="close-filters"
                                aria-label="Close filters">
                            &times;
                        </button>
                    </div>

                    <!-- ======================================
                         PRICE AND WEIGHT
                         ====================================== -->

                    <section class="filter-group filter-group--ranges">

                        <!-- PRICE -->
                        <div class="range-block">

                            <div class="filter-section-header">

                                <h4>Maximum price</h4>

                                <span class="range-value">

                                    <strong id="priceMaxLabel">
                                        <?= $pmax !== null
                                            ? (int)$pmax
                                            : 500 ?>
                                    </strong>

                                    DKK
                                </span>
                            </div>

                            <input type="hidden"
                                   name="pmin"
                                   value="0">

                            <div class="single-range">

                                <input class="single-range__input"
                                       id="priceMax"
                                       aria-label="Maximum price in DKK"
                                       type="range"
                                       name="pmax"
                                       min="0"
                                       max="500"
                                       step="1"
                                       value="<?= h(
                                           $pmax !== null
                                               ? max(
                                                   0,
                                                   min(500, $pmax)
                                               )
                                               : 500
                                       ) ?>">
                            </div>

                            <div class="range-scale">
                                <span>0 DKK</span>
                                <span>500 DKK</span>
                            </div>
                        </div>

                        <!-- WEIGHT -->
                        <div class="range-block">

                            <div class="filter-section-header">

                                <h4>Maximum weight</h4>

                                <span class="range-value">

                                    <strong id="weightMaxLabel">
                                        <?= $wmax !== null
                                            ? (int)$wmax
                                            : 5000 ?>
                                    </strong>

                                    g
                                </span>
                            </div>

                            <input type="hidden"
                                   name="wmin"
                                   value="0">

                            <div class="single-range">

                                <input class="single-range__input"
                                       id="weightMax"
                                       aria-label="Maximum weight in grams"
                                       type="range"
                                       name="wmax"
                                       min="0"
                                       max="5000"
                                       step="50"
                                       value="<?= h(
                                           $wmax !== null
                                               ? max(
                                                   0,
                                                   min(5000, $wmax)
                                               )
                                               : 5000
                                       ) ?>">
                            </div>

                            <div class="range-scale">
                                <span>0 g</span>
                                <span>5000 g</span>
                            </div>
                        </div>
                    </section>

                    <!-- ======================================
                         DIET
                         ====================================== -->

                    <section class="filter-group filter-group--choices">

                        <h4 class="group-title">
                            Diet
                        </h4>

                        <div class="diet-choice-grid">

                            <label class="filter-row filter-choice">

                                <input type="checkbox"
                                       name="veg"
                                       value="1"
                                       <?= $onlyVeg
                                           ? 'checked'
                                           : '' ?>>

                                <span>Vegetarian only</span>
                            </label>

                            <label class="filter-row filter-choice">

                                <input type="checkbox"
                                       name="meat"
                                       value="1"
                                       <?= $onlyMeat
                                           ? 'checked'
                                           : '' ?>>

                                <span>Meat products only</span>
                            </label>
                        </div>
                    </section>

                    <!-- ======================================
                         CLIMATE IMPACT
                         ====================================== -->

                    <section class="filter-group filter-group--choices">

                        <h4 class="group-title">
                            Climate impact
                        </h4>

                        <div class="climate-choice-grid">

                            <label class="filter-row climate-choice">

                                <input type="checkbox"
                                       name="lowco2"
                                       value="1"
                                       <?= $lowCO2
                                           ? 'checked'
                                           : '' ?>>

                                <span class="impact-dot impact-low"
                                      aria-hidden="true"></span>

                                <span>Green leaf</span>
                            </label>

                            <label class="filter-row climate-choice">

                                <input type="checkbox"
                                       name="midco2"
                                       value="1"
                                       <?= $midCO2
                                           ? 'checked'
                                           : '' ?>>

                                <span class="impact-dot impact-medium"
                                      aria-hidden="true"></span>

                                <span>Golden leaf</span>
                            </label>

                            <label class="filter-row climate-choice">

                                <input type="checkbox"
                                       name="highco2"
                                       value="1"
                                       <?= $highCO2
                                           ? 'checked'
                                           : '' ?>>

                                <span class="impact-dot impact-high"
                                      aria-hidden="true"></span>

                                <span>Earth leaf</span>
                            </label>
                        </div>
                    </section>

                    <!-- ======================================
                         SORT
                         ====================================== -->

                    <section class="filter-group filter-group--sort">

                        <h4 class="group-title">
                            Sort by
                        </h4>

                        <select class="sort-select"
                                name="sort">

                            <option value="eco"
                                <?= $sort === 'eco'
                                    ? 'selected'
                                    : '' ?>>
                                Eco first
                            </option>

                            <option value="price_asc"
                                <?= $sort === 'price_asc'
                                    ? 'selected'
                                    : '' ?>>
                                Price low → high
                            </option>

                            <option value="price_desc"
                                <?= $sort === 'price_desc'
                                    ? 'selected'
                                    : '' ?>>
                                Price high → low
                            </option>

                            <option value="newest"
                                <?= $sort === 'newest'
                                    ? 'selected'
                                    : '' ?>>
                                Newest
                            </option>
                        </select>
                    </section>

                    <!-- ======================================
                         FILTER ACTIONS
                         ====================================== -->

                    <div class="filter-actions">

                        <button class="apply-button"
                                type="submit">
                            Apply filters
                        </button>

                        <a class="reset-button"
                           href="home.php">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </details>
    </aside>

    <!-- ======================================
         PRODUCTS
         ====================================== -->

    <main class="container"
          id="products">

        <?php if (empty($items)): ?>

            <div class="no-results">

                <div class="no-results-mark"></div>

                <h3>No products found</h3>

                <p>
                    No products match your selected filters.
                </p>

                <a class="reset-button"
                   href="home.php">
                    Reset filters
                </a>
            </div>

        <?php else: ?>

            <div class="product-sections">

                <?php foreach ($productSections as $section): ?>

                    <?php if (!empty($section['items'])): ?>

                        <section class="product-section"
                                 id="<?= h($section['id']) ?>">

                            <header class="product-section-header">

                                <div>
                                    <h3>
                                        <?= h($section['title']) ?>
                                    </h3>

                                    <p>
                                        <?= h($section['description']) ?>
                                    </p>
                                </div>
                            </header>

                            <div class="grid-cards"
                                 aria-label="<?= h(
                                     $section['title']
                                 ) ?>">

                                <?php foreach (
                                    $section['items'] as $row
                                ): ?>

                                    <?php
                                    $productId = (int)$row['id'];

                                    $isFavorite = isset(
                                        $savedFavorites[$productId]
                                    );

                                    $sig = is_array(
                                        $row['_sig'] ?? null
                                    )
                                        ? $row['_sig']
                                        : [];

                                    $co2Signal = is_array(
                                        $sig['co2'] ?? null
                                    )
                                        ? $sig['co2']
                                        : [];

                                    $rank = (int)(
                                        $co2Signal['rank'] ?? 0
                                    );

                                    $co2Class = match ($rank) {
                                        1 => 'low',
                                        2 => 'medium',
                                        3 => 'high',
                                        default => '',
                                    };

                                    $dotClass = match ($rank) {
                                        1 => 'impact-low',
                                        2 => 'impact-medium',
                                        3 => 'impact-high',
                                        default => '',
                                    };

                                    $climateLabel =
                                        'Climate impact';

                                    $climate = climate_metrics(
                                        $row,
                                        $sig,
                                        $rank
                                    );

                                    $instantContext =
                                        $instantComparisons->context(
                                            $climate,
                                            $productId
                                        );

                                    $instantText =
                                        $instantComparisons->read(
                                            $instantContext
                                        )['text'];

                                    $productUrl =
                                        'product.php?id=' .
                                        $productId;

                                    $tooltipId =
                                        'climate-tip-' .
                                        $section['id'] .
                                        '-' .
                                        $productId;

                                    $weight =
                                        $sig['weight_g'] ?? null;

                                    $titleHasWeight = preg_match(
                                        '/\d+(?:[.,]\d+)?\s*(?:kg|g|ml|l)\b/i',
                                        (string)$row['title']
                                    ) === 1;
                                    ?>

                                    <article class="card">

                                        <!-- WISHLIST HEART -->
                                        <form class="card-favourite add-to-fav"
                                              action="../Api/AddFav.php"
                                              method="post">

                                            <input type="hidden"
                                                   name="product_id"
                                                   value="<?= $productId ?>">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= h(
                                                       csrfToken()
                                                   ) ?>">

                                            <input type="hidden"
                                                   name="wishlist_action"
                                                   value="<?= $isFavorite
                                                       ? 'remove'
                                                       : 'add' ?>">

                                            <button type="submit"
                                                    class="<?= $isFavorite
                                                        ? 'is-saved'
                                                        : '' ?>"
                                                    aria-pressed="<?= $isFavorite
                                                        ? 'true'
                                                        : 'false' ?>"
                                                    aria-label="<?= $isFavorite
                                                        ? 'Remove from wishlist'
                                                        : 'Add to wishlist' ?>">

                                                <svg viewBox="0 0 24 24"
                                                     fill="none"
                                                     xmlns="http://www.w3.org/2000/svg"
                                                     aria-hidden="true">

                                                    <path
                                                        d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"
                                                        stroke="currentColor"
                                                        stroke-width="1.8"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </form>

                                        <!-- PRODUCT IMAGE -->
                                        <a class="product-visual"
                                           href="<?= h($productUrl) ?>"
                                           aria-label="View details for <?= h(
                                               $row['title']
                                           ) ?>">

                                            <?php if (
                                                !empty($row['image'])
                                            ): ?>

                                                <img
                                                    src="../Style/Images/products/<?= h(
                                                        $row['image']
                                                    ) ?>"
                                                    alt="<?= h(
                                                        $row['title']
                                                    ) ?>"
                                                    class="product-image"
                                                    loading="lazy">

                                            <?php else: ?>

                                                <div class="product-placeholder">
                                                    <?= h(
                                                        product_initial(
                                                            (string)$row['title']
                                                        )
                                                    ) ?>
                                                </div>

                                            <?php endif; ?>
                                        </a>

                                        <!-- PRODUCT CONTENT -->
                                        <div class="product-content">

                                            <h4 class="product-title-row">

                                                <a class="product-name-link"
                                                   href="<?= h(
                                                       $productUrl
                                                   ) ?>">

                                                    <?= h(
                                                        $row['title']
                                                    ) ?>
                                                </a>
                                            </h4>

                                            <div class="merchant-name">
                                                <?= h(
                                                    $row['merchant']
                                                ) ?>
                                            </div>

                                            <div class="price">
                                                <?= number_format(
                                                    (float)$row['price'],
                                                    2,
                                                    ',',
                                                    '.'
                                                ) ?> kr.
                                            </div>

                                            <?php if (
                                                $weight !== null &&
                                                !$titleHasWeight
                                            ): ?>

                                                <div class="product-meta">

                                                    <span>
                                                        <?= (int)$weight ?> g
                                                    </span>
                                                </div>

                                            <?php endif; ?>

                                            <!-- CLIMATE INFORMATION -->
                                            <div class="climate-summary <?= h(
                                                $co2Class
                                            ) ?>"
                                                 aria-label="CO₂e emissions">

                                                <span class="climate-label">

                                                    <span class="impact-dot <?= h(
                                                        $dotClass
                                                    ) ?>"
                                                          aria-hidden="true">
                                                    </span>

                                                    <?= h(
                                                        $climateLabel
                                                    ) ?>
                                                </span>

                                                <?php if (
                                                    $climate['available']
                                                ): ?>

                                                    <div class="home-climate-value">

                                                        <strong>
                                                            <?= h(
                                                                $climate[
                                                                    'formatted'
                                                                ]
                                                            ) ?>

                                                            kg CO₂e

                                                            <span class="climate-unit">
                                                                <?= h(
                                                                    $climate[
                                                                        'unit'
                                                                    ]
                                                                ) ?>
                                                            </span>
                                                        </strong>

                                                        <span class="home-climate-help">

                                                            <button
                                                                type="button"
                                                                class="home-climate-help-button"
                                                                data-product-id="<?= $productId ?>"
                                                                aria-label="Explain CO₂e for <?= h(
                                                                    $row['title']
                                                                ) ?>"
                                                                aria-expanded="false"
                                                                aria-describedby="<?= h(
                                                                    $tooltipId
                                                                ) ?>">
                                                                ⓘ
                                                            </button>

                                                            <span
                                                                class="home-climate-tooltip"
                                                                role="tooltip"
                                                                hidden
                                                                id="<?= h(
                                                                    $tooltipId
                                                                ) ?>">

                                                                <?= h(
                                                                    $instantText
                                                                ) ?>
                                                            </span>
                                                        </span>
                                                    </div>

                                                    <?php if (
                                                        $climate['is_example']
                                                    ): ?>

                                                        <small class="example-note">
                                                            Example value
                                                        </small>

                                                    <?php endif; ?>

                                                <?php else: ?>

                                                    <small>
                                                        Information not available
                                                    </small>

                                                <?php endif; ?>
                                            </div>

                                            <!-- ADD TO CART -->
                                            <form class="home-add-to-cart"
                                                  action="../Api/add_to_cart.php"
                                                  method="post">

                                                <input type="hidden"
                                                       name="product_id"
                                                       value="<?= $productId ?>">

                                                <input type="hidden"
                                                       name="qty"
                                                       value="1">

                                                <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?= h(
                                                           csrfToken()
                                                       ) ?>">

                                                <button class="apply-button"
                                                        type="submit">

                                                    <svg width="18"
                                                         height="18"
                                                         viewBox="0 0 24 24"
                                                         fill="none"
                                                         stroke="currentColor"
                                                         stroke-width="1.8"
                                                         stroke-linecap="round"
                                                         stroke-linejoin="round"
                                                         aria-hidden="true">

                                                        <path d="M3 3h2l3 12h11l2-8H6"/>

                                                        <circle cx="9"
                                                                cy="20"
                                                                r="1"/>

                                                        <circle cx="18"
                                                                cy="20"
                                                                r="1"/>
                                                    </svg>

                                                    <span class="home-cart-button-label">
                                                        Add to cart
                                                    </span>
                                                </button>

                                                <p class="home-cart-message"
                                                   aria-live="polite">
                                                </p>
                                            </form>
                                        </div>
                                    </article>

                                <?php endforeach; ?>
                            </div>
                        </section>

                    <?php endif; ?>

                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </main>
</div>

<!-- ==========================================
     FOOTER
     ========================================== -->

<footer id="about"
        class="site-footer">

    <h2>About us</h2>

    <p>
        Flash Food is a grocery shopping prototype with climate
        information for products and meals.
    </p>
</footer>

</body>
</html>