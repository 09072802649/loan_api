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

require_once "../../db_connect.php";
require_once "../../payment_helper.php";

ensurePaymentStatusColumn($conn);

$stats = [
    "total_users" => 0,
    "total_loans" => 0,
    "total_payments" => 0,
    "total_collections" => 0
];

$queries = [
    "total_users" => "SELECT COUNT(*) AS total FROM users",
    "total_loans" => "SELECT COUNT(*) AS total FROM loans",
    "total_payments" => "SELECT COUNT(*) AS total FROM payments WHERE status = 'approved'",
    "total_collections" => "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE status = 'approved'",
];

foreach ($queries as $key => $sql) {
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $stats[$key] = $row ? (float)$row['total'] : 0;
    }
}

$stats['total_users'] = (int)$stats['total_users'];
$stats['total_loans'] = (int)$stats['total_loans'];
$stats['total_payments'] = (int)$stats['total_payments'];
$stats['total_collections'] = (float)$stats['total_collections'];

echo json_encode([
    "status" => "success",
    "data" => $stats
]);
?>
