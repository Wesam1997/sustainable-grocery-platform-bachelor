/* ==========================================
   FLASH FOOD - PRODUCT DETAILS
   DESIGN 2
   ========================================== */

'use strict';

(() => {

    const $ = (id) => document.getElementById(id);


    /* ======================================
       FORMAT PRICE
       ====================================== */

    function formatPrice(value) {

        if (
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return 'Price not available';
        }

        const price = Number(value);

        if (
            !Number.isFinite(price) ||
            price < 0
        ) {
            return 'Price not available';
        }

        return `${price.toLocaleString('da-DK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })} kr.`;
    }


    /* ======================================
       QUANTITY CONTROLS
       ====================================== */

    function setupQuantity() {

        const input = $('quantity');
        const minus = $('decreaseQuantity');
        const plus = $('increaseQuantity');

        if (!input || !minus || !plus) {
            return;
        }

        function normalize() {

            const number = Number(input.value);

            const safe = Number.isFinite(number)
                ? Math.trunc(number)
                : 1;

            const quantity = Math.max(
                1,
                Math.min(99, safe)
            );

            input.value = String(quantity);

            return quantity;
        }

        minus.addEventListener('click', () => {
            input.value = String(
                Math.max(1, normalize() - 1)
            );
        });

        plus.addEventListener('click', () => {
            input.value = String(
                Math.min(99, normalize() + 1)
            );
        });

        input.addEventListener(
            'change',
            normalize
        );
    }


    /* ======================================
       CREATE HIDDEN INPUT
       ====================================== */

    function makeHiddenInput(name, value) {

        const input = document.createElement('input');

        input.type = 'hidden';
        input.name = name;
        input.value = String(value);

        return input;
    }


    /* ======================================
       IMAGE PLACEHOLDER
       ====================================== */

    function makePlaceholder() {

        const placeholder = document.createElement(
            'div'
        );

        placeholder.className = 'related-placeholder';

        placeholder.textContent = 'Image not available';

        return placeholder;
    }


    /* ======================================
       RELATED PRODUCTS
       ====================================== */

    function showRelatedProducts(products, currentId) {

        const section = $('relatedSection');
        const container = $('relatedProducts');

        if (!section || !container) {
            return;
        }

        container.replaceChildren();

        const csrfToken =
            section.dataset.csrfToken || '';


        for (const product of products) {

            if (
                !product ||
                !/^[1-9]\d*$/.test(String(product.id))
            ) {
                continue;
            }

            if (
                Number(product.id) === Number(currentId)
            ) {
                continue;
            }


            /* PRODUCT CARD */

            const card = document.createElement('article');

            card.className = 'related-card';


            /* PRODUCT LINK */

            const imageLink = document.createElement('a');

            imageLink.className = 'related-card-visual';

            imageLink.href =
                `product.php?id=${encodeURIComponent(
                    product.id
                )}`;

            imageLink.setAttribute(
                'aria-label',
                `View ${product.title || 'product'}`
            );


            /* PRODUCT IMAGE */

            if (
                typeof product.image === 'string' &&
                product.image.trim()
            ) {

                const filename = product.image
                    .split(/[\\/]/)
                    .pop();

                const image = document.createElement('img');

                image.src =
                    `../Style/Images/products/` +
                    encodeURIComponent(filename);

                image.alt =
                    product.title || 'Related product';

                image.loading = 'lazy';

                image.addEventListener(
                    'error',
                    () => {
                        image.replaceWith(
                            makePlaceholder()
                        );
                    },
                    { once: true }
                );

                imageLink.append(image);

            } else {

                imageLink.append(
                    makePlaceholder()
                );

            }

            card.append(imageLink);


            /* ==================================
               RELATED PRODUCT WISHLIST
               ================================== */

            const favorite = document.createElement(
                'form'
            );

            favorite.className =
                'add-to-fav related-favourite';

            favorite.method = 'post';

            favorite.action = '../Api/AddFav.php';

            favorite.append(
                makeHiddenInput(
                    'product_id',
                    product.id
                )
            );

            favorite.append(
                makeHiddenInput(
                    'csrf_token',
                    csrfToken
                )
            );


            const favoriteButton =
                document.createElement('button');

            favoriteButton.type = 'submit';

            favoriteButton.setAttribute(
                'aria-label',
                `Add ${product.title || 'product'} to wishlist`
            );

            favoriteButton.innerHTML = `
                <svg
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
            `;

            favorite.append(favoriteButton);

            card.append(favorite);


            /* ==================================
               PRODUCT INFORMATION
               ================================== */

            const info = document.createElement('div');

            info.className =
                'related-card-information';


            /* TITLE */

            const title = document.createElement('h3');

            const titleLink = document.createElement('a');

            titleLink.href = imageLink.href;

            titleLink.textContent =
                product.title || 'Product';

            title.append(titleLink);


            /* MERCHANT */

            const merchant = document.createElement('p');

            merchant.textContent =
                product.merchant || '';


            /* PRICE */

            const price = document.createElement('strong');

            price.textContent =
                formatPrice(product.price);


            info.append(
                title,
                merchant,
                price
            );

            card.append(info);

            container.append(card);
        }


        /* SHOW SECTION IF PRODUCTS EXIST */

        section.hidden =
            container.children.length === 0;
    }


    /* ======================================
       LOAD RELATED PRODUCTS
       ====================================== */

    async function loadRelatedProducts(productId) {

        try {

            const response = await fetch(
                `../Api/product_details.php?id=` +
                encodeURIComponent(productId),
                {
                    headers: {
                        Accept: 'application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error(
                    `HTTP ${response.status}`
                );
            }

            const data = await response.json();

            if (
                data.success === true &&
                Array.isArray(data.relatedProducts)
            ) {

                showRelatedProducts(
                    data.relatedProducts,
                    productId
                );

            }

        } catch (error) {

            console.warn(
                'Related products could not be loaded:',
                error
            );

        }
    }


    /* ======================================
       INITIALIZE PRODUCT PAGE
       ====================================== */

    function initialize() {

        const article = $('productDetails');

        if (!article) {
            return;
        }

        const productId =
            article.dataset.productId;

        if (
            !/^[1-9]\d*$/.test(String(productId))
        ) {
            return;
        }


        /* PRODUCT IMAGE ERROR HANDLING */

        const image = $('productImage');

        const unavailable = $('imageUnavailable');

        if (image && unavailable) {

            const showFallback = () => {
                image.hidden = true;
                unavailable.hidden = false;
            };

            image.addEventListener(
                'error',
                showFallback,
                { once: true }
            );

            if (
                image.complete &&
                image.naturalWidth === 0
            ) {
                showFallback();
            }
        }


        /* QUANTITY */

        setupQuantity();


        /* RELATED PRODUCTS */

        loadRelatedProducts(productId);


        /*
         * CO2e is calculated and rendered
         * by PHP in product.php.
         *
         * Never overwrite that value here.
         */

    }


    /* ======================================
       DOM READY
       ====================================== */

    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            initialize,
            { once: true }
        );

    } else {

        initialize();

    }

})();
