<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';
require_once __DIR__ . '/../Api/catalog.php';
require_once __DIR__ . '/../Api/climate_metrics.php';

$loggedInUser = currentUser();

$filters = parse_filters($_GET);
$items = get_catalog_items($conn, $_GET);

/* Kurv */
$cart_count = 0;

foreach ($_SESSION['cart'] ?? [] as $item) {
    $cart_count += (int)($item['qty'] ?? 0);
}

/* Filterværdier */
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

/* Sikker visning af tekst */
function h(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/* Placeholder, hvis produktet mangler et billede */
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

/*
 * Climate information for the selected "Somewhat interested" prototype.
 * Real CO2e values are used when catalog.php provides one of the supported
 * numeric fields. Rank-based fallback values are explicitly marked as examples.
 */
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

/*
 * Wolt-style rows. Supermarket products are not restricted by CO2 rank,
 * so low, medium and high impact products all remain available.
 */
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
        'description' => 'Groceries with lower, medium and higher climate impact.',
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Flash Food</title>

    <link
        rel="stylesheet"
        href="../Style/HomeStyle.css?v=home-complete-2"
    >

<script
        src="../Js/AddToFav.js"
        defer
    ></script>

    <script
        src="../Js/home.js?v=home-complete-2"
        defer
    ></script>
    
    
<style>
/* Catalog and filter */
.catalog-layout {
    width: min(1380px, calc(100% - 32px));
    margin: 0 auto;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: start;
    gap: 22px;
}

.catalog-sidebar {
    position: sticky;
    top: 90px;
    z-index: 20;
    width: max-content;
    max-width: 320px;
}

.filter {
    width: 110px;
    transition: width 0.22s ease;
}

.filter[open] {
    width: 320px;
}

.filter summary {
    list-style: none;
}

.filter summary::-webkit-details-marker {
    display: none;
}

.filter-btn {
    width: 100%;
    min-height: 48px;
    padding: 0 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    border: 1px solid var(--border);
    border-radius: 13px;
    color: #26342b;
    background: #fff;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(25, 55, 31, 0.05);
}

.filter-button-mark {
    width: 12px;
    height: 12px;
    display: block;
    border: 2px solid var(--green);
    border-radius: 3px;
}

.filter-panel {
    width: 100%;
    max-height: calc(100vh - 155px);
    margin-top: 10px;
    padding: 18px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: #fff;
    box-shadow: var(--shadow);
}

.filter-title {
    padding-bottom: 14px;
    border-bottom: 1px solid #edf1ee;
}

.filter-title h3 {
    margin: 0 0 5px;
    font-size: 18px;
}

.filter-title p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}

.filter-group {
    margin-top: 15px;
    padding: 15px;
    border: 1px solid #e3eae4;
    border-radius: 14px;
    background: #fbfcfb;
}

.group-title {
    position: relative;
    margin: 0 0 12px;
    padding-left: 11px;
    color: #26342b;
    font-size: 12px;
    font-weight: 850;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.group-title::before {
    content: "";
    position: absolute;
    top: 1px;
    left: 0;
    width: 3px;
    height: 14px;
    border-radius: 999px;
    background: var(--green);
}

.filter-row {
    min-height: 38px;
    padding: 5px 3px;
    display: flex;
    align-items: center;
    gap: 9px;
    color: #28352d;
    font-size: 13px;
    cursor: pointer;
}

.filter-row input[type="checkbox"] {
    width: 17px;
    height: 17px;
    flex: 0 0 17px;
    accent-color: var(--green);
    cursor: pointer;
}

.impact-filter-copy {
    display: grid;
    gap: 2px;
}

.impact-filter-copy small,
.climate-filter-note {
    color: #647269;
    font-size: 0.78rem;
    line-height: 1.35;
}

.climate-filter-note {
    margin: 10px 0;
}

.impact-dot {
    width: 10px;
    height: 10px;
    flex: 0 0 10px;
    display: inline-block;
    border-radius: 50%;
}

.impact-low {
    background: var(--low);
}

.impact-medium {
    background: var(--medium);
}

.impact-high {
    background: var(--high);
}

.filter-section-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
}

.filter-section-header h4 {
    margin: 0;
    color: #26342b;
    font-size: 12px;
    font-weight: 850;
    text-transform: uppercase;
}

