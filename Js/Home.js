
/* ==========================================
   FLASH FOOD - HOME.JS
   ========================================== */

(() => {
    'use strict';


    /* ==========================================
       1. PRICE AND WEIGHT SLIDERS
       ========================================== */

    function setupDualRange(
        minId,
        maxId,
        minLabelId,
        maxLabelId,
        rangeId
    ) {
        const minInput = document.getElementById(minId);
        const maxInput = document.getElementById(maxId);

        const minLabel = document.getElementById(minLabelId);
        const maxLabel = document.getElementById(maxLabelId);

        const range = document.getElementById(rangeId);

        if (
            !minInput ||
            !maxInput ||
            !minLabel ||
            !maxLabel ||
            !range
        ) {
            return;
        }

        // Prevent duplicate event listeners
        if (range.dataset.flashRangeReady === 'true') {
            return;
        }

        range.dataset.flashRangeReady = 'true';

        const absoluteMin = Number(minInput.min);
        const absoluteMax = Number(maxInput.max);

        const total = absoluteMax - absoluteMin;


        function update(changed) {
            let min = Number(minInput.value);
            let max = Number(maxInput.value);

            // Minimum cannot exceed maximum
            if (min > max) {
                if (changed === 'min') {
                    max = min;
                } else {
                    min = max;
                }
            }

            minInput.value = String(min);
            maxInput.value = String(max);

            const minPercent = total > 0
                ? ((min - absoluteMin) / total) * 100
                : 0;

            const maxPercent = total > 0
                ? ((max - absoluteMin) / total) * 100
                : 0;

            // Update selected slider track
            range.style.setProperty(
                '--min-pos',
                `${minPercent}%`
            );

            range.style.setProperty(
                '--max-pos',
                `${maxPercent}%`
            );

            // Update visible values
            minLabel.textContent = String(min);
            maxLabel.textContent = String(max);

            // Ensure overlapping handles remain usable
            minInput.style.zIndex =
                minPercent > 70 ? '5' : '4';

            maxInput.style.zIndex =
                minPercent > 70 ? '4' : '5';
        }


        minInput.addEventListener('input', () => {
            update('min');
        });

        maxInput.addEventListener('input', () => {
            update('max');
        });


        // Update values after form reset
        minInput.form?.addEventListener('reset', () => {
            setTimeout(() => update(), 0);
        });

        // Initial values
        update();
    }



    /* ==========================================
       2. SLIDE-IN FILTER DRAWER
       ========================================== */

    function setupFilterIcon() {
        const button = document.getElementById(
            'home-filter-toggle'
        );

        const filter = document.getElementById(
            'product-filters'
        );

        const sidebar = document.querySelector(
            '.catalog-sidebar'
        );

        const backdrop = document.getElementById(
            'filter-backdrop'
        );

        const closeButton = document.getElementById(
            'close-filters'
        );

        const layout = document.querySelector(
            '.catalog-layout'
        );


        if (
            !button ||
            !filter ||
            !sidebar ||
            !backdrop ||
            !closeButton ||
            !layout
        ) {
            return;
        }


        // Prevent duplicate initialization
        if (button.dataset.flashFilterReady === 'true') {
            return;
        }

        button.dataset.flashFilterReady = 'true';


        /* ======================================
           UPDATE FILTER STATE
           ====================================== */

        function sync() {
            const isOpen = filter.open;

            // Show or hide the drawer
            sidebar.hidden = !isOpen;

            // Show or hide dark background
            backdrop.hidden = !isOpen;

            // Prevent scrolling behind the drawer
            document.body.classList.toggle(
                'filter-drawer-open',
                isOpen
            );

            // Keep catalog layout stable
            layout.classList.toggle(
                'filters-closed',
                !isOpen
            );

            // Accessibility
            button.setAttribute(
                'aria-expanded',
                String(isOpen)
            );

            button.setAttribute(
                'aria-label',
                isOpen
                    ? 'Close filters'
                    : 'Open filters'
            );

            button.title = isOpen
                ? 'Close filters'
                : 'Open filters';
        }



        /* ======================================
           OPEN OR CLOSE FILTER
           ====================================== */

        function setOpen(isOpen, moveFocus = true) {
            filter.open = isOpen;

            sync();

            if (!moveFocus) {
                return;
            }

            if (isOpen) {
                closeButton.focus({
                    preventScroll: true
                });
            } else {
                button.focus({
                    preventScroll: true
                });
            }
        }



        /* ======================================
           FILTER BUTTON
           ====================================== */

        button.addEventListener('click', () => {
            setOpen(!filter.open);
        });



        /* ======================================
           CLOSE BUTTON
           ====================================== */

        closeButton.addEventListener('click', () => {
            setOpen(false);
        });



        /* ======================================
           CLICK OUTSIDE FILTER
           ====================================== */

        backdrop.addEventListener('click', () => {
            setOpen(false);
        });



        /* ======================================
           DETAILS STATE
           ====================================== */

        filter.addEventListener('toggle', sync);



        /* ======================================
           KEYBOARD ACCESSIBILITY
           ====================================== */

        document.addEventListener('keydown', event => {
            if (!filter.open) {
                return;
            }


            // Close filter with Escape
            if (event.key === 'Escape') {
                event.preventDefault();

                setOpen(false);

                return;
            }


            // Keep keyboard focus inside filter
            if (event.key !== 'Tab') {
                return;
            }


            const focusable = Array.from(
                sidebar.querySelectorAll(
                    'button:not([disabled]), ' +
                    'a[href], ' +
                    'input:not([disabled]), ' +
                    'select:not([disabled]), ' +
                    '[tabindex]:not([tabindex="-1"])'
                )
            ).filter(element => {
                return element.getClientRects().length > 0;
            });


            if (focusable.length === 0) {
                event.preventDefault();
                return;
            }


            const first = focusable[0];
            const last = focusable[focusable.length - 1];


            // If focus is outside, move it into drawer
            if (!sidebar.contains(document.activeElement)) {
                event.preventDefault();

                first.focus();

                return;
            }


            // Shift + Tab from first element
            if (
                event.shiftKey &&
                document.activeElement === first
            ) {
                event.preventDefault();

                last.focus();

                return;
            }


            // Tab from last element
            if (
                !event.shiftKey &&
                document.activeElement === last
            ) {
                event.preventDefault();

                first.focus();
            }
        });



        /* ======================================
           INITIALIZE FILTER
           ====================================== */

        layout.classList.add('filters-enhanced');

        // Start with filter closed
        setOpen(false, false);
    }



    /* ==========================================
       3. CLIMATE INFORMATION TOOLTIPS
       ========================================== */

    function setupClimateHover() {
        let active = null;
        let closeTimer = null;



        /* ======================================
           HIDE TOOLTIP
           ====================================== */

        function hide() {
            clearTimeout(closeTimer);

            if (!active) {
                return;
            }

            active.tooltip.hidden = true;

            active.button.setAttribute(
                'aria-expanded',
                'false'
            );

            active.pinned = false;
            active = null;
        }



        /* ======================================
           POSITION TOOLTIP
           ====================================== */

        function position() {
            if (!active) {
                return;
            }

            const rect = active.button.getBoundingClientRect();
            const tooltip = active.tooltip;

            const left = Math.max(
                16,
                Math.min(
                    rect.right - tooltip.offsetWidth,
                    window.innerWidth -
                        tooltip.offsetWidth - 16
                )
            );

            const above =
                rect.top - tooltip.offsetHeight - 8;

            const top = above >= 16
                ? above
                : rect.bottom + 8;


            tooltip.style.left = `${left}px`;

            tooltip.style.top = `${
                Math.max(
                    16,
                    Math.min(
                        top,
                        window.innerHeight -
                            tooltip.offsetHeight - 16
                    )
                )
            }px`;
        }



        /* ======================================
           SHOW TOOLTIP
           ====================================== */

        function show(item) {
            clearTimeout(closeTimer);

            if (active !== item) {
                hide();
            }

            active = item;

            item.tooltip.hidden = false;

            item.button.setAttribute(
                'aria-expanded',
                'true'
            );

            position();
        }



        /* ======================================
           DELAY TOOLTIP CLOSING
           ====================================== */

        function scheduleHide(item) {
            clearTimeout(closeTimer);

            closeTimer = setTimeout(() => {
                if (
                    active === item &&
                    !item.pinned &&
                    !item.hovered &&
                    document.activeElement !== item.button
                ) {
                    hide();
                }
            }, 150);
        }



        /* ======================================
           INITIALIZE CLIMATE TOOLTIPS
           ====================================== */

        document.querySelectorAll(
            '.home-climate-help'
        ).forEach(help => {

            if (
                help.dataset.flashClimateReady === 'true'
            ) {
                return;
            }

            const button = help.querySelector(
                '.home-climate-help-button'
            );

            const tooltip = help.querySelector(
                '.home-climate-tooltip'
            );

            if (!button || !tooltip) {
                return;
            }

            help.dataset.flashClimateReady = 'true';


            const item = {
                button,
                tooltip,
                hovered: false,
                pinned: false
            };


            // Move tooltip outside product card
            // so it is not clipped by overflow:hidden
            document.body.append(tooltip);



            /* ==================================
               MOUSE HOVER
               ================================== */

            [help, tooltip].forEach(target => {

                target.addEventListener(
                    'pointerenter',
                    event => {

                        if (event.pointerType === 'touch') {
                            return;
                        }

                        item.hovered = true;

                        show(item);
                    }
                );


                target.addEventListener(
                    'pointerleave',
                    event => {

                        if (event.pointerType === 'touch') {
                            return;
                        }

                        item.hovered = false;

                        scheduleHide(item);
                    }
                );
            });



            /* ==================================
               KEYBOARD FOCUS
               ================================== */

            button.addEventListener('focus', () => {
                show(item);
            });

            button.addEventListener('blur', () => {
                scheduleHide(item);
            });



            /* ==================================
               CLICK TO PIN TOOLTIP
               ================================== */

            button.addEventListener('click', () => {
                if (
                    active === item &&
                    item.pinned
                ) {
                    hide();
                } else {
                    show(item);

                    item.pinned = true;
                }
            });

        });



        /* ======================================
           CLOSE TOOLTIP WITH ESCAPE
           ====================================== */

        document.addEventListener('keydown', event => {
            if (
                event.key === 'Escape' &&
                active
            ) {
                event.preventDefault();

                hide();
            }
        });



        /* ======================================
           CLOSE TOOLTIP OUTSIDE
           ====================================== */

        document.addEventListener(
            'pointerdown',
            event => {

                if (
                    active &&
                    !active.button.contains(event.target) &&
                    !active.tooltip.contains(event.target)
                ) {
                    hide();
                }
            }
        );



        /* ======================================
           REPOSITION ON RESIZE OR SCROLL
           ====================================== */

        window.addEventListener('resize', position);

        window.addEventListener(
            'scroll',
            position,
            true
        );
    }



    /* ==========================================
       4. ADD TO CART - AJAX
       ========================================== */

    function setupAddToCart() {

        document.querySelectorAll(
            '.home-add-to-cart'
        ).forEach(form => {

            // Prevent duplicate listeners
            if (
                form.dataset.flashCartReady === 'true'
            ) {
                return;
            }

            const button = form.querySelector(
                'button[type="submit"]'
            );

            const label = form.querySelector(
                '.home-cart-button-label'
            );

            const message = form.querySelector(
                '.home-cart-message'
            );


            if (!button || !message) {
                return;
            }

            form.dataset.flashCartReady = 'true';



            /* ==================================
               HANDLE CART SUBMISSION
               ================================== */

            form.addEventListener(
                'submit',
                async event => {

                    event.preventDefault();


                    if (form.dataset.busy === 'true') {
                        return;
                    }


                    form.dataset.busy = 'true';

                    button.disabled = true;


                    if (label) {
                        label.textContent = 'Adding…';
                    }


                    message.textContent = '';

                    delete message.dataset.state;


                    const body = new FormData(form);

                    body.set('ajax', '1');



                    /* ==========================
                       SEND REQUEST
                       ========================== */

                    try {
                        const response = await fetch(
                            form.action,
                            {
                                method: 'POST',
                                body,
                                credentials: 'same-origin',
                                headers: {
                                    Accept: 'application/json'
                                }
                            }
                        );


                        const data = await response.json();


                        if (
                            !response.ok ||
                            data.ok !== true
                        ) {
                            throw new Error(
                                data.message ||
                                data.error ||
                                'Could not add the product. Please try again.'
                            );
                        }



                        /* ======================
                           UPDATE CART COUNT
                           ====================== */

                        if (
                            data.count !== undefined &&
                            data.count !== null
                        ) {
                            const count = Number(data.count);

                            if (
                                Number.isInteger(count) &&
                                count >= 0
                            ) {
                                document.querySelectorAll(
                                    '.cart-count'
                                ).forEach(counter => {

                                    counter.textContent =
                                        String(count);
                                });
                            }
                        }



                        /* ======================
                           SUCCESS MESSAGE
                           ====================== */

                        message.dataset.state = 'success';

                        message.textContent = 'Added to cart.';



                        /* ======================
                           CART ICON ANIMATION
                           ====================== */

                        const icon = document.getElementById(
                            'cart-icon'
                        );

                        const reducedMotion =
                            window.matchMedia(
                                '(prefers-reduced-motion: reduce)'
                            ).matches;


                        if (icon && !reducedMotion) {

                            icon.classList.remove(
                                'is-shaking'
                            );

                            // Restart animation
                            void icon.offsetWidth;

                            icon.classList.add(
                                'is-shaking'
                            );


                            icon.addEventListener(
                                'animationend',
                                () => {
                                    icon.classList.remove(
                                        'is-shaking'
                                    );
                                },
                                { once: true }
                            );
                        }



                    /* ==========================
                       ERROR HANDLING
                       ========================== */

                    } catch (error) {

                        message.dataset.state = 'error';


                        if (error instanceof SyntaxError) {

                            message.textContent =
                                'The server returned an unexpected response. Check your cart before trying again.';

                        } else if (error instanceof TypeError) {

                            message.textContent =
                                'Connection problem. Check your cart before trying again.';

                        } else {

                            message.textContent =
                                error instanceof Error
                                    ? error.message
                                    : 'Could not add the product. Please try again.';
                        }



                    /* ==========================
                       RESTORE BUTTON
                       ========================== */

                    } finally {

                        form.dataset.busy = 'false';

                        button.disabled = false;


                        if (label) {
                            label.textContent = 'Add to cart';
                        }
                    }
                }
            );

        });
    }



    /* ==========================================
       5. INITIALIZE HOME PAGE
       ========================================== */

    function initialize() {

        // Slide-in filter
        setupFilterIcon();


        // Price range
        setupDualRange(
            'priceMin',
            'priceMax',
            'priceMinLabel',
            'priceMaxLabel',
            'priceRange'
        );


        // Weight range
        setupDualRange(
            'weightMin',
            'weightMax',
            'weightMinLabel',
            'weightMaxLabel',
            'weightRange'
        );


        // Climate explanations
        setupClimateHover();


        // Shopping cart
        setupAddToCart();
    }



    /* ==========================================
       6. WAIT FOR DOM
       ========================================== */

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
