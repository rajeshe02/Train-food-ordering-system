<?php
// ============================================================
//  Train Food Ordering System — Admin Login
//  File: admin/login.php
// ============================================================

session_start();
require_once '../config/db.php';

// Redirect if already logged in as admin
if (isset($_SESSION['admin_id']) && $_SESSION['user_role'] === 'admin') {
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

    // 3. Check admin exists
    if (empty($errors)) {
        $stmt = $conn->prepare(
            "SELECT id, name, password FROM admins WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            // 4. Verify password
            if (password_verify($pass, $admin['password'])) {

                session_regenerate_id(true);

                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['user_role']  = 'admin';

                header("Location: dashboard.php");
                exit();

            } else {
                $errors[] = "Incorrect password. Please try again.";
            }
        } else {
            $errors[] = "No admin account found with this email.";
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
  <title>Admin Login — TrainEats</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: #020617;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    /* Background grid pattern */
    body::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(99,102,241,0.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(99,102,241,0.06) 1px, transparent 1px);
      background-size: 40px 40px;
      pointer-events: none;
    }

    /* Glow orb */
    body::after {
      content: '';
      position: absolute;
      top: -100px;
      left: 50%;
      transform: translateX(-50%);
      width: 600px;
      height: 400px;
      background: radial-gradient(ellipse, rgba(99,102,241,0.12) 0%, transparent 70%);
      pointer-events: none;
    }

    .wrapper {
      width: 100%;
      max-width: 400px;
      position: relative;
      z-index: 1;
    }

    .top-badge {
      text-align: center;
      margin-bottom: 20px;
    }

    .top-badge a {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #64748b;
      font-size: 13px;
      text-decoration: none;
      padding: 6px 14px;
      border: 1px solid #1e293b;
      border-radius: 20px;
      transition: color 0.2s, border-color 0.2s;
    }

    .top-badge a:hover {
      color: #94a3b8;
      border-color: #334155;
    }

    .card {
      background: #0f172a;
      border: 1px solid #1e293b;
      border-radius: 20px;
      padding: 40px 36px;
      position: relative;
    }

    /* Top accent line */
    .card::before {
      content: '';
      position: absolute;
      top: 0; left: 20%; right: 20%;
      height: 1px;
      background: linear-gradient(90deg, transparent, #6366f1, transparent);
    }

    .logo {
      text-align: center;
      margin-bottom: 32px;
    }

    .logo-icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      margin: 0 auto 16px;
      box-shadow: 0 0 24px rgba(99,102,241,0.3);
    }

    .logo h1 {
      font-size: 22px;
      font-weight: 700;
      color: #f1f5f9;
    }

    .logo p {
      font-size: 13px;
      color: #475569;
      margin-top: 4px;
    }

    .admin-tag {
      display: inline-block;
      background: rgba(99,102,241,0.15);
      color: #a5b4fc;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 12px;
      border-radius: 20px;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-top: 8px;
      border: 1px solid rgba(99,102,241,0.25);
    }

    h2 {
      font-size: 17px;
      font-weight: 700;
      color: #e2e8f0;
      margin-bottom: 24px;
      text-align: center;
    }

    .form-group {
      margin-bottom: 18px;
    }

    label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: #64748b;
      margin-bottom: 7px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    input {
      width: 100%;
      padding: 12px 16px;
      background: #020617;
      border: 1px solid #1e293b;
      border-radius: 10px;
      font-size: 14px;
      color: #e2e8f0;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    input::placeholder { color: #334155; }

    input:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }

    .password-wrap { position: relative; }
    .password-wrap input { padding-right: 46px; }

    .toggle-pass {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 15px;
      color: #334155;
      padding: 0;
      transition: color 0.2s;
    }

    .toggle-pass:hover { color: #64748b; }

    .btn {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 8px;
      transition: opacity 0.2s, transform 0.15s;
      letter-spacing: 0.02em;
    }

    .btn:hover {
      opacity: 0.92;
      transform: translateY(-1px);
    }

    .btn:active { transform: translateY(0); }

    .errors {
      background: rgba(239,68,68,0.08);
      border: 1px solid rgba(239,68,68,0.25);
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 20px;
    }

    .errors p {
      color: #fca5a5;
      font-size: 13px;
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .errors p:last-child { margin-bottom: 0; }

    .security-note {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      background: rgba(99,102,241,0.06);
      border: 1px solid rgba(99,102,241,0.15);
      border-radius: 10px;
      padding: 12px 14px;
      margin-top: 20px;
    }

    .security-note .icon {
      font-size: 16px;
      flex-shrink: 0;
      margin-top: 1px;
    }

    .security-note p {
      font-size: 12px;
      color: #64748b;
      line-height: 1.5;
    }

    .security-note strong { color: #a5b4fc; }

    .bottom-links {
      text-align: center;
      margin-top: 20px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .bottom-links a {
      font-size: 12px;
      color: #475569;
      text-decoration: none;
      transition: color 0.2s;
    }

    .bottom-links a:hover { color: #94a3b8; }

    /* Subtle animation */
    .card {
      animation: fadeUp 0.4s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

<div class="wrapper">

  <div class="top-badge">
    <a href="../index.php">&#8592; Back to TrainEats</a>
  </div>

  <div class="card">

    <div class="logo">
      <div class="logo-icon">⚙️</div>
      <h1>Admin Panel</h1>
      <p>TrainEats Control Center</p>
      <span class="admin-tag">Administrator Access</span>
    </div>

    <h2>Sign in</h2>

    <?php if (!empty($errors)): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?>
          <p>&#x26A0; <?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">

      <div class="form-group">
        <label for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="admin@trainfoods.com"
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
            placeholder="Enter admin password"
            required>
          <button type="button" class="toggle-pass" onclick="togglePass()">👁</button>
        </div>
      </div>

      <button type="submit" class="btn">Access Admin Panel</button>

    </form>

    <div class="security-note">
      <span class="icon">🔒</span>
      <p>
        This area is restricted to <strong>authorized administrators</strong> only.
        Unauthorized access attempts are logged.
      </p>
    </div>

    <div class="bottom-links">
      <a href="../passenger/login.php">Passenger Login &rarr;</a>
      <a href="../vendor/login.php">Vendor Login &rarr;</a>
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
