<?php
require_once __DIR__ . '/../Api/auth.php';
header('Cache-Control: no-store');
$user = currentUser();
if ($user === null) {
    header('Location: ../Login/login.php');
    exit;
}
function profileEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My profile – Flash Food</title>
    <link rel="stylesheet" href="../Style/profil.css">
    <style>
        .header-action-icon { display:block; width:22px; height:22px; flex-shrink:0; }
        .avatar a, .wishlist-pill, .pill-link, .home-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:6px;
        }
        .avatar a { color:inherit; text-decoration:none; }
        .logout-form { margin:0; width:100%; }
        .logout-form button { width:100%; font:inherit; cursor:pointer; }
        .profile-options { margin-top:16px; display:flex; gap:10px; flex-wrap:wrap; }
        .profile-heading { margin:0 0 12px; }
        .profile-summary .row > span:last-child { min-width:0; overflow-wrap:anywhere; }
    </style>
</head>
<body>
    <header>
        <div class="topbar">
            <a class="brand" href="../Home/home.php" aria-label="Go to home">Flash Food</a>
            <div class="avatar">
                <a href="profil.php" title="My profile" aria-label="My profile" aria-current="page">
                    <svg class="header-action-icon" xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="8" r="4" /><path d="M4 21v-2a8 8 0 0 1 16 0v2" />
                </svg>
                </a>
            </div>
        </div>
    </header>
    <main class="container">
        <h1 class="muted profile-heading">My profile</h1>
        <div class="grid">
            <aside class="card">
                <div class="card-header">Actions</div>
                <div class="card-body actions">
                    <a class="link-btn btn-primary" href="redigerPersonligOplysninger.html">Edit personal details</a>
                    <a class="link-btn btn-outline" href="minReturn.php">My returns</a>
                    <form class="logout-form" action="../Api/logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= profileEscape(csrfToken()) ?>">
                        <button class="link-btn btn-danger" type="submit">Log out</button>
                    </form>
                </div>
            </aside>
            <section class="card profile-summary" aria-labelledby="details-heading">
                <div class="card-header" id="details-heading">Personal details</div>
                <div class="card-body">
                    <div class="row"><span class="label">Name</span><span><?= profileEscape($user['name']) ?></span></div>
                    <div class="row"><span class="label">Address</span><span>Not added</span></div>
                    <div class="row"><span class="label">Email</span><span><?= profileEscape($user['email']) ?></span></div>
                    <div class="row"><span class="label">Phone</span><span>Not added</span></div>
                    <div class="profile-options">
                        <a class="btn" href="skift-adgangskode.html">Change password</a>
                        <a class="btn-outline btn" href="adressebog.html">Manage addresses</a>
                    </div>
                </div>
            </section>
        </div>
    </main>
    <footer>
        <nav class="bottombar" aria-label="Footer navigation">
            <div class="slot left">
                <a class="home-btn" href="../Home/home.php" aria-label="Go to home" title="Home"><svg class="header-action-icon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m3 10 9-7 9 7"/><path d="M5 9v12h5v-7h4v7h5V9"/></svg></a>
            </div>
            <div class="slot center">
                <a class="wishlist-pill" href="../Home/fav.php"><svg class="header-action-icon" xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z" />
                </svg><span>My wishlist</span></a>
            </div>
            <div class="slot right">
                <a class="pill-link" href="../Home/inkobLister.php"><svg class="header-action-icon" xmlns="http://www.w3.org/2000/svg"
                     width="22" height="22" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M3 3h2l3 12h11l2-8H6" /><circle cx="9" cy="20" r="1" /><circle cx="18" cy="20" r="1" />
                </svg><span>Cart</span></a>
            </div>
        </nav>
    </footer>
</body>
</html>
