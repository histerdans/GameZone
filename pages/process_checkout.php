<?php
require_once '../includes/config.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$conn->begin_transaction();

try {

    // 🔒 Lock cart + game rows
    $cartSql = "
        SELECT 
            c.game_id,
            c.quantity,
            g.stock,
            g.price
        FROM cart c
        JOIN games g ON c.game_id = g.id
        WHERE c.user_id = ?
        FOR UPDATE
    ";

    $stmt = $conn->prepare($cartSql);
    if (!$stmt) {
        throw new Exception("Cart query failed: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cartItems = $stmt->get_result();

    if ($cartItems->num_rows === 0) {
        throw new Exception("Your cart is empty.");
    }

    while ($item = $cartItems->fetch_assoc()) {

        if ($item['quantity'] > $item['stock']) {
            throw new Exception("Insufficient stock for a game in your cart.");
        }

        // 1️⃣ Reduce stock
        $updateStockSql = "
            UPDATE games 
            SET stock = stock - ?
            WHERE id = ?
        ";
        $updateStock = $conn->prepare($updateStockSql);
        if (!$updateStock) {
            throw new Exception("Stock update failed: " . $conn->error);
        }

        $updateStock->bind_param(
            "ii",
            $item['quantity'],
            $item['game_id']
        );
        $updateStock->execute();

        // 2️⃣ Insert purchase record
        // ⚠️ Use ONLY columns that exist in your table
        $purchaseSql = "
            INSERT INTO purchases (user_id, game_id, quantity, amount)
            VALUES (?, ?, ?, ?)
        ";
        $purchase = $conn->prepare($purchaseSql);
        if (!$purchase) {
            throw new Exception("Purchase insert failed: " . $conn->error);
        }

        $amount = $item['price'] * $item['quantity'];

        $purchase->bind_param(
            "iiid",
            $user_id,
            $item['game_id'],
            $item['quantity'],
            $amount
        );
        $purchase->execute();
    }

    // 3️⃣ Clear cart
    $clearCart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    if (!$clearCart) {
        throw new Exception("Cart cleanup failed: " . $conn->error);
    }
    $clearCart->bind_param("i", $user_id);
    $clearCart->execute();

    // ✅ All good
    $conn->commit();
    header("Location: success.php");
    exit;

} catch (Exception $e) {

    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
    header("Location: checkout.php");
    exit;
}
