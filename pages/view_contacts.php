<?php
require_once '../includes/header.php'; // includes session and config

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Handle delete action
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: view_contacts.php?deleted=1");
    exit;
}

// Fetch all messages
$stmt = $conn->prepare("SELECT * FROM contacts ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h2 class="mb-4 text-white">💬 Contact Messages</h2>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Message deleted successfully.</div>
    <?php endif; ?>

    <?php if (empty($messages)): ?>
        <div class="alert alert-info">No messages found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered table-dark align-middle text-white">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?= $msg['id'] ?></td>
                            <td><?= htmlspecialchars($msg['name']) ?></td>
                            <td><?= htmlspecialchars($msg['email']) ?></td>
                            <td><?= htmlspecialchars($msg['subject']) ?></td>
                            <td>
                                <?php if($msg['status'] === 'unread'): ?>
                                    <span class="badge bg-warning text-dark">Unread</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Read</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date("d M Y H:i", strtotime($msg['created_at'])) ?></td>
                            <td>
                                <a href="view_contact_detail.php?id=<?= $msg['id'] ?>" class="btn btn-sm btn-info">View</a>
                                <a href="mailto:<?= $msg['email'] ?>?subject=Re: <?= urlencode($msg['subject']) ?>" class="btn btn-sm btn-success">Reply</a>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this message?')">
                                    <input type="hidden" name="delete_id" value="<?= $msg['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
