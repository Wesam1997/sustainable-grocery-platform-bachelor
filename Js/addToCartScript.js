
/* ==========================================
   FLASH FOOD - ADD TO CART
   ========================================== */

'use strict';

document.addEventListener('submit', async (event) => {

    const form = event.target;

    if (
        !(form instanceof HTMLFormElement) ||
        !form.matches('form.add-to-cart')
    ) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector(
        'button[type="submit"]'
    );

    if (!button || button.disabled) {
        return;
    }

    // Only change the text, not the SVG icon.
    const label = button.querySelector('span') || button;

    const data = new FormData(form);

    data.set('ajax', '1');

    button.disabled = true;

    label.textContent = 'Adding...';


    try {

        const response = await fetch(form.action, {
            method: 'POST',
            body: data,
            headers: {
                Accept: 'application/json'
            }
        });

        const result = await response.json();

        if (!response.ok || result?.ok !== true) {
            throw new Error(
                result?.error || 'Could not add the item.'
            );
        }


        /* UPDATE CART COUNT */

        if (result.count !== undefined) {

            document.querySelectorAll(
                '.cart-count'
            ).forEach((element) => {

                element.textContent =
                    String(result.count);

            });

        }


        /* ANIMATE CART ICON */

        const cartIcon = document.getElementById(
            'cart-icon'
        );

        if (cartIcon) {

            cartIcon.classList.remove('is-shaking');

            void cartIcon.getBoundingClientRect();

            cartIcon.classList.add('is-shaking');

            cartIcon.addEventListener(
                'animationend',
                () => {
                    cartIcon.classList.remove(
                        'is-shaking'
                    );
                },
                { once: true }
            );

        }


        /* SUCCESS FEEDBACK */

        label.textContent = 'Added to cart';

        window.setTimeout(() => {

            label.textContent = 'Add to cart';

            button.disabled = false;

        }, 1200);


    } catch (error) {

        label.textContent = 'Add to cart';

        button.disabled = false;

        alert(
            'Could not add the item to your cart: ' +
            error.message
        );

    }

});
