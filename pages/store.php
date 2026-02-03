<?php
// store.php — Game Store (Purchased Hidden + Stock Control)

require_once '../includes/header.php';
require_once '../includes/config.php';
require_once '../includes/session.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$currentUser = (int) $_SESSION['user_id'];
$userRole    = $_SESSION['role'] ?? 'buyer';

// ================= SETTINGS =================
$gamesPerPage = 12;
$page   = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $gamesPerPage;

// ================= SORTING ==================
$allowedSorts = ['new', 'rating', 'downloads'];
$sort = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts)) ? $_GET['sort'] : 'new';

switch ($sort) {
    case 'rating':
        $orderBy = "rating DESC, g.created_at DESC";
        break;
    case 'downloads':
        $orderBy = "downloads_count DESC, g.created_at DESC";
        break;
    default:
        $orderBy = "g.created_at DESC";
}

// ================= SEARCH ===================
$where   = [];
$params  = [];
$types   = "";

$searchKeyword = '';
if (!empty($_GET['search'])) {
    $searchKeyword = trim($_GET['search']);
    $where[]  = "g.title LIKE ?";
    $params[] = "%{$searchKeyword}%";
    $types   .= "s";
}

$whereSQL = count($where) ? "AND " . implode(" AND ", $where) : "";

// =================================================
// MAIN QUERY — Hide purchased + include stock
// =================================================
$sql = "
    SELECT 
        g.id,
        g.title,
        g.price,
        g.thumbnail,
        g.stock,
        g.downloads AS downloads_count,
        g.created_at,
        COALESCE(AVG(r.rating), 0) AS rating,
        COUNT(r.id) AS rating_count
    FROM games g
    LEFT JOIN purchases p 
        ON g.id = p.game_id AND p.user_id = ?
    LEFT JOIN ratings r 
        ON g.id = r.game_id
    WHERE p.id IS NULL
    $whereSQL
    GROUP BY g.id
    ORDER BY $orderBy
    LIMIT ?, ?
";

$bindParams = array_merge([$currentUser], $params, [$offset, $gamesPerPage]);
$bindTypes  = "i" . $types . "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$result = $stmt->get_result();

// ================= PAGINATION COUNT =================
$countSql = "
    SELECT COUNT(DISTINCT g.id)
    FROM games g
    LEFT JOIN purchases p 
        ON g.id = p.game_id AND p.user_id = ?
    WHERE p.id IS NULL
    $whereSQL
";

$countParams = array_merge([$currentUser], $params);
$countTypes  = "i" . $types;

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$countStmt->bind_result($totalGames);
$countStmt->fetch();
$countStmt->close();

$totalPages = max(1, ceil($totalGames / $gamesPerPage));
?>

<style>
body {
    background: url('../assets/img/bg-store.png') no-repeat center center fixed;
    background-size: cover;
    color: #fff;
}
body::before {
    content: "";
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: -1;
}
.card { border-radius: 12px; overflow: hidden; }
.card img { height: 180px; object-fit: cover; width: 100%; }
.card-body { background: rgba(0,0,0,.75); }
.badge-stock { font-size: 0.85rem; }
</style>

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-4">
        <h2>🕹️ Game Store</h2>
        <?php if ($userRole === 'seller'): ?>
            <a href="report.php" class="btn btn-dark">📊 Sales Report</a>
        <?php endif; ?>
    </div>

    <!-- FILTERS -->
    <form class="row g-2 bg-dark p-3 rounded mb-4" method="get">
        <div class="col-auto">
            <input type="text" name="search"
                   value="<?= htmlspecialchars($searchKeyword) ?>"
                   class="form-control"
                   placeholder="Search games">
        </div>
        <div class="col-auto">
            <select name="sort" class="form-select">
                <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top Rated</option>
                <option value="downloads" <?= $sort === 'downloads' ? 'selected' : '' ?>>Most Downloaded</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-warning">Apply</button>
        </div>
    </form>

    <?php if ($result->num_rows > 0): ?>
        <div class="row">

        <?php while ($game = $result->fetch_assoc()): ?>
            <?php
                $stock    = max(0, (int)$game['stock']);
                $inStock  = $stock > 0;

                $thumb = "../uploads/" . $game['thumbnail'];
                if (empty($game['thumbnail']) || !file_exists($thumb)) {
                    $thumb = "../assets/img/default-game.png";
                }
            ?>

            <div class="col-md-3 mb-4">
                <div class="card h-100">

                    <img src="<?= $thumb ?>" alt="<?= htmlspecialchars($game['title']) ?>">

                    <div class="card-body d-flex flex-column">
                        <h5><?= htmlspecialchars($game['title']) ?></h5>

                        <p><strong>KES <?= number_format($game['price'], 2) ?></strong></p>

                        <span class="badge <?= $inStock ? 'bg-success' : 'bg-danger' ?> badge-stock mb-2">
                            <?= $inStock ? "Stock: {$stock} left" : "Out of Stock" ?>
                        </span>

                        <p class="mb-1">
                            ⭐ <?= number_format($game['rating'], 1) ?>
                            (<?= (int)$game['rating_count'] ?>)
                        </p>

                        <small class="text-muted mb-3">
                            <?= date("M d, Y", strtotime($game['created_at'])) ?>
                        </small>

                        <?php if ($inStock): ?>
                            <a href="add_to_cart.php?game_id=<?= (int)$game['id'] ?>"
                               class="btn btn-warning btn-sm mt-auto">
                                Add to Cart
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm mt-auto" disabled>
                                Unavailable
                            </button>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        <?php endwhile; ?>

        </div>

        <!-- PAGINATION -->
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>

    <?php else: ?>
        <p class="text-center">🎉 No games available</p>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
