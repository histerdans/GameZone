<?php
// report.php — Corrected Version with Independent Purchase & Download Counts
require_once '../includes/header.php';
require_once '../includes/config.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$userRole = $role; // from header.php
$printedBy = $userFullName;
$reportDateTime = date("l, d M Y H:i");

// --- Filters ---
$from_date = $_GET['from_date'] ?? "";
$to_date   = $_GET['to_date'] ?? "";
$selected_seller = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;

// --- Seller List for Admin ---
$sellers = [];
if ($userRole === 'admin') {
    $rs = $conn->query("SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM users WHERE role='seller'");
    while ($s = $rs->fetch_assoc()) $sellers[] = $s;
}

// =============================================
// MAIN GAME REPORT QUERY (PER GAME)
// Purchases and Downloads are counted separately
// =============================================

$query = "
    SELECT
        g.id AS game_id,
        g.title AS game_name,
        g.price AS unit_price,
        g.created_at,
        u.id AS seller_id,
        CONCAT(u.first_name,' ',u.last_name) AS seller_name,
        COUNT(DISTINCT p.id) AS total_purchases,
        COUNT(DISTINCT d.id) AS total_downloads
    FROM games g
    LEFT JOIN purchases p 
        ON g.id = p.game_id 
        AND ('{$from_date}' = '' OR '{$to_date}' = '' OR DATE(p.purchase_date) BETWEEN '{$from_date}' AND '{$to_date}')
    LEFT JOIN downloads d 
        ON g.id = d.game_id
        AND ('{$from_date}' = '' OR '{$to_date}' = '' OR DATE(d.download_time) BETWEEN '{$from_date}' AND '{$to_date}')
    LEFT JOIN users u ON g.seller_id = u.id
    WHERE 1
";

$params = [];
$types = "";

// --- Seller options ---
if ($userRole === 'seller') {
    $query .= " AND g.seller_id = ?";
    $params[] = $user_id;
    $types .= "i";
} elseif ($userRole === 'admin' && $selected_seller > 0) {
    $query .= " AND g.seller_id = ?";
    $params[] = $selected_seller;
    $types .= "i";
}

$query .= "
    GROUP BY g.id
    ORDER BY g.created_at DESC
";

$stmt = $conn->prepare($query);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// =============================================
// SUMMARY QUERY (CORRECTED)
// Independent purchase/download counts
// =============================================

$summaryQuery = "
    SELECT 
        COUNT(DISTINCT g.id) AS total_games,
        COUNT(DISTINCT p.id) AS total_purchases,
        COUNT(DISTINCT d.id) AS total_downloads
    FROM games g
    LEFT JOIN purchases p 
        ON g.id = p.game_id
        AND ('{$from_date}' = '' OR '{$to_date}' = '' OR DATE(p.purchase_date) BETWEEN '{$from_date}' AND '{$to_date}')
    LEFT JOIN downloads d 
        ON g.id = d.game_id
        AND ('{$from_date}' = '' OR '{$to_date}' = '' OR DATE(d.download_time) BETWEEN '{$from_date}' AND '{$to_date}')
    WHERE 1
";

if ($userRole === 'seller') {
    $summaryQuery .= " AND g.seller_id = {$user_id}";
} elseif ($userRole === 'admin' && $selected_seller > 0) {
    $summaryQuery .= " AND g.seller_id = {$selected_seller}";
}

$summary = $conn->query($summaryQuery)->fetch_assoc();

$totalGames     = $summary['total_games'] ?? 0;
$totalPurchases = $summary['total_purchases'] ?? 0;
$totalDownloads = $summary['total_downloads'] ?? 0;

// =============================================
// CALCULATE REVENUE & PROFIT
// =============================================

$totalRevenue = 0;

$revSQL = "
    SELECT g.price, COUNT(p.id) AS purchases
    FROM games g
    LEFT JOIN purchases p ON g.id = p.game_id
    WHERE 1
";

if ($userRole === 'seller') {
    $revSQL .= " AND g.seller_id = {$user_id}";
} elseif ($userRole === 'admin' && $selected_seller > 0) {
    $revSQL .= " AND g.seller_id = {$selected_seller}";
}

$revSQL .= " GROUP BY g.id";

$res = $conn->query($revSQL);
while ($r = $res->fetch_assoc()) {
    $totalRevenue += ($r['price'] * $r['purchases']);
}

$totalProfit = $totalRevenue * 0.15;

?>

<style>
.card h3 { font-size: 32px; font-weight: bold; }
</style>

