<?php
require_once __DIR__ . '/../includes/env_loader.php';

// PHPMailer includes
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send OTP via Twilio SMS + Email
 *
 * @param string $phone Recipient phone number (with country code, e.g., +2547xxxxxxx)
 * @param string $email Recipient email address
 * @param string $otp   OTP code
 * @return array
 */
function sendOTP($phone, $email, $otp) {
    $results = [
        'sms'   => ['success' => false, 'error' => ''],
        'email' => ['success' => false, 'error' => '']
    ];

    // ---------- Twilio SMS ----------
    if (!empty($phone)) {
        $sid   = getenv('TWILIO_SID');
        $token = getenv('TWILIO_AUTH_TOKEN');
        $from  = getenv('TWILIO_PHONE_NUMBER');
        $body  = "Your GameSphere OTP is: $otp";

        if (!$sid || !$token || !$from) {
            $results['sms']['error'] = "Missing Twilio credentials in environment variables.";
        } else {
            $url  = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
            $data = http_build_query([
                'To' => $phone,
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
    }

    // ---------- Email via PHPMailer ----------
    if (!empty($email)) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = getenv('EMAIL_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('EMAIL_HOST_USER');
            $mail->Password   = getenv('EMAIL_HOST_PASSWORD');
            $mail->SMTPSecure = getenv('EMAIL_ENCRYPTION') ?: 'tls';
            $mail->Port       = getenv('EMAIL_PORT') ?: 587;

            $mail->setFrom(getenv('EMAIL_HOST_USER'), 'GameSphere');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your GameSphere OTP';
            $mail->Body    = "<p>Your OTP code is: <b>$otp</b></p>";

            $mail->send();
            $results['email']['success'] = true;
        } catch (Exception $e) {
            $results['email']['error'] = $mail->ErrorInfo;
        }
    }

    return $results;
}
