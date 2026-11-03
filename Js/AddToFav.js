'use strict';

document.addEventListener('DOMContentLoaded', () => {

    /* ==========================================
       FLASH FOOD - WISHLIST
       ADD / REMOVE FAVORITES
       ========================================== */

    const favoriteSelector = 'form.add-to-fav';

    const savedIds = new Set(
        Array.isArray(window.flashFoodSavedIds)
            ? window.flashFoodSavedIds.map(String)
            : []
    );

    const pendingProducts = new Set();

    /* ==========================================
       ANIMATION HELPER
       ========================================== */

    function restartAnimation(element, className) {

        if (!element) {
            return;
        }

        element.classList.remove(className);

        void element.offsetWidth;

        element.classList.add(className);

        element.addEventListener(
            'animationend',
            () => {
                element.classList.remove(className);
            },
            { once: true }
        );
    }

    /* ==========================================
       ANIMATE WISHLIST HEADER
       ========================================== */

    function animateWishlist() {

        const wishlistIcon = document.getElementById(
            'wishlist-icon'
        );

        const wishlistLink = document.getElementById(
            'wishlist-link'
        );

        if (wishlistIcon) {

            restartAnimation(
                wishlistIcon,
                'is-shaking'
            );

        } else {

            restartAnimation(
                wishlistLink,
                'wishlist-arrival'
            );
        }
    }

    /* ==========================================
       UPDATE ONE FAVORITE FORM
       ========================================== */

    function updateForm(form, saved) {

        const button = form.querySelector(
            'button[type="submit"]'
        );

        if (!button) {
            return;
        }

        /* UPDATE HEART */

        button.classList.toggle(
            'is-saved',
            saved
        );

        /* ACCESSIBILITY */

        button.setAttribute(
            'aria-pressed',
            String(saved)
        );

        button.setAttribute(
            'aria-label',
            saved
                ? 'Remove from wishlist'
                : 'Add to wishlist'
        );

        /* UPDATE ACTION */

        let actionInput = form.querySelector(
            'input[name="wishlist_action"]'
        );

        if (!actionInput) {

            actionInput = document.createElement(
                'input'
            );

            actionInput.type = 'hidden';
            actionInput.name = 'wishlist_action';

            form.appendChild(actionInput);
        }

        actionInput.value = saved
            ? 'remove'
            : 'add';
    }

    /* ==========================================
       UPDATE ALL HEARTS FOR SAME PRODUCT
       ========================================== */

    function updateFavoriteHearts(productId, saved) {

        if (saved) {
            savedIds.add(productId);
        } else {
            savedIds.delete(productId);
        }

        document.querySelectorAll(
            favoriteSelector
        ).forEach((form) => {

            const currentId = form.querySelector(
                'input[name="product_id"]'
            )?.value;

            if (currentId !== productId) {
                return;
            }

            updateForm(form, saved);
        });
    }

    /* ==========================================
       INITIALIZE HEARTS
       ========================================== */

    function initializeHearts(container = document) {

        container.querySelectorAll(
            favoriteSelector
        ).forEach((form) => {

            const productId = form.querySelector(
                'input[name="product_id"]'
            )?.value;

            if (!productId) {
                return;
            }

            updateForm(
                form,
                savedIds.has(productId)
            );
        });
    }

    initializeHearts();

    /* ==========================================
       RELATED PRODUCTS
       ========================================== */

    /*
       Related products can be loaded dynamically
       by productDetails.js.

       Their hearts must also reflect the saved
       wishlist state.
    */

    const relatedProducts = document.getElementById(
        'relatedProducts'
    );

    if (relatedProducts) {

        const observer = new MutationObserver(() => {

            initializeHearts(relatedProducts);
        });

        observer.observe(
            relatedProducts,
            {
                childList: true,
                subtree: true
            }
        );
    }

    /* ==========================================
       ADD / REMOVE FAVORITE - AJAX
       ========================================== */

    document.addEventListener(
        'submit',
        async (event) => {

            const form = event.target;

            if (
                !(form instanceof HTMLFormElement) ||
                !form.matches(favoriteSelector)
            ) {
                return;
            }

            /* PREVENT PAGE RELOAD */

            event.preventDefault();

            const button = form.querySelector(
                'button[type="submit"]'
            );

            const productId = form.querySelector(
                'input[name="product_id"]'
            )?.value;

            if (!button || !productId) {
                return;
            }

            /* PREVENT DOUBLE REQUESTS */

            if (pendingProducts.has(productId)) {
                return;
            }

            /* CHECK CURRENT STATE */

            const isSaved = savedIds.has(productId);

            const action = isSaved
                ? 'remove'
                : 'add';

            pendingProducts.add(productId);

            button.disabled = true;

            try {

                /* PREPARE REQUEST */

                const formData = new FormData(form);

                formData.set('ajax', '1');

                formData.set(
                    'wishlist_action',
                    action
                );

                /* SEND TO PHP API */

                const response = await fetch(
                    form.action,
                    {
                        method: 'POST',

                        body: formData,

                        credentials: 'same-origin',

                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With':
                                'XMLHttpRequest'
                        }
                    }
                );

                const result = await response.json();

                /* CHECK RESPONSE */

                if (
                    !response.ok ||
                    result.ok !== true ||
                    typeof result.saved !== 'boolean'
                ) {
                    throw new Error(
                        result.error ||
                        'Could not update wishlist.'
                    );
                }

                /* UPDATE HEARTS */

                updateFavoriteHearts(
                    productId,
                    result.saved
                );

                /* HEART ANIMATION */

                restartAnimation(
                    button,
                    'heart-pop'
                );

                /* HEADER ANIMATION */

                animateWishlist();

            } catch (error) {

                console.error(
                    'Wishlist update failed:',
                    error
                );

                /*
                   No popup.
                   No toast.
                   Keep previous state if request fails.
                */

            } finally {

                pendingProducts.delete(productId);

                button.disabled = false;
            }
        }
    );

});
