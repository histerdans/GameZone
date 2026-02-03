<?php
require_once __DIR__ . '/../includes/config.php'; // Make sure DB connection is available

/**
 * Add a new contact message
 *
 * @param string $name
 * @param string $email
 * @param string|null $phone
 * @param string $subject
 * @param string $message
 * @param int|null $user_id
 * @return array
 */
function addContact($name, $email, $phone, $subject, $message, $user_id = null) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO contacts (user_id, name, email, phone, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'unread', NOW())");
    $stmt->bind_param("isssss", $user_id, $name, $email, $phone, $subject, $message);

    if ($stmt->execute()) {
        return ['status' => 'success', 'message' => 'Your message has been sent successfully!'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to send message: ' . $stmt->error];
    }
}


function getContacts($limit = 50) {
    global $conn;
    $query = "SELECT c.*, u.username 
              FROM contacts c 
              LEFT JOIN users u ON c.user_id = u.id 
              ORDER BY c.created_at DESC 
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $contacts = [];
    while($row = $result->fetch_assoc()){
        $contacts[] = $row;
    }
    return $contacts;
}


function deleteContact($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function markAsRead($id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE contacts SET status='read' WHERE id=?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}