<div class="container mt-5">
    <div class="d-flex justify-content-between mb-4">
        <h2 class="text-warning">
            <?= $userRole === 'admin' ? "📊 Admin Profit & Sales Report" : "📈 Seller Sales Report" ?>
        </h2>
        <a href="<?= $userRole === 'admin' ? 'admin_dashboard.php' : 'seller_dashboard.php' ?>" 
           class="btn btn-secondary">⬅ Back</a>
    </div>

    <!-- Summary Cards -->
    <div class="row text-center mb-4">

        <?php if ($userRole === 'admin'): ?>
        <div class="col-md-3 mb-3">
            <div class="card bg-dark text-info border border-info shadow">
                <div class="card-body">
                    <h5>🎮 Total Games</h5>
                    <h3><?= number_format($totalGames) ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-md-3 mb-3">
            <div class="card bg-dark text-primary border border-primary shadow">
                <div class="card-body">
                    <h5>⬇️ Total Downloads</h5>
                    <h3><?= number_format($totalDownloads) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card bg-dark text-success border border-success shadow">
                <div class="card-body">
                    <h5>🧾 Total Purchases</h5>
                    <h3><?= number_format($totalPurchases) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card bg-dark text-warning border border-warning shadow">
                <div class="card-body">
                    <h5>💰 Total Revenue</h5>
                    <h3>KES <?= number_format($totalRevenue, 2) ?></h3>
                </div>
            </div>
        </div>

        <?php if ($userRole === 'admin'): ?>
        <div class="col-md-3 mb-3">
            <div class="card bg-dark text-danger border border-danger shadow">
                <div class="card-body">
                    <h5>📈 Total Profit (15%)</h5>
                    <h3>KES <?= number_format($totalProfit, 2) ?></h3>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Filters -->
    <div class="bg-dark p-3 rounded mb-4">
        <form class="row g-3" method="GET">

            <?php if ($userRole === 'admin'): ?>
            <div class="col-md-4">
                <label class="text-white">Seller:</label>
                <select name="seller_id" class="form-select">
                    <option value="">-- All Sellers --</option>
                    <?php foreach ($sellers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selected_seller == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-3">
                <label class="text-white">From:</label>
                <input type="date" name="from_date" value="<?= $from_date ?>" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="text-white">To:</label>
                <input type="date" name="to_date" value="<?= $to_date ?>" class="form-control">
            </div>

            <div class="col-md-2">
                <button class="btn btn-warning w-100">Filter</button>
            </div>

        </form>
    </div>

    <div class="text-end mb-3">
        <button class="btn btn-success" onclick="printPDF()">🖨️ Export PDF</button>
    </div>

    <!-- Report Table -->
    <div id="reportArea" class="bg-light text-dark p-4 rounded shadow">

        <div class="text-center mb-3 pb-3 border-bottom">
            <img src="../assets/img/logoh.png" style="height:70px"><br>
            <h3><?= $userRole === 'admin' ? "GameSphere Admin Report" : "GameSphere Seller Report" ?></h3>
            <p>
                Printed by: <?= $printedBy ?><br>
                Date: <?= $reportDateTime ?>
            </p>
        </div>

        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <?php if ($userRole === 'admin'): ?><th>Seller</th><?php endif; ?>
                    <th>Game</th>
                    <th>Uploaded</th>
                    <th>Unit Price</th>
                    <th>Downloads</th>
                    <th>Purchases</th>
                    <th>Revenue</th>
                    <?php if ($userRole === 'admin'): ?><th>Profit</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 1;
                $grandRevenue = 0;
                $grandProfit = 0;

                while ($row = $result->fetch_assoc()):
                    $revenue = $row['total_purchases'] * $row['unit_price'];
                    $profit = $revenue * 0.15;
                    $grandRevenue += $revenue;
                    $grandProfit += $profit;
                ?>
                <tr>
                    <td><?= $count++ ?></td>
                    <?php if ($userRole === 'admin'): ?>
                        <td><?= htmlspecialchars($row['seller_name']) ?></td>
                    <?php endif; ?>

                    <td><?= htmlspecialchars($row['game_name']) ?></td>
                    <td><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
                    <td><?= number_format($row['unit_price'], 2) ?></td>
                    <td><?= $row['total_downloads'] ?></td>
                    <td><?= $row['total_purchases'] ?></td>
                    <td><?= number_format($revenue, 2) ?></td>

                    <?php if ($userRole === 'admin'): ?>
                        <td><?= number_format($profit, 2) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>

            <tfoot class="table-secondary">
                <tr>
                    <td colspan="<?= $userRole === 'admin' ? 7 : 6 ?>" class="text-end"><strong>Total Revenue:</strong></td>
                    <td><strong><?= number_format($grandRevenue, 2) ?> KES</strong></td>

                    <?php if ($userRole === 'admin'): ?>
                        <td><strong><?= number_format($grandProfit, 2) ?> KES</strong></td>
                    <?php endif; ?>
                </tr>
            </tfoot>
        </table>

        <p class="text-center small text-muted mt-2">
            Generated by GameSphere © <?= date("Y") ?> — Confidential
        </p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function printPDF() {
    html2pdf().from(document.getElementById('reportArea')).set({
        margin: 0.4,
        filename: 'GameSphere_Report_<?= date("Ymd_His") ?>.pdf',
        html2canvas: { scale: 2 },
        jsPDF: { orientation: 'landscape' }
    }).save();
}
</script>

<?php require_once '../includes/footer.php'; ?>
