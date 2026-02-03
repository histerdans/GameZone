<?php
require_once '../includes/header.php';
require_once '../includes/session.php';
require_once '../includes/config.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch registered users
$userQuery = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");

// Fetch games
$gameQuery = mysqli_query($conn, "SELECT * FROM games ORDER BY created_at DESC");

// Purchases (with usernames/games)
$purchaseQuery = mysqli_query($conn, "
    SELECT p.*, u.username, g.title, g.price 
    FROM purchases p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN games g ON p.game_id = g.id
    ORDER BY p.purchase_date DESC
");

// ADMIN PROFIT = 15% OF SALES
$profitQuery = mysqli_query($conn, "
    SELECT g.id, g.title, g.price,
           COUNT(p.id) AS total_purchases,
           (COUNT(p.id) * g.price) AS revenue,
           (COUNT(p.id) * g.price * 0.15) AS profit
    FROM games g
    LEFT JOIN purchases p ON g.id = p.game_id
    GROUP BY g.id
    ORDER BY g.created_at DESC
");

// Contacts
$contactQuery = mysqli_query($conn, "
    SELECT c.*, u.username 
    FROM contacts c 
    LEFT JOIN users u ON c.user_id = u.id 
    ORDER BY c.created_at DESC LIMIT 20
");
?>

<!-- JS for PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
.export-btn {
    float:right; 
    margin-bottom:10px;
}
</style>

<div class="container-fluid mt-4">
<div class="row">

<!-- CONTACTS -->
<div class="col-lg-3 mb-4">
    <h4 class="mb-3 text-white">💬 Recent Contact Messages</h4>
    <div class="card p-3 shadow-sm bg-dark text-white" style="height: 90vh; overflow-y: auto;">
        <?php if(mysqli_num_rows($contactQuery) > 0): ?>
            <?php while($contact = mysqli_fetch_assoc($contactQuery)): ?>
                <div class="mb-3 p-2 border rounded border-secondary">
                    <small class="text-light">
                        <?= htmlspecialchars($contact['username'] ?? "Guest") ?> 
                        - <?= date("M d, Y H:i", strtotime($contact['created_at'])) ?>
                    </small>
                    <p class="mb-1"><strong>Subject:</strong> <?= htmlspecialchars($contact['subject']) ?></p>
                    <p><?= nl2br(htmlspecialchars($contact['message'])) ?></p>
                </div>
                <hr class="border-secondary">
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-light">No messages yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- MAIN RIGHT COLUMN -->
<div class="col-lg-9">

<!-- Navigation -->
<div class="row mb-4">
    <div class="col-md-4 mb-2">
        <a href="#manageUsers" class="text-decoration-none">
            <div class="card text-center shadow-sm bg-primary text-white p-3">
                <h5>👥 Manage Users</h5>
                <p>Export, edit or delete users</p>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-2">
        <a href="#manageGames" class="text-decoration-none">
            <div class="card text-center shadow-sm bg-success text-white p-3">
                <h5>🎮 Manage Games</h5>
                <p>Export, add or edit games</p>
            </div>
        </a>
    </div>
    <div class="col-md-4 mb-2">
        <a href="#reports" class="text-decoration-none">
            <div class="card text-center shadow-sm bg-warning text-dark p-3">
                <h5>📊 Reports</h5>
                <p>Export purchases & profits</p>
            </div>
        </a>
    </div>
</div>

<!-- USERS TABLE -->
<div id="manageUsers" class="card p-4 shadow-sm mb-4">

    <button onclick="exportPDF('userTable','Users_Report')" class="btn btn-danger export-btn">🖨️ Export Users PDF</button>

    <h4 class="mb-3 text-dark">Registered Users</h4>

    <div class="table-responsive" id="userTable">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Username</th><th>Role</th><th>Created</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($userQuery)): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><span class="badge bg-secondary"><?= $user['role'] ?></span></td>
                    <td><?= date("M d, Y", strtotime($user['created_at'])) ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a onclick="return confirm('Delete user?')" 
                           href="delete_user.php?id=<?= $user['id'] ?>" 
                           class="btn btn-sm btn-danger">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- GAMES TABLE -->
<div id="manageGames" class="card p-4 shadow-sm mb-4">

    <button onclick="exportPDF('gameTable','Games_Report')" class="btn btn-danger export-btn">🖨️ Export Games PDF</button>

    <h4 class="mb-3 text-dark">Manage Games</h4>

    <div class="table-responsive" id="gameTable">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr><th>ID</th><th>Title</th><th>Price</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($game = mysqli_fetch_assoc($gameQuery)): ?>
                <tr>
                    <td><?= $game['id'] ?></td>
                    <td><?= htmlspecialchars($game['title']) ?></td>
                    <td>KES <?= number_format($game['price'], 2) ?></td>
                    <td><?= date("M d, Y", strtotime($game['created_at'])) ?></td>
                    <td>
                        <a href="edit_game.php?id=<?= $game['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a onclick="return confirm('Delete game?')" href="delete_game.php?id=<?= $game['id'] ?>" class="btn btn-sm btn-danger">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PURCHASE REPORT -->
<div id="reports" class="card p-4 shadow-sm mb-4">

    <button onclick="exportPDF('purchaseTable','Purchases_Report')" class="btn btn-danger export-btn">🖨️ Export Purchases PDF</button>

    <h4 class="mb-3 text-dark">Recent Purchases</h4>

    <div class="table-responsive" id="purchaseTable">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr><th>ID</th><th>User</th><th>Game</th><th>Price</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php while ($purchase = mysqli_fetch_assoc($purchaseQuery)): ?>
                <tr>
                    <td><?= $purchase['id'] ?></td>
                    <td><?= htmlspecialchars($purchase['username']) ?></td>
                    <td><?= htmlspecialchars($purchase['title']) ?></td>
                    <td>KES <?= number_format($purchase['price'], 2) ?></td>
                    <td><?= date("M d, Y H:i", strtotime($purchase['purchase_date'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PROFIT REPORT -->
<div id="profitReport" class="card p-4 shadow-sm mb-4">

    <button onclick="exportPDF('profitTable','Profit_Report')" class="btn btn-danger export-btn">🖨️ Export Profits PDF</button>

    <h4 class="mb-3 text-dark">Revenue & Profit (15%)</h4>

    <div class="table-responsive" id="profitTable">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Game</th><th>Price</th><th>Purchases</th><th>Revenue</th><th>Profit (15%)</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($profitQuery)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td>KES <?= number_format($row['price'], 2) ?></td>
                    <td><?= $row['total_purchases'] ?></td>
                    <td>KES <?= number_format($row['revenue'], 2) ?></td>
                    <td>KES <?= number_format($row['profit'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- RIGHT COLUMN END -->
</div><!-- ROW END -->
</div><!-- CONTAINER END -->

<script>
function exportPDF(sectionId, filename) {
    let element = document.getElementById(sectionId);

    let opt = {
        margin: 0.5,
        filename: filename + "_" + new Date().toISOString().slice(0,19).replace(/:/g,"-") + ".pdf",
        html2canvas: { scale: 2, useCORS:true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };

    html2pdf().set(opt).from(element).save();
}
</script>

<?php require_once '../includes/footer.php'; ?>
