<?php
require_once '../includes/session.php';
require_once '../includes/config.php';
require_once '../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}

$user = $result->fetch_assoc();
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    // Prevent editing own role
    if ($user_id === $admin_id) {
        $role = 'admin'; // Prevent downgrade
    }

    $update = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
    $update->bind_param("sssi", $username, $email, $role, $user_id);

    if ($update->execute()) {
        $message = "<div class='alert alert-success'>✅ User updated successfully!</div>";

        // Refresh data
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $message = "<div class='alert alert-danger'>❌ Failed to update user.</div>";
    }
}
?>

<div class="container mt-5">
    <h2 class="mb-4 text-center">✏️ Edit User</h2>
    <?= $message ?>

    <form method="POST" class="card p-4 shadow-sm mx-auto" style="max-width: 600px;">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" <?= $user_id === $admin_id ? 'disabled' : '' ?>>
                <option value="buyer" <?= $user['role'] === 'buyer' ? 'selected' : '' ?>>Buyer</option>
                <option value="seller" <?= $user['role'] === 'seller' ? 'selected' : '' ?>>Seller</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <?php if ($user_id === $admin_id): ?>
                <input type="hidden" name="role" value="admin">
                <div class="form-text text-muted">You cannot change your own role.</div>
            <?php endif; ?>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
