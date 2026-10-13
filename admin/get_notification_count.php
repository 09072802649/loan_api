<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);
    exit;
}

require_once "../db_connect.php";
require_once "../notification_helper.php";

ensure_notifications_table($conn);

echo json_encode([
    "status" => "success",
    "unread_count" => get_unread_notification_count($conn)
]);
?>
