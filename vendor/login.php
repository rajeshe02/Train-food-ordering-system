<?php
// ============================================================
//  Train Food Ordering System — Vendor Login
//  File: vendor/login.php
// ============================================================

session_start();
require_once '../config/db.php';

// Redirect if already logged in as vendor
if (isset($_SESSION['vendor_id']) && $_SESSION['user_role'] === 'vendor') {
    header("Location: dashboard.php");
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Collect & sanitize
    $email = trim($conn->real_escape_string($_POST['email']));
    $pass  = $_POST['password'];

    // 2. Validate
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Enter a valid email address.";
    if (empty($pass))
        $errors[] = "Password is required.";

    // 3. Check vendor exists
    if (empty($errors)) {
        $stmt = $conn->prepare(
            "SELECT id, name, password, is_active FROM vendors WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $vendor = $result->fetch_assoc();

            // 4. Check if vendor is active
            if (!$vendor['is_active']) {
                $errors[] = "Your vendor account has been deactivated. Contact admin.";

            // 5. Verify password
            } elseif (password_verify($pass, $vendor['password'])) {

                session_regenerate_id(true);

                $_SESSION['vendor_id']   = $vendor['id'];
                $_SESSION['vendor_name'] = $vendor['name'];
                $_SESSION['user_role']   = 'vendor';

                header("Location: dashboard.php");
                exit();

            } else {
                $errors[] = "Incorrect password. Please try again.";
            }
        } else {
            $errors[] = "No vendor account found with this email.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vendor Login — TrainEats</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: #0f172a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .wrapper {
      width: 100%;
      max-width: 420px;
    }

    .top-badge {
      text-align: center;
      margin-bottom: 20px;
    }

    .top-badge a {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #94a3b8;
      font-size: 13px;
      text-decoration: none;
      padding: 6px 14px;
      border: 1px solid #1e293b;
      border-radius: 20px;
      transition: color 0.2s;
    }

    .top-badge a:hover { color: #e2e8f0; }

    .card {
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 16px;
      padding: 36px 32px;
    }

    .logo {
      text-align: center;
      margin-bottom: 28px;
    }

    .logo-icon {
      width: 56px;
      height: 56px;
      background: #f97316;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      margin: 0 auto 14px;
    }

    .logo h1 {
      font-size: 20px;
      font-weight: 700;
      color: #f1f5f9;
    }

    .logo p {
      font-size: 13px;
      color: #64748b;
      margin-top: 4px;
    }

    .vendor-tag {
      display: inline-block;
      background: rgba(249,115,22,0.15);
      color: #fb923c;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-top: 6px;
    }

    h2 {
      font-size: 17px;
      font-weight: 700;
      color: #f1f5f9;
      margin-bottom: 22px;
    }

    .form-group {
      margin-bottom: 16px;
    }

    label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: #94a3b8;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    input {
      width: 100%;
      padding: 11px 14px;
      background: #0f172a;
      border: 1.5px solid #334155;
      border-radius: 8px;
      font-size: 14px;
      color: #f1f5f9;
      outline: none;
      transition: border-color 0.2s;
    }

    input::placeholder { color: #475569; }
    input:focus { border-color: #f97316; }

    .password-wrap { position: relative; }
    .password-wrap input { padding-right: 44px; }
    .toggle-pass {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 15px;
      color: #475569;
      padding: 0;
    }
    .toggle-pass:hover { color: #94a3b8; }

    .btn {
      width: 100%;
      padding: 12px;
      background: #f97316;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 6px;
      transition: background 0.2s;
    }

    .btn:hover { background: #ea6c0a; }

    .errors {
      background: rgba(239,68,68,0.1);
      border: 1px solid rgba(239,68,68,0.3);
      border-radius: 8px;
      padding: 12px 14px;
      margin-bottom: 18px;
    }

    .errors p {
      color: #fca5a5;
      font-size: 13px;
      margin-bottom: 4px;
    }

    .errors p:last-child { margin-bottom: 0; }

    .divider {
      text-align: center;
      margin: 20px 0 16px;
      font-size: 12px;
      color: #334155;
      position: relative;
    }

    .divider::before, .divider::after {
      content: '';
      position: absolute;
      top: 50%;
      width: 40%;
      height: 1px;
      background: #1e293b;
      border-top: 1px solid #334155;
    }

    .divider::before { left: 0; }
    .divider::after  { right: 0; }

    .bottom-links {
      text-align: center;
      font-size: 13px;
      color: #475569;
    }

    .bottom-links a {
      color: #fb923c;
      text-decoration: none;
      font-weight: 600;
    }

    .bottom-links a:hover { text-decoration: underline; }

    .info-box {
      background: rgba(249,115,22,0.07);
      border: 1px solid rgba(249,115,22,0.2);
      border-radius: 8px;
      padding: 12px 14px;
      margin-bottom: 20px;
      font-size: 12px;
      color: #94a3b8;
      line-height: 1.6;
    }

    .info-box strong { color: #fb923c; }
  </style>
</head>
<body>

<div class="wrapper">

  <div class="top-badge">
    <a href="../index.php">&#8592; Back to TrainEats</a>
  </div>

  <div class="card">

    <div class="logo">
      <div class="logo-icon">🏪</div>
      <h1>Vendor Portal</h1>
      <p>TrainEats Partner Dashboard</p>
      <span class="vendor-tag">Vendor Access</span>
    </div>

    <h2>Sign in to your account</h2>

    <?php if (!empty($errors)): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?>
          <p>&#x26A0; <?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="info-box">
      <strong>Note:</strong> This portal is for registered food vendors only.
      Contact admin at <strong>admin@trainfoods.com</strong> to register your stall.
    </div>

    <form method="POST" action="">

      <div class="form-group">
        <label for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="your@restaurant.com"
          value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
          required
          autofocus>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            required>
          <button type="button" class="toggle-pass" onclick="togglePass()">👁</button>
        </div>
      </div>

      <button type="submit" class="btn">Sign In to Vendor Portal</button>

    </form>

    <div class="divider">or</div>

    <div class="bottom-links">
      Are you a passenger? <a href="../passenger/login.php">Passenger login &rarr;</a>
    </div>

  </div>

</div>

<script>
function togglePass() {
  const input = document.getElementById('password');
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
