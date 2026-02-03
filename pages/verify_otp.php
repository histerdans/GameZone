<?php
require_once '../includes/config.php';
require_once '../includes/session.php';

$message = '';
$user_id = $_SESSION['pending_user_id'] ?? null;
$maskedPhone = $_SESSION['masked_phone'] ?? '';
$maskedEmail = $_SESSION['masked_email'] ?? '';

if (!$user_id) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify OTP
    if (isset($_POST['verify'])) {
        $otp = trim($_POST['otp']);
        $stmt = $conn->prepare("SELECT id, otp, otp_expires_at, is_verified FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $message = "User not found.";
        } elseif ($user['is_verified'] == 1) {
            unset($_SESSION['pending_user_id']);
            header("Location: login.php?success=Account already verified, please login.");
            exit;
        } elseif ($user['otp'] != $otp) {
            $message = "Invalid OTP.";
        } elseif (strtotime($user['otp_expires_at']) < time()) {
            $message = "OTP expired. Please request a new one.";
        } else {
            // Mark verified
            $stmt = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expires_at = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            unset($_SESSION['pending_user_id'], $_SESSION['masked_phone'], $_SESSION['masked_email']);
            header("Location: login.php?success=Account verified! Please login.");
            exit;
        }
    }
}
   // Resend OTP
if (isset($_POST['resend'])) {
    $new_otp = rand(100000, 999999);
    $otp_expires_at = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    $stmt = $conn->prepare("UPDATE users SET otp = ?, otp_expires_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_otp, $otp_expires_at, $user_id);
    $stmt->execute();
    $stmt->close();

    // Fetch user contact details
    $stmt = $conn->prepare("SELECT email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    require_once '../functions/send_otp.php';

    try {
        $otpSent = sendOTP($user['phone'], $user['email'], $new_otp);
        if ($otpSent) {
            $message = "A new OTP has been sent to your phone and email.";
        } else {
            $message = "Failed to send OTP. Please try again.";
        }
    } catch (Exception $e) {
        $message = "Error sending OTP: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Account - GameSphere</title>
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
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
    .verify-container {
      background: rgba(255, 255, 255, 0.96);
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      width: 100%;
      max-width: 420px;
      text-align: center;
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
<div class="verify-container">
    <h2>Verify Your Account</h2>
    <p class="text-muted">An OTP has been sent to your phone <strong><?= htmlspecialchars($maskedPhone) ?></strong> 
    and email <strong><?= htmlspecialchars($maskedEmail) ?></strong>.</p>

    <?php if (!empty($message)): ?>
      <div class="alert alert-info text-center"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="otp" class="form-control mb-2" required placeholder="Enter OTP">
        <button type="submit" name="verify" class="btn btn-success w-100 mb-2">Verify</button>
    </form>

    <form method="POST">
        <button type="submit" name="resend" class="btn btn-warning w-100">Resend OTP</button>
    </form>
</div>
</body>
</html>
