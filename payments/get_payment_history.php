<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// db_connect.php lives one level up at loan_api/db_connect.php
require_once '../db_connect.php';
require_once '../payment_helper.php';

ensurePaymentStatusColumn($conn);

$borrower_id = $_GET['borrower_id'] ?? null;

if (!$borrower_id) {
    echo json_encode([
        "status" => "error",
        "message" => "borrower_id required"
    ]);
    exit;
}

$sql = "
    SELECT
        p.amount,
        p.payment_date,
        p.status,
        u.fullname AS collector_name
    FROM payments p
    JOIN loans l ON p.loan_id = l.id
    LEFT JOIN users u ON p.collector_id = u.id
    WHERE l.borrower_id = ?
    ORDER BY p.payment_date DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();

echo json_encode([
    "status" => "success",
    "data" => $data
]);
?>
