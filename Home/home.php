<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';
require_once __DIR__ . '/../Api/catalog.php';
require_once __DIR__ . '/../Api/climate_metrics.php';
require_once __DIR__ . '/../Api/instant_comparisons.php';

$instantComparisons = new FlashFoodInstantComparisons($conn);
$loggedInUser = currentUser();
$filters = parse_filters($_GET);
$items = get_catalog_items($conn, $_GET);
$cart_count = flashfood_application($conn)->cart()->count();

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
$localOnly = $filters['localOnly'];
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
        'description' => 'Groceries with low, medium and high CO₂e emissions.',
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flash Food</title>

    <link rel="stylesheet" href="../Style/HomeStyle.css?v=20261031">
    <script src="../Js/AddToFav.js" defer></script>
    <script src="../Js/Home.js?v=20261031" defer></script>
</head>

<body>

<!-- HEADER -->
<header class="site-header">
    <div class="topbar">
        <a class="brand" href="./home.php">
            <span class="brand-mark" aria-hidden="true"></span>
            <span>Flash Food</span>
        </a>

        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-link active" href="./home.php">Home</a>
            <a class="nav-link" href="#about">About us</a>
        </nav>

        <div class="header-actions">
            <a class="header-link" href="./fav.php"
               id="wishlist-link" aria-label="Wishlist">
                <svg id="wishlist-icon" class="header-action-icon"
                     xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>
                </svg>
                <span>Wishlist</span>
            </a>

            <a class="header-link" href="./inkobLister.php"
               id="cart-link" aria-label="Cart">
                <svg id="cart-icon" class="header-action-icon"
                     xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 3h2l3 12h11l2-8H6"/>
                    <circle cx="9" cy="20" r="1"/>
                    <circle cx="18" cy="20" r="1"/>
                </svg>
                <span>Cart</span>
                <span class="cart-count"><?= (int)$cart_count ?></span>
            </a>

            <a class="header-link" href="../profil/profil.php"
               id="profile-link" aria-label="Profile">
                <svg id="profile-icon" class="header-action-icon"
                     xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="8" r="4"/>
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

<!-- HERO -->
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

<!-- TYPE TABS -->
<div class="tabs">
    <a class="pill <?= $type === 'all' ? 'is-active' : '' ?>"
       href="?type=all">All</a>

    <a class="pill <?= $type === 'restaurant' ? 'is-active' : '' ?>"
       href="?type=restaurant">Restaurants</a>

    <a class="pill <?= $type === 'market' ? 'is-active' : '' ?>"
       href="?type=market">Supermarket</a>
</div>

<!-- SEARCH -->
<section class="search-wrap">
    <form class="search" method="get"
          action="../Api/smart_search.php">

        <input type="hidden" name="type" value="<?= h($type) ?>">

        <button type="button"
                class="home-filter-toggle"
                id="home-filter-toggle"
                aria-label="Show filters"
                aria-controls="product-filters"
                aria-expanded="false"
                title="Filters">
            <svg width="22" height="22" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round"
                 aria-hidden="true">
                <path d="M4 6h9m4 0h3M4 12h3m4 0h9M4 18h9m4 0h3"/>
                <circle cx="15" cy="6" r="2"/>
                <circle cx="9" cy="12" r="2"/>
                <circle cx="15" cy="18" r="2"/>
            </svg>
        </button>

        <input type="text"
               name="q"
               aria-label="Search products and meals"
               placeholder="Search for products or meals..."
               value="<?= h($query) ?>"
               autocomplete="off">

        <button type="submit">Search</button>
    </form>
</section>

