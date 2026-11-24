<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') { header("Location: login.php"); exit(); }
$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$tab  = isset($_GET['tab']) ? $_GET['tab'] : 'home';
$msg  = ''; $error = '';

if (isset($_POST['action']) && $_POST['action'] === 'add_vendor') {
    $name=$conn->real_escape_string(trim($_POST['vname']));
    $station=$conn->real_escape_string(trim($_POST['station_name']));
    $code=strtoupper($conn->real_escape_string(trim($_POST['station_code'])));
    $email=$conn->real_escape_string(trim($_POST['vemail']));
    $phone=$conn->real_escape_string(trim($_POST['vphone']));
    $pass=$_POST['vpassword'];
    if (empty($name)||empty($station)||empty($code)||empty($email)||empty($phone)||empty($pass)) { $error="All fields are required."; }
    elseif (!filter_var($_POST['vemail'],FILTER_VALIDATE_EMAIL)) { $error="Enter a valid email."; }
    elseif (strlen($pass)<6) { $error="Password must be at least 6 characters."; }
    else {
        if ($conn->query("SELECT id FROM vendors WHERE email='$email' LIMIT 1")->num_rows>0) { $error="Email already exists."; }
        else {
            $h=password_hash($pass,PASSWORD_BCRYPT);
            $st=$conn->prepare("INSERT INTO vendors (name,station_name,station_code,email,phone,password) VALUES (?,?,?,?,?,?)");
            $st->bind_param("ssssss",$name,$station,$code,$email,$phone,$h);
            $st->execute() ? $msg="Vendor '$name' added!" : $error="Failed.";
            $st->close();
        }
    }
}
if (isset($_POST['action']) && $_POST['action']==='toggle_vendor') { $vid=(int)$_POST['vendor_id']; $conn->query("UPDATE vendors SET is_active=1-is_active WHERE id=$vid"); $msg="Vendor status updated."; }
if (isset($_POST['action']) && $_POST['action']==='delete_vendor') { $vid=(int)$_POST['vendor_id']; $conn->query("DELETE FROM vendors WHERE id=$vid"); $msg="Vendor deleted."; }
if (isset($_POST['action']) && $_POST['action']==='delete_user')   { $uid=(int)$_POST['user_id'];  $conn->query("DELETE FROM users WHERE id=$uid"); $msg="User deleted."; }
if (isset($_POST['action']) && $_POST['action']==='update_order_status') {
    $oid=(int)$_POST['order_id']; $status=$conn->real_escape_string($_POST['new_status']);
    $allowed=['placed','confirmed','preparing','out_for_delivery','delivered','cancelled'];
    if (in_array($status,$allowed)) {
        $conn->query("UPDATE orders SET status='$status' WHERE id=$oid");
        $conn->query("INSERT INTO order_status_log (order_id,status) VALUES ($oid,'$status')");
        $ur=$conn->query("SELECT user_id FROM orders WHERE id=$oid")->fetch_assoc();
        if ($ur) { $uid=$ur['user_id']; $lbl=ucwords(str_replace('_',' ',$status)); $conn->query("INSERT INTO notifications (user_id,message) VALUES ($uid,'Order #$oid: $lbl')"); }
        $msg="Order #$oid updated.";
    }
}

