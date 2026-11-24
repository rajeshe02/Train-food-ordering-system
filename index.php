<?php
// ============================================================
//  Train Food Ordering System — Homepage
//  File: index.php (project root)
// ============================================================

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'passenger') {
    header("Location: passenger/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TrainEats — Food Delivered to Your Seat</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy:   #0b1437;
      --blue:   #1a56db;
      --sky:    #38bdf8;
      --accent: #f97316;
      --white:  #ffffff;
      --light:  #f0f6ff;
      --muted:  #94a3b8;
      --card:   #ffffff;
      --border: #e2e8f0;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--white);
      color: var(--navy);
      overflow-x: hidden;
    }

    /* ── NAVBAR ── */
    header {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 200;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 48px;
      height: 64px;
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
    }

    .brand {
      font-family: 'Syne', sans-serif;
      font-size: 20px;
      font-weight: 800;
      color: var(--navy);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .brand span { color: var(--blue); }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .nav-links a {
      font-size: 14px;
      font-weight: 500;
      color: #475569;
      text-decoration: none;
      padding: 7px 16px;
      border-radius: 8px;
      transition: all 0.2s;
    }

    .nav-links a:hover { background: var(--light); color: var(--blue); }

    .btn-nav {
      background: var(--blue) !important;
      color: var(--white) !important;
      font-weight: 600 !important;
    }

    .btn-nav:hover { background: #1e40af !important; }

    /* ── HERO ── */
    .hero {
      min-height: 100vh;
      background: var(--navy);
      display: flex;
      align-items: center;
      position: relative;
      overflow: hidden;
      padding: 100px 48px 60px;
    }

    /* Decorative track lines */
    .hero::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background:
        repeating-linear-gradient(
          90deg,
          transparent,
          transparent 80px,
          rgba(255,255,255,0.025) 80px,
          rgba(255,255,255,0.025) 81px
        );
      pointer-events: none;
    }

    .hero-grid {
      max-width: 1100px;
      margin: 0 auto;
      width: 100%;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(56,189,248,0.12);
      border: 1px solid rgba(56,189,248,0.3);
      color: var(--sky);
      font-size: 12px;
      font-weight: 600;
      padding: 6px 14px;
      border-radius: 20px;
      margin-bottom: 20px;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      animation: fadeUp 0.6s ease both;
    }

    .hero h1 {
      font-family: 'Syne', sans-serif;
      font-size: clamp(36px, 5vw, 58px);
      font-weight: 800;
      color: var(--white);
      line-height: 1.1;
      margin-bottom: 20px;
      animation: fadeUp 0.6s 0.1s ease both;
    }

    .hero h1 .highlight {
      color: var(--accent);
      position: relative;
    }

    .hero p {
      font-size: 16px;
      color: #94a3b8;
      line-height: 1.7;
      margin-bottom: 36px;
      max-width: 460px;
      animation: fadeUp 0.6s 0.2s ease both;
    }

    .hero-btns {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      animation: fadeUp 0.6s 0.3s ease both;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--accent);
      color: var(--white);
      padding: 13px 28px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 15px;
      text-decoration: none;
      transition: transform 0.2s, background 0.2s;
    }

    .btn-primary:hover { background: #ea6c0a; transform: translateY(-2px); }

    .btn-outline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1.5px solid rgba(255,255,255,0.2);
      color: var(--white);
      padding: 13px 28px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 15px;
      text-decoration: none;
      transition: all 0.2s;
    }

    .btn-outline:hover {
      background: rgba(255,255,255,0.08);
      border-color: rgba(255,255,255,0.4);
      transform: translateY(-2px);
    }

    /* Train illustration */
    .hero-visual {
      animation: fadeUp 0.6s 0.2s ease both;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }

    .train-card {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 20px;
      padding: 28px;
      width: 100%;
      max-width: 380px;
    }

    .train-card-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--sky);
      font-weight: 600;
      margin-bottom: 12px;
    }

    .train-info {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .train-name {
      font-family: 'Syne', sans-serif;
      font-size: 18px;
      font-weight: 700;
      color: var(--white);
    }

    .train-num {
      font-size: 13px;
      color: var(--muted);
      margin-top: 2px;
    }

    .train-status {
      background: rgba(16,185,129,0.15);
      color: #34d399;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 20px;
    }

    .seat-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 16px;
    }

    .seat-pill {
      background: rgba(249,115,22,0.15);
      border: 1px solid rgba(249,115,22,0.3);
      color: var(--accent);
      font-size: 13px;
      font-weight: 700;
      padding: 6px 14px;
      border-radius: 8px;
    }

    .seat-label { font-size: 13px; color: var(--muted); }

    .food-preview {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .food-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(255,255,255,0.05);
      border-radius: 10px;
      padding: 10px 14px;
    }

    .food-item-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .food-emoji { font-size: 22px; }

    .food-name { font-size: 13px; font-weight: 600; color: var(--white); }
    .food-desc { font-size: 11px; color: var(--muted); margin-top: 1px; }
    .food-price { font-size: 14px; font-weight: 700; color: var(--accent); }

    .order-total {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-top: 1px solid rgba(255,255,255,0.08);
      padding-top: 14px;
      margin-top: 4px;
    }

    .order-total-label { font-size: 13px; color: var(--muted); font-weight: 600; }
    .order-total-amt   { font-size: 18px; font-weight: 800; color: var(--white); }

    /* ── HOW IT WORKS ── */
    .section {
      padding: 80px 48px;
    }

    .section-inner {
      max-width: 1100px;
      margin: 0 auto;
    }

    .section-tag {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--blue);
      margin-bottom: 10px;
    }

    .section-title {
      font-family: 'Syne', sans-serif;
      font-size: clamp(26px, 3vw, 38px);
      font-weight: 800;
      color: var(--navy);
      margin-bottom: 12px;
    }

    .section-sub {
      font-size: 15px;
      color: #64748b;
      max-width: 520px;
      line-height: 1.7;
      margin-bottom: 52px;
    }

    .steps {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 24px;
    }

    .step {
      position: relative;
    }

    .step-num {
      width: 48px;
      height: 48px;
      background: var(--light);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Syne', sans-serif;
      font-size: 20px;
      font-weight: 800;
      color: var(--blue);
      margin-bottom: 16px;
    }

    .step h3 {
      font-size: 16px;
      font-weight: 700;
      color: var(--navy);
      margin-bottom: 8px;
    }

    .step p {
      font-size: 14px;
      color: #64748b;
      line-height: 1.6;
    }

    /* connector line */
    .step:not(:last-child)::after {
      content: '';
      position: absolute;
      top: 24px;
      right: -12px;
      width: 24px;
      height: 2px;
      background: var(--border);
    }

    /* ── FEATURES ── */
    .features-bg {
      background: var(--navy);
      padding: 80px 48px;
    }

    .features {
      max-width: 1100px;
      margin: 0 auto;
    }

    .features .section-title { color: var(--white); }
    .features .section-sub   { color: #94a3b8; }
    .features .section-tag   { color: var(--sky); }

    .feat-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
    }

    .feat-card {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: 16px;
      padding: 28px;
      transition: border-color 0.2s, transform 0.2s;
    }

    .feat-card:hover {
      border-color: rgba(56,189,248,0.3);
      transform: translateY(-4px);
    }

    .feat-icon {
      font-size: 28px;
      margin-bottom: 16px;
    }

    .feat-card h3 {
      font-size: 16px;
      font-weight: 700;
      color: var(--white);
      margin-bottom: 8px;
    }

    .feat-card p {
      font-size: 13px;
      color: #94a3b8;
      line-height: 1.6;
    }

    /* ── CTA ── */
    .cta-section {
      padding: 80px 48px;
      text-align: center;
      background: var(--light);
    }

    .cta-inner { max-width: 600px; margin: 0 auto; }

    .cta-inner .section-title { margin-bottom: 16px; }

    .cta-inner p {
      font-size: 15px;
      color: #64748b;
      margin-bottom: 32px;
      line-height: 1.7;
    }

    .cta-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }

    .btn-cta-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--blue);
      color: var(--white);
      padding: 14px 32px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 15px;
      text-decoration: none;
      transition: background 0.2s, transform 0.2s;
    }

    .btn-cta-primary:hover { background: #1e40af; transform: translateY(-2px); }

    .btn-cta-outline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 2px solid var(--blue);
      color: var(--blue);
      padding: 14px 32px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 15px;
      text-decoration: none;
      transition: all 0.2s;
    }

    .btn-cta-outline:hover {
      background: var(--blue);
      color: var(--white);
      transform: translateY(-2px);
    }

    /* ── FOOTER ── */
    footer {
      background: var(--navy);
      padding: 32px 48px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    footer .brand { color: var(--white); font-size: 16px; }
    footer p { font-size: 13px; color: #475569; }

    footer .foot-links { display: flex; gap: 20px; }
    footer .foot-links a { font-size: 13px; color: #475569; text-decoration: none; }
    footer .foot-links a:hover { color: var(--sky); }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      header { padding: 0 20px; }
      .hero  { padding: 90px 20px 50px; }
      .hero-grid { grid-template-columns: 1fr; gap: 40px; }
      .hero-visual { display: none; }
      .section, .features-bg, .cta-section { padding: 56px 20px; }
      .steps { grid-template-columns: repeat(2,1fr); }
      .feat-grid { grid-template-columns: 1fr; }
      .step::after { display: none; }
      footer { flex-direction: column; gap: 16px; text-align: center; }
    }

    @media (max-width: 560px) {
      .nav-links a:not(.btn-nav) { display: none; }
      .steps { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ── HEADER ── -->
<header>
  <a class="brand" href="index.php">🚆 Train<span>Eats</span></a>
  <nav class="nav-links">
    <a href="#how-it-works">How it works</a>
    <a href="#features">Features</a>
    <a href="passenger/login.php">Login</a>
    <a href="passenger/register.php" class="btn-nav">Get Started</a>
  </nav>
</header>

<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-grid">

    <div class="hero-content">
      <div class="hero-badge">🚄 Now serving 500+ trains across India</div>
      <h1>
        Hot food delivered to your
        <span class="highlight">train seat</span>
      </h1>
      <p>
        Order freshly prepared meals from verified vendors at upcoming stations.
        Enter your PNR, pick your food, and relax — we deliver to your coach.
      </p>
      <div class="hero-btns">
        <a href="passenger/register.php" class="btn-primary">
          Order Now &rarr;
        </a>
        <a href="passenger/login.php" class="btn-outline">
          Sign In
        </a>
      </div>
    </div>

    <!-- Mock order card -->
    <div class="hero-visual">
      <div class="train-card">
        <div class="train-card-label">Your current journey</div>
        <div class="train-info">
          <div>
            <div class="train-name">Rajdhani Express</div>
            <div class="train-num">Train No: 12301 &nbsp;|&nbsp; PNR: 8841234567</div>
          </div>
          <div class="train-status">On Time</div>
        </div>
        <div class="seat-row">
          <div class="seat-pill">B4 — 32</div>
          <div class="seat-label">Next delivery station: Nagpur Jn (12:40)</div>
        </div>
        <div class="food-preview">
          <div class="food-item">
            <div class="food-item-left">
              <span class="food-emoji">🍛</span>
              <div>
                <div class="food-name">Veg Thali</div>
                <div class="food-desc">Dal, sabzi, roti, rice, pickle</div>
              </div>
            </div>
            <div class="food-price">₹120</div>
          </div>
          <div class="food-item">
            <div class="food-item-left">
              <span class="food-emoji">☕</span>
              <div>
                <div class="food-name">Masala Chai</div>
                <div class="food-desc">Hot ginger tea, 250ml</div>
              </div>
            </div>
            <div class="food-price">₹30</div>
          </div>
          <div class="order-total">
            <span class="order-total-label">Total</span>
            <span class="order-total-amt">₹150</span>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section class="section" id="how-it-works">
  <div class="section-inner">
    <div class="section-tag">Simple process</div>
    <h2 class="section-title">Order food in 4 easy steps</h2>
    <p class="section-sub">From browsing to your seat — the whole process takes under 2 minutes.</p>

    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <h3>Enter your PNR</h3>
        <p>We fetch your train details, route, and upcoming station stops automatically.</p>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <h3>Browse the menu</h3>
        <p>Pick from verified vendors at stations along your route. Filter by veg, price, and cuisine.</p>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <h3>Place your order</h3>
        <p>Confirm your seat number, pay via UPI, card, or cash on delivery.</p>
      </div>
      <div class="step">
        <div class="step-num">4</div>
        <h3>Enjoy your meal</h3>
        <p>The vendor delivers hot food directly to your coach at the station.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── FEATURES ── -->
<section class="features-bg" id="features">
  <div class="features">
    <div class="section-tag">Why TrainEats</div>
    <h2 class="section-title">Everything you need on the go</h2>
    <p class="section-sub" style="margin-bottom:44px;">Built for Indian railways. Designed for hungry passengers.</p>

    <div class="feat-grid">
      <div class="feat-card">
        <div class="feat-icon">🧾</div>
        <h3>PNR-based ordering</h3>
        <p>Just enter your PNR and we handle the rest — train number, coach, seat, and station timing.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">📍</div>
        <h3>Delivery at your seat</h3>
        <p>Vendors deliver food to your exact coach and seat number at the scheduled station stop.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">🕐</div>
        <h3>Live order tracking</h3>
        <p>Track every step — placed, confirmed, preparing, out for delivery, delivered.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">🥗</div>
        <h3>Veg &amp; non-veg both</h3>
        <p>Filter menus by dietary preference. All items are clearly labelled with green/red dots.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">💳</div>
        <h3>Multiple payment modes</h3>
        <p>Pay via UPI, credit/debit card, or cash on delivery — whatever works for you.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon">✅</div>
        <h3>Verified vendors only</h3>
        <p>Every vendor is registered, verified, and rated by passengers. Quality is guaranteed.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section">
  <div class="cta-inner">
    <div class="section-tag" style="text-align:center;">Ready to eat?</div>
    <h2 class="section-title">Your next meal is just a PNR away</h2>
    <p>Create a free account and start ordering from hundreds of vendors across India's railway network.</p>
    <div class="cta-btns">
      <a href="passenger/register.php" class="btn-cta-primary">Create free account</a>
      <a href="passenger/login.php"    class="btn-cta-outline">I already have an account</a>
    </div>
  </div>
</section>

<!-- ── FOOTER ── -->
<footer>
  <a class="brand" href="index.php">🚆 TrainEats</a>
  <p>&copy; <?= date('Y') ?> TrainEats. All rights reserved.</p>
  <div class="foot-links">
    <a href="passenger/register.php">Register</a>
    <a href="passenger/login.php">Login</a>
    <a href="vendor/login.php">Vendor</a>
    <a href="admin/login.php">Admin</a>
  </div>
</footer>

</body>
</html>
