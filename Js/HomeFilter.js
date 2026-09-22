(() => {
    "use strict";

    function setupFilterIcon() {
        const button = document.getElementById("search-filter-toggle");
        const filter = document.getElementById("product-filters");
        const sidebar = filter?.closest(".catalog-sidebar");
        const layout = filter?.closest(".catalog-layout");

        if (!button || !filter || !sidebar || !layout) return;

        // Undgå at tilføje samme funktion flere gange.
        if (button.dataset.flashFilterReady === "true") return;
        button.dataset.flashFilterReady = "true";

        function updateFilterState() {
            const isOpen = filter.open;

            sidebar.hidden = !isOpen;
            layout.classList.toggle("filters-closed", !isOpen);

            button.setAttribute("aria-expanded", String(isOpen));
            button.setAttribute(
                "aria-label",
                isOpen ? "Hide filters" : "Show filters"
            );

            button.title = isOpen ? "Hide filters" : "Show filters";
        }

        button.addEventListener("click", () => {
            filter.open = !filter.open;
            updateFilterState();
        });

        filter.addEventListener("toggle", updateFilterState);

        document.addEventListener("keydown", (event) => {
            if (event.key !== "Escape" || !filter.open) return;

            const focusIsInside =
                sidebar.contains(document.activeElement) ||
                document.activeElement === button;

            if (!focusIsInside) return;

            filter.open = false;
            updateFilterState();
            button.focus();
        });

        layout.classList.add("filters-enhanced");
        updateFilterState();
    }

    function setupDualRange({
        minId,
        maxId,
        minLabelId,
        maxLabelId,
        rangeId
    }) {
        const minInput = document.getElementById(minId);
        const maxInput = document.getElementById(maxId);
        const minLabel = document.getElementById(minLabelId);
        const maxLabel = document.getElementById(maxLabelId);
        const range = document.getElementById(rangeId);

        if (!minInput || !maxInput || !minLabel || !maxLabel || !range) {
            return;
        }

        if (range.dataset.flashRangeReady === "true") return;
        range.dataset.flashRangeReady = "true";

        const absoluteMin = Number(minInput.min);
        const absoluteMax = Number(maxInput.max);
        const total = absoluteMax - absoluteMin;

        function updateRange(changed) {
            let minValue = Number(minInput.value);
            let maxValue = Number(maxInput.value);

            if (minValue > maxValue) {
                if (changed === "min") {
                    maxValue = minValue;
                } else {
                    minValue = maxValue;
                }
            }

            minInput.value = String(minValue);
            maxInput.value = String(maxValue);

            const minPercent = total > 0
                ? ((minValue - absoluteMin) / total) * 100
                : 0;

            const maxPercent = total > 0
                ? ((maxValue - absoluteMin) / total) * 100
                : 0;

            range.style.setProperty("--min-pos", `${minPercent}%`);
            range.style.setProperty("--max-pos", `${maxPercent}%`);

            minLabel.textContent = String(minValue);
            maxLabel.textContent = String(maxValue);

            minInput.style.zIndex = minPercent > 70 ? "5" : "4";
            maxInput.style.zIndex = minPercent > 70 ? "4" : "5";
        }

        minInput.addEventListener("input", () => updateRange("min"));
        maxInput.addEventListener("input", () => updateRange("max"));

        minInput.form?.addEventListener("reset", () => {
            setTimeout(() => updateRange(), 0);
        });

        updateRange();
    }

    function initialize() {
        setupFilterIcon();

        setupDualRange({
            minId: "priceMin",
            maxId: "priceMax",
            minLabelId: "priceMinLabel",
            maxLabelId: "priceMaxLabel",
            rangeId: "priceRange"
        });

        setupDualRange({
            minId: "weightMin",
            maxId: "weightMax",
            minLabelId: "weightMinLabel",
            maxLabelId: "weightMaxLabel",
            rangeId: "weightRange"
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialize, {
            once: true
        });
    } else {
        initialize();
    }
})();