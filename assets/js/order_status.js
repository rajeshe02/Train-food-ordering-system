// ============================================================
//  Train Food Ordering System — Order Status JS
//  File: assets/js/order_status.js
//  Handles: auto-refresh order status every 30 seconds
//           on the dashboard track tab
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

  // ── Only run on the track tab ─────────────────────────────
  const params = new URLSearchParams(window.location.search);
  if (params.get('tab') !== 'track') return;

  const orderId = params.get('order_id');
  if (!orderId) return;

  // Don't refresh if order is in a final state
  const statusEl = document.querySelector('[data-order-status]');
  if (statusEl) {
    const finalStates = ['delivered', 'cancelled'];
    if (finalStates.includes(statusEl.dataset.orderStatus)) return;
  }

  // ── Countdown timer display ───────────────────────────────
  let countdown = 30;
  const timerEl = document.getElementById('refresh-timer');

  function updateTimer() {
    if (timerEl) {
      timerEl.textContent = 'Refreshing in ' + countdown + 's';
    }
  }

  updateTimer();

  const interval = setInterval(function () {
    countdown--;
    updateTimer();

    if (countdown <= 0) {
      // Reload the page to get fresh status
      window.location.reload();
    }
  }, 1000);

  // ── Manual refresh button ─────────────────────────────────
  const refreshBtn = document.getElementById('refresh-btn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () {
      clearInterval(interval);
      window.location.reload();
    });
  }

  // ── Inject refresh bar into the page if elements exist ────
  const trackerCard = document.querySelector('.tracker-steps, .tracker-wrap');
  if (trackerCard && !document.getElementById('auto-refresh-bar')) {
    const bar = document.createElement('div');
    bar.id = 'auto-refresh-bar';
    bar.style.cssText = [
      'display:flex',
      'align-items:center',
      'justify-content:space-between',
      'padding:8px 20px',
      'background:#f0fdf4',
      'border-top:1px solid #86efac',
      'font-size:12px',
      'color:#166534',
      'gap:10px'
    ].join(';');

    bar.innerHTML =
      '<span id="refresh-timer">Refreshing in 30s</span>' +
      '<button id="refresh-btn" style="' +
        'padding:4px 12px;background:#dcfce7;color:#166534;' +
        'border:1px solid #86efac;border-radius:6px;' +
        'font-size:12px;font-weight:600;cursor:pointer;' +
      '">Refresh Now</button>';

    // Insert after tracker
    trackerCard.closest('.card')
      ? trackerCard.closest('.card').appendChild(bar)
      : trackerCard.parentNode.insertBefore(bar, trackerCard.nextSibling);

    // Re-attach click handler after DOM insert
    document.getElementById('refresh-btn').addEventListener('click', function () {
      clearInterval(interval);
      window.location.reload();
    });
  }

  // ── Show "placed" success message if redirected ───────────
  if (params.get('placed') === '1') {
    if (window.showToast) {
      window.showToast('Your order has been placed successfully!', 'success');
    }
    // Remove ?placed=1 from URL cleanly
    const cleanUrl = window.location.pathname + '?tab=track&order_id=' + orderId;
    window.history.replaceState({}, '', cleanUrl);
  }

});
