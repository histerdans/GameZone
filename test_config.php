<?php
// ==============================
// 1️⃣ Load Config & Helpers
// ==============================
require_once __DIR__ . '/includes/config.php';

// ==============================
// 2️⃣ Include PHPMailer manually
// ==============================
require_once __DIR__ . '/functions/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/functions/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/functions/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==============================
// 3️⃣ Send OTP Function
// ==============================
function sendOTP($phone, $email, $otp) {
    global $twilio; // from config.php
    $results = [
        'sms' => ['success' => false, 'error' => ''],
        'email' => ['success' => false, 'error' => '']
    ];

    // ---------- Twilio SMS ----------
    if ($phone && !empty($twilio['sid']) && !empty($twilio['auth_token'])) {
        $sid   = $twilio['sid'];
        $token = $twilio['auth_token'];
        $from  = $twilio['phone_number'];
        $body  = "Your GameSphere OTP is: $otp";

        $url  = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $data = http_build_query([
            'To'   => $phone,
            'From' => $from,
            'Body' => $body
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $results['sms']['error'] = curl_error($ch);
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            $results['sms']['success'] = true;
        } else {
            $results['sms']['error'] = "Twilio HTTP $httpCode — Response: $response";
        }

        curl_close($ch);
    }

    // ---------- Email via PHPMailer ----------
    if ($email && EMAIL_HOST_USER && EMAIL_HOST_PASSWORD) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = EMAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = EMAIL_HOST_USER;
            $mail->Password   = EMAIL_HOST_PASSWORD;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = EMAIL_PORT;

            $mail->setFrom(EMAIL_HOST_USER, 'GameSphere');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your GameSphere OTP';
            $mail->Body    = "Your OTP code is: <b>$otp</b>";

            $mail->send();
            $results['email']['success'] = true;
        } catch (Exception $e) {
            $results['email']['error'] = $mail->ErrorInfo;
        }
    }

    return $results;
}

// ==============================
// 4️⃣ Database Test (PDO & MySQLi)
// ==============================
echo "<h2>🚀 GameSphere Configuration Test</h2>";
echo "<h3>1. Database Connection</h3>";

// PDO Test
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT NOW() AS db_time");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color:green'>✅ PDO Connection OK — Current DB Time: " . htmlspecialchars($row['db_time']) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ PDO Connection Failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// MySQLi Test
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    echo "<p style='color:red'>❌ MySQLi Connection Failed: " . htmlspecialchars($conn->connect_error) . "</p>";
} else {
    echo "<p style='color:green'>✅ MySQLi Connection OK</p>";
}

// ==============================
// 5️⃣ Twilio SMS & Email Test
// ==============================
echo "<h3>2. Twilio SMS & Email Test</h3>";

$otp = "123456"; // Dummy OTP
$testPhone = getenv('TEST_PHONE');
$testEmail = getenv('TEST_EMAIL');

if ($testPhone || $testEmail) {
    $results = sendOTP($testPhone ?? '', $testEmail ?? '', $otp);

    // SMS Result
    if ($testPhone) {
        if (!empty($results['sms']['success'])) {
            echo "<p style='color:green'>✅ SMS sent to $testPhone</p>";
        } else {
            $err = $results['sms']['error'] ?? 'Unknown error';
            echo "<p style='color:red'>❌ SMS failed: $err</p>";
        }
    } else {
        echo "<p style='color:red'>❌ TEST_PHONE not set in .env</p>";
    }

    // Email Result
    if ($testEmail) {
        if (!empty($results['email']['success'])) {
            echo "<p style='color:green'>✅ Email sent to $testEmail</p>";
        } else {
            $err = $results['email']['error'] ?? 'Unknown error';
            echo "<p style='color:red'>❌ Email failed: $err</p>";
        }
    } else {
        echo "<p style='color:red'>❌ TEST_EMAIL not set in .env</p>";
    }
} else {
    echo "<p style='color:red'>❌ TEST_PHONE and TEST_EMAIL not set in .env — cannot test OTP</p>";
}

// ==============================
// 6️⃣ Summary
// ==============================
echo "<h3>3. Summary</h3>";
echo "<ul>";
echo "<li>Database PDO & MySQLi connections tested</li>";
echo "<li>Twilio SMS and Email OTP tests executed</li>";
echo "<li>Check above messages for errors</li>";
echo "</ul>";
