<?php
declare(strict_types=1);

/**
 * Flash Food - Green Points Component
 *
 * Responsibilities:
 * - Display the user's Green Points
 * - Display progress towards the next milestone
 * - Explain the reward system
 *
 * No database queries or point calculations here.
 *
 * Data will be provided by GreenPointsService.
 */

$greenPoints = $greenPoints ?? [
    'available' => false,
    'total_points' => null,
    'next_milestone' => null,
    'progress_percent' => null,
    'points_to_next' => null,
];

// Validate the data before displaying it.
$pointsAvailable =
    ($greenPoints['available'] ?? false) === true
    && isset(
        $greenPoints['total_points'],
        $greenPoints['next_milestone'],
        $greenPoints['progress_percent'],
        $greenPoints['points_to_next']
    )
    && is_int($greenPoints['total_points'])
    && is_int($greenPoints['next_milestone'])
    && is_numeric($greenPoints['progress_percent'])
    && is_int($greenPoints['points_to_next'])
    && $greenPoints['total_points'] >= 0
    && $greenPoints['next_milestone'] > 0
    && $greenPoints['points_to_next'] >= 0
    && is_finite((float) $greenPoints['progress_percent'])
    && (float) $greenPoints['progress_percent'] >= 0
    && (float) $greenPoints['progress_percent'] <= 100;

$totalPoints = $pointsAvailable
    ? (int) $greenPoints['total_points']
    : null;

$progressPercent = $pointsAvailable
    ? (float) $greenPoints['progress_percent']
    : 0.0;

$pointsToNext = $pointsAvailable
    ? (int) $greenPoints['points_to_next']
    : null;

$nextMilestone = $pointsAvailable
    ? (int) $greenPoints['next_milestone']
    : null;
?>

<section
    class="card rewards-card"
    id="green-points"
    aria-labelledby="green-points-title"
>

    <div class="rewards-head">

        <!-- REWARD ICON -->

        <span class="rewards-icon" aria-hidden="true">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M8 21h8M12 17v4"/>

                <path
                    d="M7 3h10v7a5 5 0 0 1-10 0V3Z"
                />

                <path
                    d="M7 5H4v3a4 4 0 0 0 4 4"
                />

                <path
                    d="M17 5h3v3a4 4 0 0 1-4 4"
                />
            </svg>

        </span>

        <div>

            <h2 id="green-points-title">
                Your Green Points
            </h2>

            <p>
                Follow your progress towards
                greener shopping milestones.
            </p>

        </div>

    </div>


    <?php if ($pointsAvailable): ?>

        <!-- AVAILABLE POINTS -->

        <div class="rewards-status">

            <strong>
                <?= number_format($totalPoints) ?>
                points
            </strong>

            <span>
                Your current Green Points balance
            </span>

        </div>


        <!-- PROGRESS -->

        <div class="rewards-progress">

            <div class="progress-labels">

                <span>
                    Progress to next milestone
                </span>

                <strong>
                    <?= number_format($nextMilestone) ?>
                    points
                </strong>

            </div>

            <div
                class="progress-track"
                role="progressbar"
                aria-label="Green Points progress"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="<?= (int) round($progressPercent) ?>"
            >
                <div
                    class="progress-fill"
                    style="width: <?= profileEscape(
                        number_format(
                            $progressPercent,
                            2,
                            '.',
                            ''
                        )
                    ) ?>%;"
                ></div>
            </div>

            <p class="progress-description">
                <?= number_format($pointsToNext) ?>
                points until your next milestone.
            </p>

        </div>

    <?php else: ?>

        <!-- NOT AVAILABLE YET -->

        <div class="rewards-status">

            <strong>
                Coming soon
            </strong>

            <span>
                Green Points will be available
                when the reward calculation
                system has been implemented.
            </span>

        </div>

    <?php endif; ?>

</section>
