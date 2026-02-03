<?php
require_once '../includes/config.php';
require_once '../includes/session.php';

/* =========================
   ADD TO CART (STOCK AWARE)
========================= */
function addToCart($userId, $gameId) {
    global $conn;

    // Check current stock
    $check = $conn->prepare("SELECT stock FROM games WHERE id = ?");
    $check->bind_param("i", $gameId);
    $check->execute();
    $stockRow = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$stockRow || $stockRow['stock'] <= 0) {
        // Out of stock → do nothing
        return false;
    }

    // Check if already in cart
    $stmt = $conn->prepare("
        SELECT id, quantity 
        FROM cart 
        WHERE user_id = ? AND game_id = ?
    ");
    $stmt->bind_param("ii", $userId, $gameId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Cap quantity to available stock
        if ($row['quantity'] < $stockRow['stock']) {
            $update = $conn->prepare("
                UPDATE cart 
                SET quantity = quantity + 1 
                WHERE id = ?
            ");
            $update->bind_param("i", $row['id']);
            $update->execute();
            $update->close();
        }
    } else {
        // Insert new item with quantity = 1
        $insert = $conn->prepare("
            INSERT INTO cart (user_id, game_id, quantity)
            VALUES (?, ?, 1)
        ");
        $insert->bind_param("ii", $userId, $gameId);
        $insert->execute();
        $insert->close();
    }

    return true;
}

/* =========================
   REMOVE FROM CART
========================= */
function removeFromCart($cartId, $userId) {
    global $conn;

    $stmt = $conn->prepare("
        DELETE FROM cart 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $cartId, $userId);
    $stmt->execute();
    $stmt->close();
}

/* =========================
   GET CART ITEMS (WITH STOCK)
========================= */
function getCartItems($userId) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            c.id AS cart_id,
            c.quantity,
            g.id AS game_id,
            g.title,
            g.price,
            g.stock
        FROM cart c
        JOIN games g ON c.game_id = g.id
        WHERE c.user_id = ?
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result();
}
