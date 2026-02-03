<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/twilio_auth_otp.php';
require_once __DIR__ . '/../includes/session.php';


// ==============================
// Login User
// ==============================
function loginUser($login_id, $password, $login_type = 'email') {
    global $conn;

    if ($login_type === 'email') {
        $query = "SELECT * FROM users WHERE email = ?";
    } else {
        $query = "SELECT * FROM users WHERE username = ?";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $login_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ucfirst($login_type) . " not found.";
    }

    if ((int)$user['is_verified'] === 0) {
        $_SESSION['pending_user_id'] = $user['id'];
        return "Please verify your account to continue.";
    }

    if (!password_verify($password, $user['password'])) {
        return "Invalid password.";
    }

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];

    return true;
}

// ==============================
// Verify OTP Function (leading-zero safe)
// ==============================
function verifyOTP($userId, $otp) {
    global $conn;

    $stmt = $conn->prepare("SELECT otp, otp_expires_at, is_verified FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) return ["success" => false, "error" => "User not found."];

    if ($user['is_verified'] == 1) 
        return ["success" => false, "error" => "Account already verified."];

    // ✅ Compare as strings to preserve leading zeros
    if (strval($user['otp']) !== strval($otp)) 
        return ["success" => false, "error" => "Invalid OTP."];

    if (strtotime($user['otp_expires_at']) < time()) 
        return ["success" => false, "error" => "OTP expired."];

    $stmt = $conn->prepare("UPDATE users 
        SET is_verified = 1, otp = NULL, otp_expires_at = NULL 
        WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    return ["success" => true, "message" => "Account verified successfully."];
}
