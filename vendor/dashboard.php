<?php
// ============================================================
//  Train Food Ordering System — Vendor Dashboard
//  File: vendor/dashboard.php
//  Tabs: Home | Orders | Menu | Profile
// ============================================================

session_start();
require_once '../config/db.php';

if (!isset($_SESSION['vendor_id']) || $_SESSION['user_role'] !== 'vendor') {
    header("Location: login.php");
    exit();
}

$vendor_id   = $_SESSION['vendor_id'];
$vendor_name = $_SESSION['vendor_name'];

$tab   = isset($_GET['tab']) ? $_GET['tab'] : 'home';
$msg   = '';
$error = '';

// ── IMAGE UPLOAD HELPER ───────────────────────────────────
function uploadFoodImage($file) {
    $upload_dir = '../assets/images/food/';

    // Create folder if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed = ['jpg','jpeg','png','webp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => 'Upload failed.'];
    if ($file['size'] > $max_size)        return ['ok' => false, 'msg' => 'Image must be under 2MB.'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed))        return ['ok' => false, 'msg' => 'Only JPG, PNG, WEBP allowed.'];

    // Check it's actually an image
    $info = getimagesize($file['tmp_name']);
    if (!$info)                           return ['ok' => false, 'msg' => 'Invalid image file.'];

    // Unique filename
    $filename = 'food_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        return ['ok' => true, 'filename' => $filename];
    }

    return ['ok' => false, 'msg' => 'Could not save image. Check folder permissions.'];
}

// ── HANDLE: Update order status ───────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id   = (int)$_POST['order_id'];
    $new_status = $conn->real_escape_string($_POST['new_status']);
    $allowed_statuses = ['confirmed','preparing','out_for_delivery','delivered','cancelled'];

    if (in_array($new_status, $allowed_statuses)) {
        // Verify order belongs to this vendor
        $check = $conn->query(
            "SELECT id, user_id FROM orders WHERE id=$order_id AND vendor_id=$vendor_id LIMIT 1"
        )->fetch_assoc();

        if ($check) {
            $conn->query("UPDATE orders SET status='$new_status' WHERE id=$order_id");
            $conn->query(
                "INSERT INTO order_status_log (order_id, status) VALUES ($order_id, '$new_status')"
            );
            // Notify passenger
            $uid  = $check['user_id'];
            $label = ucwords(str_replace('_', ' ', $new_status));
            $conn->query(
                "INSERT INTO notifications (user_id, message)
                 VALUES ($uid, 'Your order #$order_id status updated to: $label')"
            );
            $msg = "Order #$order_id status updated to $label.";
        }
    }
}

// ── HANDLE: Add menu item ─────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $name     = trim($conn->real_escape_string($_POST['item_name']));
    $desc     = trim($conn->real_escape_string($_POST['item_desc']));
    $price    = (float)$_POST['item_price'];
    $cat_id   = (int)$_POST['category_id'];
    $is_veg   = isset($_POST['is_veg']) ? 1 : 0;
    $image_name = '';

    if (empty($name))    { $error = "Item name is required."; }
    elseif ($price <= 0) { $error = "Price must be greater than 0."; }
    elseif ($cat_id <= 0){ $error = "Please select a category."; }
    else {
        // Handle image upload
        if (!empty($_FILES['item_image']['name'])) {
            $upload = uploadFoodImage($_FILES['item_image']);
            if (!$upload['ok']) {
                $error = $upload['msg'];
            } else {
                $image_name = $upload['filename'];
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare(
                "INSERT INTO menu_items (vendor_id, category_id, name, description, price, image, is_veg)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iissdsi", $vendor_id, $cat_id, $name, $desc, $price, $image_name, $is_veg);
            if ($stmt->execute()) {
                $msg = "Menu item '$name' added successfully!";
            } else {
                $error = "Failed to add item. Please try again.";
            }
            $stmt->close();
        }
    }
}

