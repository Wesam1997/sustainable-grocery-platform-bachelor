<?php
declare(strict_types=1);

/**
 * Flash Food - Climate Impact Component
 *
 * Displays recorded CO2e from completed purchases.
 * Calculations are provided by OrderHistoryService.
 *
 * Recorded emissions are not emissions saved.
 */

$climateImpact = $climateImpact ?? [
    'total_orders' => 0,
    'known_orders' => 0,
    'unknown_orders' => 0,
    'known_co2e_kg' => 0.0,
    'complete' => false,
];

$totalOrders = max(
    0,
    (int) ($climateImpact['total_orders'] ?? 0)
);

$knownOrders = max(
    0,
    (int) ($climateImpact['known_orders'] ?? 0)
);

$unknownOrders = max(
    0,
    (int) ($climateImpact['unknown_orders'] ?? 0)
);

$emissions = $climateImpact['known_co2e_kg'] ?? null;

$validEmissions = is_numeric($emissions)
    && is_finite((float) $emissions)
    && (float) $emissions >= 0;

$hasRecordedEmissions = $knownOrders > 0
    && $validEmissions;

$emissionsText = $hasRecordedEmissions
    ? number_format((float) $emissions, 3, '.', ',')
    : '—';

$hasPurchases = $totalOrders > 0;

?>

<section
    class="card impact-card"
    id="climate-impact"
    aria-labelledby="climate-impact-title"
>

    <div class="section-heading">
        <div>
            <div class="section-title">

                <span
                    class="section-icon"
                    aria-hidden="true"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            d="M20.5 3.5C12.3 3 6 6.1 4.2 12c-1.2 3.9.8 7.5 4.4 8.3 5.6 1.2 11.5-3.4 11.9-16.8Z"
                        />
                        <path d="M6 21c1-5 4-9 9-13"/>
                    </svg>
                </span>

                <h2 id="climate-impact-title">
                    Your climate impact
                </h2>

            </div>

            <p class="section-description">
                Understand the estimated carbon
                footprint of your purchases.
            </p>
        </div>
    </div>

    <div class="metric-grid">

        <div
            class="metric"
            style="grid-column: 1 / -1;"
        >

            <span
                class="metric-icon"
                aria-hidden="true"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path
                        d="M7 17H6a4 4 0 0 1-.4-8A6.5 6.5 0 0 1 18 10a3.5 3.5 0 1 1 0 7H7Z"
                    />
                </svg>
            </span>

            <strong class="metric-value">
                <?= profileEscape($emissionsText) ?>
            </strong>

            <span class="metric-label">
                kg CO₂e recorded
            </span>

            <small>
                Estimated emissions from purchases
                with available climate data.
            </small>

        </div>

    </div>

    <div class="method-note">

        <?php if (!$hasPurchases): ?>

            <strong>
                No purchases yet
            </strong>

            <p>
                Your climate impact will appear
                here after your first purchase,
                when climate data is available.
            </p>

        <?php elseif (!$hasRecordedEmissions): ?>

            <strong>
                Climate data is not available yet
            </strong>

            <p>
                Your purchases have been recorded,
                but their CO₂e values are unavailable.
            </p>

        <?php else: ?>

            <strong>
                What does this number mean?
            </strong>

            <p>
                This is the estimated amount of
                greenhouse gas emissions associated
                with your recorded purchases.
            </p>

            <?php if ($unknownOrders > 0): ?>

                <p>
                    Some earlier purchases do not
                    have climate data, so the
                    displayed amount does not
                    include your entire
                    purchase history.
                </p>

            <?php endif; ?>

            <p>
                <strong>Important:</strong>
                This is your recorded carbon
                footprint, not the amount
                of CO₂e you have saved.
            </p>

        <?php endif; ?>

    </div>

</section>
