<?php
require_once '../includes/session.php';
require_once '../functions/contact.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: application/json');
$contacts = getContacts(50); // Fetch latest messages
echo json_encode($contacts);
8