
/**
 * Flash Food - Profile Interactions
 *
 * File: Js/profile.js
 *
 * Responsibilities:
 * - Navigate between profile sections
 * - Highlight the active sidebar link
 * - Update navigation while scrolling
 * - Support keyboard navigation
 *
 * No business logic or API requests.
 */

(() => {
    "use strict";

    function initializeProfileNavigation() {

        const navigationLinks = Array.from(
            document.querySelectorAll(
                ".side-nav .side-link[data-section]"
            )
        );

        if (navigationLinks.length === 0) {
            return;
        }

        // Match links with existing page sections.
        const sections = navigationLinks
            .map((link) => {
                const sectionId = link.dataset.section;

                const section = document.getElementById(
                    sectionId
                );

                return section
                    ? { link, section, sectionId }
                    : null;
            })
            .filter(Boolean);

        if (sections.length === 0) {
            return;
        }

        /**
         * Update the selected sidebar link.
         */
        function setActiveSection(sectionId) {

            sections.forEach(({ link, sectionId: id }) => {

                const isActive = id === sectionId;

                link.classList.toggle(
                    "selected",
                    isActive
                );

                if (isActive) {
                    link.setAttribute(
                        "aria-current",
                        "location"
                    );
                } else {
                    link.removeAttribute(
                        "aria-current"
                    );
                }
            });
        }

        /**
         * Navigate to a section when clicked.
         * Native anchor navigation handles
         * scrolling, URL hash and keyboard use.
         */
        sections.forEach(({ link, sectionId }) => {

            link.addEventListener("click", () => {
                setActiveSection(sectionId);
            });

        });

        /**
         * Determine which section is closest
         * to the current scroll position.
         */
        function updateActiveSection() {

            const scrollOffset = 150;

            let activeSectionId = sections[0].sectionId;

            sections.forEach(({ section, sectionId }) => {

                const sectionTop =
                    section.getBoundingClientRect().top;

                if (sectionTop <= scrollOffset) {
                    activeSectionId = sectionId;
                }

            });

            setActiveSection(activeSectionId);
        }

        /**
         * Keep navigation synchronized
         * with browser scrolling.
         */
        let scrollScheduled = false;

        window.addEventListener(
            "scroll",
            () => {

                if (scrollScheduled) {
                    return;
                }

                scrollScheduled = true;

                window.requestAnimationFrame(() => {
                    updateActiveSection();
                    scrollScheduled = false;
                });

            },
            { passive: true }
        );

        /**
         * Support browser back/forward navigation.
         */
        window.addEventListener("hashchange", () => {

            const sectionId = decodeURIComponent(
                window.location.hash.slice(1)
            );

            const exists = sections.some(
                (item) => item.sectionId === sectionId
            );

            if (exists) {
                setActiveSection(sectionId);
            } else {
                updateActiveSection();
            }

        });

        // Set the initial active section.
        const initialSectionId = decodeURIComponent(
            window.location.hash.slice(1)
        );

        const initialMatch = sections.find(
            (item) => item.sectionId === initialSectionId
        );

        if (initialMatch) {
            setActiveSection(initialSectionId);
        } else {
            updateActiveSection();
        }
    }

    if (document.readyState === "loading") {

        document.addEventListener(
            "DOMContentLoaded",
            initializeProfileNavigation
        );

    } else {
        initializeProfileNavigation();
    }

})();
