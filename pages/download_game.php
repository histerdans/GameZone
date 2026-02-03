<?php
require_once '../includes/config.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: ../index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$gameId = intval($_GET['id'] ?? 0);

// ✅ Verify buyer owns the game
$stmt = $conn->prepare("
    SELECT g.file_path 
    FROM purchases p
    JOIN games g ON p.game_id = g.id
    WHERE p.user_id = ? AND g.id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $userId, $gameId);
$stmt->execute();
$result = $stmt->get_result();
$game = $result->fetch_assoc();

if (!$game || empty($game['file_path'])) {
    die("⚠️ Invalid game or file not found.");
}

// ✅ Log download
$log = $conn->prepare("INSERT INTO downloads (user_id, game_id) VALUES (?, ?)");
$log->bind_param("ii", $userId, $gameId);
$log->execute();

// ✅ Serve the file
$filePath = "../uploads/games/" . $game['file_path'];
if (file_exists($filePath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    flush();
    readfile($filePath);
    exit;
} else {
    die("⚠️ Game file not found on server.");
}
?>
