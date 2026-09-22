document.addEventListener('submit', async (e) => {
  const form = e.target;

  if (!form.matches('form.add-to-cart')) return;

  e.preventDefault();

  const btn = form.querySelector('button[type="submit"]');

  if (btn?.disabled) return;

  const fd = new FormData(form);
  fd.set('ajax', '1');

  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Adding...';
  }

  try {
    const res = await fetch(form.action, {
      method: 'POST',
      body: fd,
      headers: {
        Accept: 'application/json'
      }
    });

    const data = await res.json();

    if (!res.ok || !data?.ok) {
      throw new Error(
        data?.error || 'Could not add the item.'
      );
    }

    document.querySelectorAll('.cart-count').forEach((counter) => {
      if (typeof data.count !== 'undefined') {
        counter.textContent = data.count;
      }
    });

    // Shake the cart after a successful addition.
    const cartIcon = document.getElementById('cart-icon');
if (cartIcon) {
  cartIcon.classList.remove('is-shaking');

  // Force the browser to register the reset.
  void cartIcon.getBoundingClientRect();

  cartIcon.classList.add('is-shaking');

  cartIcon.addEventListener('animationend', () => {
    cartIcon.classList.remove('is-shaking');
  }, { once: true });
}

    if (btn) {
      btn.textContent = 'Added to cart';

      setTimeout(() => {
        btn.textContent = 'Add to cart';
        btn.disabled = false;
      }, 1200);
    }
  } catch (err) {
    if (btn) {
      btn.textContent = 'Add to cart';
      btn.disabled = false;
    }

    alert('Could not add the item to your cart: ' + err.message);
  }
});
