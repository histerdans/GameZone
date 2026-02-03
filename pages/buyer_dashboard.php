<?php
require_once '../includes/header.php';
require_once '../includes/config.php';
require_once '../includes/session.php';

// Optional: include twilio helper if you already have one. We use an inline helper below.
require_once '../functions/twilio_auth_otp.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: ../pages/login.php");
    exit;
}

$userId = intval($_SESSION['user_id']);
if (isset($_SESSION['flash_message'])) {
    echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>"
        . htmlspecialchars($_SESSION['flash_message']) .
        "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>";
    unset($_SESSION['flash_message']);
}
// -----------------------
// Local helper: send SMS using Twilio REST API (simple wrapper)
// -----------------------
function sendSmsUsingTwilio($to, $message) {
    $sid   = getenv('TWILIO_SID');
    $token = getenv('TWILIO_AUTH_TOKEN');
    $from  = getenv('TWILIO_PHONE_NUMBER');

    if (!$sid || !$token || !$from) {
        return ['success' => false, 'error' => 'Twilio credentials missing'];
    }

    $url  = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
    $data = http_build_query([
        'To'   => $to,
        'From' => $from,
        'Body' => $message
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $err];
    }

    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'response' => $response];
    } else {
        return ['success' => false, 'error' => "Twilio HTTP $httpCode — Response: $response"];
    }
}

// -----------------------
// Fetch purchased games
// -----------------------
$purchasedQuery = "
    SELECT g.id, g.title, g.price,g.stock, g.thumbnail, p.purchase_date 
    FROM purchases p
    JOIN games g ON p.game_id = g.id
    WHERE p.user_id = ?
    ORDER BY p.purchase_date DESC
";
$stmt = $conn->prepare($purchasedQuery);
if (!$stmt) {
    die("DB prepare error (purchasedQuery): " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$purchasedGames = $stmt->get_result();
$stmt->close();

// -----------------------
// Fetch available games (not purchased yet)
// -----------------------
$availableQuery = "
    SELECT g.id, g.title, g.price, g.thumbnail, g.stock
    FROM games g
    WHERE g.id NOT IN (
        SELECT game_id FROM purchases WHERE user_id = ?
    )
";
$stmt2 = $conn->prepare($availableQuery);
if ($stmt2 === false) {
    die("DB prepare error (availableQuery): " . htmlspecialchars($conn->error));
}
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$availableGames = $stmt2->get_result();
$stmt2->close();

// -----------------------
// Purchase success message (from checkout)
// -----------------------
$purchaseMessage = '';
if (isset($_SESSION['purchase_success']) && $_SESSION['purchase_success'] === true) {
    $gameName = $_SESSION['purchased_game_name'] ?? "a game";

    // Try to get buyer phone (from session or DB)
    $phone = $_SESSION['phone'] ?? null;
    if (!$phone) {
        $stmtPhone = $conn->prepare("SELECT phone FROM users WHERE id = ?");
        if ($stmtPhone) {
            $stmtPhone->bind_param("i", $userId);
            $stmtPhone->execute();
            $res = $stmtPhone->get_result()->fetch_assoc();
            $phone = $res['phone'] ?? null;
            $stmtPhone->close();
        }
    }

    // Send SMS only if phone is available and Twilio creds are set
    if ($phone) {
        $text = "🎮 Thank you for your purchase! You bought '{$gameName}' on GameSphere.";
        $smsResult = sendSmsUsingTwilio($phone, $text);
        if ($smsResult['success']) {
            $purchaseMessage = "Purchase successful! A confirmation SMS has been sent.";
        } else {
            $purchaseMessage = "Purchase successful! (SMS could not be sent: " . htmlspecialchars($smsResult['error']) . ")";
        }
    } else {
        $purchaseMessage = "Purchase successful!";
    }

    // Clear session flags
    unset($_SESSION['purchase_success'], $_SESSION['purchased_game_name']);
}

// -----------------------
// Helper for thumbnail path
// -----------------------
function thumbUrl($thumb) {
    if (!empty($thumb)) {
        if (strpos($thumb, '/') !== false) {
            return "../" . ltrim($thumb, '/');
        }
        return "../uploads/" . $thumb;
    }
    return "../assets/img/default-game.png";
}
?>

<div class="container mt-4">
    <h2 class="mb-4">Buyer Dashboard</h2>

    <?php if (!empty($purchaseMessage)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($purchaseMessage) ?></div>
    <?php endif; ?>

    <!-- My Games Section -->
    <div class="mb-5">
        <h3>🎮 My Games</h3>
        <?php if ($purchasedGames && $purchasedGames->num_rows > 0): ?>
            <div class="row mt-3">
                <?php while ($game = $purchasedGames->fetch_assoc()): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-dark text-white h-100">
                            <img src="<?= htmlspecialchars(thumbUrl($game['thumbnail'])) ?>" 
                                 class="card-img-top" 
                                 alt="<?= htmlspecialchars($game['title']) ?>">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                                <p><small>Purchased on: <?= htmlspecialchars($game['purchase_date']) ?></small></p>
                                <div class="mt-auto">
                                    <a href="play_game.php?id=<?= $game['id'] ?>" class="btn btn-success btn-sm">Play</a>
                                    <a href="download_game.php?id=<?= $game['id'] ?>" class="btn btn-primary btn-sm">Download</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <a href="purchases.php" class="btn btn-primary mt-3">View All Purchases</a>
        <?php else: ?>
            <p>You haven’t purchased any games yet.</p>
        <?php endif; ?>
    </div>

    <!-- Buy Games Section -->

<div>
        <h3>🛒 Buy Games</h3>
        <?php if ($availableGames && $availableGames->num_rows > 0): ?>
            <div class="row mt-3">
                <?php while ($game = $availableGames->fetch_assoc()): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card bg-dark text-white h-100">
                            <img src="<?= htmlspecialchars(thumbUrl($game['thumbnail'])) ?>" 
                                 class="card-img-top" 
                                 alt="<?= htmlspecialchars($game['title']) ?>">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                                <p><strong>Ksh. <?= number_format($game['price'], 2) ?></strong></p>
                                <!-- 🔹 STOCK DISPLAY -->
                            <p class="small mb-2">
                                <?php if ($game['stock'] > 0): ?>
                                    <span class="text-success">
                                        <?= $game['stock'] ?> in stock
                                    </span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold">
                                        Out of stock
                                    </span>
                                <?php endif; ?>
                            </p>

                               <!-- 🔹 ACTION BUTTON -->
                            <?php if ($game['stock'] <= 0): ?>
                                <button class="btn btn-secondary btn-sm mt-auto" disabled>
                                    Out of Stock
                                </button>
                            <?php else: ?>
                                <a href="../pages/add_to_cart.php?game_id=<?= $game['id'] ?>" 
                                   class="btn btn-warning btn-sm mt-auto">
                                    Add to Cart
                                </a>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>All available games are already in your library 🎉</p>
        <?php endif; ?>
    </div>
</div>



<?php require_once '../includes/footer.php'; ?>