<!-- FILTERS AND PRODUCTS -->
<div class="catalog-layout">

    <!-- FILTER SIDEBAR -->
    <aside class="catalog-sidebar">
        <details class="filter" id="product-filters">
            <summary class="filter-btn">
                <span class="filter-button-mark"></span>
                Filters
            </summary>

            <div class="filter-panel">
                <form method="get" action="home.php">
                    <input type="hidden" name="type" value="<?= h($type) ?>">

                    <?php if ($query !== ''): ?>
                        <input type="hidden" name="q" value="<?= h($query) ?>">
                    <?php endif; ?>

                    <div class="filter-title">
                        <h3>Filter products</h3>
                        <p>Adjust the sliders or choose categories below.</p>
                    </div>

                    <!-- PRICE -->
                    <section class="filter-group">
                        <div class="filter-section-header">
                            <h4>Price (DKK)</h4>
                            <span class="range-value">
                                <strong id="priceMinLabel">
                                    <?= $pmin !== null ? (int)$pmin : 0 ?>
                                </strong>
                                <span>–</span>
                                <strong id="priceMaxLabel">
                                    <?= $pmax !== null ? (int)$pmax : 500 ?>
                                </strong>
                                DKK
                            </span>
                        </div>

                        <div class="dual-range" id="priceRange">
                            <div class="dual-range__track"></div>

                            <input
                                class="dual-range__input dual-range__input--min"
                                id="priceMin"
                                aria-label="Minimum price in DKK"
                                type="range"
                                name="pmin"
                                min="0"
                                max="500"
                                step="1"
                                value="<?= h($pmin !== null
                                    ? max(0, min(500, $pmin))
                                    : 0) ?>">

                            <input
                                class="dual-range__input dual-range__input--max"
                                id="priceMax"
                                aria-label="Maximum price in DKK"
                                type="range"
                                name="pmax"
                                min="0"
                                max="500"
                                step="1"
                                value="<?= h($pmax !== null
                                    ? max(0, min(500, $pmax))
                                    : 500) ?>">
                        </div>

                        <div class="range-scale">
                            <span>0 DKK</span>
                            <span>500 DKK</span>
                        </div>
                    </section>

                    <!-- WEIGHT -->
                    <section class="filter-group">
                        <div class="filter-section-header">
                            <h4>Weight (g)</h4>
                            <span class="range-value">
                                <strong id="weightMinLabel">
                                    <?= $wmin !== null ? (int)$wmin : 0 ?>
                                </strong>
                                <span>–</span>
                                <strong id="weightMaxLabel">
                                    <?= $wmax !== null ? (int)$wmax : 5000 ?>
                                </strong>
                                g
                            </span>
                        </div>

                        <div class="dual-range" id="weightRange">
                            <div class="dual-range__track"></div>

                            <input
                                class="dual-range__input dual-range__input--min"
                                id="weightMin"
                                aria-label="Minimum weight in grams"
                                type="range"
                                name="wmin"
                                min="0"
                                max="5000"
                                step="50"
                                value="<?= h($wmin !== null
                                    ? max(0, min(5000, $wmin))
                                    : 0) ?>">

                            <input
                                class="dual-range__input dual-range__input--max"
                                id="weightMax"
                                aria-label="Maximum weight in grams"
                                type="range"
                                name="wmax"
                                min="0"
                                max="5000"
                                step="50"
                                value="<?= h($wmax !== null
                                    ? max(0, min(5000, $wmax))
                                    : 5000) ?>">
                        </div>

                        <div class="range-scale">
                            <span>0 g</span>
                            <span>5000 g</span>
                        </div>
                    </section>

                    <!-- DIET -->
                    <section class="filter-group">
                        <h4 class="group-title">Diet</h4>

                        <label class="filter-row">
                            <input type="checkbox"
                                   name="veg"
                                   value="1"
                                   <?= $onlyVeg ? 'checked' : '' ?>>
                            <span>Vegetarian only</span>
                        </label>

                        <label class="filter-row">
                            <input type="checkbox"
                                   name="meat"
                                   value="1"
                                   <?= $onlyMeat ? 'checked' : '' ?>>
                            <span>Meat products only</span>
                        </label>
                    </section>

                    <!-- CLIMATE FILTERS -->
                    <section class="filter-group">
                        <h4 class="group-title">CO₂e emissions</h4>

                        <label class="filter-row">
                            <input type="checkbox"
                                   name="lowco2"
                                   value="1"
                                   <?= $lowCO2 ? 'checked' : '' ?>>

                            <span class="impact-dot impact-low"></span>

                            <span class="impact-filter-copy">
                                <strong>Low CO₂e emissions</strong>
                                <small>
                                    Up to <?= h((string)CO2_LOW_MAX) ?>
                                    kg CO₂e/kg (prototype category)
                                </small>
                            </span>
                        </label>

                        <label class="filter-row">
                            <input type="checkbox"
                                   name="midco2"
                                   value="1"
                                   <?= $midCO2 ? 'checked' : '' ?>>

                            <span class="impact-dot impact-medium"></span>

                            <span class="impact-filter-copy">
                                <strong>Medium CO₂e emissions</strong>
                                <small>
                                    Above <?= h((string)CO2_LOW_MAX) ?>
                                    and up to <?= h((string)CO2_MEDIUM_MAX) ?>
                                    kg CO₂e/kg (prototype category)
                                </small>
                            </span>
                        </label>

                        <label class="filter-row">
                            <input type="checkbox"
                                   name="highco2"
                                   value="1"
                                   <?= $highCO2 ? 'checked' : '' ?>>

                            <span class="impact-dot impact-high"></span>

                            <span class="impact-filter-copy">
                                <strong>High CO₂e emissions</strong>
                                <small>
                                    Above <?= h((string)CO2_MEDIUM_MAX) ?>
                                    kg CO₂e/kg (prototype category)
                                </small>
                            </span>
                        </label>

                        <p class="climate-filter-note">
                            Prototype categories use emissions per kg.
                            Meal values are displayed per serving or box.
                        </p>

                        <label class="filter-row">
                            <input type="checkbox"
                                   name="local"
                                   value="1"
                                   <?= $localOnly ? 'checked' : '' ?>>
                            <span>Local</span>
                        </label>
                    </section>

                    <!-- SORT -->
                    <section class="filter-group">
                        <h4 class="group-title">Sort by</h4>

                        <select class="sort-select" name="sort">
                            <option value="eco"
                                <?= $sort === 'eco' ? 'selected' : '' ?>>
                                Eco first
                            </option>

                            <option value="price_asc"
                                <?= $sort === 'price_asc' ? 'selected' : '' ?>>
                                Price low → high
                            </option>

                            <option value="price_desc"
                                <?= $sort === 'price_desc' ? 'selected' : '' ?>>
                                Price high → low
                            </option>

                            <option value="newest"
                                <?= $sort === 'newest' ? 'selected' : '' ?>>
                                Newest
                            </option>
                        </select>
                    </section>

                    <div class="filter-actions">
                        <button class="apply-button" type="submit">
                            Apply filters
                        </button>

                        <a class="reset-button" href="home.php">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </details>
    </aside>

    <!-- PRODUCTS -->
    <main class="container" id="products">
        <div class="section-heading">
            <div></div>

            <p class="result-count">
                <?= count($items) ?>
                <?= count($items) === 1 ? 'result' : 'results' ?>
            </p>
        </div>

        <?php if (empty($items)): ?>

            <div class="no-results">
                <div class="no-results-mark"></div>
                <h3>No products found</h3>
                <p>No products match your selected filters.</p>
                <a class="reset-button" href="home.php">
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
                                    <h3><?= h($section['title']) ?></h3>
                                    <p><?= h($section['description']) ?></p>
                                </div>

                                <span class="section-count">
                                    <?= count($section['items']) ?>
                                    <?= count($section['items']) === 1
                                        ? 'product'
                                        : 'products' ?>
                                </span>
                            </header>

                            <div class="grid-cards"
                                 aria-label="<?= h($section['title']) ?>">

                                <?php foreach ($section['items'] as $row): ?>
                                    <?php
                                    $sig = is_array($row['_sig'] ?? null)
                                        ? $row['_sig']
                                        : [];

                                    $co2Signal = is_array($sig['co2'] ?? null)
                                        ? $sig['co2']
                                        : [];

                                    $rank = (int)($co2Signal['rank'] ?? 0);

                                    $co2Class = match ($rank) {
                                        1 => 'low',
                                        2 => 'medium',
                                        3 => 'high',
                                        default => '',
                                    };

                                    $climateLabel = match ($rank) {
                                        1 => 'Low CO₂e emissions',
                                        2 => 'Medium CO₂e emissions',
                                        3 => 'High CO₂e emissions',
                                        default => 'CO₂e emissions',
                                    };

                                    $dotClass = match ($rank) {
                                        1 => 'impact-low',
                                        2 => 'impact-medium',
                                        3 => 'impact-high',
                                        default => '',
                                    };

                                    $climate = climate_metrics(
                                        $row,
                                        $sig,
                                        $rank
                                    );

                                    $instantContext = $instantComparisons
                                        ->context($climate, (int)$row['id']);

                                    $instantText = $instantComparisons
                                        ->read($instantContext)['text'];

                                    $productUrl = 'product.php?id='
                                        . (int)$row['id'];

                                    $tooltipId = 'climate-tip-'
                                        . $section['id']
                                        . '-'
                                        . (int)$row['id'];

                                    $weight = $sig['weight_g'] ?? null;

                                    $titleHasWeight = preg_match(
                                        '/\d+(?:[.,]\d+)?\s*(?:kg|g|ml|l)\b/i',
                                        (string)$row['title']
                                    ) === 1;
                                    ?>

                                    <article class="card">

                                        <!-- FAVOURITE -->
                                        <form class="card-favourite add-to-fav"
                                              action="../Api/AddFav.php"
                                              method="post">

                                            <input type="hidden"
                                                   name="product_id"
                                                   value="<?= (int)$row['id'] ?>">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= h(csrfToken()) ?>">

                                            <button type="submit"
                                                    aria-label="Add to wishlist">
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
                                           aria-label="View details for <?= h($row['title']) ?>">

                                            <?php if (!empty($row['image'])): ?>
                                                <img
                                                    src="../Style/Images/products/<?= h($row['image']) ?>"
                                                    alt="<?= h($row['title']) ?>"
                                                    class="product-image">
                                            <?php else: ?>
                                                <div class="product-placeholder">
                                                    <?= h(product_initial(
                                                        (string)$row['title']
                                                    )) ?>
                                                </div>
                                            <?php endif; ?>
                                        </a>

                                        <div class="product-content">

                                            <h4 class="product-title-row">
                                                <a class="product-name-link"
                                                   href="<?= h($productUrl) ?>">
                                                    <?= h($row['title']) ?>
                                                </a>
                                            </h4>

                                            <div class="merchant-name">
                                                <?= h($row['merchant']) ?>
                                            </div>

                                            <div class="price">
                                                <?= number_format(
                                                    (float)$row['price'],
                                                    2,
                                                    ',',
                                                    '.'
                                                ) ?> kr.
                                            </div>

                                            <!-- PRODUCT METADATA -->
                                            <?php if (
                                                ($weight !== null && !$titleHasWeight)
                                                || !empty($sig['local'])
                                            ): ?>
                                                <div class="product-meta">
                                                    <?php if (
                                                        $weight !== null
                                                        && !$titleHasWeight
                                                    ): ?>
                                                        <span>
                                                            <?= (int)$weight ?> g
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($sig['local'])): ?>
                                                        <span class="sig local">
                                                            Local
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- CLIMATE INFORMATION -->
                                            <div class="climate-summary <?= h($co2Class) ?>"
                                                 aria-label="CO₂e emissions">

                                                <span class="climate-label">
                                                    <span
                                                        class="impact-dot <?= h($dotClass) ?>"
                                                        aria-hidden="true"></span>
                                                    <?= h($climateLabel) ?>
                                                </span>

                                                <?php if ($climate['available']): ?>
                                                    <div class="home-climate-value">
                                                        <strong>
                                                            <?= h($climate['formatted']) ?>
                                                            kg CO₂e
                                                            <span class="climate-unit">
                                                                <?= h($climate['unit']) ?>
                                                            </span>
                                                        </strong>

                                                        <span class="home-climate-help">
                                                            <button
                                                                type="button"
                                                                class="home-climate-help-button"
                                                                data-product-id="<?= (int)$row['id'] ?>"
                                                                aria-label="Explain CO₂e for <?= h($row['title']) ?>"
                                                                aria-expanded="false"
                                                                aria-describedby="<?= h($tooltipId) ?>">
                                                                ⓘ
                                                            </button>

                                                            <span
                                                                class="home-climate-tooltip"
                                                                role="tooltip"
                                                                hidden
                                                                id="<?= h($tooltipId) ?>">
                                                                <?= h($instantText) ?>
                                                            </span>
                                                        </span>
                                                    </div>

                                                    <?php if ($climate['is_example']): ?>
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
                                                       value="<?= (int)$row['id'] ?>">

                                                <input type="hidden"
                                                       name="qty"
                                                       value="1">

                                                <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?= h(csrfToken()) ?>">

                                                <button class="apply-button"
                                                        type="submit">
                                                    &#128722;
                                                    <span class="home-cart-button-label">
                                                        Add to cart
                                                    </span>
                                                </button>

                                                <p class="home-cart-message"
                                                   aria-live="polite"></p>
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

</body>
</html>
