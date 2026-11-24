<?php
// ============================================================
//  Train Food Ordering System — Menu + Cart + Checkout
//  File: passenger/menu.php  (with food images)
// ============================================================

session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'passenger') {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$action = isset($_POST['action']) ? $_POST['action'] : '';
$msg    = '';
$error  = '';

// ── HANDLE: Add to cart ───────────────────────────────────
if ($action === 'add_to_cart') {
    $item_id   = (int)$_POST['item_id'];
    $vendor_id = (int)$_POST['vendor_id'];

    if (!empty($_SESSION['cart']) && isset($_SESSION['cart']['vendor_id']) && $_SESSION['cart']['vendor_id'] != $vendor_id) {
        $error = "Your cart already has items from another vendor. Please clear cart first.";
    } else {
        $item = $conn->query(
            "SELECT id, name, price FROM menu_items
             WHERE id=$item_id AND vendor_id=$vendor_id AND is_available=1 LIMIT 1"
        )->fetch_assoc();

        if ($item) {
            $_SESSION['cart']['vendor_id'] = $vendor_id;
            if (isset($_SESSION['cart']['items'][$item_id])) {
                $_SESSION['cart']['items'][$item_id]['qty']++;
            } else {
                $_SESSION['cart']['items'][$item_id] = [
                    'name'  => $item['name'],
                    'price' => $item['price'],
                    'qty'   => 1,
                ];
            }
        }
    }
}

// ── HANDLE: Update quantity ───────────────────────────────
if ($action === 'update_qty') {
    $item_id = (int)$_POST['item_id'];
    $qty     = (int)$_POST['qty'];
    if ($qty <= 0) {
        unset($_SESSION['cart']['items'][$item_id]);
        if (empty($_SESSION['cart']['items'])) $_SESSION['cart'] = [];
    } else {
        $_SESSION['cart']['items'][$item_id]['qty'] = $qty;
    }
}

// ── HANDLE: Clear cart ────────────────────────────────────
if ($action === 'clear_cart') {
    $_SESSION['cart'] = [];
}

// ── HANDLE: Place order ───────────────────────────────────
if ($action === 'place_order') {
    $train_num  = trim($conn->real_escape_string($_POST['train_number']));
    $pnr        = trim($conn->real_escape_string($_POST['pnr_number']));
    $seat       = trim($conn->real_escape_string($_POST['seat_number']));
    $coach      = trim($conn->real_escape_string($_POST['coach_number']));
    $station    = trim($conn->real_escape_string($_POST['delivery_station']));
    $pay_method = $_POST['payment_method'];
    $note       = trim($conn->real_escape_string($_POST['special_note'] ?? ''));
    $vendor_id  = (int)$_SESSION['cart']['vendor_id'];

    if (empty($train_num) || empty($pnr) || empty($seat) || empty($coach) || empty($station)) {
        $error = "Please fill in all delivery details.";
    } elseif (empty($_SESSION['cart']['items'])) {
        $error = "Your cart is empty.";
    } else {
        $total = 0;
        foreach ($_SESSION['cart']['items'] as $it) {
            $total += $it['price'] * $it['qty'];
        }

        $stmt = $conn->prepare(
            "INSERT INTO orders
             (user_id, vendor_id, train_number, pnr_number, seat_number,
              coach_number, delivery_station, total_amount, payment_method, special_note)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            "iisssssdss",
            $user_id, $vendor_id, $train_num, $pnr, $seat,
            $coach, $station, $total, $pay_method, $note
        );

        if ($stmt->execute()) {
            $order_id = $conn->insert_id;

            foreach ($_SESSION['cart']['items'] as $item_id => $it) {
                $subtotal = $it['price'] * $it['qty'];
                $conn->query(
                    "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal)
                     VALUES ($order_id, $item_id, {$it['qty']}, {$it['price']}, $subtotal)"
                );
            }

            $conn->query(
                "INSERT INTO payments (order_id, amount, method, status)
                 VALUES ($order_id, $total, '$pay_method', 'pending')"
            );

            $conn->query(
                "INSERT INTO order_status_log (order_id, status) VALUES ($order_id, 'placed')"
            );

            $conn->query(
                "INSERT INTO notifications (user_id, message)
                 VALUES ($user_id, 'Your order #$order_id has been placed successfully!')"
            );

            $_SESSION['cart'] = [];
            header("Location: dashboard.php?tab=track&order_id=$order_id&placed=1");
            exit();
        } else {
            $error = "Failed to place order. Please try again.";
        }
        $stmt->close();
    }
}

// ── VIEW ──────────────────────────────────────────────────
$view      = isset($_GET['view'])      ? $_GET['view']      : 'vendors';
$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;
$filter    = isset($_GET['filter'])    ? $_GET['filter']    : 'all'; // all|veg|nonveg

// ── Fetch vendors ─────────────────────────────────────────
$vendors = $conn->query(
    "SELECT id, name, station_name, station_code FROM vendors WHERE is_active=1 ORDER BY station_name"
);

// ── Fetch menu ────────────────────────────────────────────
$vendor_info = null;
$menu_by_cat = [];

if ($view === 'menu' && $vendor_id > 0) {
    $vendor_info = $conn->query(
        "SELECT * FROM vendors WHERE id=$vendor_id AND is_active=1 LIMIT 1"
    )->fetch_assoc();

    if ($vendor_info) {
        $veg_filter = '';
        if ($filter === 'veg')    $veg_filter = 'AND mi.is_veg=1';
        if ($filter === 'nonveg') $veg_filter = 'AND mi.is_veg=0';

        $items = $conn->query(
            "SELECT mi.*, mc.name AS cat_name
             FROM menu_items mi
             JOIN menu_categories mc ON mi.category_id=mc.id
             WHERE mi.vendor_id=$vendor_id AND mi.is_available=1 $veg_filter
             ORDER BY mc.name, mi.name"
        );
        while ($item = $items->fetch_assoc()) {
            $menu_by_cat[$item['cat_name']][] = $item;
        }
    }
}

// ── Cart totals ───────────────────────────────────────────
$cart_count = 0;
$cart_total = 0;
if (!empty($_SESSION['cart']['items'])) {
    foreach ($_SESSION['cart']['items'] as $it) {
        $cart_count += $it['qty'];
        $cart_total += $it['price'] * $it['qty'];
    }
}

// ── Image helper ──────────────────────────────────────────
function foodImage($img, $name) {
    if (!empty($img) && file_exists('../assets/images/food/' . $img)) {
        return '<img src="../assets/images/food/' . htmlspecialchars($img) . '"
                alt="' . htmlspecialchars($name) . '"
                style="width:100%;height:100%;object-fit:cover;">';
    }
    // Placeholder with first letter
    $colors = ['#eff6ff','#f0fdf4','#fff7ed','#fdf4ff','#fffbeb','#fef2f2'];
    $col    = $colors[abs(crc32($name)) % count($colors)];
    $letter = strtoupper(substr($name, 0, 1));
    return '<div style="width:100%;height:100%;background:' . $col . ';
            display:flex;align-items:center;justify-content:center;
            font-size:32px;font-weight:700;color:#6b7280;">' . $letter . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Food — TrainEats</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;color:#111827;}

    nav{background:#1a56db;padding:0 24px;display:flex;align-items:center;
        justify-content:space-between;height:56px;position:sticky;top:0;z-index:200;}
    .nav-brand{color:#fff;font-size:18px;font-weight:700;text-decoration:none;}
    .nav-back{color:#bfdbfe;font-size:13px;text-decoration:none;display:flex;align-items:center;gap:6px;}
    .nav-back:hover{color:#fff;}
    .cart-btn{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.15);
              color:#fff;border:none;padding:7px 16px;border-radius:8px;font-size:13px;
              font-weight:600;cursor:pointer;text-decoration:none;}
    .cart-btn:hover{background:rgba(255,255,255,0.25);}
    .cart-badge{background:#f97316;color:#fff;border-radius:10px;padding:1px 8px;font-size:11px;font-weight:700;}

    .layout{max-width:1100px;margin:0 auto;padding:24px 20px 60px;
            display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;}

    .alert-success{background:#f0fdf4;border:1px solid #86efac;color:#166534;
                   border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;}
    .alert-error  {background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;
                   border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;}

    /* VENDOR CARDS */
    .vendor-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;}
    .vendor-card{background:#fff;border-radius:12px;padding:20px;
                 box-shadow:0 1px 6px rgba(0,0,0,0.07);border:1.5px solid #e5e7eb;
                 text-decoration:none;color:#111827;
                 transition:border-color 0.2s,box-shadow 0.2s;display:block;}
    .vendor-card:hover{border-color:#1a56db;box-shadow:0 4px 14px rgba(26,86,219,0.12);}
    .vendor-name{font-size:16px;font-weight:700;margin-bottom:6px;}
    .vendor-station{font-size:13px;color:#6b7280;display:flex;align-items:center;gap:5px;}
    .vendor-code{background:#eff6ff;color:#1e40af;font-size:11px;font-weight:700;
                 padding:2px 8px;border-radius:10px;margin-left:6px;}
    .view-menu-btn{display:inline-block;margin-top:14px;padding:7px 18px;
                   background:#1a56db;color:#fff;border-radius:8px;font-size:13px;font-weight:600;}

    /* BREADCRUMB & FILTER */
    .breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:#6b7280;margin-bottom:12px;}
    .breadcrumb a{color:#1a56db;text-decoration:none;}
    .breadcrumb a:hover{text-decoration:underline;}

    .filter-bar{display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
    .page-title{font-size:20px;font-weight:700;margin-bottom:4px;}
    .page-sub{font-size:14px;color:#6b7280;margin-bottom:14px;}
    .filter-btn{padding:6px 16px;border-radius:20px;border:1.5px solid #e5e7eb;
                background:#fff;font-size:13px;font-weight:600;cursor:pointer;
                color:#374151;text-decoration:none;transition:all 0.15s;}
    .filter-btn:hover{border-color:#1a56db;color:#1a56db;}
    .filter-btn.active{background:#1a56db;color:#fff;border-color:#1a56db;}
    .filter-veg   {border-color:#86efac;color:#166534;}
    .filter-veg.active{background:#dcfce7;color:#166534;border-color:#86efac;}
    .filter-nveg  {border-color:#fca5a5;color:#991b1b;}
    .filter-nveg.active{background:#fee2e2;color:#991b1b;border-color:#fca5a5;}

    /* CATEGORY */
    .cat-title{font-size:14px;font-weight:700;color:#374151;padding:10px 0 10px;
               border-bottom:2px solid #e5e7eb;margin-bottom:16px;margin-top:24px;}
    .cat-title:first-of-type{margin-top:0;}

    /* FOOD CARDS (image style) */
    .food-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:4px;}

    .food-card{background:#fff;border-radius:12px;overflow:hidden;
               box-shadow:0 1px 6px rgba(0,0,0,0.07);
               border:1.5px solid #e5e7eb;
               transition:box-shadow 0.2s,border-color 0.2s;display:flex;flex-direction:column;}
    .food-card:hover{box-shadow:0 4px 14px rgba(0,0,0,0.10);border-color:#d1d5db;}

    .food-img{width:100%;height:140px;overflow:hidden;position:relative;flex-shrink:0;}
    .food-img img{width:100%;height:100%;object-fit:cover;transition:transform 0.3s;}
    .food-card:hover .food-img img{transform:scale(1.05);}

    .veg-tag{position:absolute;top:8px;left:8px;width:18px;height:18px;
             border-radius:3px;border:2px solid;display:flex;align-items:center;justify-content:center;}
    .veg-tag.veg  {border-color:#166534;background:#fff;}
    .veg-tag.veg::after{content:'';width:8px;height:8px;border-radius:50%;background:#166534;}
    .veg-tag.nveg {border-color:#991b1b;background:#fff;}
    .veg-tag.nveg::after{content:'';width:8px;height:8px;border-radius:50%;background:#991b1b;}

    .food-body{padding:12px;flex:1;display:flex;flex-direction:column;}
    .food-name{font-size:14px;font-weight:700;color:#111827;margin-bottom:4px;
               white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .food-desc{font-size:12px;color:#6b7280;line-height:1.4;flex:1;
               display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .food-footer{display:flex;align-items:center;justify-content:space-between;
                 margin-top:10px;padding-top:10px;border-top:1px solid #f3f4f6;}
    .food-price{font-size:15px;font-weight:800;color:#1a56db;}

    .add-btn{padding:5px 14px;background:#1a56db;color:#fff;border:none;
             border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:background 0.15s;}
    .add-btn:hover{background:#1e40af;}
    .added-btn{padding:5px 14px;background:#dcfce7;color:#166534;border:none;
               border-radius:7px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:4px;}

    /* CART SIDEBAR */
    .cart-sidebar{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,0.07);
                  position:sticky;top:76px;}
    .cart-header{padding:16px 20px;border-bottom:1px solid #f3f4f6;font-size:15px;
                 font-weight:700;color:#111827;display:flex;justify-content:space-between;align-items:center;}
    .clear-btn{font-size:12px;color:#ef4444;background:none;border:none;cursor:pointer;font-weight:600;}
    .clear-btn:hover{text-decoration:underline;}
    .cart-empty{padding:32px 20px;text-align:center;color:#9ca3af;font-size:13px;}

    .cart-item{display:flex;align-items:center;justify-content:space-between;
               padding:10px 20px;border-bottom:1px solid #f9fafb;gap:10px;}
    .ci-name{font-size:13px;font-weight:600;color:#111827;flex:1;}
    .ci-price{font-size:13px;color:#6b7280;white-space:nowrap;}
    .qty-ctrl{display:flex;align-items:center;gap:6px;}
    .qty-btn{width:24px;height:24px;border-radius:6px;border:1.5px solid #d1d5db;background:#fff;
             font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-weight:700;}
    .qty-btn:hover{border-color:#1a56db;color:#1a56db;}
    .qty-num{font-size:13px;font-weight:700;min-width:18px;text-align:center;}

    .cart-total{padding:14px 20px;border-top:1px solid #e5e7eb;
                display:flex;justify-content:space-between;align-items:center;}
    .cart-total-label{font-size:14px;font-weight:700;color:#374151;}
    .cart-total-amt{font-size:18px;font-weight:800;color:#1a56db;}

    .checkout-section{border-top:1px solid #e5e7eb;padding:16px 20px;}
    .checkout-section h3{font-size:12px;font-weight:700;color:#9ca3af;
                         margin-bottom:12px;text-transform:uppercase;letter-spacing:0.05em;}
    .form-group{margin-bottom:10px;}
    .form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;}
    .form-group input,.form-group select,.form-group textarea{
      width:100%;padding:8px 12px;border:1.5px solid #d1d5db;border-radius:7px;
      font-size:13px;color:#111827;outline:none;font-family:inherit;}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#1a56db;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;}

    .pay-opts{display:flex;flex-direction:column;gap:6px;}
    .pay-opt{display:flex;align-items:center;gap:8px;padding:8px 10px;
             border:1.5px solid #e5e7eb;border-radius:8px;cursor:pointer;
             font-size:13px;font-weight:500;color:#374151;transition:border-color 0.15s;}
    .pay-opt:hover{border-color:#1a56db;}
    .pay-opt input[type=radio]{accent-color:#1a56db;}

    .place-btn{width:100%;padding:12px;background:#1a56db;color:#fff;border:none;
               border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;
               margin-top:4px;transition:background 0.2s;}
    .place-btn:hover{background:#1e40af;}

    .empty-state{text-align:center;padding:60px 20px;color:#9ca3af;}
    .empty-state p{font-size:14px;margin-top:10px;}

    @media(max-width:800px){
      .layout{grid-template-columns:1fr;}
      .cart-sidebar{position:static;}
      .food-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr));}
    }
    @media(max-width:480px){
      .food-grid{grid-template-columns:1fr 1fr;}
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <a class="nav-brand" href="dashboard.php">🚆 TrainEats</a>
  <a class="nav-back" href="dashboard.php">&#8592; Dashboard</a>
  <?php if ($cart_count > 0): ?>
  <a href="#cart" class="cart-btn">
    🛒 Cart <span class="cart-badge"><?= $cart_count ?></span>
    &nbsp;&#8377;<?= number_format($cart_total,2) ?>
  </a>
  <?php endif; ?>
</nav>

<div class="layout">

  <!-- ══════════ MAIN ══════════ -->
  <div class="main">

    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php // ═══ VENDOR LIST ═══
    if ($view === 'vendors'): ?>

      <div class="page-title">Choose a vendor</div>
      <div class="page-sub">Select a food stall at your upcoming station</div>

      <?php if ($vendors->num_rows > 0): ?>
      <div class="vendor-grid">
        <?php while ($v = $vendors->fetch_assoc()): ?>
        <a class="vendor-card" href="?view=menu&vendor_id=<?= $v['id'] ?>">
          <div class="vendor-name">🍽️ <?= htmlspecialchars($v['name']) ?></div>
          <div class="vendor-station">
            📍 <?= htmlspecialchars($v['station_name']) ?>
            <span class="vendor-code"><?= htmlspecialchars($v['station_code']) ?></span>
          </div>
          <span class="view-menu-btn">View Menu &rarr;</span>
        </a>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
        <div class="empty-state"><div style="font-size:48px;">🏪</div><p>No vendors available.</p></div>
      <?php endif; ?>

    <?php // ═══ MENU VIEW ═══
    elseif ($view === 'menu' && $vendor_info): ?>

      <div class="breadcrumb">
        <a href="?view=vendors">All Vendors</a>
        <span>›</span>
        <span><?= htmlspecialchars($vendor_info['name']) ?></span>
      </div>

      <div class="page-title"><?= htmlspecialchars($vendor_info['name']) ?></div>
      <div class="page-sub">📍 <?= htmlspecialchars($vendor_info['station_name']) ?> (<?= htmlspecialchars($vendor_info['station_code']) ?>)</div>

      <!-- Filter bar -->
      <div class="filter-bar">
        <span style="font-size:13px;font-weight:600;color:#374151;">Filter:</span>
        <a href="?view=menu&vendor_id=<?= $vendor_id ?>&filter=all"    class="filter-btn <?= $filter==='all'    ?'active':'' ?>">All</a>
        <a href="?view=menu&vendor_id=<?= $vendor_id ?>&filter=veg"    class="filter-btn filter-veg <?= $filter==='veg'    ?'active':'' ?>">🟢 Veg</a>
        <a href="?view=menu&vendor_id=<?= $vendor_id ?>&filter=nonveg" class="filter-btn filter-nveg <?= $filter==='nonveg' ?'active':'' ?>">🔴 Non-Veg</a>
      </div>

      <?php if (empty($menu_by_cat)): ?>
        <div class="empty-state"><div style="font-size:48px;">🍽️</div><p>No items available.</p></div>
      <?php else: ?>
        <?php foreach ($menu_by_cat as $cat => $items): ?>
          <div class="cat-title"><?= htmlspecialchars($cat) ?></div>
          <div class="food-grid">
            <?php foreach ($items as $item):
              $in_cart = isset($_SESSION['cart']['items'][$item['id']]);
            ?>
            <div class="food-card">

              <!-- Image -->
              <div class="food-img">
                <?= foodImage($item['image'], $item['name']) ?>
                <div class="veg-tag <?= $item['is_veg'] ? 'veg' : 'nveg' ?>"></div>
              </div>

              <!-- Info -->
              <div class="food-body">
                <div class="food-name"><?= htmlspecialchars($item['name']) ?></div>
                <?php if ($item['description']): ?>
                  <div class="food-desc"><?= htmlspecialchars($item['description']) ?></div>
                <?php endif; ?>

                <div class="food-footer">
                  <div class="food-price">&#8377;<?= number_format($item['price'],2) ?></div>

                  <?php if ($in_cart): ?>
                    <div class="added-btn">&#10003; Added</div>
                  <?php else: ?>
                    <form method="POST" action="">
                      <input type="hidden" name="action"    value="add_to_cart">
                      <input type="hidden" name="item_id"   value="<?= $item['id'] ?>">
                      <input type="hidden" name="vendor_id" value="<?= $vendor_info['id'] ?>">
                      <button type="submit" class="add-btn">+ Add</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>

            </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php elseif ($view === 'menu'): ?>
      <div class="alert-error">Vendor not found.</div>
      <a href="?view=vendors" style="color:#1a56db;font-size:14px;">&#8592; Back to vendors</a>
    <?php endif; ?>

  </div><!-- /main -->

  <!-- ══════════ CART SIDEBAR ══════════ -->
  <div class="cart-sidebar" id="cart">
    <div class="cart-header">
      🛒 Your Cart
      <?php if (!empty($_SESSION['cart']['items'])): ?>
      <form method="POST" action="" style="display:inline;">
        <input type="hidden" name="action" value="clear_cart">
        <button type="submit" class="clear-btn">Clear</button>
      </form>
      <?php endif; ?>
    </div>

    <?php if (empty($_SESSION['cart']['items'])): ?>
      <div class="cart-empty">Your cart is empty.<br>Add items from the menu.</div>
    <?php else: ?>

      <?php foreach ($_SESSION['cart']['items'] as $item_id => $it): ?>
      <div class="cart-item">
        <div class="ci-name"><?= htmlspecialchars($it['name']) ?></div>
        <div class="qty-ctrl">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action"  value="update_qty">
            <input type="hidden" name="item_id" value="<?= $item_id ?>">
            <input type="hidden" name="qty"     value="<?= $it['qty']-1 ?>">
            <button type="submit" class="qty-btn">-</button>
          </form>
          <span class="qty-num"><?= $it['qty'] ?></span>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action"  value="update_qty">
            <input type="hidden" name="item_id" value="<?= $item_id ?>">
            <input type="hidden" name="qty"     value="<?= $it['qty']+1 ?>">
            <button type="submit" class="qty-btn">+</button>
          </form>
        </div>
        <div class="ci-price">&#8377;<?= number_format($it['price']*$it['qty'],2) ?></div>
      </div>
      <?php endforeach; ?>

      <div class="cart-total">
        <span class="cart-total-label">Total</span>
        <span class="cart-total-amt">&#8377;<?= number_format($cart_total,2) ?></span>
      </div>

      <form method="POST" action="?view=<?= $view ?>&vendor_id=<?= $vendor_id ?>">
        <input type="hidden" name="action" value="place_order">

        <div class="checkout-section">
          <h3>Delivery Details</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Train No. *</label>
              <input type="text" name="train_number" placeholder="e.g. 12301"
                     value="<?= isset($_POST['train_number'])?htmlspecialchars($_POST['train_number']):'' ?>" required>
            </div>
            <div class="form-group">
              <label>PNR *</label>
              <input type="text" name="pnr_number" placeholder="10-digit"
                     value="<?= isset($_POST['pnr_number'])?htmlspecialchars($_POST['pnr_number']):'' ?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Coach *</label>
              <input type="text" name="coach_number" placeholder="e.g. B4"
                     value="<?= isset($_POST['coach_number'])?htmlspecialchars($_POST['coach_number']):'' ?>" required>
            </div>
            <div class="form-group">
              <label>Seat *</label>
              <input type="text" name="seat_number" placeholder="e.g. 32"
                     value="<?= isset($_POST['seat_number'])?htmlspecialchars($_POST['seat_number']):'' ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Delivery Station *</label>
            <input type="text" name="delivery_station" placeholder="e.g. Nagpur Jn"
                   value="<?= isset($_POST['delivery_station'])?htmlspecialchars($_POST['delivery_station']):'' ?>" required>
          </div>
          <div class="form-group">
            <label>Special Note</label>
            <textarea name="special_note" rows="2" placeholder="Spice level, allergies..."
                      style="resize:none;"><?= isset($_POST['special_note'])?htmlspecialchars($_POST['special_note']):'' ?></textarea>
          </div>
        </div>

        <div class="checkout-section" style="padding-top:0;">
          <h3>Payment</h3>
          <div class="pay-opts">
            <label class="pay-opt"><input type="radio" name="payment_method" value="COD" checked> 💵 Cash on Delivery</label>
            <label class="pay-opt"><input type="radio" name="payment_method" value="UPI"> 📱 UPI</label>
            <label class="pay-opt"><input type="radio" name="payment_method" value="CARD"> 💳 Card</label>
          </div>
        </div>

        <div style="padding:0 20px 20px;">
          <button type="submit" class="place-btn">
            Place Order — &#8377;<?= number_format($cart_total,2) ?>
          </button>
        </div>
      </form>

    <?php endif; ?>
  </div><!-- /cart -->

</div><!-- /layout -->
<script src="../assets/js/cart.js"></script>
</body>
</html>
