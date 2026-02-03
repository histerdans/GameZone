<?php
require_once '../includes/header.php';
require_once '../includes/config.php';
require_once '../includes/session.php';

$gameId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$game = null;

// ✅ Fetch game details safely
$sql = "SELECT * FROM games WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $gameId);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
    $stmt->close();
} else {
    die("❌ SQL Error (game fetch): " . $conn->error);
}

// ✅ Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating']) && $game) {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $rating = intval($_POST['rating']);
        $review = trim($_POST['review']);

        // Check purchase
        $stmtCheck = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND game_id = ?");
        if ($stmtCheck) {
            $stmtCheck->bind_param("ii", $userId, $gameId);
            $stmtCheck->execute();
            $hasPaid = $stmtCheck->get_result()->num_rows > 0;
            $stmtCheck->close();

            if ($hasPaid) {
                $stmtR = $conn->prepare("
                    INSERT INTO ratings (game_id, user_id, rating, review)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        rating = VALUES(rating), 
                        review = VALUES(review), 
                        updated_at = NOW()
                ");
                if ($stmtR) {
                    $stmtR->bind_param("iiis", $gameId, $userId, $rating, $review);
                    $stmtR->execute();
                    $stmtR->close();
                }
            }
        }
    }
}

// ✅ Stats init
$rating = 0;
$ratingCount = 0;
$downloads = 0;
$plays = 0;

if ($game) {
    // Ratings
    $stmtR = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM ratings WHERE game_id = ?");
    if ($stmtR) {
        $stmtR->bind_param("i", $gameId);
        $stmtR->execute();
        $resR = $stmtR->get_result()->fetch_assoc();
        $rating = round($resR['avg_rating'] ?? 0, 1);
        $ratingCount = $resR['total'] ?? 0;
        $stmtR->close();
    }

    // Downloads
    $stmtD = $conn->prepare("SELECT COUNT(*) as total FROM downloads WHERE game_id = ?");
    if ($stmtD) {
        $stmtD->bind_param("i", $gameId);
        $stmtD->execute();
        $downloads = $stmtD->get_result()->fetch_assoc()['total'] ?? 0;
        $stmtD->close();
    }

    // Plays
    $stmtP = $conn->prepare("SELECT COUNT(*) as total FROM plays WHERE game_id = ?");
    if ($stmtP) {
        $stmtP->bind_param("i", $gameId);
        $stmtP->execute();
        $plays = $stmtP->get_result()->fetch_assoc()['total'] ?? 0;
        $stmtP->close();
    }
}

// ✅ User purchase check
$isLoggedIn = isset($_SESSION['user_id']);
$userHasPaid = false;

if ($isLoggedIn && $game) {
    $stmtC = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND game_id = ?");
    if ($stmtC) {
        $stmtC->bind_param("ii", $_SESSION['user_id'], $gameId);
        $stmtC->execute();
        $userHasPaid = $stmtC->get_result()->num_rows > 0;
        $stmtC->close();
    }
}
?>


<div class="container mt-5  text-dark">
    <?php if ($game): ?>
        <div class="card shadow-sm p-4 mb-4">
            <h2 class="mb-3"><?= htmlspecialchars($game['title']) ?></h2>

            <?php if (!empty($game['thumbnail'])): ?>
                <img src="../uploads/<?= htmlspecialchars($game['thumbnail']) ?>"
                     alt="<?= htmlspecialchars($game['title']) ?>"
                     class="img-fluid rounded mb-3"
                     style="max-width: 400px;">
            <?php endif; ?>

            <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($game['description'])) ?></p>
            <p><strong>Price:</strong> Ksh.<?= number_format($game['price'], 2) ?></p>

            <?php if (!empty($game['size'])): ?>
                <p><strong>Size:</strong> <?= htmlspecialchars($game['size']) ?></p>
            <?php endif; ?>

            <p><strong>Uploaded:</strong> <?= date('M d, Y', strtotime($game['created_at'])) ?></p>

            <!-- Rating & Stats -->
            <p>
                <strong>Rating:</strong>
                <?php
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= round($rating) ? "★" : "☆";
                }
                ?>
                (<?= $rating ?>/5 from <?= $ratingCount ?> reviews)
            </p>
            <p><strong>Downloads:</strong> <?= $downloads ?> | <strong>Played:</strong> <?= $plays ?> times</p>

            <!-- Download Button -->
            <p>
                <?php if ($isLoggedIn && $userHasPaid && !empty($game['file_path'])): ?>
                    <a href="../uploads/games/<?= htmlspecialchars($game['file_path']) ?>" 
                       class="btn btn-success" download>
                        ⬇️ Download Game
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>
                        ⛔ Download Locked
                        <?php if (!$isLoggedIn): ?>
                            (Login required)
                        <?php elseif (!$userHasPaid): ?>
                            (Payment required)
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </p>

            <!-- Rating Form -->
            <?php if ($isLoggedIn && $userHasPaid): ?>
                <div class="mt-4">
                    <h5>Leave a Rating</h5>
                    <form method="POST">
                        <div class="mb-2">
                            <label for="rating">Stars:</label>
                            <select name="rating" id="rating" class="form-select w-auto d-inline">
                                <option value="1">★☆☆☆☆ (1)</option>
                                <option value="2">★★☆☆☆ (2)</option>
                                <option value="3">★★★☆☆ (3)</option>
                                <option value="4">★★★★☆ (4)</option>
                                <option value="5">★★★★★ (5)</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <textarea name="review" class="form-control" rows="3" placeholder="Write your review..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit Rating</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Admin/Seller Controls -->
            <?php if (
                $isLoggedIn &&
                ($_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $game['seller_id'])
            ): ?>
                <div class="d-flex gap-2 mt-4">
                    <a href="edit_game.php?id=<?= $game['id'] ?>" class="btn btn-warning">
                        ✏️ Edit Game
                    </a>
                    <a href="delete_game.php?id=<?= $game['id'] ?>" class="btn btn-danger"
                       onclick="return confirm('Are you sure you want to delete this game? This action cannot be undone.')">
                        🗑️ Delete Game
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">⚠️ Game not found or was deleted.</div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>