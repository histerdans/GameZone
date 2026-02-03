<?php
require_once '../includes/header.php';
require_once '../includes/config.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: ../pages/login.php");
    exit;
}

$userId = intval($_SESSION['user_id']);

// Get search/filter inputs
$searchTitle = isset($_GET['title']) ? trim($_GET['title']) : '';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Build query dynamically
$query = "
    SELECT g.id AS game_id, g.title, g.thumbnail, g.description, g.file_size, p.purchase_date
    FROM purchases p
    JOIN games g ON p.game_id = g.id
    WHERE p.user_id = ?
";

$params = [$userId];
$types = "i";

if ($searchTitle !== '') {
    $query .= " AND g.title LIKE ?";
    $types .= "s";
    $params[] = "%$searchTitle%";
}

if ($startDate !== '') {
    $query .= " AND p.purchase_date >= ?";
    $types .= "s";
    $params[] = $startDate . " 00:00:00";
}

if ($endDate !== '') {
    $query .= " AND p.purchase_date <= ?";
    $types .= "s";
    $params[] = $endDate . " 23:59:59";
}

$query .= " ORDER BY p.purchase_date DESC";

$stmt = $conn->prepare($query);
if (!$stmt) die("Prepare failed: " . $conn->error);

// Bind params dynamically
$stmt->bind_param($types, ...$params);
$stmt->execute();
$purchases = $stmt->get_result();
$stmt->close();
?>

<div class="container mt-4">
    <h2>My Purchased Games</h2>

    <form class="row g-3 mb-4" method="get">
        <div class="col-md-6">
            <input type="text" name="title" class="form-control" placeholder="Search by game title" value="<?= htmlspecialchars($searchTitle) ?>">
        </div>
        <div class="col-md-3">
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="col-md-3">
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        </div>
    </form>

    <?php if ($purchases->num_rows > 0): ?>
        <div class="row mt-3">
            <?php while ($p = $purchases->fetch_assoc()): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 shadow-sm bg-dark text-white game-card" 
                         title="<?= htmlspecialchars($p['description']) ?>"> <!-- Full description tooltip -->
                        <img src="<?= !empty($p['thumbnail']) ? '../uploads/' . htmlspecialchars($p['thumbnail']) : '../assets/img/default-game.png' ?>" 
                             class="card-img-top" 
                             alt="<?= htmlspecialchars($p['title']) ?>" style="height:150px; object-fit: cover;">
                        <div class="card-body d-flex flex-column justify-content-between">
                            <h5 class="card-title"><?= htmlspecialchars($p['title']) ?></h5>
                            <p class="mb-1"><small>
                                <?= htmlspecialchars(mb_strimwidth($p['description'], 0, 50, "...")) ?>
                            </small></p>
                            <p class="mb-1"><small>File Size: <?= htmlspecialchars($p['file_size']) ?></small></p>
                            <p class="mb-1"><small>Purchased: <?= date("F j, Y", strtotime($p['purchase_date'])) ?></small></p>
                            <div class="mt-2">
                                <?php if (!empty($p['file_size'])): ?>
                                    <a href="../pages/play_game.php?id=<?= $p['game_id'] ?>" class="btn btn-success btn-sm w-100">
                                        ▶ Play / Download
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-secondary w-100 text-center">No file available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No purchases found.</p>
    <?php endif; ?>

    <a href="buyer_dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
</div>

<style>
.game-card:hover {
    transform: scale(1.03);
    transition: transform 0.2s ease-in-out;
    cursor: pointer;
}
</style>

<?php require_once '../includes/footer.php'; ?>
