<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once '../db_connect.php';
require_once '../notification_helper.php';

ensure_notifications_table($conn);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid notification ID']);
    exit;
}

$stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'status'       => 'success',
    'unread_count' => get_unread_notification_count($conn),
]);