// ── HANDLE: Toggle item availability ─────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'toggle_item') {
    $item_id = (int)$_POST['item_id'];
    $conn->query(
        "UPDATE menu_items SET is_available = 1 - is_available
         WHERE id=$item_id AND vendor_id=$vendor_id"
    );
    $msg = "Item availability updated.";
}

// ── HANDLE: Delete menu item ──────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_item') {
    $item_id = (int)$_POST['item_id'];
    // Get image filename before deleting
    $img_row = $conn->query(
        "SELECT image FROM menu_items WHERE id=$item_id AND vendor_id=$vendor_id LIMIT 1"
    )->fetch_assoc();
    if ($img_row && !empty($img_row['image'])) {
        $img_path = '../assets/images/food/' . $img_row['image'];
        if (file_exists($img_path)) unlink($img_path);
    }
    $conn->query("DELETE FROM menu_items WHERE id=$item_id AND vendor_id=$vendor_id");
    $msg = "Item deleted.";
}

// ── HANDLE: Add category ──────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $cat_name = trim($conn->real_escape_string($_POST['cat_name']));
    if (!empty($cat_name)) {
        $conn->query(
            "INSERT INTO menu_categories (vendor_id, name) VALUES ($vendor_id, '$cat_name')"
        );
        $msg = "Category '$cat_name' added.";
    }
}

// ── HANDLE: Profile update ────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_name  = trim($conn->real_escape_string($_POST['vname']));
    $new_phone = trim($conn->real_escape_string($_POST['phone']));
    $cur_pass  = $_POST['current_password'];
    $new_pass  = $_POST['new_password'];

    $v = $conn->query("SELECT password FROM vendors WHERE id=$vendor_id")->fetch_assoc();
    if (!password_verify($cur_pass, $v['password'])) {
        $error = "Current password is incorrect.";
    } else {
        if (!empty($new_pass) && strlen($new_pass) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            if (!empty($new_pass)) {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $conn->query("UPDATE vendors SET name='$new_name', phone='$new_phone', password='$hashed' WHERE id=$vendor_id");
            } else {
                $conn->query("UPDATE vendors SET name='$new_name', phone='$new_phone' WHERE id=$vendor_id");
            }
            $_SESSION['vendor_name'] = $new_name;
            $vendor_name = $new_name;
            $msg = "Profile updated successfully!";
        }
    }
}

// ── FETCH: Summary ────────────────────────────────────────
$total_orders   = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE vendor_id=$vendor_id")->fetch_assoc()['c'];
$new_orders     = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE vendor_id=$vendor_id AND status='placed'")->fetch_assoc()['c'];
$active_orders  = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE vendor_id=$vendor_id AND status NOT IN ('delivered','cancelled')")->fetch_assoc()['c'];
$today_revenue  = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE vendor_id=$vendor_id AND status='delivered' AND DATE(created_at)=CURDATE()")->fetch_assoc()['r'];
$total_revenue  = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE vendor_id=$vendor_id AND status='delivered'")->fetch_assoc()['r'];
$total_items    = $conn->query("SELECT COUNT(*) AS c FROM menu_items WHERE vendor_id=$vendor_id")->fetch_assoc()['c'];

// ── FETCH: Orders ─────────────────────────────────────────
$orders = $conn->query(
    "SELECT o.*, u.name AS passenger_name, u.phone AS passenger_phone
     FROM orders o JOIN users u ON o.user_id=u.id
     WHERE o.vendor_id=$vendor_id
     ORDER BY o.created_at DESC"
);

// ── FETCH: Menu items ─────────────────────────────────────
$menu_items = $conn->query(
    "SELECT mi.*, mc.name AS cat_name
     FROM menu_items mi
     JOIN menu_categories mc ON mi.category_id=mc.id
     WHERE mi.vendor_id=$vendor_id
     ORDER BY mc.name, mi.name"
);

// ── FETCH: Categories ─────────────────────────────────────
$categories = $conn->query(
    "SELECT * FROM menu_categories WHERE vendor_id=$vendor_id ORDER BY name"
);

