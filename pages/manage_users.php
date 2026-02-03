<?php
require_once '../includes/header.php';
require_once '../includes/session.php';
require_once '../includes/config.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all users
$userQuery = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
?>

<div class="container mt-4">
    <h2 class="text-white">👥 Manage Users</h2>
    <div class="table-responsive mt-3">
        <table class="table table-hover table-dark align-middle text-white">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($userQuery)): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><span class="badge bg-secondary"><?= $user['role'] ?></span></td>
                        <td><?= date("M d, Y", strtotime($user['created_at'])) ?></td>
                        <td>
                            <?php if ($user['id'] != $user_id): ?>
                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="delete_user.php?id=<?= $user['id'] ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger">Delete</a>
                            <?php else: ?>
                                <span class="text-light">Current Admin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
