<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../functions/cart.php';

// Ensure user is logged in as buyer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: ../pages/login.php");
    exit;
}

$userId = intval($_SESSION['user_id']);
$hasOutOfStock = false;

/* =========================
   REMOVE ITEM FROM CART
   (Must happen before any HTML output)
========================= */
if (isset($_GET['remove'])) {
    $cartId = intval($_GET['remove']);
    if ($cartId > 0) {
        removeFromCart($cartId, $userId);
    }
    header("Location: cart.php");
    exit;
}

/* =========================
   UPDATE QUANTITIES
   (Also before HTML output)
========================= */
if (isset($_POST['update_quantity']) && isset($_POST['quantities'])) {
    foreach ($_POST['quantities'] as $cartId => $qty) {

        $cartId = intval($cartId);
        $qty = max(1, intval($qty));

        // Get current stock
        $check = $conn->prepare("
            SELECT g.stock
            FROM cart c
            JOIN games g ON c.game_id = g.id
            WHERE c.id = ? AND c.user_id = ?
        ");
        $check->bind_param("ii", $cartId, $userId);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$result) continue;

        // Cap quantity to available stock
        if ($qty > $result['stock']) {
            $qty = $result['stock'];
        }

        // Remove item if stock is 0
        if ($qty <= 0) {
            removeFromCart($cartId, $userId);
            continue;
        }

        $stmt = $conn->prepare("
            UPDATE cart 
            SET quantity = ? 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("iii", $qty, $cartId, $userId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: cart.php");
    exit;
}

/* =========================
   FETCH CART ITEMS
========================= */
$items = getCartItems($userId);
$grandTotal = 0;
?>

<?php require_once '../includes/header.php'; ?>

<div class="container mt-4">
    <h2>🛒 Your Cart</h2>

    <?php if ($items->num_rows > 0): ?>
        <form method="post">
            <table class="table table-bordered text-white">
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Stock</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $items->fetch_assoc()):
                    $availableStock = (int)$row['stock'];

                    if ($availableStock <= 0) {
                        $hasOutOfStock = true;
                        $itemTotal = 0;
                    } else {
                        if ($row['quantity'] > $availableStock) {
                            $row['quantity'] = $availableStock;
                        }
                        $itemTotal = $row['price'] * $row['quantity'];
                        $grandTotal += $itemTotal;
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td>KES <?= number_format($row['price'], 2) ?></td>
                        <td style="max-width:90px;">
                            <?php if ($availableStock > 0): ?>
                                <input type="number"
                                       name="quantities[<?= $row['cart_id'] ?>]"
                                       value="<?= $row['quantity'] ?>"
                                       min="1"
                                       max="<?= $availableStock ?>"
                                       class="form-control">
                            <?php else: ?>
                                <span class="text-danger">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($availableStock > 0): ?>
                                <span class="badge bg-success"><?= $availableStock ?> left</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Out of stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($availableStock > 0): ?>
                                KES <?= number_format($itemTotal, 2) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="cart.php?remove=<?= $row['cart_id'] ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Remove this item from your cart?');">
                               Remove
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>Grand Total</strong></td>
                        <td colspan="2"><strong>KES <?= number_format($grandTotal, 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <div class="d-flex justify-content-between">
                <button type="submit" name="update_quantity" class="btn btn-primary">
                    Update Quantities
                </button>

                <?php if ($hasOutOfStock): ?>
                    <button class="btn btn-secondary" disabled>
                        Some items are out of stock
                    </button>
                <?php else: ?>
                    <a href="../pages/checkout.php" class="btn btn-success">
                        Proceed to Checkout
                    </a>
                <?php endif; ?>
            </div>
        </form>

    <?php else: ?>
        <p>Your cart is empty.</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
