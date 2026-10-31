<?php
declare(strict_types=1);

/**
 * Flash Food - Profile Header
 *
 * Responsibilities:
 * - Display the Flash Food logo
 * - Display main navigation
 * - Display wishlist, cart and profile links
 *
 * $cartCount is provided by profil.php.
 */

$headerCartCount = max(0, (int) ($cartCount ?? 0));
?>

<header class="site-header">

    <div class="topbar">

        <!-- BRAND / LOGO -->

        <a
            href="../Home/home.php"
            class="brand"
            aria-label="Flash Food Home"
        >
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
                href="../Home/home.php"
                class="nav-link"
            >
                Home
            </a>

            <a
                href="../Home/home.php#about"
                class="nav-link"
            >
                About us
            </a>

        </nav>


        <!-- HEADER ACTIONS -->

        <div class="header-actions">

            <!-- WISHLIST -->

            <a
                href="../Home/fav.php"
                class="header-link"
                aria-label="My wishlist"
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


            <!-- SHOPPING CART -->

            <a
                href="../Home/inkobLister.php"
                class="header-link"
                aria-label="Shopping cart"
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
                    <path d="M3 3h2l3 12h11l2-8H6"/>
                    <circle cx="9" cy="20" r="1"/>
                    <circle cx="18" cy="20" r="1"/>
                </svg>

                <span>Cart</span>

                <?php if ($headerCartCount > 0): ?>
                    <span class="cart-count">
                        <?= $headerCartCount ?>
                    </span>
                <?php endif; ?>

            </a>


            <!-- MY PROFILE -->

            <a
                href="../profil/profil.php"
                class="header-link profile-current"
                aria-current="page"
                aria-label="My profile"
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
                    <path
                        d="M4 21v-2a8 8 0 0 1 16 0v2"
                    />
                </svg>

                <span>Profile</span>

            </a>

        </div>

    </div>

</header>
