<?php
declare(strict_types=1);

require_once __DIR__ . '/../Api/auth.php';

// Redirect already logged-in users
if (currentUser() !== null) {
    header('Location: ../profil/profil.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Log in – Flash Food</title>

    <link rel="stylesheet" href="../Style/Login.css?v=3">
    <script src="../Js/login.js?v=3" defer></script>
</head>
<body>

    <main class="login-page">
        <section class="login-card">

            <!-- Flash Food logo -->
            <div class="brand-logo" aria-hidden="true">
                <svg viewBox="0 0 64 64" fill="none">
                    <path
                        d="M31 53C30 33 39 16 55 7C59 30 48 45 31 53Z"
                        fill="#397844"
                    />
                    <path
                        d="M29 48C13 46 6 34 7 20C24 22 32 34 29 48Z"
                        fill="#92BA68"
                    />
                    <path
                        d="M31 55C31 39 38 27 49 17"
                        stroke="#356E40"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    />
                </svg>
            </div>

            <h1>Welcome to Flash Food</h1>
            <p class="login-subtitle">Shop sustainably.</p>

            <!-- Error and status messages -->
            <p
                id="statusMessage"
                role="status"
                aria-live="polite"
            ></p>

            <!-- Login form -->
            <form
                id="loginForm"
                action="../Api/login.php"
                method="post"
            >

                <!-- CSRF token -->
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        csrfToken(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email</label>

                    <div class="input-wrapper">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect
                                    x="3"
                                    y="5"
                                    width="18"
                                    height="14"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                />
                                <path
                                    d="M4 7L12 13L20 7"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                />
                            </svg>
                        </span>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            placeholder="Enter your email"
                            autocomplete="username"
                            maxlength="254"
                            required
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="adgangskode">Password</label>

                    <div class="input-wrapper">
                        <span class="input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect
                                    x="5"
                                    y="10"
                                    width="14"
                                    height="11"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                />
                                <path
                                    d="M8 10V7A4 4 0 0 1 16 7V10"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                />
                                <circle
                                    cx="12"
                                    cy="15.5"
                                    r="1.2"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>

                        <input
                            id="adgangskode"
                            name="password"
                            type="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <!-- Show / hide password -->
                        <button
                            type="button"
                            class="password-toggle"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path
                                    d="M2 12S5.5 6 12 6S22 12 22 12S18.5 18 12 18S2 12 2 12Z"
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
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button
                    class="login-button"
                    type="submit"
                >
                    Log in
                </button>

            </form>

            <!-- Registration link -->
            <p class="register-link">
                Don't have an account?
                <a href="opreatProfil.php">
                    Create account
                </a>
            </p>

        </section>
    </main>

</body>
</html>