// ── FETCH: Vendor profile ─────────────────────────────────
$vendor_profile = $conn->query(
    "SELECT * FROM vendors WHERE id=$vendor_id LIMIT 1"
)->fetch_assoc();

// ── FETCH: Order items for a specific order ───────────────
$view_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$view_order    = null;
$view_items    = null;
if ($view_order_id > 0) {
    $r = $conn->query(
        "SELECT o.*, u.name AS passenger_name, u.phone AS passenger_phone
         FROM orders o JOIN users u ON o.user_id=u.id
         WHERE o.id=$view_order_id AND o.vendor_id=$vendor_id LIMIT 1"
    );
    if ($r->num_rows === 1) {
        $view_order = $r->fetch_assoc();
        $view_items = $conn->query(
            "SELECT oi.*, m.name AS item_name FROM order_items oi
             JOIN menu_items m ON oi.menu_item_id=m.id
             WHERE oi.order_id=$view_order_id"
        );
    }
}

// ── STATUS BADGE ─────────────────────────────────────────
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

// ── NEXT STATUS OPTIONS ───────────────────────────────────
function nextStatusOptions($current, $order_id) {
    $flow = [
        'placed'           => ['confirmed' => '✅ Confirm', 'cancelled' => '❌ Cancel'],
        'confirmed'        => ['preparing' => '👨‍🍳 Start Preparing', 'cancelled' => '❌ Cancel'],
        'preparing'        => ['out_for_delivery' => '🛵 Out for Delivery'],
        'out_for_delivery' => ['delivered' => '🎉 Mark Delivered'],
        'delivered'        => [],
        'cancelled'        => [],
    ];
    $options = $flow[$current] ?? [];
    if (empty($options)) return '<span style="font-size:12px;color:#9ca3af;">—</span>';

    $html = '<div style="display:flex;gap:6px;flex-wrap:wrap;">';
    foreach ($options as $val => $label) {
        $color = str_contains($val, 'cancel') ? '#fee2e2' : '#f0fdf4';
        $tcolor = str_contains($val, 'cancel') ? '#991b1b' : '#166534';
        $html .= '<form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" value="' . $order_id . '">
            <input type="hidden" name="new_status" value="' . $val . '">
            <button type="submit" style="background:' . $color . ';color:' . $tcolor . ';
                border:none;border-radius:6px;padding:5px 12px;font-size:12px;
                font-weight:600;cursor:pointer;">' . $label . '</button>
        </form>';
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vendor Dashboard — TrainEats</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;color:#111827;}

    nav{background:#ea6c0a;padding:0 24px;display:flex;align-items:center;
        justify-content:space-between;height:56px;position:sticky;top:0;z-index:100;}
    .nav-brand{color:#fff;font-size:18px;font-weight:700;text-decoration:none;}
    .nav-user{display:flex;align-items:center;gap:10px;color:#fed7aa;font-size:13px;}
    .avatar{width:32px;height:32px;background:#f97316;border-radius:50%;display:flex;
            align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;}
    .logout-btn{background:none;border:none;color:#fed7aa;font-size:13px;cursor:pointer;
                padding:6px 12px;border-radius:6px;}
    .logout-btn:hover{background:rgba(255,255,255,0.15);color:#fff;}

    .tab-bar{background:#fff;border-bottom:1px solid #e5e7eb;display:flex;
             padding:0 24px;overflow-x:auto;}
    .tab-btn{display:flex;align-items:center;gap:7px;padding:14px 20px;font-size:14px;
             font-weight:600;color:#6b7280;text-decoration:none;
             border-bottom:3px solid transparent;white-space:nowrap;transition:all 0.2s;}
    .tab-btn:hover{color:#ea6c0a;}
    .tab-btn.active{color:#ea6c0a;border-bottom-color:#ea6c0a;}

    .page{max-width:1000px;margin:0 auto;padding:28px 20px 60px;}

    .card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,0.07);
          overflow:hidden;margin-bottom:24px;}
    .card-header{padding:16px 20px;border-bottom:1px solid #f3f4f6;
                 font-size:15px;font-weight:700;color:#111827;
                 display:flex;align-items:center;justify-content:space-between;}

    .stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px;}
    .stat-card{background:#fff;border-radius:12px;padding:18px;text-align:center;
               box-shadow:0 1px 6px rgba(0,0,0,0.07);}
    .stat-num{font-size:26px;font-weight:800;color:#ea6c0a;}
    .stat-lbl{font-size:12px;color:#6b7280;margin-top:3px;}

    table{width:100%;border-collapse:collapse;font-size:13px;}
    thead th{background:#f9fafb;padding:11px 16px;text-align:left;font-weight:600;
             color:#374151;border-bottom:1px solid #e5e7eb;}
    tbody td{padding:11px 16px;border-bottom:1px solid #f3f4f6;color:#374151;vertical-align:middle;}
    tbody tr:last-child td{border-bottom:none;}
    tbody tr:hover td{background:#fafafa;}

    .new-badge{background:#dbeafe;color:#1e40af;font-size:11px;font-weight:700;
               padding:2px 8px;border-radius:10px;margin-left:6px;}

    .alert-success{background:#f0fdf4;border:1px solid #86efac;color:#166534;
                   border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;}
    .alert-error{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;
                 border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;}

    /* ORDER DETAIL PANEL */
    .order-panel{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;
                 padding:18px 20px;margin-bottom:20px;}
    .order-panel h3{font-size:14px;font-weight:700;color:#92400e;margin-bottom:12px;}
    .order-meta-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;}
    .ometa label{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;
                 display:block;margin-bottom:2px;}
    .ometa span{font-size:13px;font-weight:600;color:#111827;}

    /* MENU ITEMS TABLE */
    .item-img{width:44px;height:44px;border-radius:8px;object-fit:cover;display:block;}
    .item-img-placeholder{width:44px;height:44px;border-radius:8px;background:#f3f4f6;
                           display:flex;align-items:center;justify-content:center;
                           font-size:18px;font-weight:700;color:#9ca3af;}
    .veg-dot{display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:5px;}
    .veg-dot.veg{background:#16a34a;}
    .veg-dot.nveg{background:#dc2626;}
    .avail-on {background:#dcfce7;color:#166534;font-size:11px;font-weight:700;
                padding:2px 8px;border-radius:10px;}
    .avail-off{background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;
                padding:2px 8px;border-radius:10px;}

    /* ADD ITEM FORM */
    .add-form{padding:20px;}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .form-full{grid-column:1/-1;}
    .form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;}
    .form-group input,.form-group select,.form-group textarea{
      width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;
      font-size:13px;color:#111827;outline:none;font-family:inherit;}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#ea6c0a;}
    .form-group input[type=file]{padding:6px;}
    .form-group input[type=checkbox]{width:auto;margin-right:6px;}

    .save-btn{padding:10px 24px;background:#ea6c0a;color:#fff;border:none;
              border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;}
    .save-btn:hover{background:#c2590a;}
    .del-btn{padding:5px 12px;background:#fee2e2;color:#991b1b;border:none;
             border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;}
    .del-btn:hover{background:#fca5a5;}
    .toggle-btn{padding:5px 12px;background:#f3f4f6;color:#374151;border:none;
                border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;}
    .toggle-btn:hover{background:#e5e7eb;}

    /* IMAGE PREVIEW */
    .img-preview-wrap{margin-top:8px;}
    .img-preview{width:80px;height:80px;border-radius:8px;object-fit:cover;
                 border:2px solid #e5e7eb;display:none;}

    /* PROFILE FORM */
    .profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:20px;}
    .profile-full{grid-column:1/-1;}

    /* SECTION HEADING */
    .sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
    .sec-hdr h2{font-size:15px;font-weight:700;}
    .sec-hdr a{font-size:13px;color:#ea6c0a;text-decoration:none;}

    .empty{text-align:center;padding:40px 20px;color:#9ca3af;}
    .empty p{font-size:14px;margin-top:8px;}

    @media(max-width:700px){
      .stats{grid-template-columns:repeat(3,1fr);}
      .form-grid,.profile-grid{grid-template-columns:1fr;}
      .form-full,.profile-full{grid-column:1;}
      .order-meta-grid{grid-template-columns:1fr 1fr;}
      table{display:block;overflow-x:auto;}
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav>
  <a class="nav-brand" href="dashboard.php">🏪 Vendor Portal</a>
  <div class="nav-user">
    <div class="avatar"><?= strtoupper(substr($vendor_name,0,1)) ?></div>
    <span><?= htmlspecialchars(explode(' ',$vendor_name)[0]) ?></span>
    <form method="POST" action="logout.php" style="display:inline;">
      <button type="submit" class="logout-btn">Logout</button>
    </form>
  </div>
</nav>

<!-- TABS -->
<div class="tab-bar">
  <a href="?tab=home"    class="tab-btn <?= $tab==='home'   ?'active':'' ?>">🏠 Home</a>
  <a href="?tab=orders"  class="tab-btn <?= $tab==='orders' ?'active':'' ?>">
    📦 Orders
    <?= $new_orders > 0 ? "<span style='background:#ef4444;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;'>$new_orders new</span>" : '' ?>
  </a>
  <a href="?tab=menu"    class="tab-btn <?= $tab==='menu'   ?'active':'' ?>">🍱 Menu</a>
  <a href="?tab=profile" class="tab-btn <?= $tab==='profile'?'active':'' ?>">👤 Profile</a>
</div>

<div class="page">

<?php if ($msg):   ?><div class="alert-success">&#10003; <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert-error">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php // ══════════════════ HOME ══════════════════
if ($tab === 'home'): ?>

  <div style="margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:700;">Hello, <?= htmlspecialchars(explode(' ',$vendor_name)[0]) ?> 👋</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;">
      <?= htmlspecialchars($vendor_profile['station_name']) ?>
      (<?= htmlspecialchars($vendor_profile['station_code']) ?>)
    </p>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-num"><?= $new_orders ?></div>
      <div class="stat-lbl">New Orders</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $active_orders ?></div>
      <div class="stat-lbl">Active</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $total_orders ?></div>
      <div class="stat-lbl">Total Orders</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="font-size:20px;">&#8377;<?= number_format($today_revenue,0) ?></div>
      <div class="stat-lbl">Today's Revenue</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="font-size:20px;">&#8377;<?= number_format($total_revenue,0) ?></div>
      <div class="stat-lbl">Total Revenue</div>
    </div>
  </div>

  <!-- New orders needing attention -->
  <?php
  $new_list = $conn->query(
      "SELECT o.*, u.name AS passenger_name FROM orders o JOIN users u ON o.user_id=u.id
       WHERE o.vendor_id=$vendor_id AND o.status='placed' ORDER BY o.created_at ASC"
  );
  ?>
  <div class="sec-hdr">
    <h2>New Orders <?= $new_orders>0?"<span class='new-badge'>$new_orders</span>":'' ?></h2>
    <a href="?tab=orders">View all &rarr;</a>
  </div>
  <div class="card">
    <?php if ($new_list->num_rows > 0): ?>
    <table>
      <thead><tr><th>#</th><th>Passenger</th><th>Train / Seat</th><th>Amount</th><th>Time</th><th>Action</th></tr></thead>
      <tbody>
        <?php while ($o=$new_list->fetch_assoc()): ?>
        <tr>
          <td>#<?= $o['id'] ?></td>
          <td><?= htmlspecialchars($o['passenger_name']) ?></td>
          <td><?= htmlspecialchars($o['train_number']) ?> / <?= htmlspecialchars($o['coach_number']) ?>-<?= htmlspecialchars($o['seat_number']) ?></td>
          <td>&#8377;<?= number_format($o['total_amount'],2) ?></td>
          <td><?= date('h:i A',strtotime($o['created_at'])) ?></td>
          <td><?= nextStatusOptions($o['status'],$o['id']) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty"><div style="font-size:36px;">📭</div><p>No new orders right now.</p></div>
    <?php endif; ?>
  </div>

<?php // ══════════════════ ORDERS ══════════════════
elseif ($tab === 'orders'): ?>

  <div style="margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">All Orders</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;">Manage and update your orders</p>
  </div>

  <!-- Order detail panel if selected -->
  <?php if ($view_order): ?>
  <div class="order-panel">
    <h3>📦 Order #<?= $view_order['id'] ?> — <?= statusBadge($view_order['status']) ?></h3>
    <div class="order-meta-grid">
      <div class="ometa"><label>Passenger</label><span><?= htmlspecialchars($view_order['passenger_name']) ?></span></div>
      <div class="ometa"><label>Phone</label><span><?= htmlspecialchars($view_order['passenger_phone']) ?></span></div>
      <div class="ometa"><label>Train No.</label><span><?= htmlspecialchars($view_order['train_number']) ?></span></div>
      <div class="ometa"><label>Coach / Seat</label><span><?= htmlspecialchars($view_order['coach_number']) ?> / <?= htmlspecialchars($view_order['seat_number']) ?></span></div>
      <div class="ometa"><label>PNR</label><span><?= htmlspecialchars($view_order['pnr_number']) ?></span></div>
      <div class="ometa"><label>Station</label><span><?= htmlspecialchars($view_order['delivery_station']) ?></span></div>
      <div class="ometa"><label>Payment</label><span><?= strtoupper($view_order['payment_method']) ?></span></div>
      <div class="ometa"><label>Total</label><span>&#8377;<?= number_format($view_order['total_amount'],2) ?></span></div>
      <?php if ($view_order['special_note']): ?>
      <div class="ometa" style="grid-column:1/-1;"><label>Note</label><span><?= htmlspecialchars($view_order['special_note']) ?></span></div>
      <?php endif; ?>
    </div>
    <?php if ($view_items && $view_items->num_rows > 0): ?>
    <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;">Items</div>
    <?php while ($itm=$view_items->fetch_assoc()): ?>
      <div style="display:flex;justify-content:space-between;font-size:13px;padding:5px 0;border-bottom:1px solid #fde68a;">
        <span><?= htmlspecialchars($itm['item_name']) ?> x<?= $itm['quantity'] ?></span>
        <span style="font-weight:700;">&#8377;<?= number_format($itm['subtotal'],2) ?></span>
      </div>
    <?php endwhile; ?>
    <?php endif; ?>
    <div style="margin-top:14px;"><?= nextStatusOptions($view_order['status'],$view_order['id']) ?></div>
  </div>
  <?php endif; ?>

  <div class="card">
    <?php if ($orders->num_rows > 0): ?>
    <table>
      <thead><tr><th>#</th><th>Passenger</th><th>Train/Seat</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php while ($o=$orders->fetch_assoc()): ?>
        <tr>
          <td>
            <a href="?tab=orders&order_id=<?= $o['id'] ?>" style="color:#ea6c0a;font-weight:700;text-decoration:none;">#<?= $o['id'] ?></a>
            <?= $o['status']==='placed' ? "<span class='new-badge'>New</span>" : '' ?>
          </td>
          <td><?= htmlspecialchars($o['passenger_name']) ?><br>
              <span style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($o['passenger_phone']) ?></span></td>
          <td><?= htmlspecialchars($o['train_number']) ?><br>
              <span style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($o['coach_number']) ?>/<?= htmlspecialchars($o['seat_number']) ?></span></td>
          <td>&#8377;<?= number_format($o['total_amount'],2) ?><br>
              <span style="font-size:11px;color:#9ca3af;"><?= strtoupper($o['payment_method']) ?></span></td>
          <td><?= statusBadge($o['status']) ?></td>
          <td><?= date('d M',strtotime($o['created_at'])) ?><br>
              <span style="font-size:11px;color:#9ca3af;"><?= date('h:i A',strtotime($o['created_at'])) ?></span></td>
          <td><?= nextStatusOptions($o['status'],$o['id']) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty"><div style="font-size:36px;">📭</div><p>No orders yet.</p></div>
    <?php endif; ?>
  </div>

<?php // ══════════════════ MENU ══════════════════
elseif ($tab === 'menu'): ?>

  <div style="margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Menu Management</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;">Add items, upload images, toggle availability</p>
  </div>

  <!-- Add Category -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header">Add Category</div>
    <div style="padding:16px 20px;display:flex;gap:10px;align-items:flex-end;">
      <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex:1;">
        <input type="hidden" name="action" value="add_category">
        <div style="flex:1;">
          <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Category Name</label>
          <input type="text" name="cat_name" placeholder="e.g. Breakfast, Snacks, Beverages"
                 style="width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:13px;outline:none;">
        </div>
        <button type="submit" class="save-btn" style="white-space:nowrap;">+ Add Category</button>
      </form>
    </div>
    <?php if ($categories->num_rows > 0): ?>
    <div style="padding:0 20px 16px;display:flex;gap:8px;flex-wrap:wrap;">
      <?php $categories->data_seek(0); while ($c=$categories->fetch_assoc()): ?>
        <span style="background:#fff7ed;color:#c2590a;border:1px solid #fed7aa;
                     padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">
          <?= htmlspecialchars($c['name']) ?>
        </span>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Add Menu Item -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">Add New Menu Item</div>
    <form method="POST" enctype="multipart/form-data" class="add-form">
      <input type="hidden" name="action" value="add_item">
      <div class="form-grid">

        <div class="form-group">
          <label>Item Name *</label>
          <input type="text" name="item_name" placeholder="e.g. Masala Dosa" required>
        </div>

        <div class="form-group">
          <label>Category *</label>
          <select name="category_id" required>
            <option value="">-- Select Category --</option>
            <?php $categories->data_seek(0); while ($c=$categories->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Price (₹) *</label>
          <input type="number" name="item_price" placeholder="e.g. 80" min="1" step="0.01" required>
        </div>

        <div class="form-group">
          <label>Food Image (JPG/PNG/WEBP, max 2MB)</label>
          <input type="file" name="item_image" accept="image/jpeg,image/png,image/webp"
                 onchange="previewImg(this)">
          <div class="img-preview-wrap">
            <img id="imgPreview" class="img-preview" src="" alt="Preview">
          </div>
        </div>

        <div class="form-group form-full">
          <label>Description</label>
          <textarea name="item_desc" rows="2" placeholder="Brief description of the dish..."
                    style="resize:none;"></textarea>
        </div>

        <div class="form-group form-full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_veg" value="1" checked>
            <span>This is a vegetarian item</span>
          </label>
        </div>

      </div>
      <div style="margin-top:6px;">
        <button type="submit" class="save-btn">+ Add Item to Menu</button>
      </div>
    </form>
  </div>

  <!-- Menu Items List -->
  <div class="card">
    <div class="card-header">
      Your Menu Items
      <span style="font-size:13px;color:#6b7280;font-weight:400;"><?= $total_items ?> items</span>
    </div>
    <?php if ($menu_items->num_rows > 0): ?>
    <table>
      <thead><tr>
        <th>Image</th><th>Name</th><th>Category</th>
        <th>Price</th><th>Type</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php while ($item=$menu_items->fetch_assoc()): ?>
        <tr>
          <td>
            <?php if (!empty($item['image']) && file_exists('../assets/images/food/'.$item['image'])): ?>
              <img src="../assets/images/food/<?= htmlspecialchars($item['image']) ?>"
                   alt="<?= htmlspecialchars($item['name']) ?>" class="item-img">
            <?php else: ?>
              <div class="item-img-placeholder">
                <?= strtoupper(substr($item['name'],0,1)) ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-weight:600;color:#111827;"><?= htmlspecialchars($item['name']) ?></div>
            <?php if ($item['description']): ?>
              <div style="font-size:11px;color:#9ca3af;margin-top:2px;">
                <?= htmlspecialchars(substr($item['description'],0,50)) ?>...
              </div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($item['cat_name']) ?></td>
          <td style="font-weight:700;">&#8377;<?= number_format($item['price'],2) ?></td>
          <td>
            <span class="veg-dot <?= $item['is_veg']?'veg':'nveg' ?>"></span>
            <?= $item['is_veg'] ? 'Veg' : 'Non-Veg' ?>
          </td>
          <td>
            <span class="<?= $item['is_available']?'avail-on':'avail-off' ?>">
              <?= $item['is_available']?'Available':'Unavailable' ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:6px;">
              <!-- Toggle availability -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"  value="toggle_item">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <button type="submit" class="toggle-btn">
                  <?= $item['is_available'] ? 'Hide' : 'Show' ?>
                </button>
              </form>
              <!-- Delete -->
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($item['name'])) ?>?')">
                <input type="hidden" name="action"  value="delete_item">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <button type="submit" class="del-btn">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <div style="font-size:36px;">🍽️</div>
      <p>No menu items yet. Add your first item above!</p>
    </div>
    <?php endif; ?>
  </div>

<?php // ══════════════════ PROFILE ══════════════════
elseif ($tab === 'profile'): ?>

  <div style="margin-bottom:20px;">
    <h1 style="font-size:20px;font-weight:700;">Vendor Profile</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;">Update your stall details and password</p>
  </div>

  <div class="card">
    <div class="card-header">Stall Information</div>
    <form method="POST">
      <input type="hidden" name="action" value="update_profile">
      <div class="profile-grid">
        <div class="form-group">
          <label>Stall / Restaurant Name</label>
          <input type="text" name="vname" value="<?= htmlspecialchars($vendor_profile['name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Email (cannot change)</label>
          <input type="email" value="<?= htmlspecialchars($vendor_profile['email']) ?>"
                 readonly style="background:#f9fafb;color:#6b7280;">
        </div>
        <div class="form-group">
          <label>Phone Number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($vendor_profile['phone']) ?>" maxlength="10" required>
        </div>
        <div class="form-group">
          <label>Station</label>
          <input type="text" value="<?= htmlspecialchars($vendor_profile['station_name']) ?> (<?= htmlspecialchars($vendor_profile['station_code']) ?>)"
                 readonly style="background:#f9fafb;color:#6b7280;">
        </div>
        <div class="form-group profile-full"
             style="border-top:1px solid #f3f4f6;padding-top:14px;margin-top:4px;">
          <label style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:0.05em;">
            Change Password — leave blank to keep current
          </label>
        </div>
        <div class="form-group">
          <label>Current Password *</label>
          <input type="password" name="current_password" placeholder="Required to save changes">
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" placeholder="Leave blank to keep current">
        </div>
      </div>
      <div style="padding:0 20px 20px;">
        <button type="submit" class="save-btn">Save Changes</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header">Account Summary</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;padding:16px 20px;">
      <div><label style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:2px;">Total Orders</label>
           <span style="font-size:16px;font-weight:700;"><?= $total_orders ?></span></div>
      <div><label style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:2px;">Menu Items</label>
           <span style="font-size:16px;font-weight:700;"><?= $total_items ?></span></div>
      <div><label style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;display:block;margin-bottom:2px;">Total Revenue</label>
           <span style="font-size:16px;font-weight:700;">&#8377;<?= number_format($total_revenue,0) ?></span></div>
    </div>
  </div>

<?php endif; ?>

</div>
</body>

<script src="../assets/js/cart.js"></script>
<script>
function previewImg(input) {
  const preview = document.getElementById('imgPreview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      preview.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

</html>
