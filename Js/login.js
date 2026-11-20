document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const statusMessage = document.getElementById('statusMessage');

    if (!form || !statusMessage) {
        return;
    }

    const loginButton = form.querySelector(
        'button[type="submit"]'
    );

    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('adgangskode');
    const passwordToggle = document.querySelector(
        '.password-toggle'
    );

    const resendButton = document.getElementById(
        'resendVerification'
    );

    const csrfInput = form.querySelector(
        'input[name="csrf_token"]'
    );

    // Show / hide password
    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener('click', () => {
            const show = passwordInput.type === 'password';

            passwordInput.type = show ? 'text' : 'password';

            passwordToggle.setAttribute(
                'aria-label',
                show ? 'Hide password' : 'Show password'
            );

            passwordToggle.setAttribute(
                'aria-pressed',
                String(show)
            );

            passwordToggle.innerHTML = show
                ? `<svg viewBox="0 0 24 24" fill="none"
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
                   </svg>`
                : `<svg viewBox="0 0 24 24" fill="none"
                        aria-hidden="true">
                     <path
                         d="M2 12S5.5 6 12 6S22 12 22 12
                            S18.5 18 12 18S2 12 2 12Z"
                         stroke="currentColor"
                         stroke-width="1.8"
                     />
                     <circle
                         cx="12" cy="12" r="3"
                         stroke="currentColor"
                         stroke-width="1.8"
                     />
                   </svg>`;
        });
    }

    // Login
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!loginButton || loginButton.disabled) {
            return;
        }

        statusMessage.textContent = '';

        if (!emailInput || !passwordInput) {
            statusMessage.textContent =
                'Login form is incomplete.';
            return;
        }

        if (!emailInput.value.trim()) {
            statusMessage.textContent =
                'Please enter your email.';
            return;
        }

        if (!passwordInput.value) {
            statusMessage.textContent =
                'Please enter your password.';
            return;
        }

        loginButton.disabled = true;
        loginButton.textContent = 'Logging in...';

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
                window.location.assign(
                    '../profil/profil.php'
                );
                return;
            }

            statusMessage.textContent =
                data.message ||
                'Login failed. Please try again.';

        } catch (error) {
            console.error('Login error:', error);

            statusMessage.textContent =
                'Could not complete login. Please try again later.';

        } finally {
            loginButton.disabled = false;
            loginButton.textContent = 'Log in';
        }
    });

    // Resend verification email
    if (resendButton && emailInput && csrfInput) {
        resendButton.addEventListener('click', async () => {
            const email = emailInput.value.trim();

            if (!email || !emailInput.checkValidity()) {
                statusMessage.textContent =
                    'Please enter a valid email address first.';
                emailInput.focus();
                return;
            }

            if (resendButton.disabled) {
                return;
            }

            resendButton.disabled = true;
            resendButton.textContent = 'Sending...';
            statusMessage.textContent = '';

            const body = new FormData();
            body.append('email', email);
            body.append('csrf_token', csrfInput.value);

            try {
                const response = await fetch(
                    '../Api/resend_verification.php',
                    {
                        method: 'POST',
                        body,
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json'
                        }
                    }
                );

                const data = await response.json();

                statusMessage.textContent =
                    data.message ||
                    'Could not process your request.';

            } catch (error) {
                console.error('Resend error:', error);

                statusMessage.textContent =
                    'Could not send request. Please try again later.';

            } finally {
                resendButton.disabled = false;
                resendButton.textContent =
                    'Resend verification email';
            }
        });
    }
});
