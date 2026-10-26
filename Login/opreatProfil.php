<?php
require_once __DIR__ . '/../Api/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account – Flash Food</title>
    <link rel="stylesheet" href="../Style/opreatProfil.css?v=4">
    <script src="../Js/opreatProfil.js?v=3" defer></script>
</head>
<body>
<main class="register-page">
    <section class="register-card" aria-labelledby="registerTitle">
        <div class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 64 64" width="56" height="56" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M31 53C30 33 39 16 55 7C59 30 48 45 31 53Z" fill="#397844"/>
                <path d="M29 48C13 46 6 34 7 20C24 22 32 34 29 48Z" fill="#92BA68"/>
                <path d="M31 55C31 39 38 27 49 17" stroke="#356E40" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <h1 id="registerTitle">Create account</h1>
        <p class="register-subtitle">Join Flash Food and start shopping<br>more sustainably.</p>
        <p id="statusMessage" role="status" aria-live="polite"></p>
        <form id="registerForm" method="post" action="../Api/register.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="name">Name</label>
                <div class="input-wrapper">
                    <svg class="field-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
                    <input id="name" name="name" type="text" placeholder="Enter your name" autocomplete="name" maxlength="100" required>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <svg class="field-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 7 9-7"/></svg>
                    <input id="email" name="email" type="email" placeholder="Enter your email" autocomplete="email" maxlength="254" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <svg class="field-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg>
                    <input id="password" name="password" type="password" placeholder="Create a password" autocomplete="new-password" minlength="15" aria-describedby="passwordHelp" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
                <small id="passwordHelp">Use at least 15 characters and a maximum of 72 bytes. Some characters use multiple bytes.</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Repeat password</label>
                <div class="input-wrapper">
                    <svg class="field-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg>
                    <input id="confirm_password" name="confirm_password" type="password" placeholder="Repeat your password" autocomplete="new-password" minlength="15" required>
                    <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
            </div>
            <button class="submit-button" type="submit">Create account</button>
        </form>
        <div class="divider"><span>or</span></div>
        <p class="login-text">Already have an account? <a href="login.php">Log in</a></p>
    </section>
</main>
</body>
</html>
