document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('loginForm');
    const statusMessage = document.getElementById('statusMessage');

    if (!form || !statusMessage) {
        return;
    }

    const button = form.querySelector('button[type="submit"]');
    const passwordInput = document.getElementById('adgangskode');
    const passwordToggle = document.querySelector('.password-toggle');

    // ==========================================
    // SHOW / HIDE PASSWORD
    // ==========================================

    if (passwordInput && passwordToggle) {

        passwordToggle.addEventListener('click', () => {

            const isHidden = passwordInput.type === 'password';

            passwordInput.type = isHidden ? 'text' : 'password';

            passwordToggle.setAttribute(
                'aria-label',
                isHidden ? 'Hide password' : 'Show password'
            );

            passwordToggle.setAttribute(
                'aria-pressed',
                String(isHidden)
            );

            if (isHidden) {

                passwordToggle.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none"
                         aria-hidden="true">
                        <path
                            d="M3 3L21 21"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />
                        <path
                            d="M10.6 6.1A10.6 10.6 0 0 1 12 6
                               C18.5 6 22 12 22 12
                               A16.8 16.8 0 0 1 18.5 15.7
                               M6.2 6.2C3.5 8 2 12 2 12
                               S5.5 18 12 18
                               C13.4 18 14.7 17.7 15.8 17.2"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />
                    </svg>
                `;

            } else {

                passwordToggle.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none"
                         aria-hidden="true">
                        <path
                            d="M2 12S5.5 6 12 6S22 12 22 12
                               S18.5 18 12 18S2 12 2 12Z"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />
                        <circle
                            cx="12"
                            cy="12"
                            r="3"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />
                    </svg>
                `;
            }
        });
    }

    // ==========================================
    // LOGIN FORM
    // ==========================================

    form.addEventListener('submit', async (event) => {

        event.preventDefault();

        if (!button || button.disabled) {
            return;
        }

        statusMessage.textContent = '';

        const emailInput = document.getElementById('email');

        if (!emailInput || !passwordInput) {
            statusMessage.textContent =
                'Login form is incomplete.';
            return;
        }

        const email = emailInput.value.trim();
        const password = passwordInput.value;

        if (!email) {
            statusMessage.textContent =
                'Please enter your email.';
            return;
        }

        if (!password) {
            statusMessage.textContent =
                'Please enter your password.';
            return;
        }

        button.disabled = true;
        button.textContent = 'Logging in...';

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
                data.message || 'Login failed. Please try again.';

        } catch (error) {

            console.error('Login error:', error);

            statusMessage.textContent =
                'Could not complete login. Please try again later.';

        } finally {

            button.disabled = false;
            button.textContent = 'Log in';
        }
    });
});