.range-value {
    color: #237239;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.dual-range {
    --min-pos: 0%;
    --max-pos: 100%;
    position: relative;
    width: 100%;
    height: 40px;
    margin-top: 8px;
}

.dual-range__track {
    position: absolute;
    top: 50%;
    right: 0;
    left: 0;
    height: 5px;
    transform: translateY(-50%);
    border-radius: 999px;
    background: linear-gradient(
        to right,
        #dae2db 0 var(--min-pos),
        var(--green) var(--min-pos) var(--max-pos),
        #dae2db var(--max-pos) 100%
    );
}

.dual-range__input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 40px;
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    pointer-events: none;
    appearance: none;
    -webkit-appearance: none;
}

.dual-range__input::-webkit-slider-runnable-track {
    height: 5px;
    background: transparent;
}

.dual-range__input::-webkit-slider-thumb {
    width: 19px;
    height: 19px;
    margin-top: -7px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 1px 5px rgba(0, 0, 0, 0.22);
    pointer-events: auto;
    cursor: grab;
    appearance: none;
    -webkit-appearance: none;
}

.dual-range__input::-moz-range-track {
    height: 5px;
    background: transparent;
}

.dual-range__input::-moz-range-thumb {
    width: 17px;
    height: 17px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: var(--green);
    pointer-events: auto;
    cursor: grab;
}

.range-scale {
    margin-top: -2px;
    display: flex;
    justify-content: space-between;
    color: #7b847d;
    font-size: 10px;
}

.sort-select {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    background: #fff;
    outline: none;
}

.sort-select:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(47, 143, 70, 0.1);
}

