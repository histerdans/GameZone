<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/env_loader.php';
require_once __DIR__ . '/../includes/header.php';

// check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = "";

// Ensure avatar column exists (if not, try to add it)
$colCheck = $conn->query("SHOW COLUMNS FROM `users` LIKE 'avatar'");
if ($colCheck && $colCheck->num_rows === 0) {
    $alter = $conn->query("ALTER TABLE `users` ADD `avatar` VARCHAR(255) NOT NULL DEFAULT 'avatar.png' AFTER `phone`");
    if (!$alter) {
        $message .= "⚠️ Could not add avatar column: " . htmlspecialchars($conn->error) . "<br>";
    }
}

// fetch user details (now including avatar)
$stmt = $conn->prepare("SELECT username, email, phone, avatar FROM users WHERE id=?");
if (!$stmt) {
    die("SQL error: " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// ensure avatar value
$currentAvatar = !empty($user['avatar']) ? $user['avatar'] : 'avatar.png';
$avatarUploadDir = __DIR__ . '/../uploads/avatars/';
$avatarPreviewPath = file_exists($avatarUploadDir . $currentAvatar) ? ("../uploads/avatars/" . $currentAvatar) : "../assets/img/avatar.png";

// ---------- SEND OTP ----------
if (isset($_POST['send_otp'])) {
    $newEmail = trim($_POST['email']);
    $newPhone = trim($_POST['phone']);

    $_SESSION['pending_email'] = $newEmail;
    $_SESSION['pending_phone'] = $newPhone;

    $otp = rand(100000, 999999);
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes

    if ($newPhone !== $user['phone']) {
        // send via Twilio SMS
        $sid   = getenv('TWILIO_SID');
        $token = getenv('TWILIO_AUTH_TOKEN');
        $from  = getenv('TWILIO_PHONE_NUMBER');

        if ($sid && $token && $from) {
            $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
            $data = [
                "From" => $from,
                "To"   => $newPhone,
                "Body" => "Your OTP code is $otp"
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
            $resp = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($curlErr) {
                $message .= "⚠️ Twilio error: " . htmlspecialchars($curlErr) . "<br>";
            } else {
                $message .= "📲 OTP sent to your new phone.<br>";
            }
        } else {
            $message .= "⚠️ Twilio not configured properly (TWILIO_* env missing).<br>";
        }
    } elseif ($newEmail !== $user['email']) {
        // send via email (mail() fallback)
        $to      = $newEmail;
        $subject = "OTP Verification";
        $body    = "Your OTP code is $otp";
        $headers = "From: " . getenv('EMAIL_HOST_USER');

        if (mail($to, $subject, $body, $headers)) {
            $message .= "📧 OTP sent to your new email.<br>";
        } else {
            $message .= "❌ Failed to send OTP email (mail() returned false).<br>";
        }
    } else {
        $message .= "ℹ️ No email or phone change detected, OTP not required.<br>";
    }
}

// ---------- VERIFY OTP ----------
if (isset($_POST['verify_otp'])) {
    $entered = trim($_POST['otp']);
    if (isset($_SESSION['otp']) && $entered == $_SESSION['otp'] && time() < $_SESSION['otp_expiry']) {
        $_SESSION['otp_verified'] = true;
        $message .= "✅ OTP verified! You can now update.<br>";
    } else {
        $message .= "❌ Invalid or expired OTP.<br>";
    }
}

// ---------- UPDATE PROFILE ----------
if (isset($_POST['update'])) {
    $username = trim($_POST['username']);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    $finalEmail = $user['email'];
    $finalPhone = $user['phone'];

    // apply verified changes for email/phone
    if (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true) {
        if (isset($_SESSION['pending_email'])) $finalEmail = $_SESSION['pending_email'];
        if (isset($_SESSION['pending_phone'])) $finalPhone = $_SESSION['pending_phone'];
    }

    // handle avatar upload (validate types + size)
    $avatarFile = $currentAvatar;
    if (!empty($_FILES['avatar']['name'])) {
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $maxBytes = 2 * 1024 * 1024; // 2 MB

        if (!in_array($ext, $allowed)) {
            $message .= "❌ Avatar must be jpg, jpeg, png, or gif.<br>";
        } elseif ($_FILES['avatar']['size'] > $maxBytes) {
            $message .= "❌ Avatar file too large (max 2 MB).<br>";
        } else {
            if (!is_dir($avatarUploadDir)) mkdir($avatarUploadDir, 0777, true);
            $fileName = "avatar_" . $userId . "_" . time() . "." . $ext;
            $targetFile = $avatarUploadDir . $fileName;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
                $avatarFile = $fileName;
                // update preview path immediately
                $avatarPreviewPath = "../uploads/avatars/" . $avatarFile;
            } else {
                $message .= "❌ Failed to move uploaded avatar file.<br>";
            }
        }
    }

    // perform update query (include avatar column)
    if ($password) {
        $stmtUp = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, password=?, avatar=? WHERE id=?");
        if (!$stmtUp) { $message .= "SQL error (prepare): " . htmlspecialchars($conn->error) . "<br>"; }
        $stmtUp->bind_param("sssssi", $username, $finalEmail, $finalPhone, $password, $avatarFile, $userId);
    } else {
        $stmtUp = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, avatar=? WHERE id=?");
        if (!$stmtUp) { $message .= "SQL error (prepare): " . htmlspecialchars($conn->error) . "<br>"; }
        $stmtUp->bind_param("ssssi", $username, $finalEmail, $finalPhone, $avatarFile, $userId);
    }

    if (isset($stmtUp) && $stmtUp->execute()) {
        $message .= "✅ Profile updated successfully.<br>";
        // refresh $user values and preview
        $user['username'] = $username;
        $user['email'] = $finalEmail;
        $user['phone'] = $finalPhone;
        $user['avatar'] = $avatarFile;
        unset($_SESSION['otp'], $_SESSION['otp_verified'], $_SESSION['pending_email'], $_SESSION['pending_phone']);
    } else {
        if (isset($stmtUp)) {
            $message .= "❌ Error updating profile: " . htmlspecialchars($stmtUp->error) . "<br>";
        } else {
            $message .= "❌ Unknown error during update.<br>";
        }
    }
}
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">👤 My Profile</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?= $message ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="profileForm" class="row g-3">
                <div class="col-md-3 text-center">
                    <label class="form-label">Avatar</label>
                    <div>
                        <img src="<?= htmlspecialchars($avatarPreviewPath) ?>" alt="Avatar" class="rounded-circle" style="width:120px;height:120px;object-fit:cover;border:2px solid #ddd;">
                    </div>
                    <input type="file" name="avatar" class="form-control mt-2">
                    <small class="text-muted">jpg/png/gif ≤ 2MB</small>
                </div>

                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input id="email" type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input id="phone" type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control">
                        </div>

                        <div class="col-12">
                            <div id="otpControls" class="d-none">
                                <button type="submit" name="send_otp" class="btn btn-warning">📩 Send OTP</button>
                            </div>

                            <?php if (isset($_SESSION['otp']) && !isset($_SESSION['otp_verified'])): ?>
                                <div class="input-group mt-3">
                                    <input type="text" name="otp" placeholder="Enter OTP" class="form-control" required>
                                    <button type="submit" name="verify_otp" class="btn btn-success">✔ Verify OTP</button>
                                </div>
                            <?php endif; ?>

                            <button type="submit" name="update" class="btn btn-primary mt-3"
                                <?= (!isset($_SESSION['otp_verified']) &&
                                    ((isset($_SESSION['pending_email']) && $_SESSION['pending_email'] != $user['email'])
                                    || (isset($_SESSION['pending_phone']) && $_SESSION['pending_phone'] != $user['phone'])))
                                    ? 'disabled' : '' ?>>
                                💾 Update Profile
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const emailField = document.getElementById('email');
const phoneField = document.getElementById('phone');
const otpControls = document.getElementById('otpControls');

const originalEmail = <?= json_encode($user['email']) ?>;
const originalPhone = <?= json_encode($user['phone']) ?>;

function checkSensitiveChange() {
    if (emailField.value !== originalEmail || phoneField.value !== originalPhone) {
        otpControls.classList.remove('d-none');
    } else {
        otpControls.classList.add('d-none');
    }
}

emailField.addEventListener('input', checkSensitiveChange);
phoneField.addEventListener('input', checkSensitiveChange);

// run on load
checkSensitiveChange();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
