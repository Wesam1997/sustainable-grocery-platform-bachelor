<?php
session_start();
require_once __DIR__ . '/../src/bootstrap.php';
$favoriteService = new FavoriteService(null, new SessionBasketStore());
$cartService = new CartService(null, new SessionBasketStore());
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'clear') { $favoriteService->clear(); header('Location: fav.php'); exit; }
    if ($_GET['action'] === 'remove' && isset($_GET['pid'])) { $favoriteService->remove((int)$_GET['pid']); header('Location: fav.php'); exit; }
}
$fav = $favoriteService->items();
$fav_count = count($fav);
$cart_count = $cartService->count();
?>
<!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Min ønskeliste</title>

  <!-- Styles (brug ../Style/fav.css når fav.php ligger i /Home/) -->
  <link rel="stylesheet" href="../Style/fav.css">
  <!-- JS til “læg i kurv” (samme som på home) -->
  <script src="../Js/addToCartScript.js" defer></script>
</head>
<body>
  <h1>Min ønskeliste</h1>

  <?php if (empty($fav)): ?>
    <p>Ønskelisten er tom.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($fav as $pid => $it): ?>
        <li>
          <?= htmlspecialchars($it['title']) ?>
          (<?= number_format((float)$it['price'], 2, ',', '.') ?> kr)

          <!-- Knapper til den enkelte vare -->
          <div class="item-actions">
            <!-- Læg i kurv -->
            <form class="add-to-cart" action="../Api/add_to_cart.php" method="POST">
              <input type="hidden" name="product_id" value="<?= (int)$pid ?>">
              <input type="hidden" name="qty" value="1">
              <button type="submit" class="btn btn-primary">Læg i kurv</button>
            </form>

            <!-- Fjern fra ønskeliste -->
            <a class="btn btn-danger"
               href="fav.php?action=remove&pid=<?= (int)$pid ?>"
               onclick="return confirm('Fjern denne vare fra ønskelisten?')">
              Fjern
            </a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>

    <!-- Ryd hele ønskelisten -->
    <a class="btn btn-outline"
       href="fav.php?action=clear"
       onclick="return confirm('Ryd hele ønskelisten?')">
      Ryd ønskeliste
    </a>
  <?php endif; ?>

  <footer>
    <nav class="bottombar" aria-label="Bundnavigation">
      <div class="slot left">
        <a class="home-btn" href="home.php" aria-label="Gå til forside">🏠</a>
      </div>
      <div class="slot right">
        <a class="wishlist-pill" href="inkobLister.php" aria-label="Kurv">
          🛒 Kurv <b class="cart-count"><?= (int)$cart_count ?></b>
        </a>
      </div>
    </nav>
  </footer>
</body>
</html>
