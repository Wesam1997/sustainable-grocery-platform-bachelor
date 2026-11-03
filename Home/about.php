<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';

$application = flashfood_application($conn);
$loggedInUser = currentUser();
$cartCount = $application->cart()->count();

function about_h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
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

    <meta
        name="description"
        content="Discover how Flash Food makes sustainable grocery choices easier to understand."
    >

    <title>About us | Flash Food</title>

    <!-- Existing Flash Food header styles -->
    <link
        rel="stylesheet"
        href="../Style/HomeStyle.css?v=20261103-8"
    >

    <!-- About page styles -->
    <link
        rel="stylesheet"
        href="../Style/about.css?v=20261103-2"
    >

    <!-- About page interactions -->
    <script
        src="../Js/about.js?v=20261103-1"
        defer
    ></script>
</head>

<body class="about-page">

<!-- ==========================================
     HEADER
     ========================================== -->

<header class="site-header">
    <div class="topbar">

        <a class="brand" href="./home.php">
            <span
                class="brand-mark"
                aria-hidden="true"
            ></span>

            <span>Flash Food</span>
        </a>

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
                class="nav-link active"
                href="./about.php"
                aria-current="page"
            >
                About us
            </a>
        </nav>

        <div class="header-actions">

            <!-- WISHLIST -->

            <a
                class="header-link"
                href="./fav.php"
                aria-label="Wishlist"
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
                aria-label="Cart"
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

                <span class="cart-count">
                    <?= (int) $cartCount ?>
                </span>
            </a>

            <!-- PROFILE -->

            <a
                class="header-link"
                href="../profil/profil.php"
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

                    <path
                        d="M4 21v-2a8 8 0 0 1 16 0v2"
                    />
                </svg>

                <span>
                    <?php if ($loggedInUser !== null): ?>
                        Hi, <?= about_h(
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

<main class="about-main">

    <!-- ======================================
         HERO
         ====================================== -->

    <section
        class="about-hero"
        aria-labelledby="about-title"
    >
        <div class="about-hero-copy">

            <h1 id="about-title">
                Small choices
                <span>add up.</span>
            </h1>

            <p>
                Compare climate impact, choose the
                lower-CO<sub>2</sub>e option, and
                earn Green Points as you shop.
            </p>

        </div>
    </section>

    <!-- ======================================
         INTERACTIVE CO2E SECTION
         ====================================== -->

    <section
        class="co2-playground"
        aria-labelledby="playground-title"
    >
        <div class="playground-heading">

            <span class="section-kicker">
                Make climate numbers meaningful
            </span>

            <h2 id="playground-title">
                Make CO<sub>2</sub>e easier to picture
            </h2>

            <p>
                Move the slider to turn a climate
                number into something familiar.
            </p>

        </div>

        <div class="playground-panel">

            <!-- ==================================
                 CO2E CALCULATOR
                 ================================== -->

            <div class="calculator-side">

                <div class="amount-heading">

                    <label for="co2-slider">
                        CO<sub>2</sub>e amount
                    </label>

                    <output
                        id="co2-output"
                        for="co2-slider"
                    >
                        2.2 kg CO<sub>2</sub>e
                    </output>

                </div>

                <input
                    class="co2-slider"
                    id="co2-slider"
                    type="range"
                    min="0.1"
                    max="10"
                    step="0.1"
                    value="2.2"
                    aria-describedby="car-reference-note"
                >

                <!-- Car and route illustration -->

                <svg
                    class="car-route"
                    viewBox="0 0 760 220"
                    role="img"
                    aria-label="A car travelling along a route"
                >
                    <path
                        class="route-line"
                        d="M66 166 C185 166 210 106 332 117 C447 127 503 178 625 144"
                    />

                    <path
                        class="route-dashes"
                        d="M67 166 C185 166 210 106 332 117 C447 127 503 178 625 144"
                    />

                    <g
                        class="car-shape"
                        transform="translate(45 117)"
                    >
                        <path
                            d="M18 34h98l-12-25H48L31 34Z"
                        />

                        <rect
                            x="4"
                            y="34"
                            width="126"
                            height="42"
                            rx="15"
                        />

                        <circle
                            cx="34"
                            cy="76"
                            r="13"
                        />

                        <circle
                            cx="103"
                            cy="76"
                            r="13"
                        />

                        <path
                            d="M55 10v24M89 10v24"
                        />
                    </g>

                    <g
                        class="pin-shape"
                        transform="translate(620 70)"
                    >
                        <path
                            d="M35 0C16 0 0 15 0 35c0 27 35 64 35 64s35-37 35-64C70 15 54 0 35 0Z"
                        />

                        <circle
                            cx="35"
                            cy="35"
                            r="12"
                        />
                    </g>
                </svg>

                <div
                    class="distance-result"
                    aria-live="polite"
                >
                    <span>
                        ≈

                        <strong id="driving-km">
                            13.6
                        </strong>

                        km
                    </span>

                    <small>of car travel</small>
                </div>

                <p
                    id="car-reference-note"
                    class="reference-note"
                >
                    Based on the Flash Food car
                    reference: 0.16152 kg
                    CO<sub>2</sub>e per km.
                </p>

            </div>

            <!-- ==================================
                 DAILY LLM TIPS
                 ================================== -->

            <aside
                class="fact-side"
                aria-labelledby="fact-title"
            >
                <div class="fact-topline">

                    <span class="fact-label">
                        3 tips for today
                    </span>

                    <span class="fact-counter">
                        Tip

                        <strong id="tip-number">
                            1
                        </strong>

                        of 3
                    </span>

                </div>

                <h3 id="fact-title">
                    Today’s green tip
                </h3>

                <p
                    id="climate-fact"
                    aria-live="polite"
                >
                    Loading today’s sustainability
                    tips…
                </p>

                <p
                    class="fact-status"
                    id="fact-status"
                    aria-live="polite"
                ></p>

                <button
                    class="fact-button"
                    id="next-fact"
                    type="button"
                    disabled
                >
                    Show next tip
                </button>

                <a
                    class="shopping-button"
                    href="./home.php"
                >
                    Start shopping
                </a>

            </aside>

        </div>
    </section>

    <!-- ======================================
         GREEN POINTS MESSAGE
         ====================================== -->

    <section class="points-message">

        <div
            class="points-icon"
            aria-hidden="true"
        >
            <svg
                viewBox="0 0 64 64"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="M51 13C28 14 15 25 14 43c13 4 32-2 37-30Z"
                />

                <path
                    d="M15 49c7-13 18-22 33-29"
                />
            </svg>
        </div>

        <div>
            <span>Green Points</span>

            <h2>
                Your lower-impact choices can
                unlock discounts.
            </h2>
        </div>

        <a href="./home.php">
            Shop with impact
        </a>

    </section>

</main>

</body>
</html>