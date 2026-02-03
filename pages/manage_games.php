<?php
require_once '../includes/header.php';
require_once '../includes/session.php';
require_once '../includes/config.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Fetch all games
$gameQuery = mysqli_query($conn, "SELECT * FROM games ORDER BY created_at DESC");
?>

<div class="container mt-4">
    <h2 class="text-white">🎮 Manage Games</h2>

    <div class="table-responsive">
        <table class="table table-hover table-dark align-middle text-white">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Price</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($game = mysqli_fetch_assoc($gameQuery)): ?>
                    <tr>
                        <td><?= $game['id'] ?></td>
                        <td><?= htmlspecialchars($game['title']) ?></td>
                        <td>$<?= number_format($game['price'], 2) ?></td>
                        <td><?= date("M d, Y", strtotime($game['created_at'])) ?></td>
                        <td>
                            <a href="edit_game.php?id=<?= $game['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="delete_game.php?id=<?= $game['id'] ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
