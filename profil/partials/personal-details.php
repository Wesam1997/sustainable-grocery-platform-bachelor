<?php
declare(strict_types=1);

/**
 * Flash Food - Personal Details
 *
 * Responsibilities:
 * - Display the authenticated user's name
 * - Display the authenticated user's email
 *
 * User data is provided by profil.php.
 * This component does not query the database.
 */

// Values are prepared in profil.php.
$displayName = $profileName ?? '';
$displayEmail = $profileEmail ?? '';
?>

<section
    class="card account-card"
    id="personal-details"
    aria-labelledby="personal-details-title"
>

    <!-- SECTION HEADER -->

    <div class="section-heading">

        <div class="section-title">

            <span class="section-icon" aria-hidden="true">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <circle cx="12" cy="8" r="4"/>

                    <path
                        d="M4 21v-2a8 8 0 0 1 16 0v2"
                    />
                </svg>

            </span>

            <h2 id="personal-details-title">
                Personal details
            </h2>

        </div>

    </div>


    <!-- USER INFORMATION -->

    <div class="detail-list">

        <!-- NAME -->

        <div class="detail-row">

            <span>Name</span>

            <strong>
                <?= profileEscape($displayName) ?>
            </strong>

        </div>


        <!-- EMAIL -->

        <div class="detail-row">

            <span>Email</span>

            <strong>
                <?= profileEscape($displayEmail) ?>
            </strong>

        </div>

    </div>


    <!-- INFORMATION -->

    <p class="detail-note">
        Your account information is securely
        associated with your Flash Food account.
    </p>

</section>
