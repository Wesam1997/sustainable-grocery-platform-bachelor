'use strict';

document.addEventListener('DOMContentLoaded', () => {
    /*
     * CO2e calculator elements
     */
    const slider =
        document.getElementById('co2-slider');

    const co2Output =
        document.getElementById('co2-output');

    const drivingKm =
        document.getElementById('driving-km');

    /*
     * Daily tips elements
     */
    const factText =
        document.getElementById('climate-fact');

    const nextFactButton =
        document.getElementById('next-fact');

    const tipNumber =
        document.getElementById('tip-number');

    const factStatus =
        document.getElementById('fact-status');

    /*
     * Flash Food car reference:
     * 0.16152 kg CO2e per kilometre.
     */
    const carKgCo2ePerKm = 0.16152;

    /*
     * Used only if the API cannot be reached.
     * The PHP service also has its own fallback.
     */
    const browserFallbackTips = [
        'Compare similar products and choose the lower-CO₂e option to reduce the estimated climate impact of your basket.',

        'Organic farming can support soil, biodiversity and production methods that limit the use of synthetic pesticides.',

        'Lower-impact choices can earn Green Points and help you unlock discounts while shopping.'
    ];

    let dailyTips = [];
    let currentTipIndex = 0;
    let isChangingTip = false;

    /*
     * Update the CO2e slider and car distance.
     */
    function updateCalculator() {
        if (
            !(slider instanceof HTMLInputElement)
        ) {
            return;
        }

        const co2eValue =
            Number.parseFloat(slider.value);

        if (!Number.isFinite(co2eValue)) {
            return;
        }

        const kilometres =
            co2eValue / carKgCo2ePerKm;

        const minimum =
            Number.parseFloat(slider.min);

        const maximum =
            Number.parseFloat(slider.max);

        const progress =
            (
                (co2eValue - minimum) /
                (maximum - minimum)
            ) * 100;

        if (co2Output !== null) {
            co2Output.innerHTML =
                `${co2eValue.toFixed(1)} kg ` +
                `CO<sub>2</sub>e`;
        }

        if (drivingKm !== null) {
            drivingKm.textContent =
                kilometres.toFixed(1);
        }

        slider.style.setProperty(
            '--slider-progress',
            `${Math.min(
                100,
                Math.max(0, progress)
            )}%`
        );

        slider.setAttribute(
            'aria-valuetext',
            `${co2eValue.toFixed(1)} kilograms ` +
            `CO2 equivalent, approximately ` +
            `${kilometres.toFixed(1)} kilometres ` +
            `of car travel`
        );
    }

    /*
     * Display one of today's three tips.
     */
    function renderTip(
        index,
        withAnimation = false
    ) {
        if (
            factText === null ||
            dailyTips.length === 0
        ) {
            return;
        }

        currentTipIndex =
            (
                index % dailyTips.length +
                dailyTips.length
            ) % dailyTips.length;

        function updateText() {
            factText.textContent =
                dailyTips[currentTipIndex];

            if (tipNumber !== null) {
                tipNumber.textContent =
                    String(currentTipIndex + 1);
            }

            if (nextFactButton !== null) {
                const nextNumber =
                    (
                        (currentTipIndex + 1) %
                        dailyTips.length
                    ) + 1;

                nextFactButton.setAttribute(
                    'aria-label',
                    `Show tip ${nextNumber} of ` +
                    `${dailyTips.length}`
                );
            }
        }

        if (!withAnimation) {
            updateText();
            return;
        }

        factText.classList.add('is-changing');

        window.setTimeout(() => {
            updateText();

            factText.classList.remove(
                'is-changing'
            );

            isChangingTip = false;
        }, 180);
    }

    /*
     * Move to the next tip.
     */
    function showNextTip() {
        if (
            dailyTips.length === 0 ||
            isChangingTip
        ) {
            return;
        }

        isChangingTip = true;

        const nextIndex =
            (currentTipIndex + 1) %
            dailyTips.length;

        renderTip(nextIndex, true);
    }

    /*
     * Check the API response before displaying it.
     */
    function responseHasValidTips(data) {
        if (
            data === null ||
            typeof data !== 'object' ||
            data.success !== true ||
            !Array.isArray(data.tips) ||
            data.tips.length !== 3
        ) {
            return false;
        }

        return data.tips.every((tip) => {
            return (
                typeof tip === 'string' &&
                tip.trim().length > 0
            );
        });
    }

    /*
     * Display tips and enable the button.
     */
    function useTips(tips, statusMessage) {
        dailyTips = tips.map(
            (tip) => tip.trim()
        );

        currentTipIndex = 0;

        renderTip(0);

        if (factStatus !== null) {
            factStatus.textContent =
                statusMessage;
        }

        if (nextFactButton !== null) {
            nextFactButton.disabled = false;
        }
    }

    /*
     * Load today's tips from the PHP API.
     */
    async function loadDailyTips() {
        if (nextFactButton !== null) {
            nextFactButton.disabled = true;
        }

        if (factStatus !== null) {
            factStatus.textContent =
                'Loading today’s tips…';
        }

        try {
            const response = await fetch(
                '../Api/daily_tips.php',
                {
                    method: 'GET',

                    headers: {
                        Accept: 'application/json'
                    },

                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                throw new Error(
                    `Daily tips request failed: ` +
                    `${response.status}`
                );
            }

            const data = await response.json();

            if (!responseHasValidTips(data)) {
                throw new Error(
                    'Daily tips response is invalid.'
                );
            }

            const statusMessage =
                data.source === 'llm'
                    ? 'Fresh tips generated for today.'
                    : 'Today’s saved sustainability tips.';

            useTips(
                data.tips,
                statusMessage
            );
        } catch (error) {
            console.error(
                'Could not load daily tips:',
                error
            );

            useTips(
                browserFallbackTips,
                'Sustainability tips are available offline.'
            );
        }
    }

    /*
     * Event listeners
     */
    slider?.addEventListener(
        'input',
        updateCalculator
    );

    nextFactButton?.addEventListener(
        'click',
        showNextTip
    );

    /*
     * Initial page state
     */
    updateCalculator();
    loadDailyTips();
});