<?php
require_once __DIR__ . '/env_loader.php';

// Load .env file
loadEnv(__DIR__ . '/../.env');

// ==============================
// Application Config
// ==============================
define("APP_NAME", getenv("APP_NAME") ?: "GameSphere");
define("APP_URL", getenv("APP_URL") ?: "http://localhost/gamesphere");

// ==============================
// Database Config (PDO + MySQLi)
// ==============================
define("DB_HOST", getenv("DB_HOST") ?: "127.0.0.1");
define("DB_PORT", getenv("DB_PORT") ?: "3306");
define("DB_NAME", getenv("DB_NAME") ?: "gamesphere");
define("DB_USER", getenv("DB_USER") ?: "root");
define("DB_PASSWORD", getenv("DB_PASSWORD") ?: "");

// ==============================
// Twilio Config (as array)
// ==============================
$twilio = [
    "sid"          => getenv("TWILIO_SID"),
    "auth_token"   => getenv("TWILIO_AUTH_TOKEN"),
    "phone_number" => getenv("TWILIO_FROM") ?: getenv("TWILIO_PHONE_NUMBER") // fallback if using TWILIO_FROM
];

// ==============================
// Email Config (Gmail SMTP)
// ==============================
define("EMAIL_HOST", getenv("EMAIL_HOST") ?: "smtp.gmail.com");
define("EMAIL_PORT", getenv("EMAIL_PORT") ?: 587); // default TLS
define("EMAIL_HOST_USER", getenv("EMAIL_HOST_USER"));
define("EMAIL_HOST_PASSWORD", getenv("EMAIL_HOST_PASSWORD"));

// ==============================
// DB Connection Function
// ==============================
function getDbConnection($driver = "mysqli") {
    if ($driver === "pdo") {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            return $pdo;
        } catch (PDOException $e) {
            die("PDO Connection failed: " . $e->getMessage());
        }
    } else {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            die("MySQLi Connection failed: " . $conn->connect_error);
        }
        return $conn;
    }
}

// ==============================
// Validate Critical Config
// ==============================
function validateConfig($twilio) {
    $errors = [];

    // Database
    if (!DB_HOST || !DB_NAME || !DB_USER) {
        $errors[] = "Database configuration is incomplete.";
    }

    // Twilio
    if (empty($twilio['sid']) || empty($twilio['auth_token']) || empty($twilio['phone_number'])) {
        $errors[] = "Twilio configuration is missing. Check .env.";
    }

    // Email
    if (!EMAIL_HOST_USER || !EMAIL_HOST_PASSWORD) {
        $errors[] = "Email (SMTP) configuration is missing. Check .env.";
    }

    if (!empty($errors)) {
        foreach ($errors as $err) {
            error_log("[CONFIG ERROR] " . $err);
        }
        die("Configuration error(s):<br>" . implode("<br>", $errors));
    }
}

// Run validation at startup
validateConfig($twilio);

// ==============================
// Init Default MySQLi Connection
// ==============================
$conn = getDbConnection("mysqli");
?>