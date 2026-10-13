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
require_once "../remittance_helper.php";

ensureRemittanceSchema($conn);

$stmt = $conn->prepare("
    SELECT
        r.id AS remittance_id,
        u.fullname AS collector_name,
        r.remittance_date,
        r.total_amount,
        r.transaction_count,
        r.status AS remittance_status
    FROM remittances r
    LEFT JOIN users u ON u.id = r.collector_id
    ORDER BY
        CASE
            WHEN r.status = 'pending' THEN 0
            WHEN r.status = 'received' THEN 1
            ELSE 2
        END,
        r.remittance_date DESC,
        r.id DESC
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();
$records = [];

while ($row = $result->fetch_assoc()) {
    $records[] = [
        "remittance_id" => (int)$row['remittance_id'],
        "collector_name" => $row['collector_name'] ?: 'Unknown Collector',
        "remittance_date" => $row['remittance_date'],
        "total_amount" => round((float)$row['total_amount'], 2),
        "transaction_count" => (int)$row['transaction_count'],
        "remittance_status" => $row['remittance_status'],
    ];
}

$stmt->close();

echo json_encode([
    "status" => "success",
    "records" => $records
]);
?>
