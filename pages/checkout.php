<?php
require_once '../includes/config.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$stockError = false;
$cart_items = [];
$grandTotal = 0;
$errorMsg = "";

// ==========================
// PROCESS PURCHASE IF POST
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $conn->begin_transaction();

    try {

        // 1️⃣ Fetch cart with stock for update
        $cartSql = "
            SELECT c.game_id, c.quantity, g.stock, g.price
            FROM cart c
            JOIN games g ON c.game_id = g.id
            WHERE c.user_id = ?
            FOR UPDATE
        ";
        $stmt = $conn->prepare($cartSql);
        if (!$stmt) throw new Exception("Cart query failed: " . $conn->error);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $cartItems = $stmt->get_result();

        if ($cartItems->num_rows === 0) {
            throw new Exception("Your cart is empty.");
        }

        // 2️⃣ Loop through items, check stock, update stock, insert purchase
        while ($item = $cartItems->fetch_assoc()) {
            if ($item['quantity'] > $item['stock']) {
                throw new Exception("Insufficient stock for one or more items.");
            }

            // Reduce stock
            $updateStockSql = "UPDATE games SET stock = stock - ? WHERE id = ?";
            $updateStock = $conn->prepare($updateStockSql);
            if (!$updateStock) throw new Exception("Stock update failed: " . $conn->error);
            $updateStock->bind_param("ii", $item['quantity'], $item['game_id']);
            $updateStock->execute();

            // Insert purchase record (Option 1: no amount)
            $purchaseSql = "INSERT INTO purchases (user_id, game_id) VALUES (?, ?)";
            $purchase = $conn->prepare($purchaseSql);
            if (!$purchase) throw new Exception("Purchase insert failed: " . $conn->error);
            $purchase->bind_param("ii", $user_id, $item['game_id']);
            $purchase->execute();
        }

        // 3️⃣ Clear cart
        $clearCart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        if (!$clearCart) throw new Exception("Cart cleanup failed: " . $conn->error);
        $clearCart->bind_param("i", $user_id);
        $clearCart->execute();

        $conn->commit();

        // ✅ Set success message and redirect to dashboard
        $_SESSION['flash_message'] = "🎉 Purchase successful!";
        header("Location: ../pages/buyer_dashboard.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = $e->getMessage();
    }
}

// ==========================
// FETCH CART ITEMS + STOCK
// ==========================
$sql = "
    SELECT c.id AS cart_id, g.id AS game_id, g.title, g.price, g.stock, c.quantity
    FROM cart c
    JOIN games g ON c.game_id = g.id
    WHERE c.user_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['quantity'] > $row['stock']) {
        $stockError = true;
    }
    $cart_items[] = $row;
    $grandTotal += min($row['quantity'], $row['stock']) * $row['price'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        body {
            background: url('../assets/img/bg_store.png') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Segoe UI', sans-serif;
        }
        .checkout-container {
            background: rgba(255,255,255,.95);
            padding: 2rem;
            max-width: 900px;
            margin: 3rem auto;
            border-radius: 12px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .6rem; border-bottom: 1px solid #ccc; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>

<div class="checkout-container">
    <h2>🧾 Checkout</h2>

    <?php if ($errorMsg): ?>
        <p class="error">⚠ <?= htmlspecialchars($errorMsg) ?></p>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <p>Your cart is empty.</p>
    <?php else: ?>

        <?php if ($stockError): ?>
            <p class="error">⚠ Some items exceed available stock. Please update your cart.</p>
        <?php endif; ?>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Game</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Stock</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td>KES <?= number_format($item['price'],2) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= $item['stock'] ?></td>
                    <td>KES <?= number_format(min($item['quantity'], $item['stock']) * $item['price'],2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4"><strong>Total</strong></td>
                    <td><strong>KES <?= number_format($grandTotal,2) ?></strong></td>
                </tr>
            </tbody>
        </table>

        <?php if (!$stockError && !empty($cart_items)): ?>
            <form method="post">
                <button type="submit" class="btn btn-success mt-3">✅ Confirm Purchase</button>
            </form>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>
