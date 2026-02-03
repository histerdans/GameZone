<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Prevent self-deletion
if ($user_id === $admin_id) {
    die("⚠️ You cannot delete your own admin account.");
}

// Check if user exists
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("❌ User not found.");
}

$user = $result->fetch_assoc();

// Delete user
$delete = $conn->prepare("DELETE FROM users WHERE id = ?");
$delete->bind_param("i", $user_id);

if ($delete->execute()) {
    header("Location: dashboard.php?msg=User+deleted+successfully");
    exit;
} else {
    die("❌ Failed to delete user.");
}
