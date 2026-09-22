<!doctype html>
<html lang="da">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Returner – Flash Food</title>
  <style>
    :root{
      --bg:#f7f8fb; --card:#ffffff; --text:#111827; --muted:#6b7280;
      --border:#e5e7eb; --accent:#16a34a; --accent-2:#0ea5e9;
      --radius:14px; --shadow:0 10px 24px rgba(0,0,0,.06);
      --btn:#eef7f0; --btn-hover:#e6eee9;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Inter,Roboto,Arial,sans-serif;color:var(--text);background:var(--bg)}

    /* HEADER – identisk med Home */
    header{ position:sticky; top:0; z-index:10; background:#fff; border-bottom:1px solid var(--border); }
    .topbar{ max-width:1100px; margin:0 auto; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .brand{font-weight:800;font-size:20px;text-decoration:none;color:inherit}
    .avatar{
      display:grid;place-items:center;width:36px;height:36px;border-radius:50%;
      background:#111;color:#fff; box-shadow: var(--shadow); text-decoration:none;
    }
    .avatar svg{width:20px;height:20px;display:block}

    /* PAGE LAYOUT */
    .wrap{max-width:1100px;margin:24px auto;padding:0 16px}

    /* Tabs */
    .tabs{display:flex;gap:18px;justify-content:space-between;max-width:900px;margin:0 auto 18px}
    .tab{flex:1;text-align:center;padding:12px 16px;border:1px solid var(--border);border-radius:10px;background:#f3f4f6;text-decoration:none;color:#111;font-weight:600}
    .tab.is-active{background:#e6eee9;color:var(--accent)}
    .tab:hover{filter:brightness(.98)}

    /* Chips */
    .chips{display:flex;gap:10px;justify-content:center;margin:0 auto 16px}
    .chips input{display:none}
    .chips label{padding:10px 16px;border:1px solid var(--border);border-radius:999px;background:#fff;cursor:pointer;font-weight:700;box-shadow:var(--shadow)}
    .chips input:checked + label{background:var(--btn);color:#065f46}

    /* Cards */
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
    .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px;display:flex;flex-direction:column;gap:10px}
    .title{font-weight:800}
    .muted{color:var(--muted);font-size:14px}
    .thumb{width:100%;height:140px;border-radius:12px;background:#e5e7eb;display:grid;place-items:center;color:#9ca3af;font-size:13px}
    .row{display:flex;gap:10px;align-items:center}.grow{flex:1}
    .badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
    .b-approved{background:#dcfce7;border:1px solid #bbf7d0;color:#065f46}
    .b-complete{background:#eef2f7;border:1px solid #e2e8f0;color:#334155}
    .btn{padding:10px 12px;border:none;border-radius:10px;cursor:pointer;font-weight:700}
    .btn.primary{background:var(--accent);color:#fff}.btn.ghost{background:#eef2f7;border:1px solid var(--border);color:#0f172a}
    .btn:hover{filter:brightness(.97)}

    /* Show/Hide lists */
    .list-bags{display:block}.list-coupons{display:none}
    #bags:checked ~ .lists .list-bags{display:block}
    #bags:checked ~ .lists .list-coupons{display:none}
    #coupons:checked ~ .lists .list-bags{display:none}
    #coupons:checked ~ .lists .list-coupons{display:block}

    /* FOOTER – identisk med Home */
    footer{ position:sticky; bottom:0; background:#fff; border-top:1px solid var(--border) }
    .bottombar{ max-width:1100px; margin:0 auto; padding:10px 16px; display:flex; align-items:center; gap:12px; }
    .slot{ flex:1 }
    .slot.center{ text-align:center }
    .slot.right{ text-align:right }

    .wishlist-pill{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 16px; border-radius:14px; font-weight:800;
      background:#e6f4ec; color:#065f46; border:1px solid #bbf7d0;
      text-decoration:none; box-shadow:var(--shadow);
    }
    .wishlist-pill:hover{ background:#dff0e6 }

    .pill-link{
      display:inline-flex; align-items:center; gap:8px;
      padding:10px 14px; border-radius:999px; border:1px solid var(--border);
      background:#fff; text-decoration:none; color:inherit; box-shadow:var(--shadow);
    }
    .pill-link:hover{ background:#f3f4f6 }

    .home-btn{
      display:grid; place-items:center; width:44px; height:44px;
      background:#111; color:#fff; border-radius:12px; border:2px solid #fff;
      text-decoration:none; box-shadow:0 6px 16px rgba(0,0,0,.2); font-size:20px;
    }

    @media (max-width:700px){.tabs{flex-direction:column}}
  </style>
</head>
<body>

  <!-- HEADER -->
  <header>
    <div class="topbar">
      <a class="brand" href="../Home/home.php" aria-label="Gå til forsiden">Flash Food</a>
      <div style="flex:1"></div>
      <a class="avatar" href="../profil/profil.php" aria-label="Min profil" title="Min profil">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="8" r="4" fill="#7c3aed"></circle>
          <path d="M4 20c0-4 4-6 8-6s8 2 8 6" fill="#7c3aed"></path>
        </svg>
      </a>
    </div>
  </header>

  <main class="wrap">
    <!-- Top-tabs -->
    <nav class="tabs" aria-label="Ordreoversigt">
      <a class="tab" herf="Returnet.php">Returnet</a>
      <a class="tab" href="kobt.php">Købt</a>
      <a class="tab" href="nuvaerende.php">Retuner nu</a>
    </nav>

    <!-- Chips -->
    <div class="chips">
      <input type="radio" id="bags" name="kind" checked>
      <label for="bags">Poser</label>
      <input type="radio" id="coupons" name="kind">
      </div>

    <!-- Lists -->
    <div class="lists">
      <!-- POSER -->
      <section class="list-bags" aria-label="Returneringer – Poser">
        <div class="grid">
          <article class="card">
            <div class="thumb">Posen – Billede</div>
            <div class="row">
              <div class="title">Bagerpose – Restaurant</div>
              <div class="grow"></div>
              <span class="badge b-approved">Godkendt</span>
            </div>
            <div class="muted">Ordre #FF-20388 · 11.03.2025 · 1 stk</div>
          </article>
        </div>
      </section>

      <!-- KUPONER -->
      <section class="list-coupons" aria-label="Returneringer – Kuponer">
        <div class="grid">
          <article class="card">
            <div class="thumb">Kupon</div>
            <div class="row">
              <div class="title">Kupon: 50 kr rabat</div>
              <div class="grow"></div>
              <span class="badge b-approved">Refusion godkendt</span>
            </div>
            <div class="muted">Kuponkode: FF-9X2Q · Udløb: 20.03.2025</div>
            <div class="row">
              <button class="btn ghost">Se detaljer</button>
              <button class="btn primary">Kontakt support</button>
            </div>
          </article>

          <article class="card">
            <div class="thumb">Kupon</div>
            <div class="row">
              <div class="title">Kupon: Gratis levering</div>
              <div class="grow"></div>
              <span class="badge b-complete">Afsluttet</span>
            </div>
            <div class="muted">Kuponkode: FF-DELIV · Refusion afsluttet</div>
            <div class="row">
              <button class="btn ghost">Se kvittering</button>
              <button class="btn ghost">Arkivér</button>
            </div>
          </article>
        </div>
      </section>
    </div>
  </main>

  <!-- FOOTER – matcher Home (grøn ønskeliste + rund kurv-pill) -->
  <footer>
    <nav class="bottombar" aria-label="Bundnavigation">
      <div class="slot left">
        <a class="home-btn" href="../Home/home.php" aria-label="Gå til forside">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z"/>
          </svg>
        </a>
      </div>
      <div class="slot center">
        <a class="wishlist-pill" href="../Home/wishlist.html">❤️ Min ønskeliste</a>
      </div>
      <div class="slot right">
        <a class="pill-link" href="../Home/kurv.html" aria-label="Kurv">🛒 Kurv</a>
      </div>
    </nav>
  </footer>
</body>
</html>
