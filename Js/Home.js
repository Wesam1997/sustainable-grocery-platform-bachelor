'use strict';

document.addEventListener('DOMContentLoaded', () => {
    setupDualRange({
        containerId: 'priceRange',
        minInputId: 'priceMin',
        maxInputId: 'priceMax',
        minLabelId: 'priceMinLabel',
        maxLabelId: 'priceMaxLabel',
        minimumDistance: 1
    });

    setupDualRange({
        containerId: 'weightRange',
        minInputId: 'weightMin',
        maxInputId: 'weightMax',
        minLabelId: 'weightMinLabel',
        maxLabelId: 'weightMaxLabel',
        minimumDistance: 50
    });

    initialiseFilterPanel();
});

/*
 * Produktbillede, navn og "See more" bruger links
 * i home.php. Navigationen kræver ikke JavaScript.
 */

function setupDualRange(options) {
    const container = document.getElementById(options.containerId);
    const minInput = document.getElementById(options.minInputId);
    const maxInput = document.getElementById(options.maxInputId);
    const minLabel = document.getElementById(options.minLabelId);
    const maxLabel = document.getElementById(options.maxLabelId);

    if (
        !container ||
        !minInput ||
        !maxInput ||
        !minLabel ||
        !maxLabel
    ) {
        return;
    }

    const lower = Number(minInput.min);
    const upper = Number(minInput.max);

    if (
        !Number.isFinite(lower) ||
        !Number.isFinite(upper) ||
        upper <= lower
    ) {
        return;
    }

    const gap = Math.min(
        options.minimumDistance,
        upper - lower
    );

    function clamp(value, minimum, maximum) {
        return Math.min(maximum, Math.max(minimum, value));
    }

    function update(changedInput = null) {
        let low = clamp(
            Number(minInput.value),
            lower,
            upper - gap
        );

        let high = clamp(
            Number(maxInput.value),
            lower + gap,
            upper
        );

        if (high - low < gap) {
            if (changedInput === maxInput) {
                low = high - gap;
            } else {
                high = low + gap;
            }
        }

        minInput.value = String(low);
        maxInput.value = String(high);

        const minPosition =
            ((low - lower) / (upper - lower)) * 100;

        const maxPosition =
            ((high - lower) / (upper - lower)) * 100;

        container.style.setProperty(
            '--min-pos',
            `${minPosition}%`
        );

        container.style.setProperty(
            '--max-pos',
            `${maxPosition}%`
        );

        minLabel.textContent = String(low);
        maxLabel.textContent = String(high);

        minInput.setAttribute('aria-valuetext', String(low));
        maxInput.setAttribute('aria-valuetext', String(high));
    }

    minInput.addEventListener('input', () => {
        update(minInput);
    });

    maxInput.addEventListener('input', () => {
        update(maxInput);
    });

    update();
}

function initialiseFilterPanel() {
    const filter = document.querySelector('.filter');

    if (!filter) {
        return;
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !filter.open) {
            return;
        }

        const focusWasInside = filter.contains(
            document.activeElement
        );

        filter.open = false;

        if (focusWasInside) {
            filter.querySelector('summary')?.focus();
        }
    });
}