$total_users   =$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$total_vendors =$conn->query("SELECT COUNT(*) AS c FROM vendors")->fetch_assoc()['c'];
$active_vendors=$conn->query("SELECT COUNT(*) AS c FROM vendors WHERE is_active=1")->fetch_assoc()['c'];
$total_orders  =$conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
$today_orders  =$conn->query("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$total_revenue =$conn->query("SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE status='delivered'")->fetch_assoc()['r'];
$today_revenue =$conn->query("SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE status='delivered' AND DATE(created_at)=CURDATE()")->fetch_assoc()['r'];
$pending_orders=$conn->query("SELECT COUNT(*) AS c FROM orders WHERE status='placed'")->fetch_assoc()['c'];
$recent_orders =$conn->query("SELECT o.id,o.train_number,o.total_amount,o.status,o.created_at,u.name AS uname,v.name AS vname FROM orders o JOIN users u ON o.user_id=u.id JOIN vendors v ON o.vendor_id=v.id ORDER BY o.created_at DESC LIMIT 8");
$vendors       =$conn->query("SELECT v.*,COUNT(DISTINCT o.id) AS order_count,COALESCE(SUM(CASE WHEN o.status='delivered' THEN o.total_amount ELSE 0 END),0) AS revenue FROM vendors v LEFT JOIN orders o ON v.id=o.vendor_id GROUP BY v.id ORDER BY v.created_at DESC");
$users         =$conn->query("SELECT u.*,COUNT(o.id) AS order_count,COALESCE(SUM(o.total_amount),0) AS total_spent FROM users u LEFT JOIN orders o ON u.id=o.user_id GROUP BY u.id ORDER BY u.created_at DESC");
$all_orders    =$conn->query("SELECT o.*,u.name AS uname,v.name AS vname FROM orders o JOIN users u ON o.user_id=u.id JOIN vendors v ON o.vendor_id=v.id ORDER BY o.created_at DESC");
$daily_orders  =$conn->query("SELECT DATE(created_at) AS day,COUNT(*) AS cnt,COALESCE(SUM(total_amount),0) AS revenue FROM orders WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
$top_vendors   =$conn->query("SELECT v.name,COUNT(o.id) AS orders,COALESCE(SUM(CASE WHEN o.status='delivered' THEN o.total_amount ELSE 0 END),0) AS revenue FROM vendors v LEFT JOIN orders o ON v.id=o.vendor_id GROUP BY v.id ORDER BY revenue DESC LIMIT 5");
$status_counts =$conn->query("SELECT status,COUNT(*) AS cnt FROM orders GROUP BY status");

function statusBadge($s){$m=['placed'=>['#dbeafe','#1e40af','Placed'],'confirmed'=>['#ede9fe','#5b21b6','Confirmed'],'preparing'=>['#fef3c7','#92400e','Preparing'],'out_for_delivery'=>['#ffedd5','#9a3412','Out for Delivery'],'delivered'=>['#dcfce7','#166534','Delivered'],'cancelled'=>['#fee2e2','#991b1b','Cancelled']];$r=$m[$s]??['#f3f4f6','#374151',ucfirst($s)];return "<span style='background:{$r[0]};color:{$r[1]};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>{$r[2]}</span>";}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard — TrainEats</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;color:#111827;}
nav{background:#4f46e5;padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:56px;position:sticky;top:0;z-index:100;}
.nav-brand{color:#fff;font-size:18px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:8px;}
.admin-pill{background:rgba(255,255,255,0.2);color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;}
.nav-user{display:flex;align-items:center;gap:10px;color:#c7d2fe;font-size:13px;}
.avatar{width:32px;height:32px;background:#6366f1;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;}
.logout-btn{background:none;border:none;color:#c7d2fe;font-size:13px;cursor:pointer;padding:6px 12px;border-radius:6px;}
.logout-btn:hover{background:rgba(255,255,255,0.15);color:#fff;}
.tab-bar{background:#fff;border-bottom:1px solid #e5e7eb;display:flex;padding:0 24px;overflow-x:auto;}
.tab-btn{display:flex;align-items:center;gap:6px;padding:14px 18px;font-size:14px;font-weight:600;color:#6b7280;text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap;transition:all 0.2s;}
.tab-btn:hover{color:#4f46e5;}
.tab-btn.active{color:#4f46e5;border-bottom-color:#4f46e5;}
.page{max-width:1060px;margin:0 auto;padding:28px 20px 60px;}
.card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,0.07);overflow:hidden;margin-bottom:24px;}
.card-header{padding:16px 20px;border-bottom:1px solid #f3f4f6;font-size:15px;font-weight:700;color:#111827;display:flex;align-items:center;justify-content:space-between;}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 6px rgba(0,0,0,0.07);border-left:4px solid #e5e7eb;}
.stat-card.blue{border-left-color:#3b82f6;}.stat-card.green{border-left-color:#10b981;}.stat-card.purple{border-left-color:#8b5cf6;}.stat-card.orange{border-left-color:#f97316;}
.stat-num{font-size:28px;font-weight:800;color:#111827;}.stat-lbl{font-size:12px;color:#6b7280;margin-top:4px;}.stat-sub{font-size:11px;color:#9ca3af;margin-top:2px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
thead th{background:#f9fafb;padding:11px 16px;text-align:left;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;}
tbody td{padding:11px 16px;border-bottom:1px solid #f3f4f6;color:#374151;vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}tbody tr:hover td{background:#fafafa;}
.alert-s{background:#f0fdf4;border:1px solid #86efac;color:#166534;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;}
.alert-e{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;}
.btn-p{padding:10px 22px;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;}
.btn-p:hover{background:#4338ca;}
.btn-s{padding:5px 12px;background:#dcfce7;color:#166534;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;}
.btn-d{padding:5px 12px;background:#fee2e2;color:#991b1b;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;}
.btn-w{padding:5px 12px;background:#fef3c7;color:#92400e;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-full{grid-column:1/-1;}
.form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;}
.form-group input{width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:13px;color:#111827;outline:none;}
.form-group input:focus{border-color:#4f46e5;}
.ss{padding:5px 8px;border:1.5px solid #e5e7eb;border-radius:6px;font-size:12px;font-weight:600;color:#374151;outline:none;cursor:pointer;}
.badge-a{background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;}
.badge-i{background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;}
.report-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;}
.rc{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 6px rgba(0,0,0,0.07);}
.rc h3{font-size:14px;font-weight:700;color:#374151;margin-bottom:16px;}
.bar-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
.bar-lbl{font-size:12px;color:#6b7280;width:90px;flex-shrink:0;text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.bar-wrap{flex:1;background:#f3f4f6;border-radius:4px;height:20px;overflow:hidden;}
.bar-fill{height:100%;border-radius:4px;background:#4f46e5;}
.bar-val{font-size:12px;font-weight:700;color:#374151;width:72px;text-align:right;flex-shrink:0;}
.srow{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f3f4f6;}
.srow:last-child{border-bottom:none;}
.empty{text-align:center;padding:40px 20px;color:#9ca3af;}
.empty p{font-size:14px;margin-top:8px;}
.sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.sec-hdr h2{font-size:15px;font-weight:700;}
@media(max-width:760px){.stats{grid-template-columns:repeat(2,1fr);}.form-grid{grid-template-columns:1fr;}.form-full{grid-column:1;}.report-grid{grid-template-columns:1fr;}table{display:block;overflow-x:auto;}}
</style>
</head>
<body>

<nav>
  <a class="nav-brand" href="dashboard.php">⚙️ TrainEats Admin <span class="admin-pill">ADMIN</span></a>
  <div class="nav-user">
    <div class="avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
    <span><?= htmlspecialchars($admin_name) ?></span>
    <form method="POST" action="logout.php" style="display:inline;">
      <button type="submit" class="logout-btn">Logout</button>
    </form>
  </div>
</nav>

<div class="tab-bar">
  <a href="?tab=home"    class="tab-btn <?= $tab==='home'   ?'active':'' ?>">🏠 Home</a>
  <a href="?tab=vendors" class="tab-btn <?= $tab==='vendors'?'active':'' ?>">🏪 Vendors</a>
  <a href="?tab=users"   class="tab-btn <?= $tab==='users'  ?'active':'' ?>">👥 Users</a>
  <a href="?tab=orders"  class="tab-btn <?= $tab==='orders' ?'active':'' ?>">
    📦 Orders<?= $pending_orders>0?"<span style='background:#ef4444;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;'>$pending_orders</span>":'' ?>
  </a>
  <a href="?tab=reports" class="tab-btn <?= $tab==='reports'?'active':'' ?>">📊 Reports</a>
</div>

<div class="page">
<?php if ($msg):   ?><div class="alert-s">&#10003; <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert-e">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($tab==='home'): ?>
  <div style="margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:700;">Welcome, <?= htmlspecialchars($admin_name) ?> ⚙️</h1>
    <p style="font-size:14px;color:#6b7280;margin-top:4px;"><?= date('l, d M Y') ?></p>
  </div>
  <div class="stats">
    <div class="stat-card blue"><div class="stat-num"><?= $total_users ?></div><div class="stat-lbl">Total Passengers</div></div>
    <div class="stat-card green"><div class="stat-num"><?= $active_vendors ?><span style="font-size:14px;color:#9ca3af;"> / <?= $total_vendors ?></span></div><div class="stat-lbl">Active Vendors</div></div>
    <div class="stat-card purple"><div class="stat-num"><?= $total_orders ?></div><div class="stat-lbl">Total Orders</div><div class="stat-sub">Today: <?= $today_orders ?></div></div>
    <div class="stat-card orange"><div class="stat-num" style="font-size:20px;">&#8377;<?= number_format($total_revenue,0) ?></div><div class="stat-lbl">Total Revenue</div><div class="stat-sub">Today: &#8377;<?= number_format($today_revenue,0) ?></div></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;">
    <?php foreach([['?tab=vendors','#eff6ff','#1e40af','🏪','Add Vendor'],['?tab=users','#f0fdf4','#166534','👥','View Users'],['?tab=orders','#fff7ed','#9a3412','📦','All Orders'],['?tab=reports','#fdf4ff','#6b21a8','📊','Reports']] as $q): ?>
    <a href="<?= $q[0] ?>" style="display:flex;flex-direction:column;align-items:center;background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:18px;text-decoration:none;gap:8px;transition:border-color 0.2s;" onmouseover="this.style.borderColor='<?= $q[2] ?>'" onmouseout="this.style.borderColor='#e5e7eb'">
      <div style="width:40px;height:40px;background:<?= $q[1] ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;"><?= $q[3] ?></div>
      <span style="font-size:13px;font-weight:600;color:#374151;"><?= $q[4] ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="sec-hdr"><h2>Recent Orders</h2><a href="?tab=orders" style="font-size:13px;color:#4f46e5;text-decoration:none;">View all &rarr;</a></div>
  <div class="card">
    <?php if ($recent_orders->num_rows>0): ?>
    <table><thead><tr><th>#</th><th>Passenger</th><th>Vendor</th><th>Train</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
    <?php while ($o=$recent_orders->fetch_assoc()): ?>
    <tr><td style="font-weight:700;">#<?= $o['id'] ?></td><td><?= htmlspecialchars($o['uname']) ?></td><td><?= htmlspecialchars($o['vname']) ?></td><td><?= htmlspecialchars($o['train_number']) ?></td><td>&#8377;<?= number_format($o['total_amount'],2) ?></td><td><?= statusBadge($o['status']) ?></td><td style="font-size:12px;color:#9ca3af;"><?= date('d M, h:i A',strtotime($o['created_at'])) ?></td></tr>
    <?php endwhile; ?></tbody></table>
    <?php else: ?><div class="empty"><div style="font-size:36px;">📭</div><p>No orders yet.</p></div><?php endif; ?>
  </div>

<?php elseif ($tab==='vendors'): ?>
  <div style="margin-bottom:20px;"><h1 style="font-size:20px;font-weight:700;">Manage Vendors</h1><p style="font-size:14px;color:#6b7280;margin-top:4px;">Add and manage food vendors</p></div>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">Add New Vendor</div>
    <form method="POST" style="padding:20px;">
      <input type="hidden" name="action" value="add_vendor">
      <div class="form-grid">
        <div class="form-group"><label>Stall Name *</label><input type="text" name="vname" placeholder="e.g. Sharma Tiffin Centre" required></div>
        <div class="form-group"><label>Station Name *</label><input type="text" name="station_name" placeholder="e.g. Nagpur Junction" required></div>
        <div class="form-group"><label>Station Code *</label><input type="text" name="station_code" placeholder="e.g. NGP" maxlength="10" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="vemail" placeholder="vendor@example.com" required></div>
        <div class="form-group"><label>Phone *</label><input type="tel" name="vphone" placeholder="10-digit" maxlength="10" required></div>
        <div class="form-group"><label>Password *</label><input type="text" name="vpassword" placeholder="Min 6 characters" required></div>
      </div>
      <div style="margin-top:10px;"><button type="submit" class="btn-p">+ Add Vendor</button></div>
    </form>
  </div>
  <div class="card">
    <div class="card-header">All Vendors <span style="font-size:13px;color:#6b7280;font-weight:400;"><?= $total_vendors ?> total</span></div>
    <?php if ($vendors->num_rows>0): ?>
    <table><thead><tr><th>#</th><th>Name</th><th>Station</th><th>Contact</th><th>Orders</th><th>Revenue</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php while ($v=$vendors->fetch_assoc()): ?>
    <tr>
      <td><?= $v['id'] ?></td>
      <td style="font-weight:600;"><?= htmlspecialchars($v['name']) ?></td>
      <td><?= htmlspecialchars($v['station_name']) ?> <span style="background:#eff6ff;color:#1e40af;font-size:11px;font-weight:700;padding:1px 6px;border-radius:8px;"><?= htmlspecialchars($v['station_code']) ?></span></td>
      <td><?= htmlspecialchars($v['email']) ?><br><span style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($v['phone']) ?></span></td>
      <td style="text-align:center;"><?= $v['order_count'] ?></td>
      <td>&#8377;<?= number_format($v['revenue'],0) ?></td>
      <td><span class="<?= $v['is_active']?'badge-a':'badge-i' ?>"><?= $v['is_active']?'Active':'Inactive' ?></span></td>
      <td><div style="display:flex;gap:6px;">
        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="toggle_vendor"><input type="hidden" name="vendor_id" value="<?= $v['id'] ?>"><button type="submit" class="<?= $v['is_active']?'btn-w':'btn-s' ?>"><?= $v['is_active']?'Deactivate':'Activate' ?></button></form>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($v['name'])) ?>?')"><input type="hidden" name="action" value="delete_vendor"><input type="hidden" name="vendor_id" value="<?= $v['id'] ?>"><button type="submit" class="btn-d">Delete</button></form>
      </div></td>
    </tr>
    <?php endwhile; ?></tbody></table>
    <?php else: ?><div class="empty"><div style="font-size:36px;">🏪</div><p>No vendors yet.</p></div><?php endif; ?>
  </div>

<?php elseif ($tab==='users'): ?>
  <div style="margin-bottom:20px;"><h1 style="font-size:20px;font-weight:700;">Manage Users</h1><p style="font-size:14px;color:#6b7280;margin-top:4px;">View and manage passenger accounts</p></div>
  <div class="card">
    <div class="card-header">All Passengers <span style="font-size:13px;color:#6b7280;font-weight:400;"><?= $total_users ?> total</span></div>
    <?php if ($users->num_rows>0): ?>
    <table><thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Joined</th><th>Orders</th><th>Total Spent</th><th>Actions</th></tr></thead><tbody>
    <?php while ($u=$users->fetch_assoc()): ?>
    <tr>
      <td><?= $u['id'] ?></td>
      <td style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></td>
      <td><?= htmlspecialchars($u['email']) ?><br><span style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($u['phone']) ?></span></td>
      <td style="font-size:12px;color:#6b7280;"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
      <td style="text-align:center;"><?= $u['order_count'] ?></td>
      <td>&#8377;<?= number_format($u['total_spent'],0) ?></td>
      <td><form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" class="btn-d">Delete</button></form></td>
    </tr>
    <?php endwhile; ?></tbody></table>
    <?php else: ?><div class="empty"><div style="font-size:36px;">👥</div><p>No users yet.</p></div><?php endif; ?>
  </div>

<?php elseif ($tab==='orders'): ?>
  <div style="margin-bottom:20px;"><h1 style="font-size:20px;font-weight:700;">All Orders</h1><p style="font-size:14px;color:#6b7280;margin-top:4px;">View and update any order across all vendors</p></div>
  <div class="card">
    <?php if ($all_orders->num_rows>0): ?>
    <table><thead><tr><th>#</th><th>Passenger</th><th>Vendor</th><th>Train/Seat</th><th>Amount</th><th>Pay</th><th>Status</th><th>Date</th><th>Update</th></tr></thead><tbody>
    <?php while ($o=$all_orders->fetch_assoc()): ?>
    <tr>
      <td style="font-weight:700;">#<?= $o['id'] ?></td>
      <td><?= htmlspecialchars($o['uname']) ?></td>
      <td style="font-size:12px;"><?= htmlspecialchars($o['vname']) ?></td>
      <td><?= htmlspecialchars($o['train_number']) ?><br><span style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($o['coach_number']) ?>/<?= htmlspecialchars($o['seat_number']) ?></span></td>
      <td>&#8377;<?= number_format($o['total_amount'],2) ?></td>
      <td style="font-size:12px;text-transform:uppercase;"><?= htmlspecialchars($o['payment_method']) ?></td>
      <td><?= statusBadge($o['status']) ?></td>
      <td style="font-size:12px;color:#9ca3af;"><?= date('d M Y',strtotime($o['created_at'])) ?></td>
      <td><?php if (!in_array($o['status'],['delivered','cancelled'])): ?>
        <form method="POST" style="display:flex;gap:4px;align-items:center;">
          <input type="hidden" name="action" value="update_order_status">
          <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
          <select name="new_status" class="ss">
            <?php foreach(['placed','confirmed','preparing','out_for_delivery','delivered','cancelled'] as $s): ?>
            <option value="<?= $s ?>"<?= $s===$o['status']?' selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-s">Set</button>
        </form>
      <?php else: ?><span style="font-size:12px;color:#9ca3af;">Final</span><?php endif; ?></td>
    </tr>
    <?php endwhile; ?></tbody></table>
    <?php else: ?><div class="empty"><div style="font-size:36px;">📭</div><p>No orders yet.</p></div><?php endif; ?>
  </div>

<?php elseif ($tab==='reports'): ?>
  <div style="margin-bottom:20px;"><h1 style="font-size:20px;font-weight:700;">Reports & Analytics</h1><p style="font-size:14px;color:#6b7280;margin-top:4px;">System-wide performance</p></div>
  <div class="stats">
    <div class="stat-card blue"><div class="stat-num"><?= $total_orders ?></div><div class="stat-lbl">Total Orders</div><div class="stat-sub">Today: <?= $today_orders ?></div></div>
    <div class="stat-card green"><div class="stat-num" style="font-size:20px;">&#8377;<?= number_format($total_revenue,0) ?></div><div class="stat-lbl">Total Revenue</div><div class="stat-sub">Today: &#8377;<?= number_format($today_revenue,0) ?></div></div>
    <div class="stat-card purple"><div class="stat-num"><?= $total_users ?></div><div class="stat-lbl">Passengers</div></div>
    <div class="stat-card orange"><div class="stat-num"><?= $active_vendors ?></div><div class="stat-lbl">Active Vendors</div></div>
  </div>
  <div class="report-grid">
    <div class="rc">
      <h3>📅 Orders — Last 7 Days</h3>
      <?php
      $dd=[];
      for($i=6;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i days"));$dd[$d]=['cnt'=>0];}
      $daily_orders->data_seek(0);
      while($r=$daily_orders->fetch_assoc()){if(isset($dd[$r['day']]))$dd[$r['day']]['cnt']=$r['cnt'];}
      $mx=max(array_column($dd,'cnt')?:[1]);
      foreach($dd as $day=>$data){$pct=$mx>0?round(($data['cnt']/$mx)*100):0;?>
      <div class="bar-row"><div class="bar-lbl"><?= date('d M',strtotime($day)) ?></div><div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div><div class="bar-val"><?= $data['cnt'] ?> orders</div></div>
      <?php } ?>
    </div>
    <div class="rc">
      <h3>📊 Orders by Status</h3>
      <?php
      $sc=['placed'=>'#3b82f6','confirmed'=>'#8b5cf6','preparing'=>'#f59e0b','out_for_delivery'=>'#f97316','delivered'=>'#10b981','cancelled'=>'#ef4444'];
      $sd=[];$status_counts->data_seek(0);
      while($r=$status_counts->fetch_assoc())$sd[$r['status']]=$r['cnt'];
      $ts=array_sum($sd)?:1;
      foreach($sc as $s=>$col){$cnt=$sd[$s]??0;$pct=round(($cnt/$ts)*100);?>
      <div class="srow">
        <div style="display:flex;align-items:center;gap:8px;"><div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0;"></div><span style="font-size:13px;font-weight:600;color:#374151;"><?= ucwords(str_replace('_',' ',$s)) ?></span></div>
        <div style="display:flex;align-items:center;gap:8px;"><div style="width:80px;background:#f3f4f6;border-radius:4px;height:8px;overflow:hidden;"><div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:4px;"></div></div><span style="font-size:13px;font-weight:700;min-width:20px;text-align:right;"><?= $cnt ?></span></div>
      </div>
      <?php } ?>
    </div>
    <div class="rc" style="grid-column:1/-1;">
      <h3>🏆 Top Vendors by Revenue</h3>
      <?php
      $tv=[];$top_vendors->data_seek(0);
      while($r=$top_vendors->fetch_assoc())$tv[]=$r;
      $mr=!empty($tv)?(max(array_column($tv,'revenue'))?:1):1;
      $bc=['#4f46e5','#7c3aed','#0ea5e9','#10b981','#f97316'];
      if(empty($tv)){echo '<p style="color:#9ca3af;font-size:13px;">No delivered orders yet.</p>';}
      else{foreach($tv as $i=>$t){$p=$mr>0?round(($t['revenue']/$mr)*100):0;?>
      <div class="bar-row" style="margin-bottom:12px;">
        <div class="bar-lbl" style="width:160px;text-align:left;font-weight:600;color:#374151;"><?= htmlspecialchars(substr($t['name'],0,20)) ?></div>
        <div class="bar-wrap"><div class="bar-fill" style="width:<?= $p ?>%;background:<?= $bc[$i] ?>;"></div></div>
        <div class="bar-val">&#8377;<?= number_format($t['revenue'],0) ?></div>
      </div>
      <?php }} ?>
    </div>
  </div>
<?php endif; ?>
</div>
</body>
</html>
