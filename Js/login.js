const form = document.getElementById('loginForm');
const statusMessage = document.getElementById('statusMessage');
const button = form.querySelector('button[type="submit"]');

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (button.disabled) return;

    button.disabled = true;
    button.textContent = 'Logger ind...';
    statusMessage.textContent = '';

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

        if (response.ok && data.ok) {
            window.location.assign('../profil/profil.php');
            return;
        }

        statusMessage.textContent =
            data.message || 'Login mislykkedes.';
    } catch (error) {
        statusMessage.textContent =
            'Kunne ikke gennemføre login. Prøv igen senere.';
    } finally {
        button.disabled = false;
        button.textContent = 'Log ind';
    }
});