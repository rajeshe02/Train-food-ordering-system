// ============================================================
//  Train Food Ordering System — Cart JS
//  File: assets/js/cart.js
//  Handles: image preview, payment option highlight,
//           form validation, qty button feedback
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

  // ── Image preview when vendor picks a file ───────────────
  const fileInput = document.getElementById('item_image');
  const preview   = document.getElementById('imgPreview');

  if (fileInput && preview) {
    fileInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
          preview.src = e.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(this.files[0]);
      } else {
        preview.style.display = 'none';
        preview.src = '';
      }
    });
  }

  // ── Highlight selected payment option ────────────────────
  const payOpts = document.querySelectorAll('.pay-opt');
  payOpts.forEach(function (opt) {
    const radio = opt.querySelector('input[type="radio"]');

    // Set initial highlight
    if (radio && radio.checked) {
      opt.style.borderColor = '#1a56db';
      opt.style.background  = '#eff6ff';
    }

    opt.addEventListener('click', function () {
      // Reset all
      payOpts.forEach(function (o) {
        o.style.borderColor = '#e5e7eb';
        o.style.background  = '#fff';
      });
      // Highlight selected
      opt.style.borderColor = '#1a56db';
      opt.style.background  = '#eff6ff';
    });
  });

  // ── Qty button: briefly animate on click ─────────────────
  const qtyBtns = document.querySelectorAll('.qty-btn');
  qtyBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.style.transform = 'scale(0.85)';
      setTimeout(function () {
        btn.style.transform = 'scale(1)';
      }, 120);
    });
  });

  // ── Checkout form: basic client-side validation ───────────
  const checkoutForm = document.querySelector('form[data-checkout]');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function (e) {
      const train   = document.querySelector('[name="train_number"]');
      const pnr     = document.querySelector('[name="pnr_number"]');
      const coach   = document.querySelector('[name="coach_number"]');
      const seat    = document.querySelector('[name="seat_number"]');
      const station = document.querySelector('[name="delivery_station"]');

      let valid = true;
      const fields = [train, pnr, coach, seat, station];

      fields.forEach(function (field) {
        if (field && field.value.trim() === '') {
          field.style.borderColor = '#ef4444';
          valid = false;
        } else if (field) {
          field.style.borderColor = '#d1d5db';
        }
      });

      if (!valid) {
        e.preventDefault();
        showToast('Please fill in all delivery details.', 'error');
      }
    });
  }

  // ── Auto-dismiss alerts after 4 seconds ──────────────────
  const alerts = document.querySelectorAll('.alert-success, .alert-error');
  alerts.forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity 0.4s ease';
      alert.style.opacity    = '0';
      setTimeout(function () {
        alert.style.display = 'none';
      }, 400);
    }, 4000);
  });

  // ── Toast notification helper ─────────────────────────────
  window.showToast = function (message, type) {
    type = type || 'success';
    const existing = document.getElementById('toast-msg');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id    = 'toast-msg';
    toast.textContent = message;

    const isError = type === 'error';
    toast.style.cssText = [
      'position:fixed',
      'bottom:24px',
      'right:24px',
      'z-index:9999',
      'padding:12px 20px',
      'border-radius:10px',
      'font-size:14px',
      'font-weight:600',
      'box-shadow:0 4px 16px rgba(0,0,0,0.15)',
      'opacity:0',
      'transition:opacity 0.3s ease',
      isError
        ? 'background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;'
        : 'background:#f0fdf4;color:#166534;border:1px solid #86efac;'
    ].join(';');

    document.body.appendChild(toast);

    // Fade in
    requestAnimationFrame(function () {
      toast.style.opacity = '1';
    });

    // Fade out after 3s
    setTimeout(function () {
      toast.style.opacity = '0';
      setTimeout(function () { toast.remove(); }, 300);
    }, 3000);
  };

});
