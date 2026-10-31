<?php
declare(strict_types=1);

/**
 * Flash Food - Profile Sidebar
 *
 * Responsibility:
 * - Display profile navigation
 * - Display logout action
 *
 * Authentication is handled by profil.php.
 */

$profileNavigation = [
    [
        'id' => 'overview',
        'label' => 'Overview',
        'icon' => 'user',
    ],
    [
        'id' => 'personal-details',
        'label' => 'Personal details',
        'icon' => 'edit',
    ],
    [
        'id' => 'climate-impact',
        'label' => 'Climate impact',
        'icon' => 'leaf',
    ],
    [
        'id' => 'green-points',
        'label' => 'Green Points',
        'icon' => 'award',
    ],
    [
        'id' => 'recent-orders',
        'label' => 'Purchases',
        'icon' => 'shopping-bag',
    ],
];
?>

<aside class="side-panel" aria-label="Profile menu">

    <div class="sidebar-label">
        YOUR ACCOUNT
    </div>

    <nav class="side-nav" aria-label="Profile navigation">

        <?php foreach ($profileNavigation as $index => $item): ?>

            <a
                href="#<?= profileEscape($item['id']) ?>"
                class="side-link <?= $index === 0 ? 'selected' : '' ?>"
                data-section="<?= profileEscape($item['id']) ?>"
            >

                <span class="side-icon" aria-hidden="true">

                    <?php if ($item['icon'] === 'user'): ?>

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
                        </svg>

                    <?php elseif ($item['icon'] === 'edit'): ?>

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L9 17l-4 1 1-4Z"/>
                        </svg>

                    <?php elseif ($item['icon'] === 'leaf'): ?>

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8">
                            <path d="M20.5 3.5C12.3 3 6 6.1 4.2 12c-1.2 3.9.8 7.5 4.4 8.3 5.6 1.2 11.5-3.4 11.9-16.8Z"/>
                            <path d="M6 21c1-5 4-9 9-13"/>
                        </svg>

                    <?php elseif ($item['icon'] === 'award'): ?>

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8">
                            <circle cx="12" cy="8" r="5"/>
                            <path d="m8 12-2 9 6-3 6 3-2-9"/>
                        </svg>

                    <?php elseif ($item['icon'] === 'shopping-bag'): ?>

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8">
                            <path d="M4 8h16l-1.5 12h-13L4 8Z"/>
                            <path d="M8 9V6a4 4 0 0 1 8 0v3"/>
                        </svg>

                    <?php endif; ?>

                </span>

                <span>
                    <?= profileEscape($item['label']) ?>
                </span>

            </a>

        <?php endforeach; ?>

    </nav>

    <!-- LOGOUT -->

    <div class="sidebar-bottom">

        <form
            action="../Api/logout.php"
            method="post"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= profileEscape(csrfToken()) ?>"
            >

            <button
                type="submit"
                class="logout-button"
            >

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     aria-hidden="true">

                    <path d="M10 17l5-5-5-5"/>
                    <path d="M15 12H4"/>
                    <path d="M12 3h5a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-5"/>
                </svg>

                <span>Log out</span>

            </button>

        </form>

    </div>

</aside>