.filter-actions {
    margin-top: 15px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.apply-button,
.reset-button {
    min-height: 42px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.apply-button {
    border: 0;
    color: #fff;
    background: var(--green);
}

.apply-button:hover {
    background: var(--green-dark);
}

.reset-button {
    border: 1px solid var(--border);
    color: #344139;
    background: #fff;
}


/* Search icon and enhanced filter layout */
.search #home-filter-toggle {
    order: -1;
    width: 44px;
    height: 40px;
    padding: 0;
    margin: 0 8px 0 0;
    flex: 0 0 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 8px;
    color: var(--green, #2f8f46);
    background: transparent;
    cursor: pointer;
}
.search #home-filter-toggle:hover,
.search #home-filter-toggle[aria-expanded="true"] {
    background: #eef8f0;
}
.search #home-filter-toggle:focus-visible {
    outline: 2px solid var(--green, #2f8f46);
    outline-offset: 2px;
}
.catalog-layout.filters-enhanced .filter > summary { display: none; }
.catalog-layout.filters-enhanced .catalog-sidebar[hidden] { display: none; }
.catalog-layout.filters-closed { grid-template-columns: minmax(0, 1fr); }
.catalog-layout.filters-enhanced .catalog-sidebar {
    width: 320px;
    max-width: 100%;
    min-width: 0;
}
.catalog-layout.filters-enhanced .filter { width: 100%; }
.catalog-layout.filters-enhanced .filter-panel {
    position: static;
    inset: auto;
    width: 100%;
    margin-top: 0;
}
@media (max-width: 1000px) {
    .catalog-layout { grid-template-columns: minmax(0, 1fr); }
    .catalog-layout .catalog-sidebar {
        position: static;
        width: 100%;
        max-width: 100%;
    }
    .catalog-layout .filter,
    .catalog-layout .filter[open] { width: 100%; }
    .catalog-layout .filter-panel { max-height: none; overflow: visible; }
}

.home-add-to-cart { margin-top: 12px; }
.home-add-to-cart .apply-button { width: 100%; gap: 8px; }
.home-add-to-cart .apply-button:disabled { opacity: .65; cursor: wait; }
.home-cart-message { margin: 8px 0 0; font-size: 13px; line-height: 1.4; }
.home-cart-message:empty { display: none; }
.home-cart-message[data-state="error"] { color: #a12323; }
.home-cart-message[data-state="success"] { color: #246f36; }

</style>
</head>

<body>

<!-- HEADER -->
<header class="site-header">
    <div class="topbar">

        <a class="brand" href="./home.php">
            <span
                class="brand-mark"
                aria-hidden="true"
            ></span>

            <span>Flash Food</span>
        </a>

        <nav class="main-nav" aria-label="Main navigation">
            <a
                class="nav-link active"
                href="./home.php"
            >
                Home
            </a>

    
            <a class="nav-link" href="#about">
                About us
            </a>
        </nav>
        <div class="header-actions">
            <a class="header-link" href="./fav.php" id="wishlist-link" aria-label="Wishlist">
                <svg id="wishlist-icon" class="header-action-icon" xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z" />
                </svg>
                <span>Wishlist</span>
            </a>
            <a class="header-link" href="./inkobLister.php" id="cart-link" aria-label="Cart">
                <svg id="cart-icon" class="header-action-icon" xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M3 3h2l3 12h11l2-8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />
                </svg>
                <span>Cart</span>
                <span class="cart-count"><?= $cart_count ?></span>
            </a>
            <a class="header-link" href="../profil/profil.php" id="profile-link" aria-label="Profile">
                <svg id="profile-icon" class="header-action-icon" xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="8" r="4" /><path d="M4 21v-2a8 8 0 0 1 16 0v2" />
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
    <a
        class="pill <?= $type === 'all' ? 'is-active' : '' ?>"
        href="?type=all"
    >
        All
    </a>

    <a
        class="pill <?= $type === 'restaurant' ? 'is-active' : '' ?>"
        href="?type=restaurant"
    >
        Restaurants
    </a>

    <a
        class="pill <?= $type === 'market' ? 'is-active' : '' ?>"
        href="?type=market"
    >
        Supermarket
    </a>
</div>

<!-- SEARCH -->
<section class="search-wrap">
    <form
        class="search"
        method="get"
        action="../Api/smart_search.php"
    >
        <input
            type="hidden"
            name="type"
            value="<?= h($type) ?>"
        >

       <button
    type="button"
    class="home-filter-toggle"
    id="home-filter-toggle"
    aria-label="Show filters"
    aria-controls="product-filters"
    aria-expanded="false"
    title="Filters"
>
    <svg
        width="22"
        height="22"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        aria-hidden="true"
    >
        <path d="M4 6h9m4 0h3M4 12h3m4 0h9M4 18h9m4 0h3"/>
        <circle cx="15" cy="6" r="2"/>
        <circle cx="9" cy="12" r="2"/>
        <circle cx="15" cy="18" r="2"/>
    </svg>
</button>

        <input
            type="text"
            name="q"
            aria-label="Search products and meals"
            placeholder="Search for products or meals..."
            value="<?= h($query) ?>"
            autocomplete="off"
        >

<button type="submit">Search</button>
    </form>
</section>

<!-- FILTERS + PRODUCTS -->
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

                    <input
                        type="hidden"
                        name="type"
                        value="<?= h($type) ?>"
                    >

                    <?php if ($query !== ''): ?>
                        <input
                            type="hidden"
                            name="q"
                            value="<?= h($query) ?>"
                        >
                    <?php endif; ?>

                    <div class="filter-title">
                        <h3>Filter products</h3>

                        <p>
                            Adjust the sliders or
                            choose categories below.
                        </p>
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
                                value="<?= h(
                                    $pmin !== null
                                        ? max(0, min(500, $pmin))
                                        : 0
                                ) ?>"
                            >

                            <input
                                class="dual-range__input dual-range__input--max"
                                id="priceMax"
                                aria-label="Maximum price in DKK"
                                type="range"
                                name="pmax"
                                min="0"
                                max="500"
                                step="1"
                                value="<?= h(
                                    $pmax !== null
                                        ? max(0, min(500, $pmax))
                                        : 500
                                ) ?>"
                            >
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
                                value="<?= h(
                                    $wmin !== null
                                        ? max(0, min(5000, $wmin))
                                        : 0
                                ) ?>"
                            >

                            <input
                                class="dual-range__input dual-range__input--max"
                                id="weightMax"
                                aria-label="Maximum weight in grams"
                                type="range"
                                name="wmax"
                                min="0"
                                max="5000"
                                step="50"
                                value="<?= h(
                                    $wmax !== null
                                        ? max(0, min(5000, $wmax))
                                        : 5000
                                ) ?>"
                            >
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
                            <input
                                type="checkbox"
                                name="veg"
                                value="1"
                                <?= $onlyVeg ? 'checked' : '' ?>
                            >

                            <span>Vegetarian only</span>
                        </label>

                        <label class="filter-row">
                            <input
                                type="checkbox"
                                name="meat"
                                value="1"
                                <?= $onlyMeat ? 'checked' : '' ?>
                            >

                            <span>Meat products only</span>
                        </label>
                    </section>

                    <!-- CLIMATE -->
                    <section class="filter-group">
                        <h4 class="group-title">
                            Climate impact
                        </h4>

                        <label class="filter-row">
                            <input
                                type="checkbox"
                                name="lowco2"
                                value="1"
                                <?= $lowCO2 ? 'checked' : '' ?>
                            >

                            <span class="impact-dot impact-low"></span>
                            <span class="impact-filter-copy">
                                <strong>Lower climate impact</strong>
                                <small>Under 1 kg CO₂e/kg - lower emissions than similar products</small>
                            </span>
                        </label>

                        <label class="filter-row">
                            <input
                                type="checkbox"
                                name="midco2"
                                value="1"
                                <?= $midCO2 ? 'checked' : '' ?>
                            >

                            <span class="impact-dot impact-medium"></span>
                            <span class="impact-filter-copy">
                                <strong>Medium climate impact</strong>
                                <small>1-3 kg CO₂e/kg - moderate emissions compared with similar products</small>
                            </span>
                        </label>

                        <label class="filter-row">
                            <input
                                type="checkbox"
                                name="highco2"
                                value="1"
                                <?= $highCO2 ? 'checked' : '' ?>
                            >

                            <span class="impact-dot impact-high"></span>
                            <span class="impact-filter-copy">
                                <strong>Higher climate impact</strong>
                                <small>Over 3 kg CO₂e/kg - higher emissions than similar products</small>
                            </span>
                        </label>

                        <p class="climate-filter-note">
                            Lower numbers mean less climate warming. Meal ranges use CO₂e per serving.
                        </p>

                        <label class="filter-row">
                            <input
                                type="checkbox"
                                name="local"
                                value="1"
                                <?= $localOnly ? 'checked' : '' ?>
                            >

                            <span>Local</span>
                        </label>
                    </section>

                    <!-- SORT -->
                    <section class="filter-group">
                        <h4 class="group-title">Sort by</h4>

                        <select class="sort-select" name="sort">
                            <option
                                value="eco"
                                <?= $sort === 'eco' ? 'selected' : '' ?>
                            >
                                Eco first
                            </option>

                            <option
                                value="price_asc"
                                <?= $sort === 'price_asc' ? 'selected' : '' ?>
                            >
                                Price low → high
                            </option>

                            <option
                                value="price_desc"
                                <?= $sort === 'price_desc' ? 'selected' : '' ?>
                            >
                                Price high → low
                            </option>

                            <option
                                value="newest"
                                <?= $sort === 'newest' ? 'selected' : '' ?>
                            >
                                Newest
                            </option>
                        </select>
                    </section>

                    <div class="filter-actions">
                        <button
                            class="apply-button"
                            type="submit"
                        >
                            Apply filters
                        </button>

                        <a
                            class="reset-button"
                            href="home.php"
                        >
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
            <div>
                
            </div>

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
                <a class="reset-button" href="home.php">Reset filters</a>
            </div>

        <?php else: ?>

            <div class="product-sections">
                <?php foreach ($productSections as $section): ?>
                    <?php if (!empty($section['items'])): ?>
                        <section class="product-section" id="<?= h($section['id']) ?>">
                            <header class="product-section-header">
                                <div>
                                    <h3><?= h($section['title']) ?></h3>
                                    <p><?= h($section['description']) ?></p>
                                </div>
                                <span class="section-count">
                                    <?= count($section['items']) ?>
                                    <?= count($section['items']) === 1 ? 'product' : 'products' ?>
                                </span>
                            </header>

                            <div
                                class="grid-cards"
                                aria-label="<?= h($section['title']) ?>"
                            >
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
                                        default => ''
                                    };

                                    $climateLabel = match ($rank) {
                                        1 => 'Lower climate impact',
                                        2 => 'Medium climate impact',
                                        3 => 'Higher climate impact',
                                        default => 'Climate impact'
                                    };

                                    $dotClass = match ($rank) {
                                        1 => 'impact-low',
                                        2 => 'impact-medium',
                                        3 => 'impact-high',
                                        default => ''
                                    };

                                    $climate = climate_metrics($row, $sig, $rank);

                                    /* Link til netop dette produkt */
                                    $productUrl = 'product.php?id=' . (int)$row['id'];
                                    ?>

                                    <article class="card">

                                        <!-- FAVOURITE -->
                                        <form
                                            class="card-favourite add-to-fav"
                                            action="../Api/AddFav.php"
                                            method="post"
                                        >
                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?= (int)$row['id'] ?>"
                                            >

                                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

                                            <button
                                                type="submit"
                                                aria-label="Add to wishlist"
                                            >
                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    aria-hidden="true"
                                                    focusable="false"
                                                >
                                                    <path
                                                        d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"
                                                        stroke="currentColor"
                                                        stroke-width="1.8"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </button>
                                        </form>

                                        <!-- KLIK PÅ BILLEDET ÅBNER PRODUKTET -->
                                        <a
                                            class="product-visual"
                                            href="<?= h($productUrl) ?>"
                                            aria-label="View details for <?= h($row['title']) ?>"
                                        >
                                            <?php if (!empty($row['image'])): ?>

                                                <img
                                                    src="../Style/Images/products/<?= h($row['image']) ?>"
                                                    alt="<?= h($row['title']) ?>"
                                                    class="product-image"
                                                >

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

                                        <div class="product-content">
                                            <h4 class="product-title-row">
                                                <a class="product-name-link" href="<?= h($productUrl) ?>"><?= h($row['title']) ?></a>
                                            </h4>
                                            <div class="merchant-name"><?= h($row['merchant']) ?></div>
                                            <div class="price"><?= number_format((float)$row['price'], 2, ',', '.') ?> kr.</div>
                                            <?php
                                            $weight = $sig['weight_g'] ?? null;
                                            $titleHasWeight = preg_match('/\d+(?:[.,]\d+)?\s*(?:kg|g|ml|l)\b/i', (string)$row['title']) === 1;
                                            ?>
                                            <?php if (($weight !== null && !$titleHasWeight) || !empty($sig['local'])): ?>
                                                <div class="product-meta">
                                                    <?php if ($weight !== null && !$titleHasWeight): ?>
                                                        <span><?= (int)$weight ?> g</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($sig['local'])): ?>
                                                        <span class="sig local">Local</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="climate-summary <?= h($co2Class) ?>" aria-label="Climate impact">
                                                <span class="climate-label">
                                                    <span class="impact-dot <?= h($dotClass) ?>" aria-hidden="true"></span>
                                                    <?= h($climateLabel) ?>
                                                </span>
                                                <?php if ($climate['available']): ?>
                                                    <strong><?= h($climate['formatted']) ?> kg CO₂e <span class="climate-unit"><?= h($climate['unit']) ?></span></strong>
                                                    <?php if ($climate['is_example']): ?>
                                                        <small class="example-note">Example value</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <small>Information not available</small>
                                                <?php endif; ?>
                                            </div>
                                            <a class="product-details-link" href="<?= h($productUrl) ?>" aria-label="See more about <?= h($row['title']) ?>">See more <span aria-hidden="true">→</span></a>
                                            <form class="home-add-to-cart" action="../Api/add_to_cart.php" method="post">
                                                <input type="hidden" name="product_id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="qty" value="1">
                                                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                                <button class="apply-button" type="submit" aria-label="Add <?= h($row['title']) ?> to cart">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M3 3h2l3 12h11l2-8H6"/>
                                                        <circle cx="9" cy="20" r="1"/>
                                                        <circle cx="18" cy="20" r="1"/>
                                                    </svg>
                                                    <span class="home-cart-button-label">Add to cart</span>
                                                </button>
                                                <p class="home-cart-message" role="status" aria-live="polite" aria-atomic="true"></p>
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




