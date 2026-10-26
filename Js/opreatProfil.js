'use strict';
const form = document.getElementById('registerForm');
const statusMessage = document.getElementById('statusMessage');
const button = form.querySelector('button[type="submit"]');

// Show or hide passwords without submitting the form.
document.querySelectorAll('.password-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const input = document.getElementById(toggle.dataset.target);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.style.color = show ? '#397844' : '';
    });
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (button.disabled) return;

    const password = form.elements.password.value;
    const confirm = form.elements.confirm_password.value;
    statusMessage.textContent = '';
    statusMessage.classList.remove('success');

    if (password !== confirm) {
        statusMessage.textContent = 'The passwords do not match.';
        return;
    }
    if (new TextEncoder().encode(password).length > 72) {
        statusMessage.textContent = 'The password must not exceed 72 bytes.';
        return;
    }

    button.disabled = true;
    button.textContent = 'Creating account...';
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        });
        const data = await response.json();
        statusMessage.textContent = data.message || 'Something went wrong. Please try again.';
        if (response.ok && data.ok) {
            statusMessage.classList.add('success');
            form.reset();
            document.querySelectorAll('.password-toggle').forEach((toggle) => {
                const input = document.getElementById(toggle.dataset.target);
                if (input) input.type = 'password';
                toggle.setAttribute('aria-pressed', 'false');
                toggle.setAttribute('aria-label', 'Show password');
                toggle.style.color = '';
            });
        }
    } catch (error) {
        statusMessage.textContent = 'Could not confirm account creation. Please try again later.';
    } finally {
        button.disabled = false;
        button.textContent = 'Create account';
    }
});
