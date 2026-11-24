<?php
// ============================================================
//  Train Food Ordering System — Passenger Registration
//  File: passenger/register.php
// ============================================================

session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Collect & sanitize inputs
    $name  = trim($conn->real_escape_string($_POST['name']));
    $email = trim($conn->real_escape_string($_POST['email']));
    $phone = trim($conn->real_escape_string($_POST['phone']));
    $pass  = $_POST['password'];
    $cpass = $_POST['confirm_password'];

    // 2. Validate
    if (empty($name))  $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "A valid email address is required.";
    if (empty($phone) || !preg_match('/^[6-9]\d{9}$/', $phone))
        $errors[] = "Enter a valid 10-digit Indian mobile number.";
    if (strlen($pass) < 6)
        $errors[] = "Password must be at least 6 characters.";
    if ($pass !== $cpass)
        $errors[] = "Passwords do not match.";

    // 3. Check duplicate email / phone
    if (empty($errors)) {
        $check = $conn->query(
            "SELECT id FROM users WHERE email='$email' OR phone='$phone' LIMIT 1"
        );
        if ($check->num_rows > 0) {
            $errors[] = "An account with this email or phone already exists.";
        }
    }

    // 4. Insert into database
    if (empty($errors)) {
        $hashed = password_hash($pass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $name, $email, $phone, $hashed);

        if ($stmt->execute()) {
            $success = "Account created successfully! You can now log in.";
        } else {
            $errors[] = "Something went wrong. Please try again.";
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
  <title>Register — Train Food Ordering</title>
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
      max-width: 440px;
      padding: 36px 32px;
    }

    .logo {
      text-align: center;
      margin-bottom: 24px;
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
      margin-bottom: 20px;
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

    .success {
      background: #f0fdf4;
      border: 1px solid #86efac;
      border-radius: 8px;
      padding: 12px 14px;
      color: #15803d;
      font-size: 13px;
      margin-bottom: 16px;
    }

    .login-link {
      text-align: center;
      margin-top: 18px;
      font-size: 13px;
      color: #6b7280;
    }

    .login-link a {
      color: #1a56db;
      text-decoration: none;
      font-weight: 600;
    }

    .login-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<div class="card">

  <div class="logo">
    <h1>🚆 TrainEats</h1>
    <p>Food delivered to your seat</p>
  </div>

  <h2>Create your account</h2>

  <?php if (!empty($errors)): ?>
    <div class="errors">
      <?php foreach ($errors as $e): ?>
        <p>&#x26A0; <?= htmlspecialchars($e) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success">
      &#x2713; <?= htmlspecialchars($success) ?>
      <br><a href="login.php" style="color:#15803d;font-weight:600;">Click here to log in &rarr;</a>
    </div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST" action="">

    <div class="form-group">
      <label for="name">Full Name</label>
      <input
        type="text"
        id="name"
        name="name"
        placeholder="e.g. Rahul Sharma"
        value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
        required>
    </div>

    <div class="form-group">
      <label for="email">Email Address</label>
      <input
        type="email"
        id="email"
        name="email"
        placeholder="you@example.com"
        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
        required>
    </div>

    <div class="form-group">
      <label for="phone">Mobile Number</label>
      <input
        type="tel"
        id="phone"
        name="phone"
        placeholder="10-digit mobile number"
        maxlength="10"
        value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>"
        required>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input
        type="password"
        id="password"
        name="password"
        placeholder="Minimum 6 characters"
        required>
    </div>

    <div class="form-group">
      <label for="confirm_password">Confirm Password</label>
      <input
        type="password"
        id="confirm_password"
        name="confirm_password"
        placeholder="Re-enter your password"
        required>
    </div>

    <button type="submit" class="btn">Create Account</button>

  </form>
  <?php endif; ?>

  <div class="login-link">
    Already have an account? <a href="login.php">Log in here</a>
  </div>

</div>

</body>
</html>
