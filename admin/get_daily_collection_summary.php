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
require_once "../payment_helper.php";

ensurePaymentStatusColumn($conn);

$summary = [
    "total_collection" => 0,
    "total_transactions" => 0,
    "pending_payments" => 0,
    "approved_today" => 0,
];

$queries = [
    "total_collection" => "
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM payments
        WHERE DATE(payment_date) = CURDATE()
          AND status = 'approved'
    ",
    "total_transactions" => "
        SELECT COUNT(*) AS total
        FROM payments
        WHERE DATE(payment_date) = CURDATE()
    ",
    "pending_payments" => "
        SELECT COUNT(*) AS total
        FROM payments
        WHERE status = 'pending'
    ",
    "approved_today" => "
        SELECT COUNT(*) AS total
        FROM payments
        WHERE DATE(payment_date) = CURDATE()
          AND status = 'approved'
    ",
];

foreach ($queries as $key => $sql) {
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $summary[$key] = $row ? (float)$row['total'] : 0;
    }
}

echo json_encode([
    "status" => "success",
    "total_collection" => (float)$summary['total_collection'],
    "total_transactions" => (int)$summary['total_transactions'],
    "pending_payments" => (int)$summary['pending_payments'],
    "approved_today" => (int)$summary['approved_today'],
]);
?>
