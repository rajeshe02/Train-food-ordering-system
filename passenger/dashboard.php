<?php
// ============================================================
//  Train Food Ordering System — Passenger Dashboard (with tabs)
//  File: passenger/dashboard.php
//  Tabs: Home | My Orders | Track Order | Profile
// ============================================================

session_start();
require_once '../config/db.php';

// Block if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'passenger') {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ── Active tab ────────────────────────────────────────────
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';

// ── Summary counts ────────────────────────────────────────
$total_orders = $conn->query(
    "SELECT COUNT(*) AS cnt FROM orders WHERE user_id=$user_id"
)->fetch_assoc()['cnt'];

$active_orders = $conn->query(
    "SELECT COUNT(*) AS cnt FROM orders
     WHERE user_id=$user_id AND status NOT IN ('delivered','cancelled')"
)->fetch_assoc()['cnt'];

$delivered_orders = $conn->query(
    "SELECT COUNT(*) AS cnt FROM orders
     WHERE user_id=$user_id AND status='delivered'"
)->fetch_assoc()['cnt'];

// ── Recent orders (home tab) ──────────────────────────────
$recent = $conn->query(
    "SELECT o.id, o.train_number, o.pnr_number, o.seat_number, o.coach_number,
            o.delivery_station, o.total_amount, o.status, o.created_at,
            v.name AS vendor_name
     FROM orders o JOIN vendors v ON o.vendor_id=v.id
     WHERE o.user_id=$user_id
     ORDER BY o.created_at DESC LIMIT 5"
);

// ── All orders (my orders tab) ────────────────────────────
$all_orders = $conn->query(
    "SELECT o.id, o.train_number, o.pnr_number, o.seat_number, o.coach_number,
            o.delivery_station, o.total_amount, o.status, o.payment_method,
            o.created_at, v.name AS vendor_name
     FROM orders o JOIN vendors v ON o.vendor_id=v.id
     WHERE o.user_id=$user_id
     ORDER BY o.created_at DESC"
);

// ── Track order ───────────────────────────────────────────
$track_order = null;
$track_items = null;
$track_log   = null;
$track_id    = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($tab === 'track' && $track_id > 0) {
    $r = $conn->query(
        "SELECT o.*, v.name AS vendor_name, v.station_name
         FROM orders o JOIN vendors v ON o.vendor_id=v.id
         WHERE o.id=$track_id AND o.user_id=$user_id LIMIT 1"
    );
    if ($r->num_rows === 1) {
        $track_order = $r->fetch_assoc();
        $track_items = $conn->query(
            "SELECT oi.*, m.name AS item_name
             FROM order_items oi JOIN menu_items m ON oi.menu_item_id=m.id
             WHERE oi.order_id=$track_id"
        );
        $track_log = $conn->query(
            "SELECT * FROM order_status_log
             WHERE order_id=$track_id ORDER BY changed_at ASC"
        );
    }
}

// ── Profile update ────────────────────────────────────────
$profile_msg   = '';
$profile_error = '';
if ($tab === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name  = trim($conn->real_escape_string($_POST['name']));
    $new_phone = trim($conn->real_escape_string($_POST['phone']));
    $new_pass  = $_POST['new_password'];
    $cur_pass  = $_POST['current_password'];

    if (empty($new_name)) {
        $profile_error = "Name cannot be empty.";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $new_phone)) {
        $profile_error = "Enter a valid 10-digit mobile number.";
    } else {
        $u = $conn->query("SELECT password FROM users WHERE id=$user_id")->fetch_assoc();
        if (!password_verify($cur_pass, $u['password'])) {
            $profile_error = "Current password is incorrect.";
        } else {
            if (!empty($new_pass)) {
                if (strlen($new_pass) < 6) {
                    $profile_error = "New password must be at least 6 characters.";
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                    $conn->query("UPDATE users SET name='$new_name', phone='$new_phone', password='$hashed' WHERE id=$user_id");
                }
            } else {
                $conn->query("UPDATE users SET name='$new_name', phone='$new_phone' WHERE id=$user_id");
            }
            if (empty($profile_error)) {
                $_SESSION['user_name'] = $new_name;
                $user_name = $new_name;
                $profile_msg = "Profile updated successfully!";
            }
        }
    }
}

// ── Fetch profile data ────────────────────────────────────
$profile = $conn->query(
    "SELECT name, email, phone, created_at FROM users WHERE id=$user_id"
)->fetch_assoc();

