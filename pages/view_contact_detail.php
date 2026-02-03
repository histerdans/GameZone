<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: view_contacts.php");
    exit;
}

$message_id = intval($_GET['id']);

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $stmt = $conn->prepare("UPDATE contacts SET status='read' WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    header("Location: view_contact_detail.php?id=$message_id&updated=1");
    exit;
}

// Handle delete
if (isset($_POST['delete'])) {
    $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    header("Location: view_contacts.php?deleted=1");
    exit;
}

// Fetch message
$stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();

require_once '../includes/header.php'; // include header AFTER handling redirects

if (!$message) {
    echo "<div class='alert alert-danger mt-4'>Message not found.</div>";
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="container mt-4">
    <h2 class="text-white">💬 Contact Message Detail</h2>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Message status updated.</div>
    <?php endif; ?>

    <div class="card bg-dark text-white p-4 shadow-sm">
        <p><strong>Name:</strong> <?= htmlspecialchars($message['name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($message['email']) ?></p>
        <p><strong>Phone:</strong> <?= htmlspecialchars($message['phone']) ?: 'N/A' ?></p>
        <p><strong>Subject:</strong> <?= htmlspecialchars($message['subject']) ?></p>
        <p><strong>Message:</strong><br><?= nl2br(htmlspecialchars($message['message'])) ?></p>
        <p><strong>Status:</strong> 
            <?php if($message['status'] === 'unread'): ?>
                <span class="badge bg-warning text-dark">Unread</span>
            <?php else: ?>
                <span class="badge bg-success">Read</span>
            <?php endif; ?>
        </p>
        <p><strong>Date:</strong> <?= date("M d, Y H:i", strtotime($message['created_at'])) ?></p>

        <form method="POST" class="d-inline">
            <?php if($message['status'] === 'unread'): ?>
                <button type="submit" name="mark_read" class="btn btn-sm btn-primary">Mark as Read</button>
            <?php endif; ?>
            <a href="mailto:<?= $message['email'] ?>?subject=Re: <?= urlencode($message['subject']) ?>" class="btn btn-sm btn-success">Reply</a>
            <button type="submit" name="delete" onclick="return confirm('Delete this message?')" class="btn btn-sm btn-danger">Delete</button>
        </form>
    </div>

    <a href="view_contacts.php" class="btn btn-secondary mt-3">← Back to Contact Messages</a>
</div>

<?php require_once '../includes/footer.php'; ?>