<script>
(() => {
    'use strict';

    function setupDualRange(minId, maxId, minLabelId, maxLabelId, rangeId) {
        const minInput = document.getElementById(minId);
        const maxInput = document.getElementById(maxId);
        const minLabel = document.getElementById(minLabelId);
        const maxLabel = document.getElementById(maxLabelId);
        const range = document.getElementById(rangeId);
        if (!minInput || !maxInput || !minLabel || !maxLabel || !range) return;
        if (range.dataset.flashRangeReady === 'true') return;
        range.dataset.flashRangeReady = 'true';

        const absoluteMin = Number(minInput.min);
        const absoluteMax = Number(maxInput.max);
        const total = absoluteMax - absoluteMin;

        function update(changed) {
            let min = Number(minInput.value);
            let max = Number(maxInput.value);
            if (min > max) {
                if (changed === 'min') max = min;
                else min = max;
            }
            minInput.value = String(min);
            maxInput.value = String(max);
            const minPercent = total > 0 ? (min - absoluteMin) / total * 100 : 0;
            const maxPercent = total > 0 ? (max - absoluteMin) / total * 100 : 0;
            range.style.setProperty('--min-pos', `${minPercent}%`);
            range.style.setProperty('--max-pos', `${maxPercent}%`);
            minLabel.textContent = String(min);
            maxLabel.textContent = String(max);
            minInput.style.zIndex = minPercent > 70 ? '5' : '4';
            maxInput.style.zIndex = minPercent > 70 ? '4' : '5';
        }
        minInput.addEventListener('input', () => update('min'));
        maxInput.addEventListener('input', () => update('max'));
        minInput.form?.addEventListener('reset', () => setTimeout(update, 0));
        update();
    }

    function setupFilterIcon() {
        const button = document.getElementById('home-filter-toggle');
        const filter = document.getElementById('product-filters');
        const sidebar = filter?.closest('.catalog-sidebar');
        const layout = filter?.closest('.catalog-layout');
        if (!button || !filter || !sidebar || !layout) return;
        if (button.dataset.flashFilterReady === 'true') return;
        button.dataset.flashFilterReady = 'true';

        function sync() {
            sidebar.hidden = !filter.open;
            layout.classList.toggle('filters-closed', !filter.open);
            button.setAttribute('aria-expanded', String(filter.open));
            button.setAttribute('aria-label', filter.open ? 'Hide filters' : 'Show filters');
            button.title = filter.open ? 'Hide filters' : 'Show filters';
        }
        button.addEventListener('click', () => {
            filter.open = !filter.open;
            sync();
        });
        filter.addEventListener('toggle', sync);
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !filter.open) return;
            if (!sidebar.contains(document.activeElement) && document.activeElement !== button) return;
            filter.open = false;
            sync();
            button.focus();
        });
        layout.classList.add('filters-enhanced');
        sync();
    }

    function initialize() {
        setupFilterIcon();
        setupDualRange('priceMin', 'priceMax', 'priceMinLabel', 'priceMaxLabel', 'priceRange');
        setupDualRange('weightMin', 'weightMax', 'weightMinLabel', 'weightMaxLabel', 'weightRange');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();


(() => {
    'use strict';
    document.querySelectorAll('.home-add-to-cart').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.busy === 'true') return;
            const button = form.querySelector('button[type="submit"]');
            const label = form.querySelector('.home-cart-button-label');
            const message = form.querySelector('.home-cart-message');
            form.dataset.busy = 'true';
            button.disabled = true;
            label.textContent = 'Adding…';
            message.textContent = '';
            delete message.dataset.state;
            const body = new FormData(form);
            body.set('ajax', '1');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                const data = await response.json();
                if (!response.ok || data.ok !== true) {
                    throw new Error(data.message || data.error || 'Could not add the product. Please try again.');
                }
                const count = Number(data.count);
                if (Number.isInteger(count) && count >= 0) {
                    document.querySelectorAll('.cart-count').forEach(counter => {
                        counter.textContent = String(count);
                    });
                }
                message.dataset.state = 'success';
                message.textContent = 'Added to cart.';
                const icon = document.getElementById('cart-icon');
                if (icon && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    icon.animate([
                        { transform: 'rotate(0deg)' },
                        { transform: 'rotate(-12deg)' },
                        { transform: 'rotate(12deg)' },
                        { transform: 'rotate(0deg)' }
                    ], { duration: 350 });
                }
            } catch (error) {
                message.dataset.state = 'error';
                message.textContent = error instanceof SyntaxError
                    ? 'The server returned an unexpected response. Check your cart before trying again.'
                    : (error instanceof TypeError
                        ? 'Connection problem. Check your cart before trying again.'
                        : error.message);
            } finally {
                form.dataset.busy = 'false';
                button.disabled = false;
                label.textContent = 'Add to cart';
            }
        });
    });
})();

</script>
</body>
</html>
