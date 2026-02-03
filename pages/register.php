<?php
require_once '../includes/config.php';
require_once '../includes/session.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $username   = trim($_POST['username']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $password   = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role       = $_POST['role'];
    $country_code = $_POST['country_code'] ?? '+254'; // Default to Kenya

    // Remove leading 0 if present
    if (substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1);
    }

    // Prepend country code
    if (strpos($phone, '+') !== 0) {
        $phone = $country_code . $phone;
    }

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        $result = registerUser($first_name, $last_name, $username, $email, $phone, $password, $role);

        if ($result['status'] === 'success') {
            $_SESSION['pending_user_id'] = $result['user_id'];

            $maskedPhone = substr($phone, 0, 4) . str_repeat("x", max(0, strlen($phone) - 6)) . substr($phone, -2);
            $atPos = strpos($email, "@");
            $maskedEmail = substr($email, 0, 2) . str_repeat("*", max(0, $atPos - 2)) . substr($email, $atPos);

            $_SESSION['masked_phone'] = $maskedPhone;
            $_SESSION['masked_email'] = $maskedEmail;

            header("Location: verify_otp.php");
            exit;
        } else {
            $message = $result['message'];
        }
    }
}


function registerUser($first_name, $last_name, $username, $email, $phone, $password, $role) {
  global $conn;

  $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if username or email already exists
  $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
  $check->bind_param("ss", $username, $email);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    $check->close();
    return ['status' => 'error', 'message' => 'Username or email already exists.'];
  }
  $check->close();

    // Generate OTP
  $otp = rand(100000, 999999);
  $otp_expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    // Insert user with OTP
  $stmt = $conn->prepare("INSERT INTO users 
    (first_name, last_name, username, email, phone, password, role, otp, otp_expires_at, is_verified, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
  $stmt->bind_param("sssssssis", 
    $first_name, $last_name, $username, $email, $phone, $hashed_password, 
    $role, $otp, $otp_expires_at
  );

  if ($stmt->execute()) {
    $userId = $stmt->insert_id;
    $stmt->close();
   // Send OTP via SMS first, fallback to Email
require_once '../functions/auth.php';  // unified function we wrote earlier

try {
    $otpSent = sendOTP($phone, $email, $otp);
    if (!$otpSent) {
        return [
            'status' => 'error',
            'message' => 'User registered but OTP delivery failed on both SMS and Email.'
        ];
    }
} catch (Exception $e) {
    return [
        'status' => 'error',
        'message' => 'User registered but OTP failed: ' . $e->getMessage()
    ];
}


    return [
      'status' => 'success', 
      'message' => 'User registered successfully. OTP sent.', 
      'user_id' => $userId
    ];
  } else {
    $errorMsg = $stmt->error;
    $stmt->close();
    return ['status' => 'error', 'message' => 'Registration failed: ' . $errorMsg];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - GameSphere</title>
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
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .register-container {
      background: rgba(255, 255, 255, 0.96);
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      width: 100%;
      max-width: 480px;
      text-align: center;
    }
    .register-header { margin-bottom: 2rem; }
    .register-header h2 { font-weight: 600; color: #6f42c1; }
    .register-logo {
      width: 120px;
      margin-bottom: 15px;
      transition: transform 0.3s ease-in-out;
    }
    .register-logo:hover {
      animation: rainbowPulse 2.5s linear infinite;
      transform: scale(1.1);
      cursor: pointer;
    }
    @keyframes rainbowPulse {
      0% { filter: drop-shadow(0 0 5px red) drop-shadow(0 0 10px red); transform: scale(1.05); }
      20% { filter: drop-shadow(0 0 10px orange) drop-shadow(0 0 20px orange); transform: scale(1.08); }
      40% { filter: drop-shadow(0 0 15px yellow) drop-shadow(0 0 30px yellow); transform: scale(1.1); }
      60% { filter: drop-shadow(0 0 20px green) drop-shadow(0 0 40px green); transform: scale(1.08); }
      80% { filter: drop-shadow(0 0 25px blue) drop-shadow(0 0 50px blue); transform: scale(1.05); }
      100% { filter: drop-shadow(0 0 30px violet) drop-shadow(0 0 60px violet); transform: scale(1.1); }
    }
    .form-control, .form-select {
      margin-bottom: 1rem;
      padding: 0.75rem 1rem;
      border-radius: 8px;
    }
    .form-control:focus, .form-select:focus {
      border-color: #6f42c1;
      box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
    }
    .btn-purple {
      background-color: #6f42c1;
      border-color: #6f42c1;
      padding: 0.75rem;
      font-weight: 500;
      border-radius: 8px;
    }
    .btn-purple:hover {
      background-color: #5a32a3;
      border-color: #5a32a3;
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-header">
     <a href="../index.php"><img src="../assets/img/logo.png" alt="GameSphere Logo" class="register-logo" ></a>
     <h2>Create an Account</h2>
     <p class="text-muted">Join GameSphere today</p>
   </div>

   <?php if (!empty($message)): ?>
    <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

 <form method="POST" id="registerForm">
    <input type="text" name="first_name" class="form-control" required placeholder="Enter your first name">
    <input type="text" name="last_name" class="form-control" required placeholder="Enter your last name">
    <input type="text" name="username" class="form-control" required placeholder="Enter your username">
    <input type="email" name="email" class="form-control" required placeholder="Enter your email">

    <!-- Country code + Phone number -->
    <div class="d-flex mb-3">
        <select name="country_code" class="form-select me-2" required>
            <option value="+254" selected>Kenya (+254)</option>
            <option value="+1">USA (+1)</option>
            <option value="+44">UK (+44)</option>
            <option value="+91">India (+91)</option>
            <!-- Add more countries as needed -->
        </select>
        <input type="text" name="phone" class="form-control" required placeholder="Enter your phone number">
    </div>

    <input type="password" id="password" name="password" class="form-control" required placeholder="Password">
    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Confirm password">
    
    <select name="role" class="form-select" required>
        <option disabled selected>Select role</option>
        <option value="buyer">Buyer</option>
        <option value="seller">Seller</option>
        <option value="admin">Admin</option>
    </select>

    <button type="submit" class="btn btn-purple w-100">Register</button>
</form>

</div>
</body>
</html>
