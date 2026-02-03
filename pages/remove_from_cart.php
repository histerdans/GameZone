<?php
require_once '../includes/session.php';
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php');
    exit;
}

$buyer_id = $_SESSION['user_id'];
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;

$query = "DELETE FROM cart WHERE buyer_id = ? AND game_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $buyer_id, $game_id);
$stmt->execute();

header("Location: cart.php");
exit;
