<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'buyer') {
    if ($_SESSION['role'] === 'seller') {
        header('Location: seller_dashboard.php');
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: ../index.php');
    }
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$game_id = isset($_GET['game_id']) ? (int) $_GET['game_id'] : 0;

if ($game_id <= 0) {
    header("Location: store.php?error=invalid_game");
    exit;
}

/* =====================================================
   1️⃣ Check game exists AND has stock
   ===================================================== */
$gameStmt = $conn->prepare("
    SELECT stock 
    FROM games 
    WHERE id = ?
    LIMIT 1
");
$gameStmt->bind_param("i", $game_id);
$gameStmt->execute();
$gameResult = $gameStmt->get_result();

if ($gameResult->num_rows === 0) {
    $gameStmt->close();
    header("Location: store.php?error=game_not_found");
    exit;
}

$game = $gameResult->fetch_assoc();
$gameStmt->close();

if ((int)$game['stock'] <= 0) {
    header("Location: store.php?error=out_of_stock");
    exit;
}

/* =====================================================
   2️⃣ Check if already purchased
   ===================================================== */
$purchaseCheck = $conn->prepare("
    SELECT id 
    FROM purchases 
    WHERE user_id = ? AND game_id = ?
    LIMIT 1
");
$purchaseCheck->bind_param("ii", $user_id, $game_id);
$purchaseCheck->execute();
$purchaseCheck->store_result();

if ($purchaseCheck->num_rows > 0) {
    $purchaseCheck->close();
    header("Location: store.php?error=already_purchased");
    exit;
}
$purchaseCheck->close();

/* =====================================================
   3️⃣ Add to cart (no stock deduction here)
   ===================================================== */
$cartStmt = $conn->prepare("
    INSERT IGNORE INTO cart (user_id, game_id) 
    VALUES (?, ?)
");

if (!$cartStmt) {
    die("Prepare failed: " . $conn->error);
}

$cartStmt->bind_param("ii", $user_id, $game_id);

if (!$cartStmt->execute()) {
    $cartStmt->close();
    die("Execute failed: " . $cartStmt->error);
}

$cartStmt->close();

header("Location: cart.php?success=added");
exit;
