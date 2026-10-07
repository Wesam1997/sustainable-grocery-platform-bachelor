(() => {
    'use strict';

    function setupDualRange(minId, maxId, minLabelId, maxLabelId, rangeId) {
        const minInput = document.getElementById(minId);
        const maxInput = document.getElementById(maxId);
        const minLabel = document.getElementById(minLabelId);
        const maxLabel = document.getElementById(maxLabelId);
        const range = document.getElementById(rangeId);
        if (!minInput || !maxInput || !minLabel || !maxLabel || !range) return;
        if (range.dataset.flashRangeReady === 'true') return;
        range.dataset.flashRangeReady = 'true';

        const absoluteMin = Number(minInput.min);
        const absoluteMax = Number(maxInput.max);
        const total = absoluteMax - absoluteMin;

        function update(changed) {
            let min = Number(minInput.value);
            let max = Number(maxInput.value);
            if (min > max) {
                if (changed === 'min') max = min;
                else min = max;
            }
            minInput.value = String(min);
            maxInput.value = String(max);
            const minPercent = total > 0 ? (min - absoluteMin) / total * 100 : 0;
            const maxPercent = total > 0 ? (max - absoluteMin) / total * 100 : 0;
            range.style.setProperty('--min-pos', `${minPercent}%`);
            range.style.setProperty('--max-pos', `${maxPercent}%`);
            minLabel.textContent = String(min);
            maxLabel.textContent = String(max);
            minInput.style.zIndex = minPercent > 70 ? '5' : '4';
            maxInput.style.zIndex = minPercent > 70 ? '4' : '5';
        }
        minInput.addEventListener('input', () => update('min'));
        maxInput.addEventListener('input', () => update('max'));
        minInput.form?.addEventListener('reset', () => setTimeout(update, 0));
        update();
    }

    function setupFilterIcon() {
        const button = document.getElementById('home-filter-toggle');
        const filter = document.getElementById('product-filters');
        const sidebar = filter?.closest('.catalog-sidebar');
        const layout = filter?.closest('.catalog-layout');
        if (!button || !filter || !sidebar || !layout) return;
        if (button.dataset.flashFilterReady === 'true') return;
        button.dataset.flashFilterReady = 'true';

        function sync() {
            sidebar.hidden = !filter.open;
            layout.classList.toggle('filters-closed', !filter.open);
            button.setAttribute('aria-expanded', String(filter.open));
            button.setAttribute('aria-label', filter.open ? 'Hide filters' : 'Show filters');
            button.title = filter.open ? 'Hide filters' : 'Show filters';
        }
        button.addEventListener('click', () => {
            filter.open = !filter.open;
            sync();
        });
        filter.addEventListener('toggle', sync);
        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !filter.open) return;
            if (!sidebar.contains(document.activeElement) && document.activeElement !== button) return;
            filter.open = false;
            sync();
            button.focus();
        });
        layout.classList.add('filters-enhanced');
        sync();
    }

    function initialize() {
        setupFilterIcon();
        setupDualRange('priceMin', 'priceMax', 'priceMinLabel', 'priceMaxLabel', 'priceRange');
        setupDualRange('weightMin', 'weightMax', 'weightMinLabel', 'weightMaxLabel', 'weightRange');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();


(() => {
    'use strict';
    document.querySelectorAll('.home-add-to-cart').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.busy === 'true') return;
            const button = form.querySelector('button[type="submit"]');
            const label = form.querySelector('.home-cart-button-label');
            const message = form.querySelector('.home-cart-message');
            form.dataset.busy = 'true';
            button.disabled = true;
            label.textContent = 'Adding…';
            message.textContent = '';
            delete message.dataset.state;
            const body = new FormData(form);
            body.set('ajax', '1');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                const data = await response.json();
                if (!response.ok || data.ok !== true) {
                    throw new Error(data.message || data.error || 'Could not add the product. Please try again.');
                }
                const count = Number(data.count);
                if (Number.isInteger(count) && count >= 0) {
                    document.querySelectorAll('.cart-count').forEach(counter => {
                        counter.textContent = String(count);
                    });
                }
                message.dataset.state = 'success';
                message.textContent = 'Added to cart.';
                const icon = document.getElementById('cart-icon');
                if (icon && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    icon.animate([
                        { transform: 'rotate(0deg)' },
                        { transform: 'rotate(-12deg)' },
                        { transform: 'rotate(12deg)' },
                        { transform: 'rotate(0deg)' }
                    ], { duration: 350 });
                }
            } catch (error) {
                message.dataset.state = 'error';
                message.textContent = error instanceof SyntaxError
                    ? 'The server returned an unexpected response. Check your cart before trying again.'
                    : (error instanceof TypeError
                        ? 'Connection problem. Check your cart before trying again.'
                        : error.message);
            } finally {
                form.dataset.busy = 'false';
                button.disabled = false;
                label.textContent = 'Add to cart';
            }
        });
    });
})();
