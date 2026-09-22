<?php
require_once __DIR__ . '/../Api/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account – Flash Food</title>

    <script src="../Js/opreatProfil.js" defer></script>
</head>
<body>
    <main>
        <a href="../Home/home.php">← Back to products</a>

        <h1>Create account</h1>

        <p id="statusMessage" role="status" aria-live="polite"></p>

        <form
            id="registerForm"
            method="post"
            action="../Api/register.php"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    csrfToken(),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <p>
                <label for="name">Name</label><br>
                <input
                    id="name"
                    name="name"
                    type="text"
                    autocomplete="name"
                    maxlength="100"
                    required
                >
            </p>

            <p>
                <label for="email">Email</label><br>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    maxlength="254"
                    required
                >
            </p>

            <p>
                <label for="password">Password</label><br>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    minlength="15"
                    aria-describedby="passwordHelp"
                    required
                >
                <br>
                <small id="passwordHelp">
                    Use at least 15 characters and a maximum of 72 bytes.
                    Some characters use multiple bytes.
                </small>
            </p>

            <p>
                <label for="confirm_password">Repeat password</label><br>
                <input
                    id="confirm_password"
                    name="confirm_password"
                    type="password"
                    autocomplete="new-password"
                    minlength="15"
                    required
                >
            </p>

            <button type="submit">Create account</button>
        </form>

        <p>
            Already have an account?
            <a href="login.php">Log in</a>
        </p>
    </main>
</body>
</html>