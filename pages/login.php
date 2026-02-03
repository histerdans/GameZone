<?php
require_once '../includes/session.php';
require_once '../functions/auth.php';

$message = '';
$success = '';

if (isset($_GET['success'])) {
  $success = htmlspecialchars($_GET['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id']);
    $password = $_POST['password'];

    // Auto-detect login type: email or username
    if (filter_var($login_id, FILTER_VALIDATE_EMAIL)) {
        $login_type = 'email';
    } else {
        $login_type = 'username';
    }

    $loginResult = loginUser($login_id, $password, $login_type);

    if ($loginResult === true) {
        // Redirect based on role
        if ($_SESSION['role'] === 'seller') {
            header("Location: ../pages/seller_dashboard.php");
        } elseif ($_SESSION['role'] === 'buyer') {
            header("Location: ../pages/buyer_dashboard.php");
        } elseif ($_SESSION['role'] === 'admin') {
            header("Location: ../pages/admin_dashboard.php");
        }
        exit;
    } elseif ($loginResult === "Please verify your account to continue.") {
        header("Location: ../pages/verify_otp.php");
        exit;
    } else {
        $message = $loginResult;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - GameSphere</title>
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="shortcut icon" type="image/x-icon" href="../assets/img/stamp.png">
  <style>
    body {
      background: url('../assets/img/bg.png') no-repeat center center fixed;
      background-size: cover;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
    }

    .login-container {
      background: rgba(255, 255, 255, 0.95);
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 420px;
      text-align: center;
    }

    .login-logo {
      width: 120px;
      margin-bottom: 15px;
      transition: transform 0.3s ease-in-out;
    }

    .login-logo:hover {
      animation: rainbowPulse 2.5s linear infinite;
      transform: scale(1.1);
      cursor: pointer;
    }

    @keyframes rainbowPulse {
      0%   { filter: drop-shadow(0 0 5px red); transform: scale(1.05); }
      20%  { filter: drop-shadow(0 0 10px orange); transform: scale(1.08); }
      40%  { filter: drop-shadow(0 0 15px yellow); transform: scale(1.1); }
      60%  { filter: drop-shadow(0 0 20px green); transform: scale(1.08); }
      80%  { filter: drop-shadow(0 0 25px blue); transform: scale(1.05); }
      100% { filter: drop-shadow(0 0 30px violet); transform: scale(1.1); }
    }

    .form-control:focus {
      border-color: #6f42c1;
      box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
    }

    .btn-purple {
      background-color: #6f42c1;
      border-color: #6f42c1;
    }

    .btn-purple:hover {
      background-color: #5a32a3;
      border-color: #5a32a3;
    }
  </style>
</head>

<body>
  <div class="login-container"> 
    <!-- Logo -->
    <a href="../index.php">
      <img src="../assets/img/logo.png" alt="GameSphere Logo" class="login-logo">
    </a>

    <h2 class="text-center mb-3">Login</h2>

    <!-- Success message after registration -->
    <?php if (!empty($success)): ?>
      <div class="alert alert-success text-center"><?= $success ?></div>
    <?php endif; ?>

    <!-- Error message for login failures -->
    <?php if (!empty($message)): ?>
      <div class="alert alert-danger text-center"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Email or Username</label>
        <input type="text" name="login_id" class="form-control" required placeholder="Enter your email or username">
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required placeholder="Enter your password">
      </div>

      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-purple">Login</button>
      </div>

      <div class="text-center">
        <small class="text-muted">Don't have an account? <a href="register.php">Register here</a></small>
      </div>
    </form>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
