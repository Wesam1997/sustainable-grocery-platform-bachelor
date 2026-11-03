'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('wishlist-confirm');
    const dialogMessage = document.getElementById('wishlist-dialog-message');
    const cancelButton = document.getElementById('wishlist-cancel');
    const confirmButton = document.getElementById('wishlist-confirm-yes');
    let pendingForm = null;

    /* ==========================================
       REMOVE / CLEAR CONFIRMATION
       ========================================== */

    document.querySelectorAll('.wishlist-confirm-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (
                !dialog ||
                !dialogMessage ||
                !confirmButton ||
                typeof dialog.showModal !== 'function' ||
                form.dataset.confirmed === 'true'
            ) {
                return;
            }

            event.preventDefault();
            pendingForm = form;
            dialogMessage.textContent =
                form.dataset.confirmMessage || 'Are you sure?';

            const action = form.querySelector(
                '[name="wishlist_action"]'
            )?.value;

            confirmButton.textContent = action === 'clear'
                ? 'Clear Wishlist'
                : 'Remove Product';

            dialog.showModal();
        });
    });

    cancelButton?.addEventListener('click', () => dialog?.close());

    dialog?.addEventListener('close', () => {
        pendingForm = null;
    });

    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    confirmButton?.addEventListener('click', () => {
        if (!pendingForm) return;

        const form = pendingForm;
        form.dataset.confirmed = 'true';

        dialog.close();
        form.requestSubmit();
    });

    /* ==========================================
       ANIMATION HELPERS
       ========================================== */

    function restartAnimation(element, className) {
        if (!element) return;

        element.classList.remove(className);
        void element.offsetWidth;
        element.classList.add(className);

        element.addEventListener(
            'animationend',
            () => element.classList.remove(className),
            { once: true }
        );
    }

    function animateCart() {
        restartAnimation(
            document.getElementById('cart-icon'),
            'is-shaking'
        );

        restartAnimation(
            document.querySelector('.cart-count'),
            'is-bumping'
        );
    }

    /* ==========================================
       ADD TO CART FROM WISHLIST
       ========================================== */

    document.querySelectorAll('.wishlist-cart-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = form.querySelector(
                'button[type="submit"]'
            );

            if (!button || button.disabled) return;

            const label = button.querySelector('span');
            const originalText = label?.textContent || 'Add to Cart';

            button.disabled = true;

            try {
                const data = new FormData(form);
                data.set('ajax', '1');

                const response = await fetch(form.action, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json'
                    }
                });

                const result = await response.json();

                if (!response.ok || result.ok !== true) {
                    throw new Error(
                        result.error || 'Could not add product.'
                    );
                }

                const count = Number(result.count);

                if (Number.isFinite(count) && count >= 0) {
                    document.querySelectorAll('.cart-count').forEach(
                        (counter) => {
                            counter.textContent = String(count);
                        }
                    );
                }

                animateCart();

            } catch (error) {
                console.error('Wishlist cart error:', error);

                if (label) {
                    label.textContent = 'Try again';
                }

            } finally {
                setTimeout(() => {
                    if (label) {
                        label.textContent = originalText;
                    }

                    button.disabled = false;
                }, 800);
            }
        });
    });

    /* ==========================================
       INITIAL SAVED STATE FROM PHP
       ========================================== */

    const hasServerIds = Array.isArray(
        window.flashFoodSavedIds
    );

    const savedIds = new Set(
        hasServerIds
            ? window.flashFoodSavedIds.map(String)
            : []
    );

    const pendingProducts = new Set();

    /* ==========================================
       UPDATE ONE HEART
       ========================================== */

    function setFormState(form, saved) {
        const button = form.querySelector(
            'button[type="submit"]'
        );

        if (!button) return;

        button.classList.toggle('is-saved', saved);

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

        let actionInput = form.querySelector(
            'input[name="wishlist_action"]'
        );

        if (!actionInput) {
            actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'wishlist_action';
            form.appendChild(actionInput);
        }

        actionInput.value = saved ? 'remove' : 'add';
    }

    /* ==========================================
       INITIALIZE HEART
       ========================================== */

    function initializeFavoriteForm(form) {
        const id = form.querySelector(
            '[name="product_id"]'
        )?.value;

        const button = form.querySelector(
            'button[type="submit"]'
        );

        if (!id || !button) return;

        const saved = hasServerIds
            ? savedIds.has(id)
            : (
                savedIds.has(id) ||
                button.classList.contains('is-saved')
            );

        if (saved) {
            savedIds.add(id);
        }

        setFormState(form, saved);
    }

    /* ==========================================
       UPDATE ALL HEARTS FOR SAME PRODUCT
       ========================================== */

    function updateFavoriteHearts(id, saved) {
        if (saved) {
            savedIds.add(id);
        } else {
            savedIds.delete(id);
        }

        document.querySelectorAll('form.add-to-fav').forEach(
            (form) => {
                const currentId = form.querySelector(
                    '[name="product_id"]'
                )?.value;

                if (currentId === id) {
                    setFormState(form, saved);
                }
            }
        );
    }

    /* ==========================================
       HEADER WISHLIST ANIMATION
       ========================================== */

    function animateWishlistHeader() {
        const icon = document.getElementById(
            'wishlist-icon'
        );

        if (icon) {
            restartAnimation(icon, 'is-shaking');
        } else {
            restartAnimation(
                document.getElementById('wishlist-link'),
                'wishlist-arrival'
            );
        }
    }

    /* ==========================================
       FLYING HEART
       ========================================== */

    function animateFlyingHeart(button) {
        if (
            window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            ).matches
        ) {
            return;
        }

        const destination =
            document.getElementById('wishlist-icon') ||
            document.getElementById('wishlist-link');

        const svg = button.querySelector('svg');

        if (!destination || !svg) {
            animateWishlistHeader();
            return;
        }

        const start = button.getBoundingClientRect();
        const end = destination.getBoundingClientRect();

        const startX = start.left + start.width / 2;
        const startY = start.top + start.height / 2;
        const endX = end.left + end.width / 2;
        const endY = end.top + end.height / 2;

        const flyingHeart = document.createElement('span');

        flyingHeart.className = 'wishlist-flying-heart';

        flyingHeart.setAttribute(
            'aria-hidden',
            'true'
        );

        flyingHeart.appendChild(svg.cloneNode(true));

        flyingHeart.style.left = `${startX}px`;
        flyingHeart.style.top = `${startY}px`;

        flyingHeart.style.setProperty(
            '--fly-x',
            `${endX - startX}px`
        );

        flyingHeart.style.setProperty(
            '--fly-y',
            `${endY - startY}px`
        );

        document.body.appendChild(flyingHeart);

        flyingHeart.addEventListener(
            'animationend',
            () => flyingHeart.remove(),
            { once: true }
        );

        setTimeout(() => flyingHeart.remove(), 1200);
        setTimeout(animateWishlistHeader, 700);
    }

    /* ==========================================
       INITIALIZE ALL EXISTING HEARTS
       ========================================== */

    document.querySelectorAll(
        'form.add-to-fav'
    ).forEach(initializeFavoriteForm);

    /* ==========================================
       DYNAMIC RELATED PRODUCTS
       ========================================== */

    const relatedProducts = document.getElementById(
        'relatedProducts'
    );

    if (relatedProducts) {
        const observer = new MutationObserver(() => {
            relatedProducts.querySelectorAll(
                'form.add-to-fav'
            ).forEach(initializeFavoriteForm);
        });

        observer.observe(relatedProducts, {
            childList: true,
            subtree: true
        });
    }

    /* ==========================================
       ADD / REMOVE WISHLIST - AJAX
       ========================================== */

    document.addEventListener('submit', async (event) => {
        const form = event.target;

        if (
            !(form instanceof HTMLFormElement) ||
            !form.matches('form.add-to-fav')
        ) {
            return;
        }

        event.preventDefault();

        const button = form.querySelector(
            'button[type="submit"]'
        );

        const id = form.querySelector(
            '[name="product_id"]'
        )?.value;

        if (!button || !id || pendingProducts.has(id)) {
            return;
        }

        const wasSaved = button.classList.contains(
            'is-saved'
        );

        const action = wasSaved ? 'remove' : 'add';

        pendingProducts.add(id);
        button.disabled = true;

        try {
            const data = new FormData(form);

            data.set('ajax', '1');
            data.set('wishlist_action', action);

            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (!response.ok || result.ok !== true) {
                throw new Error(
                    result.error ||
                    result.message ||
                    'Could not update wishlist.'
                );
            }

            const saved = typeof result.saved === 'boolean'
                ? result.saved
                : action === 'add'
                    ? true
                    : null;

            if (saved === null) {
                throw new Error(
                    'API must return saved:false when removing.'
                );
            }

            updateFavoriteHearts(id, saved);

            restartAnimation(button, 'heart-pop');

            if (saved) {
                animateFlyingHeart(button);
            } else {
                animateWishlistHeader();
            }

        } catch (error) {
            console.error('Wishlist error:', error);

            // No alert, popup or toast.
            // Keep previous state on failure.

        } finally {
            pendingProducts.delete(id);
            button.disabled = false;
        }
    });
});
