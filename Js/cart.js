
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const clearForm = document
        .querySelector('[data-confirm-clear]')
        ?.closest('form');

    if (!clearForm) {
        return;
    }

    clearForm.addEventListener('submit', (event) => {
        const confirmed = window.confirm(
            'Remove all items from your cart?'
        );

        if (!confirmed) {
            event.preventDefault();
        }
    });
});
