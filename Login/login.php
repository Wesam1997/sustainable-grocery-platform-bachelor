<?php
require_once __DIR__ . '/../Api/auth.php';

// Brugere, der allerede er logget ind, sendes til profilen.
if (currentUser() !== null) {
    header('Location: ../profil/profil.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Flash Food Login</title>

    <link rel="stylesheet" href="../Style/Login.css">
    <script src="../Js/login.js" defer></script>
</head>
<body>
    <div class="container">
        <h1>Welcome to Flash Food</h1>

        <p
            id="statusMessage"
            role="status"
            aria-live="polite"
        ></p>

        <form
            id="loginForm"
            action="../Api/login.php"
            method="post"
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

            <div>
                <label for="email">E-mail</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="username"
                    maxlength="254"
                    required
                >
            </div>

            <div>
                <label for="adgangskode">Adgangskode</label>
                <input
                    id="adgangskode"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit">Log ind</button>
        </form>

        <div class="extra">
            <a href="opreatProfil.php">Opret bruger</a>
        </div>

        
    </div>
</body>
</html>