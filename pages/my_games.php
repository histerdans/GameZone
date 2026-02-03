<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'seller') {
    header('Location: ../index.php');
    exit;
}

$seller_id = $_SESSION['user_id'];

// Fetch games uploaded by this seller
$query = "
    SELECT g.*, 
           (SELECT COUNT(*) FROM purchases p WHERE p.game_id = g.id) AS downloads,
           (SELECT AVG(r.rating) FROM ratings r WHERE r.game_id = g.id) AS avg_rating,
           (SELECT COUNT(*) FROM ratings r WHERE r.game_id = g.id) AS total_reviews,
           (SELECT COUNT(*) FROM purchases p WHERE p.game_id = g.id) AS buyers_count
    FROM games g 
    WHERE g.seller_id = ? 
    ORDER BY g.created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();

// Function to render star rating
function renderStars($rating) {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars >= 0.5) ? 1 : 0;
    $emptyStars = 5 - $fullStars - $halfStar;

    $stars = str_repeat("⭐", $fullStars);
    if ($halfStar) $stars .= "⭐½";
    $stars .= str_repeat("☆", $emptyStars);

    return $stars;
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    if ($bytes > 1) return $bytes . ' bytes';
    if ($bytes == 1) return '1 byte';
    return '0 bytes';
}
?>

<div class="container mt-5">
    <h2 class="mb-4 text-center text-light">🗂 My Uploaded Games</h2>

    <div class="text-end mb-3">
        <button class="btn btn-primary" id="toggleViewBtn">Switch to Table View</button>
    </div>

    <style>
        /* Dark table styling */
        #tableView table {
            background-color: #121212;
            color: #e0e0e0;
            border-color: #2a2a2a;
        }
        #tableView thead {
            background-color: #1e1e1e;
        }
        #tableView tbody tr:hover {
            background-color: #2c2c2c;
            color: #fff !important;
            transition: 0.2s ease-in-out;
        }
        #tableView td, #tableView th {
            vertical-align: middle;
        }
        body {
            background-color: #0d0d0d;
        }
        .card-text {
            font-size: 0.9rem;
        }
        .card-title {
            font-size: 1.1rem;
        }
    </style>

    <?php if ($result->num_rows > 0): ?>
        <!-- Card View -->
        <div id="cardView" class="row row-cols-1 row-cols-md-3 g-4">
            <?php while ($game = $result->fetch_assoc()): ?>
                <?php
                $fileSizeFormatted = formatFileSize($game['file_size']);
                $avgRating = $game['avg_rating'] ?? 0;
                $totalReviews = $game['total_reviews'] ?? 0;
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <img src="../uploads/<?= htmlspecialchars($game['thumbnail']) ?>" 
                             class="card-img-top" 
                             alt="<?= htmlspecialchars($game['title']) ?>" 
                             style="height: 200px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars(substr($game['description'], 0, 80)) ?>...</p>
                            <p class="mb-1"><strong>Size:</strong> <?= $fileSizeFormatted ?></p>
                            <p class="mb-1"><strong>Sold:</strong> <?= $game['downloads'] ?></p>
                            <p class="mb-2"><strong>Rating:</strong> <?= renderStars($avgRating) ?> 
                                (<?= number_format($avgRating, 1) ?>/5 from <?= $totalReviews ?> reviews)
                            </p>
                            <p class="text-muted"><small>Uploaded: <?= date('M d, Y', strtotime($game['created_at'])) ?></small></p>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <a href="edit_game.php?id=<?= $game['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="delete_game.php?id=<?= $game['id'] ?>" 
                               onclick="return confirm('Are you sure you want to delete this game?');" 
                               class="btn btn-sm btn-danger">Delete</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Table View -->
        <div id="tableView" class="table-responsive d-none mt-4">
            <table class="table table-bordered table-hover align-middle text-light">
                <thead class="table-dark">
                    <tr>
                        <th>Thumbnail</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Size</th>
                        <th>Sold</th>
                        <th>Rating</th>
                        <th>Uploaded</th>
                        <th>Buyers</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($game = $result->fetch_assoc()):
                        $fileSizeFormatted = formatFileSize($game['file_size']);
                        $avgRating = $game['avg_rating'] ?? 0;
                        $totalReviews = $game['total_reviews'] ?? 0;
                    ?>
                        <tr>
                            <td><img src="../uploads/<?= htmlspecialchars($game['thumbnail']) ?>" 
                                     alt="<?= htmlspecialchars($game['title']) ?>" 
                                     style="width: 80px; height: 80px; object-fit: cover;"></td>
                            <td><?= htmlspecialchars($game['title']) ?></td>
                            <td><?= htmlspecialchars(substr($game['description'], 0, 50)) ?>...</td>
                            <td><?= $fileSizeFormatted ?></td>
                            <td><?= $game['downloads'] ?></td>
                            <td>
                                <?= renderStars($avgRating) ?><br>
                                <small>(<?= number_format($avgRating, 1) ?>/5 from <?= $totalReviews ?> reviews)</small>
                            </td>
                            <td><?= date('M d, Y', strtotime($game['created_at'])) ?></td>
                            <td>
                                <span class="fw-bold"><?= $game['buyers_count'] ?></span>
                            </td>
                            <td>
                                <a href="edit_game.php?id=<?= $game['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="delete_game.php?id=<?= $game['id'] ?>" 
                                   onclick="return confirm('Are you sure you want to delete this game?');" 
                                   class="btn btn-sm btn-danger">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center">📭 You haven’t uploaded any games yet.</div>
    <?php endif; ?>
</div>

<script>
document.getElementById('toggleViewBtn').addEventListener('click', function () {
    let cardView = document.getElementById('cardView');
    let tableView = document.getElementById('tableView');
    if (cardView.classList.contains('d-none')) {
        cardView.classList.remove('d-none');
        tableView.classList.add('d-none');
        this.textContent = "Switch to Table View";
    } else {
        cardView.classList.add('d-none');
        tableView.classList.remove('d-none');
        this.textContent = "Switch to Card View";
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
