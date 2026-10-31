<?php

declare(strict_types=1);

/**
 * Flash Food - My Profile
 *
 * File: profil/profil.php
 *
 * Responsibilities:
 * - Authenticate the current user
 * - Prepare data for profile components
 * - Load shared application dependencies
 * - Render the profile page
 *
 * Architecture:
 * - Business logic belongs in Services
 * - Database queries belong in Repositories
 * - HTML presentation belongs in partials
 * - CSS and JavaScript are separate
 */

require_once __DIR__ . '/../Api/auth.php';

/*
|--------------------------------------------------------------------------
| 1. SECURITY
|--------------------------------------------------------------------------
*/

// Prevent browser caching of personal information.
header('Cache-Control: no-store');

// Get the currently authenticated user.
$user = currentUser();

// Redirect unauthenticated visitors.
if (!$user) {
    header(
        'Location: ../Login/login.php',
        true,
        303
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| 2. APPLICATION DEPENDENCIES
|--------------------------------------------------------------------------
*/

// Uses the existing application bootstrap.
$app = flashfood_application($conn);

/*
|--------------------------------------------------------------------------
| 3. SHARED PAGE DATA
|--------------------------------------------------------------------------
*/

// Shopping cart item count.
$cartCount = $app->cart()->count();

// Authenticated user information.
$userId = (int) ($user['id'] ?? 0);

$profileName = (string) (
    $user['name'] ?? 'Shopper'
);

$profileEmail = (string) (
    $user['email'] ?? ''
);

/*
|--------------------------------------------------------------------------
| 4. CLIMATE IMPACT DATA
|--------------------------------------------------------------------------
|
| Recorded climate emissions are retrieved
| from the authenticated user's purchase history.
|
| Only orders with available climate data
| contribute to the recorded CO2e total.
|
| Climate emissions are NOT climate savings.
|
*/

// Default climate impact values.
$climateImpact = [
    'total_orders' => 0,
    'known_orders' => 0,
    'unknown_orders' => 0,
    'known_co2e_kg' => 0.0,
    'complete' => false,
];

// Load recorded climate impact from OrderHistoryService.
if ($userId > 0) {
    $climateImpact = $app
        ->orderHistory()
        ->getClimateImpact($userId);
}

// Climate savings require a documented baseline.
// They are not calculated from emissions alone.
$climateSavings = [
    'available' => false,
    'saved_kg' => null,
    'driving_km' => null,
    'method_note' => null,
];

/*
|--------------------------------------------------------------------------
| 5. GREEN POINTS DATA
|--------------------------------------------------------------------------
|
| Green Points are earned only when a customer
| completes an order.
|
| Reward rules:
|
| Low Climate Impact:
| 10 points per purchased unit.
|
| Medium Climate Impact:
| 0 points.
|
| High Climate Impact:
| 0 points.
|
| Unknown Climate Impact:
| 0 points.
|
| Reward:
| 400 Green Points = 20% discount.
|
| The actual points are retrieved through
| OrderHistoryService.
|
| Only placed orders contribute.
| Cancelled orders are excluded.
|
| No points are calculated or awarded
| when viewing the profile.
|
*/

// Default Green Points data.
$greenPoints = [
    'available' => false,
    'total_points' => null,
    'next_milestone' => null,
    'progress_percent' => null,
    'points_to_next' => null,
];

// Load Green Points for the authenticated user.
if ($userId > 0) {

    /*
     * Read the customer's earned points
     * from previously placed orders.
     */
    $totalPoints = $app
        ->orderHistory()
        ->getGreenPoints($userId);

    /*
     * Use the existing reward rules.
     *
     * 400 points unlock a 20% discount.
     */
    $rewardService = $app->greenPoints();

    $rewardThreshold =
        GreenPointsService::REWARD_THRESHOLD;

    /*
     * Calculate progress toward the reward.
     *
     * Progress is capped at 100%.
     */
    $progressPercent = $rewardThreshold > 0
        ? min(
            100,
            round(
                ($totalPoints / $rewardThreshold) * 100,
                1
            )
        )
        : 0;

    /*
     * Calculate how many points remain
     * before the customer reaches 400.
     */
    $pointsRemaining = $rewardService
        ->pointsRemaining($totalPoints);

    /*
     * Prepare the data expected by
     * profil/partials/green-points.php.
     */
    $greenPoints = [
        'available' => true,
        'total_points' => $totalPoints,
        'next_milestone' => $rewardThreshold,
        'progress_percent' => $progressPercent,
        'points_to_next' => $pointsRemaining,
    ];
}

/*
|--------------------------------------------------------------------------
| 6. PURCHASE HISTORY DATA
|--------------------------------------------------------------------------
|
| Retrieve the authenticated user's recent orders
| through OrderHistoryService.
|
| OrderHistoryService depends on the
| OrderHistoryReaderInterface.
|
| MysqliOrderHistoryReader performs the SQL query
| and filters orders by user_id.
|
| No SQL queries belong in this profile page.
|
*/

$recentOrders = [];
$ordersLoaded = false;

if ($userId > 0) {
    $recentOrders = $app
        ->orderHistory()
        ->getRecentOrders($userId, 5);

    $ordersLoaded = true;
}

/*
|--------------------------------------------------------------------------
| 7. OUTPUT ESCAPING
|--------------------------------------------------------------------------
|
| Escape dynamic values before rendering HTML
| to reduce the risk of XSS.
|
*/

function profileEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// CSS cache busting.
// Browser reloads CSS automatically when files change.
$profileCssVersion = filemtime(
    __DIR__ . '/../Style/profil.css'
);

$componentsCssVersion = filemtime(
    __DIR__ . '/../Style/profile-components.css'
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Profile | Flash Food</title>

    <!-- Main profile layout -->
    <link
        rel="stylesheet"
        href="../Style/profil.css?v=<?= $profileCssVersion ?>"
    >

    <!-- Individual profile components -->
    <link
        rel="stylesheet"
        href="../Style/profile-components.css?v=<?= $componentsCssVersion ?>"
    >

</head>

<body>

    <!-- ==========================================
         SHARED WEBSITE HEADER
    =========================================== -->

    <?php
    require __DIR__
        . '/partials/profile-header.php';
    ?>


    <!-- ==========================================
         PROFILE PAGE
    =========================================== -->

    <main
        class="profile-page"
        id="overview"
    >

        <!-- PAGE INTRODUCTION -->

        <div class="page-intro">

            <h1>
                My Profile
            </h1>

            <p>
                Manage your account and follow
                your sustainable shopping journey.
            </p>

        </div>


        <!-- PROFILE LAYOUT -->

        <div class="profile-layout">


            <!-- ==================================
                 SIDEBAR NAVIGATION
            =================================== -->

            <?php
            require __DIR__
                . '/partials/profile-sidebar.php';
            ?>


            <!-- ==================================
                 MAIN PROFILE CONTENT
            =================================== -->

            <div class="content-column">


                <!-- WELCOME CARD -->

                <?php
                require __DIR__
                    . '/partials/profile-welcome.php';
                ?>


                <!-- PERSONAL DETAILS -->

                <?php
                require __DIR__
                    . '/partials/personal-details.php';
                ?>


                <!-- CLIMATE IMPACT -->

                <?php
                require __DIR__
                    . '/partials/climate-impact.php';
                ?>


                <!-- GREEN POINTS -->

                <?php
                require __DIR__
                    . '/partials/green-points.php';
                ?>


                <!-- RECENT PURCHASES -->

                <?php
                require __DIR__
                    . '/partials/recent-orders.php';
                ?>


            </div>

        </div>

    </main>


    <!-- ==========================================
         PROFILE JAVASCRIPT
    =========================================== -->

    <script
        src="../Js/profile.js"
        defer
    ></script>

</body>

</html>
