<?php
require_once '../includes/header.php';
require_once '../includes/config.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../pages/login.php");
    exit;
}

$sellerId = intval($_SESSION['user_id']);

/* ===============================
   CART GAMES (AGGREGATED)
================================ */
$cartQuery = "
    SELECT 
        g.id AS game_id,
        g.title,
        g.price,
        g.thumbnail,
        COUNT(c.id) AS cart_buyers
    FROM cart c
    JOIN games g ON c.game_id = g.id
    WHERE g.seller_id = ?
    GROUP BY g.id
    ORDER BY cart_buyers DESC
";
$stmt = $conn->prepare($cartQuery);
$stmt->bind_param("i", $sellerId);
$stmt->execute();
$cartGames = $stmt->get_result();

/* ===============================
   PURCHASED GAMES (AGGREGATED)
================================ */
$purchasedQuery = "
    SELECT 
        g.id AS game_id,
        g.title,
        g.price,
        g.thumbnail,
        COUNT(DISTINCT p.user_id) AS buyers_count,
        COUNT(d.id) AS download_count,
        (COUNT(DISTINCT p.user_id) * g.price) AS game_earnings
    FROM purchases p
    JOIN games g ON p.game_id = g.id
    LEFT JOIN downloads d ON d.game_id = g.id
    WHERE g.seller_id = ?
    GROUP BY g.id
    ORDER BY game_earnings DESC
";
$stmt2 = $conn->prepare($purchasedQuery);
$stmt2->bind_param("i", $sellerId);
$stmt2->execute();
$purchasedGames = $stmt2->get_result();

/* ===============================
   TOTAL EARNINGS
================================ */
$earningsQuery = "
    SELECT COALESCE(SUM(g.price),0) AS total_earnings
    FROM purchases p
    JOIN games g ON p.game_id = g.id
    WHERE g.seller_id = ?
";
$stmt3 = $conn->prepare($earningsQuery);
$stmt3->bind_param("i", $sellerId);
$stmt3->execute();
$totalEarnings = $stmt3->get_result()->fetch_assoc()['total_earnings'];
?>

<div class="container mt-4">
    <h2 class="mb-4">💼 Seller Checkout Dashboard</h2>

    <!-- TOTAL EARNINGS -->
    <div class="alert alert-success">
        <h4 class="mb-0">💰 Total Earnings: Ksh <?= number_format($totalEarnings, 2) ?></h4>
    </div>

    <!-- CART GAMES -->
    <div class="mb-5">
        <h3>🛒 Games in Cart (Pending)</h3>

        <?php if ($cartGames->num_rows > 0): ?>
            <div class="row mt-3">
                <?php while ($game = $cartGames->fetch_assoc()): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100 shadow-sm">
                            <img src="../uploads/<?= htmlspecialchars($game['thumbnail']) ?>"
                                 class="card-img-top"
                                 alt="<?= htmlspecialchars($game['title']) ?>">

                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>

                                <p class="mb-1"><strong>Price:</strong> Ksh <?= number_format($game['price'], 2) ?></p>
                                <p class="mb-1"><strong>In Carts:</strong> <?= intval($game['cart_buyers']) ?> buyers</p>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No games currently in carts.</p>
        <?php endif; ?>
    </div>

    <!-- PURCHASED GAMES -->
    <div>
        <h3>✅ Purchased Games</h3>

        <?php if ($purchasedGames->num_rows > 0): ?>
            <div class="row mt-3">
                <?php while ($game = $purchasedGames->fetch_assoc()): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card h-100 bg-dark text-white shadow-lg">
                            <img src="../uploads/<?= htmlspecialchars($game['thumbnail']) ?>"
                                 class="card-img-top"
                                 alt="<?= htmlspecialchars($game['title']) ?>">

                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>

                                <p class="mb-1"><strong>Price:</strong> Ksh <?= number_format($game['price'], 2) ?></p>
                                <p class="mb-1"><strong>Buyers:</strong> <?= intval($game['buyers_count']) ?></p>
                                <p class="mb-1"><strong>Downloads:</strong> <?= intval($game['download_count']) ?></p>

                                <hr class="bg-light">

                                <p class="mb-0 text-success fw-bold">
                                    💰 Earnings: Ksh <?= number_format($game['game_earnings'], 2) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No purchases yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
