<?php
require_once '../includes/header.php';
require_once '../includes/session.php';
require_once '../includes/config.php';

if ($_SESSION['role'] !== 'seller') {
    header('Location: ../index.php');
    exit;
}

$sellerId = $_SESSION['user_id'];

// --- Fetch seller's own games ---
$stmt = $conn->prepare("
    SELECT id, title, price, thumbnail, stock, created_at
    FROM games
    WHERE seller_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $sellerId);
$stmt->execute();
$myGames = $stmt->get_result();
$stmt->close();

// --- Fetch other sellers' games ---
$stmt2 = $conn->prepare("
    SELECT g.id, g.title, g.price, g.thumbnail, g.stock, g.created_at, u.username AS seller_name
    FROM games g
    JOIN users u ON g.seller_id = u.id
    WHERE g.seller_id != ?
    ORDER BY g.created_at DESC
    LIMIT 12
");
$stmt2->bind_param("i", $sellerId);
$stmt2->execute();
$otherGames = $stmt2->get_result();
$stmt2->close();
?>

<div class="container mt-5">
    <div class="card shadow-lg mb-4">
        <div class="card-body text-center">
            <h1 class="card-title mb-3">🎮 Seller Dashboard</h1>
            <p class="text-muted mb-4">Manage your games, monitor listings, and see other sellers' games.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="add_game.php" class="btn btn-primary btn-lg">➕ Add New Game</a>
                <a href="my_games.php" class="btn btn-outline-secondary btn-lg">🗂 View My Games</a>
            </div>
        </div>
    </div>

    <!-- My Games Section -->
    <h3 class="mb-3">🟢 Your Games</h3>
    <?php if ($myGames->num_rows > 0): ?>
        <div class="row mb-5">
            <?php while ($game = $myGames->fetch_assoc()): 
                $thumb = !empty($game['thumbnail']) && file_exists("../uploads/" . $game['thumbnail'])
                    ? "../uploads/" . $game['thumbnail']
                    : "../assets/img/default-game.png";
                $inStock = $game['stock'] > 0;
            ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 shadow-sm">
                        <img src="<?= $thumb ?>" class="card-img-top" style="height:180px; object-fit:cover;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                            <p><strong>KES <?= number_format($game['price'],2) ?></strong></p>
                            <span class="badge <?= $inStock ? 'bg-success' : 'bg-danger' ?> mb-2">
                                <?= $inStock ? "Stock: {$game['stock']}" : "Out of Stock" ?>
                            </span>
                            <small class="text-muted">Added: <?= date("M d, Y", strtotime($game['created_at'])) ?></small>
                            <a href="edit_game.php?id=<?= $game['id'] ?>" class="btn btn-outline-primary btn-sm mt-auto">Edit</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-muted">You have not added any games yet.</p>
    <?php endif; ?>

    <!-- Other Sellers Games Section -->
    <h3 class="mb-3">🟡 Games from Other Sellers</h3>
    <?php if ($otherGames->num_rows > 0): ?>
        <div class="row mb-5">
            <?php while ($game = $otherGames->fetch_assoc()): 
                $thumb = !empty($game['thumbnail']) && file_exists("../uploads/" . $game['thumbnail'])
                    ? "../uploads/" . $game['thumbnail']
                    : "../assets/img/default-game.png";
                $inStock = $game['stock'] > 0;
            ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 shadow-sm">
                        <img src="<?= $thumb ?>" class="card-img-top" style="height:180px; object-fit:cover;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                            <p><strong>KES <?= number_format($game['price'],2) ?></strong></p>
                            <span class="badge <?= $inStock ? 'bg-success' : 'bg-danger' ?> mb-2">
                                <?= $inStock ? "Stock: {$game['stock']}" : "Out of Stock" ?>
                            </span>
                            <small class="text-muted">Seller: <?= htmlspecialchars($game['seller_name']) ?></small>
                            <small class="text-muted d-block">Added: <?= date("M d, Y", strtotime($game['created_at'])) ?></small>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-muted">No games available from other sellers yet.</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
