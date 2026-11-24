<?php
// ============================================================
//  Train Food Ordering System — Passenger Login
//  File: passenger/login.php
// ============================================================

session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Collect & sanitize
    $email = trim($conn->real_escape_string($_POST['email']));
    $pass  = $_POST['password'];

    // 2. Validate inputs
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Enter a valid email address.";
    if (empty($pass))
        $errors[] = "Password is required.";

    // 3. Check user exists
    if (empty($errors)) {
        $stmt = $conn->prepare(
            "SELECT id, name, password FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 4. Verify password against bcrypt hash
            if (password_verify($pass, $user['password'])) {

                // 5. Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // 6. Store user info in session
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = 'passenger';

                // 7. Redirect to dashboard
                header("Location: dashboard.php");
                exit();

            } else {
                $errors[] = "Incorrect password. Please try again.";
            }
        } else {
            $errors[] = "No account found with this email address.";
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
  <title>Login — Train Food Ordering</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f0f4f8;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.10);
      width: 100%;
      max-width: 420px;
      padding: 36px 32px;
    }

    .logo {
      text-align: center;
      margin-bottom: 28px;
    }

    .logo h1 {
      font-size: 22px;
      color: #1a56db;
      font-weight: 700;
    }

    .logo p {
      font-size: 13px;
      color: #6b7280;
      margin-top: 4px;
    }

    h2 {
      font-size: 18px;
      color: #111827;
      margin-bottom: 22px;
      font-weight: 600;
    }

    .form-group {
      margin-bottom: 16px;
    }

    label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #374151;
      margin-bottom: 6px;
    }

    input {
      width: 100%;
      padding: 10px 14px;
      border: 1.5px solid #d1d5db;
      border-radius: 8px;
      font-size: 14px;
      color: #111827;
      outline: none;
      transition: border-color 0.2s;
    }

    input:focus { border-color: #1a56db; }

    .password-wrap {
      position: relative;
    }

    .password-wrap input {
      padding-right: 44px;
    }

    .toggle-pass {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 16px;
      color: #6b7280;
      padding: 0;
    }

    .btn {
      width: 100%;
      padding: 11px;
      background: #1a56db;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 6px;
      transition: background 0.2s;
    }

    .btn:hover { background: #1e40af; }

    .errors {
      background: #fef2f2;
      border: 1px solid #fca5a5;
      border-radius: 8px;
      padding: 12px 14px;
      margin-bottom: 16px;
    }

    .errors p {
      color: #b91c1c;
      font-size: 13px;
      margin-bottom: 4px;
    }

    .errors p:last-child { margin-bottom: 0; }

    .divider {
      text-align: center;
      margin: 18px 0 14px;
      font-size: 12px;
      color: #9ca3af;
      position: relative;
    }

    .divider::before,
    .divider::after {
      content: '';
      position: absolute;
      top: 50%;
      width: 38%;
      height: 1px;
      background: #e5e7eb;
    }

    .divider::before { left: 0; }
    .divider::after  { right: 0; }

    .links {
      text-align: center;
      font-size: 13px;
      color: #6b7280;
    }

    .links a {
      color: #1a56db;
      text-decoration: none;
      font-weight: 600;
    }

    .links a:hover { text-decoration: underline; }

    .vendor-link {
      display: block;
      text-align: center;
      margin-top: 14px;
      font-size: 12px;
      color: #9ca3af;
    }

    .vendor-link a {
      color: #6b7280;
      text-decoration: none;
    }

    .vendor-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<div class="card">

  <div class="logo">
    <h1>🚆 TrainEats</h1>
    <p>Food delivered to your seat</p>
  </div>

  <h2>Welcome back</h2>

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
        placeholder="you@example.com"
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
        <button type="button" class="toggle-pass" onclick="togglePassword()" title="Show/hide password">
          👁
        </button>
      </div>
    </div>

    <button type="submit" class="btn">Log In</button>

  </form>

  <div class="divider">or</div>

  <div class="links">
    Don't have an account? <a href="register.php">Register here</a>
  </div>

  <div class="vendor-link">
    Are you a vendor? <a href="../vendor/login.php">Vendor login &rarr;</a>
  </div>

</div>

<script>
function togglePassword() {
  const input = document.getElementById('password');
  input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
