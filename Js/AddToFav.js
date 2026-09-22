// Js/AddToFav.js
document.addEventListener('submit', async (e) => {
  const form = e.target;
  if (!form.matches('form.add-to-fav')) return;

  e.preventDefault();

  const btn = form.querySelector('button[type="submit"]');
  const fd  = new FormData(form);
  fd.append('ajax', '1');

  try {
    const res  = await fetch(form.action, { method:'POST', body: fd, headers:{ 'Accept':'application/json' } });
    const data = await res.json();
    if (!res.ok || !data || !data.ok) throw new Error((data && data.error) || ('HTTP ' + res.status));

    // Vis kort, ikke-blokerende feedback i knappen
    if (btn) {
      const old = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '❤️ ';
      setTimeout(() => { btn.disabled = false; btn.innerHTML = old; }, 1000);
    }

    // Hvis din API sender count tilbage, kan du opdatere en badge:
    const wish = document.querySelector('.wish-count');
    if (wish && typeof data.count !== 'undefined') wish.textContent = data.count;

  } catch (err) {
    console.error(err);
    // valgfrit: vis diskret fejl uden alert
    if (btn) {
      const old = btn.innerHTML;
      btn.innerHTML = '⚠️ Fejl';
      setTimeout(() => { btn.innerHTML = old; }, 1200);
    }
  }
});