// ── Notifications ─────────────────────────────────────────
$notifs = $conn->query(
    "SELECT message, created_at FROM notifications
     WHERE user_id=$user_id AND is_read=0 ORDER BY created_at DESC LIMIT 5"
);
$notif_count = $notifs->num_rows;
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$user_id");

// ── Status badge helper ───────────────────────────────────
function statusBadge($status) {
    $map = [
        'placed'           => ['#dbeafe','#1e40af','Placed'],
        'confirmed'        => ['#ede9fe','#5b21b6','Confirmed'],
        'preparing'        => ['#fef3c7','#92400e','Preparing'],
        'out_for_delivery' => ['#ffedd5','#9a3412','Out for Delivery'],
        'delivered'        => ['#dcfce7','#166534','Delivered'],
        'cancelled'        => ['#fee2e2','#991b1b','Cancelled'],
    ];
    $s = $map[$status] ?? ['#f3f4f6','#374151', ucfirst($status)];
    return "<span style='background:{$s[0]};color:{$s[1]};padding:3px 10px;
            border-radius:20px;font-size:12px;font-weight:600;'>{$s[2]}</span>";
}

$status_steps = ['placed','confirmed','preparing','out_for_delivery','delivered'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — TrainEats</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;color:#111827;}

    nav{background:#1a56db;padding:0 24px;display:flex;align-items:center;
        justify-content:space-between;height:56px;position:sticky;top:0;z-index:100;}
    .nav-brand{color:#fff;font-size:18px;font-weight:700;text-decoration:none;}
    .nav-user{display:flex;align-items:center;gap:10px;color:#bfdbfe;font-size:13px;}
    .avatar{width:32px;height:32px;background:#3b82f6;border-radius:50%;display:flex;
            align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;}
    .logout-btn{background:none;border:none;color:#bfdbfe;font-size:13px;cursor:pointer;
                padding:6px 12px;border-radius:6px;}
    .logout-btn:hover{background:rgba(255,255,255,0.15);color:#fff;}

    .tab-bar{background:#fff;border-bottom:1px solid #e5e7eb;display:flex;
             padding:0 24px;overflow-x:auto;gap:0;}
    .tab-btn{display:flex;align-items:center;gap:7px;padding:14px 20px;font-size:14px;
             font-weight:600;color:#6b7280;text-decoration:none;border-bottom:3px solid transparent;
             white-space:nowrap;transition:all 0.2s;}
    .tab-btn:hover{color:#1a56db;}
    .tab-btn.active{color:#1a56db;border-bottom-color:#1a56db;}
    .tab-order{background:#1a56db;color:#fff !important;margin:8px 0 8px 8px;
               border-radius:8px;border-bottom:none !important;padding:8px 18px !important;}
    .tab-order:hover{background:#1e40af !important;}

    .page{max-width:960px;margin:0 auto;padding:28px 20px 60px;}

    .card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,0.07);
          overflow:hidden;margin-bottom:24px;}
    .card-header{padding:16px 20px;border-bottom:1px solid #f3f4f6;
                 font-size:15px;font-weight:700;color:#111827;}

    .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
    .stat-card{background:#fff;border-radius:12px;padding:20px;text-align:center;
               box-shadow:0 1px 6px rgba(0,0,0,0.07);}
    .stat-num{font-size:32px;font-weight:700;color:#1a56db;}
    .stat-lbl{font-size:13px;color:#6b7280;margin-top:4px;}

    .actions{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:24px;}
    .action-btn{display:flex;align-items:center;gap:14px;background:#fff;
                border:1.5px solid #e5e7eb;border-radius:12px;padding:18px 20px;
                text-decoration:none;color:#111827;font-weight:600;font-size:14px;
                transition:border-color 0.2s,box-shadow 0.2s;}
    .action-btn:hover{border-color:#1a56db;box-shadow:0 2px 10px rgba(26,86,219,0.12);}
    .action-icon{width:42px;height:42px;border-radius:10px;display:flex;
                 align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
    .action-sub{font-size:12px;color:#6b7280;font-weight:400;margin-top:2px;}

    table{width:100%;border-collapse:collapse;font-size:13px;}
    thead th{background:#f9fafb;padding:12px 16px;text-align:left;font-weight:600;
             color:#374151;border-bottom:1px solid #e5e7eb;}
    tbody td{padding:12px 16px;border-bottom:1px solid #f3f4f6;color:#374151;}
    tbody tr:last-child td{border-bottom:none;}
    tbody tr:hover td{background:#f9fafb;}
    .view-btn{display:inline-block;padding:4px 12px;background:#eff6ff;color:#1a56db;
              border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;}
    .view-btn:hover{background:#dbeafe;}

    .empty{text-align:center;padding:48px 20px;color:#9ca3af;}
    .empty p{font-size:14px;margin-top:8px;}

    .tracker-steps{display:flex;align-items:center;justify-content:center;
                   padding:28px 20px;overflow-x:auto;}
    .t-step{display:flex;flex-direction:column;align-items:center;min-width:90px;}
    .t-circle{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;
              justify-content:center;font-size:16px;border:2px solid #e5e7eb;
              background:#fff;margin-bottom:8px;}
    .t-circle.done{background:#dcfce7;border-color:#86efac;}
    .t-circle.active{background:#dbeafe;border-color:#93c5fd;}
    .t-label{font-size:11px;font-weight:600;color:#6b7280;text-align:center;line-height:1.3;}
    .t-label.done{color:#166534;}
    .t-label.active{color:#1e40af;}
    .t-line{flex:1;height:2px;background:#e5e7eb;min-width:20px;margin-bottom:20px;}
    .t-line.done{background:#86efac;}

    .order-meta{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;padding:16px 20px;}
    .meta-item label{font-size:11px;font-weight:700;color:#9ca3af;
                     text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:2px;}
    .meta-item span{font-size:14px;font-weight:600;color:#111827;}

    .log-list{padding:0 20px 20px;}
    .log-item{display:flex;gap:14px;padding:10px 0;border-bottom:1px solid #f3f4f6;}
    .log-item:last-child{border-bottom:none;}
    .log-dot{width:10px;height:10px;border-radius:50%;background:#1a56db;
             flex-shrink:0;margin-top:4px;}
    .log-status{font-size:13px;font-weight:600;color:#111827;}
    .log-time{font-size:12px;color:#9ca3af;margin-top:2px;}

    .track-search{display:flex;gap:12px;padding:20px;align-items:flex-end;}
    .track-search input{flex:1;padding:10px 14px;border:1.5px solid #d1d5db;
                        border-radius:8px;font-size:14px;outline:none;}
    .track-search input:focus{border-color:#1a56db;}
    .track-search button{padding:10px 22px;background:#1a56db;color:#fff;border:none;
                         border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;}
    .track-search button:hover{background:#1e40af;}

    .items-list{padding:0 20px 16px;}
    .item-row{display:flex;justify-content:space-between;align-items:center;
              padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:13px;}
    .item-row:last-child{border-bottom:none;}
    .item-name{font-weight:600;color:#111827;}
    .item-qty{color:#6b7280;}
    .item-price{font-weight:700;color:#1a56db;}

    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px;}
    .form-full{grid-column:1/-1;}
    .form-group label{display:block;font-size:13px;font-weight:600;
                      color:#374151;margin-bottom:6px;}
    .form-group input{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;
                      border-radius:8px;font-size:14px;color:#111827;outline:none;}
    .form-group input:focus{border-color:#1a56db;}
    .form-group input[readonly]{background:#f9fafb;color:#6b7280;}
    .save-btn{margin:0 20px 20px;padding:11px 28px;background:#1a56db;color:#fff;
              border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;}
    .save-btn:hover{background:#1e40af;}

    .alert-success{background:#f0fdf4;border:1px solid #86efac;color:#166534;
                   border-radius:8px;padding:12px 16px;margin:16px 20px 0;font-size:13px;}
    .alert-error{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;
                 border-radius:8px;padding:12px 16px;margin:0 0 16px;font-size:13px;}

    .notif-panel{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
                 padding:14px 16px;margin-bottom:20px;}
    .notif-panel h3{font-size:13px;font-weight:700;color:#92400e;margin-bottom:8px;}
    .notif-item{font-size:13px;color:#78350f;padding:4px 0;border-bottom:1px solid #fde68a;}
    .notif-item:last-child{border-bottom:none;}

    .sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
    .sec-hdr h2{font-size:15px;font-weight:700;color:#111827;}
    .sec-hdr a{font-size:13px;color:#1a56db;text-decoration:none;}

    @media(max-width:600px){
      .stats{gap:8px;}
      .actions{grid-template-columns:1fr;}
      .form-grid{grid-template-columns:1fr;}
      .form-full{grid-column:1;}
      table{display:block;overflow-x:auto;}
      .order-meta{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <a class="nav-brand" href="dashboard.php">🚆 TrainEats</a>
  <div class="nav-user">
    <div class="avatar"><?= strtoupper(substr($user_name,0,1)) ?></div>
    <span><?= htmlspecialchars(explode(' ',$user_name)[0]) ?></span>
    <form method="POST" action="logout.php" style="display:inline;">
      <button type="submit" class="logout-btn">Logout</button>
    </form>
  </div>
</nav>

<!-- TABS -->
<div class="tab-bar">
  <a href="?tab=home"    class="tab-btn <?= $tab==='home'    ?'active':'' ?>">🏠 Home</a>
  <a href="?tab=orders"  class="tab-btn <?= $tab==='orders'  ?'active':'' ?>">📋 My Orders</a>
  <a href="?tab=track"   class="tab-btn <?= $tab==='track'   ?'active':'' ?>">
    🚚 Track
    <?= $active_orders>0 ? "<span style='background:#ef4444;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;'>$active_orders</span>" : '' ?>
  </a>
  <a href="?tab=profile" class="tab-btn <?= $tab==='profile' ?'active':'' ?>">👤 Profile</a>
  <a href="menu.php"     class="tab-btn tab-order">🍱 Order Food</a>
</div>

<div class="page">

<?php // ══════════════════ HOME TAB ══════════════════
if ($tab === 'home'): ?>

  <?php if ($notif_count > 0): ?>
  <div class="notif-panel">
    <h3>🔔 <?= $notif_count ?> new notification<?= $notif_count>1?'s':'' ?></h3>
    <?php while ($n=$notifs->fetch_assoc()): ?>
      <div class="notif-item"><?= htmlspecialchars($n['message']) ?></div>
    <?php endwhile; ?>
  </div>
  <?php endif; ?>

  <div style="margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:700;">Hello, <?= htmlspecialchars(explode(' ',$user_name)[0]) ?> 👋</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;">What would you like to eat today?</p>
  </div>

  <div class="stats">
    <div class="stat-card"><div class="stat-num"><?= $total_orders ?></div><div class="stat-lbl">Total Orders</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#f59e0b;"><?= $active_orders ?></div><div class="stat-lbl">Active</div></div>
    <div class="stat-card"><div class="stat-num" style="color:#10b981;"><?= $delivered_orders ?></div><div class="stat-lbl">Delivered</div></div>
  </div>

  <div class="actions">
    <a class="action-btn" href="menu.php">
      <div class="action-icon" style="background:#eff6ff;">🍱</div>
      <div><div>Order Food</div><div class="action-sub">Browse menu by train/station</div></div>
    </a>
    <a class="action-btn" href="?tab=track">
      <div class="action-icon" style="background:#fffbeb;">🚚</div>
      <div><div>Track Order</div><div class="action-sub">Live delivery status</div></div>
    </a>
    <a class="action-btn" href="?tab=orders">
      <div class="action-icon" style="background:#f0fdf4;">📋</div>
      <div><div>My Orders</div><div class="action-sub">View all past orders</div></div>
    </a>
    <a class="action-btn" href="?tab=profile">
      <div class="action-icon" style="background:#fdf4ff;">👤</div>
      <div><div>My Profile</div><div class="action-sub">Edit your details</div></div>
    </a>
  </div>

  <div class="sec-hdr">
    <h2>Recent Orders</h2>
    <a href="?tab=orders">View all &rarr;</a>
  </div>
  <div class="card">
    <?php if ($recent->num_rows > 0): ?>
    <table>
      <thead><tr><th>#</th><th>Vendor</th><th>Train</th><th>Seat</th><th>Amount</th><th>Status</th><th>Track</th></tr></thead>
      <tbody>
        <?php while ($row=$recent->fetch_assoc()): ?>
        <tr>
          <td>#<?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['vendor_name']) ?></td>
          <td><?= htmlspecialchars($row['train_number']) ?></td>
          <td><?= htmlspecialchars($row['coach_number']) ?>/<?= htmlspecialchars($row['seat_number']) ?></td>
          <td>&#8377;<?= number_format($row['total_amount'],2) ?></td>
          <td><?= statusBadge($row['status']) ?></td>
          <td><a class="view-btn" href="?tab=track&order_id=<?= $row['id'] ?>">Track</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <div style="font-size:40px;">🍽️</div>
      <p>No orders yet. <a href="menu.php" style="color:#1a56db;">Order your first meal!</a></p>
    </div>
    <?php endif; ?>
  </div>

<?php // ══════════════════ MY ORDERS TAB ══════════════════
elseif ($tab === 'orders'): ?>

  <div style="margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">My Orders</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;">Complete order history</p>
  </div>

  <div class="card">
    <?php if ($all_orders->num_rows > 0): ?>
    <table>
      <thead><tr><th>#</th><th>Vendor</th><th>Train / PNR</th><th>Station</th><th>Amount</th><th>Payment</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php while ($row=$all_orders->fetch_assoc()): ?>
        <tr>
          <td>#<?= $row['id'] ?><br><span style="font-size:11px;color:#9ca3af;"><?= date('d M Y',strtotime($row['created_at'])) ?></span></td>
          <td><?= htmlspecialchars($row['vendor_name']) ?></td>
          <td><?= htmlspecialchars($row['train_number']) ?><br><span style="font-size:11px;color:#9ca3af;">PNR: <?= htmlspecialchars($row['pnr_number']) ?></span></td>
          <td><?= htmlspecialchars($row['delivery_station']) ?></td>
          <td>&#8377;<?= number_format($row['total_amount'],2) ?></td>
          <td style="text-transform:uppercase;font-size:12px;"><?= htmlspecialchars($row['payment_method']) ?></td>
          <td><?= statusBadge($row['status']) ?></td>
          <td><a class="view-btn" href="?tab=track&order_id=<?= $row['id'] ?>">Track</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <div style="font-size:40px;">📋</div>
      <p>No orders found. <a href="menu.php" style="color:#1a56db;">Start ordering!</a></p>
    </div>
    <?php endif; ?>
  </div>

<?php // ══════════════════ TRACK ORDER TAB ══════════════════
elseif ($tab === 'track'): ?>

  <div style="margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Track Order</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;">Enter your order ID to see live status</p>
  </div>

  <div class="card">
    <form method="GET" action="">
      <input type="hidden" name="tab" value="track">
      <div class="track-search">
        <div style="flex:1;">
          <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Order ID</label>
          <input type="number" name="order_id" placeholder="e.g. 12" value="<?= $track_id>0?$track_id:'' ?>" min="1">
        </div>
        <button type="submit">Track &rarr;</button>
      </div>
    </form>
  </div>

  <?php if ($track_id>0 && !$track_order): ?>
    <div class="alert-error">Order #<?= $track_id ?> not found or does not belong to your account.</div>
  <?php endif; ?>

  <?php if ($track_order):
    $cur_status = $track_order['status'];
    $cur_index  = array_search($cur_status, $status_steps);
    $step_icons  = ['📝','✅','👨‍🍳','🛵','🎉'];
    $step_labels = ['Placed','Confirmed','Preparing','On the way','Delivered'];
  ?>

  <div class="card">
    <div class="card-header">Order #<?= $track_order['id'] ?> — <?= statusBadge($cur_status) ?></div>
    <?php if ($cur_status !== 'cancelled'): ?>
    <div class="tracker-steps">
      <?php foreach ($status_steps as $i => $step):
        $done   = $i <= $cur_index;
        $active = $i === $cur_index;
        $cc = $active?'active':($done?'done':'');
      ?>
        <div class="t-step">
          <div class="t-circle <?= $cc ?>"><?= $step_icons[$i] ?></div>
          <div class="t-label <?= $cc ?>"><?= $step_labels[$i] ?></div>
        </div>
        <?php if ($i < count($status_steps)-1): ?>
          <div class="t-line <?= $i<$cur_index?'done':'' ?>"></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div style="text-align:center;padding:24px;color:#b91c1c;font-weight:600;">❌ This order was cancelled.</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header">Order Details</div>
    <div class="order-meta">
      <div class="meta-item"><label>Vendor</label><span><?= htmlspecialchars($track_order['vendor_name']) ?></span></div>
      <div class="meta-item"><label>Station</label><span><?= htmlspecialchars($track_order['delivery_station']) ?></span></div>
      <div class="meta-item"><label>Train No.</label><span><?= htmlspecialchars($track_order['train_number']) ?></span></div>
      <div class="meta-item"><label>PNR</label><span><?= htmlspecialchars($track_order['pnr_number']) ?></span></div>
      <div class="meta-item"><label>Coach / Seat</label><span><?= htmlspecialchars($track_order['coach_number']) ?> / <?= htmlspecialchars($track_order['seat_number']) ?></span></div>
      <div class="meta-item"><label>Total</label><span>&#8377;<?= number_format($track_order['total_amount'],2) ?></span></div>
      <div class="meta-item"><label>Payment</label><span><?= strtoupper($track_order['payment_method']) ?> — <?= ucfirst($track_order['payment_status']) ?></span></div>
      <div class="meta-item"><label>Ordered At</label><span><?= date('d M Y, h:i A',strtotime($track_order['created_at'])) ?></span></div>
    </div>
    <?php if ($track_items && $track_items->num_rows>0): ?>
    <div style="padding:0 20px 8px;font-size:13px;font-weight:700;color:#374151;border-top:1px solid #f3f4f6;padding-top:14px;">Items Ordered</div>
    <div class="items-list">
      <?php while ($itm=$track_items->fetch_assoc()): ?>
      <div class="item-row">
        <span class="item-name"><?= htmlspecialchars($itm['item_name']) ?></span>
        <span class="item-qty">x<?= $itm['quantity'] ?></span>
        <span class="item-price">&#8377;<?= number_format($itm['subtotal'],2) ?></span>
      </div>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($track_log && $track_log->num_rows>0): ?>
  <div class="card">
    <div class="card-header">Status History</div>
    <div class="log-list">
      <?php while ($lg=$track_log->fetch_assoc()): ?>
      <div class="log-item">
        <div class="log-dot"></div>
        <div>
          <div class="log-status"><?= ucwords(str_replace('_',' ',$lg['status'])) ?></div>
          <div class="log-time"><?= date('d M Y, h:i A',strtotime($lg['changed_at'])) ?></div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>

  <?php
  $active_list = $conn->query(
      "SELECT o.id, o.status, v.name AS vendor_name FROM orders o
       JOIN vendors v ON o.vendor_id=v.id
       WHERE o.user_id=$user_id AND o.status NOT IN ('delivered','cancelled')
       ORDER BY o.created_at DESC"
  );
  if ($active_list->num_rows > 0): ?>
  <div class="sec-hdr" style="margin-top:8px;"><h2>Active Orders</h2></div>
  <div class="card">
    <table>
      <thead><tr><th>#</th><th>Vendor</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php while ($a=$active_list->fetch_assoc()): ?>
        <tr>
          <td>#<?= $a['id'] ?></td>
          <td><?= htmlspecialchars($a['vendor_name']) ?></td>
          <td><?= statusBadge($a['status']) ?></td>
          <td><a class="view-btn" href="?tab=track&order_id=<?= $a['id'] ?>">Track</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<?php // ══════════════════ PROFILE TAB ══════════════════
elseif ($tab === 'profile'): ?>

  <div style="margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">My Profile</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;">Update your personal details and password</p>
  </div>

  <div class="card">
    <div class="card-header">Account Information</div>
    <?php if ($profile_msg):  ?><div class="alert-success"><?= htmlspecialchars($profile_msg) ?></div><?php endif; ?>
    <?php if ($profile_error): ?><div class="alert-error" style="margin:16px 20px 0;"><?= htmlspecialchars($profile_error) ?></div><?php endif; ?>
    <form method="POST" action="?tab=profile">
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($profile['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" value="<?= htmlspecialchars($profile['email']) ?>" readonly>
        </div>
        <div class="form-group">
          <label>Mobile Number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($profile['phone']) ?>" maxlength="10" required>
        </div>
        <div class="form-group">
          <label>Member Since</label>
          <input type="text" value="<?= date('d M Y',strtotime($profile['created_at'])) ?>" readonly>
        </div>
        <div class="form-group form-full" style="border-top:1px solid #f3f4f6;padding-top:16px;">
          <label style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">Change Password — leave blank to keep current</label>
        </div>
        <div class="form-group">
          <label>Current Password</label>
          <input type="password" name="current_password" placeholder="Required to save any changes">
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" placeholder="Leave blank to keep current">
        </div>
      </div>
      <button type="submit" name="update_profile" class="save-btn">Save Changes</button>
    </form>
  </div>

  <div class="card">
    <div class="card-header">Account Summary</div>
    <div class="order-meta">
      <div class="meta-item"><label>Total Orders</label><span><?= $total_orders ?></span></div>
      <div class="meta-item"><label>Delivered</label><span><?= $delivered_orders ?></span></div>
      <div class="meta-item"><label>Active Orders</label><span><?= $active_orders ?></span></div>
      <div class="meta-item"><label>Account Status</label><span style="color:#166534;font-weight:700;">✓ Active</span></div>
    </div>
  </div>

<?php endif; ?>

</div>
<script src="../assets/js/cart.js"></script>
<script src="../assets/js/order_status.js"></script>
</body>
</html>
