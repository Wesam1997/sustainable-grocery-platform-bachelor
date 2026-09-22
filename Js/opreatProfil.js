const form = document.getElementById('registerForm');
const statusMessage = document.getElementById('statusMessage');
const button = form.querySelector('button[type="submit"]');

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (button.disabled) return;

    const password = form.elements.password.value;
    const confirm = form.elements.confirm_password.value;

    statusMessage.textContent = '';

    if (password !== confirm) {
        statusMessage.textContent = 'The passwords do not match.';
        return;
    }

    button.disabled = true;
    button.textContent = 'Creating account...';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json'
            }
        });

        const data = await response.json();

        statusMessage.textContent =
            data.message || 'Something went wrong. Please try again.';

        if (response.ok && data.ok) {
            form.reset();
        }
    } catch (error) {
        statusMessage.textContent =
            'Could not confirm account creation. Please try again later.';
    } finally {
        button.disabled = false;
        button.textContent = 'Create account';
    }
});

