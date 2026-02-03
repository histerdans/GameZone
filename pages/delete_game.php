<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];
$game_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ensure the game belongs to the seller
$check = $conn->prepare("SELECT * FROM games WHERE id = ? AND seller_id = ?");
$check->bind_param("ii", $game_id, $seller_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    die("Access denied or game not found.");
}

// Delete the game
$delete = $conn->prepare("DELETE FROM games WHERE id = ?");
$delete->bind_param("i", $game_id);
$delete->execute();

header("Location: my_games.php");
exit;
