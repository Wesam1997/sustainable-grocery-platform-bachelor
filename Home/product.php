<?php
session_start();
/* Use the same catalogue and climate helper as home.php. */
$climatePayload = null;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id) {
    try {
        require_once __DIR__ . '/../Api/connect.php';
        require_once __DIR__ . '/../Api/catalog.php';
        require_once __DIR__ . '/../Api/climate_metrics.php';
        $catalogItems = get_catalog_items($conn, ['type' => 'all']);
        foreach ($catalogItems as $row) {
            if ((int)$row['id'] !== $id) continue;
            $sig = is_array($row['_sig'] ?? null) ? $row['_sig'] : [];
            $co2 = is_array($sig['co2'] ?? null) ? $sig['co2'] : [];
            $rank = (int)($co2['rank'] ?? 0);
            $climatePayload = climate_metrics($row, $sig, $rank);
            $climatePayload['product_id'] = $id;
            $climatePayload['className'] = match ($rank) {
                1 => 'low', 2 => 'medium', 3 => 'high', default => 'unknown'
            };
            $climatePayload['label'] = match ($rank) {
                1 => 'Lower climate impact',
                2 => 'Medium climate impact',
                3 => 'Higher climate impact',
                default => 'Climate impact'
            };
            break;
        }
    } catch (Throwable $error) {
        error_log('Product climate lookup failed: ' . $error->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product details – Flash Food</title>
    <link rel="stylesheet" href="../Style/produktdetalej.css?v=20260915-climate">
    <script id="productClimateData" type="application/json"><?= json_encode($climatePayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script src="../Js/productDetails.js?v=20260915-climate" defer></script>
</head>
<body>
<main class="product-page">
    <a class="back-button" href="home.php">← Back to products</a>
    <p id="statusMessage" role="status">Loading product...</p>
    <article id="productDetails" class="product-details" hidden>
        <div class="product-image-area">
            <img id="productImage" alt="" hidden>
            <p id="imageUnavailable" class="image-unavailable" hidden>Image not available</p>
        </div>
        <div class="product-information">
            <div class="product-heading">
                <div><h1 id="productTitle"></h1><p id="productMerchant"></p></div>
                <button class="favourite-button" type="button" aria-label="Add product to wishlist">♡</button>
            </div>
            <p id="productPrice" class="product-price"></p>
            <section id="climateBox" class="climate-box unknown" aria-labelledby="climateLabel">
                <span class="climate-icon" aria-hidden="true"></span>
                <div>
                    <strong id="climateLabel">Climate impact</strong>
                    <p id="climateValue">Information not available</p>
                    <small id="climateExample" hidden>Example value</small>
                </div>
            </section>
            <section class="co2-information">
                <span class="information-icon" aria-hidden="true">i</span>
                <div>
                    <h2>About CO₂e</h2>
                    <p>CO₂e measures the gases that warm our planet. Lower numbers mean less climate impact when the units and calculation methods match. Compare groceries per kg and meals per serving.</p>
                </div>
            </section>
            <dl class="product-facts">
                <div><dt>Origin</dt><dd id="productOrigin"></dd></div>
                <div><dt>Pack size</dt><dd id="productSize"></dd></div>
                <div><dt>Category</dt><dd id="productCategory"></dd></div>
            </dl>
            <form class="cart-form" action="../Api/add_to_cart.php" method="post">
                <input id="productId" type="hidden" name="product_id">
                <div class="quantity-picker">
                    <button id="decreaseQuantity" type="button" aria-label="Decrease quantity">−</button>
                    <input id="quantity" type="number" name="qty" min="1" max="99" step="1" value="1" aria-label="Quantity" required>
                    <button id="increaseQuantity" type="button" aria-label="Increase quantity">+</button>
                </div>
                <button class="add-button" type="submit">Add to cart</button>
            </form>
        </div>
    </article>
    <section id="relatedSection" class="related-section" hidden>
        <div class="related-heading"><h2>Related products</h2><a href="home.php">See all</a></div>
        <div id="relatedProducts" class="related-products"></div>
    </section>
</main>
</body>
</html>
