<?php
declare(strict_types=1);

/**
 * Flash Food - Profile Welcome
 *
 * Presentation component.
 * $profileName comes from profil.php.
 */
?>

<section class="welcome-card" aria-label="Welcome">

    <div class="welcome-inner">

        <div class="welcome-avatar" aria-hidden="true">
            <svg
                viewBox="0 0 64 64"
                fill="none"
                stroke="currentColor"
                stroke-width="2.7"
            >
                <circle cx="32" cy="22" r="10"/>
                <path d="M13 53c0-12 8-19 19-19s19 7 19 19"/>
            </svg>
        </div>

        <div class="welcome-copy">

            <span class="eyebrow">
                YOUR FLASH FOOD ACCOUNT
            </span>

            <h2>
                Welcome back,
                <?= profileEscape($profileName) ?>!
            </h2>

            <p>
                Small choices. A meaningful difference.
            </p>

        </div>

    </div>

    <!-- Decorative landscape -->

    <svg
        class="landscape"
        viewBox="0 0 480 180"
        preserveAspectRatio="xMidYMax slice"
        aria-hidden="true"
    >
        <circle
            cx="414"
            cy="35"
            r="23"
            fill="#f5d89b"
            opacity=".85"
        />

        <path
            d="M0 171Q110 78 240 135T480 95V180H0Z"
            fill="#d9eadc"
        />

        <path
            d="M0 180Q130 116 260 154T480 117V180H0Z"
            fill="#b6d9be"
        />

        <path
            d="M0 180Q130 156 277 174T480 133V180H0Z"
            fill="#8bbb91"
        />

        <g fill="#5b9a69">
            <path d="M329 89c-5-18 2-32 11-32s17 14 12 32l-11 10Z"/>
            <path d="M387 122c-5-18 2-32 11-32s17 14 12 32l-11 10Z"/>
        </g>

        <g
            stroke="#356f47"
            stroke-width="4"
            stroke-linecap="round"
        >
            <path d="M341 93v48M399 118v35"/>
        </g>
    </svg>

</section>
