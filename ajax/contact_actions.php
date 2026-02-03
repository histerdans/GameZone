<?php
require_once '../includes/session.php';
require_once '../functions/contact.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['status'=>'error','message'=>'Invalid ID']);
    exit;
}

if ($action === 'delete') {
    $result = deleteContact($id);
    echo json_encode(['status'=>$result ? 'success' : 'error']);
} elseif ($action === 'read') {
    $result = markAsRead($id);
    echo json_encode(['status'=>$result ? 'success' : 'error']);
} else {
    echo json_encode(['status'=>'error','message'=>'Invalid action']);
}
