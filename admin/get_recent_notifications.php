<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once '../db_connect.php';
require_once '../notification_helper.php';

ensure_notifications_table($conn);

$sql = "SELECT id, title, message, type, status, created_at
        FROM notifications
        ORDER BY CASE WHEN status = 'unread' THEN 0 ELSE 1 END, created_at DESC
        LIMIT 5";

$result = $conn->query($sql);
$notifications = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id'         => (int)$row['id'],
            'title'      => $row['title'],
            'message'    => $row['message'],
            'type'       => $row['type'],
            'status'     => $row['status'],
            'created_at' => $row['created_at'],
        ];
    }
}

echo json_encode([
    'status'        => 'success',
    'notifications' => $notifications,
    'unread_count'  => get_unread_notification_count($conn),
]);
