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
require_once "../loan_account_helper.php";
require_once "../remittance_helper.php";

ensureLoanAccountNumberColumn($conn);
ensureRemittanceSchema($conn);

$remittanceId = isset($_GET['remittance_id']) ? (int)$_GET['remittance_id'] : (int)($_POST['remittance_id'] ?? 0);

if ($remittanceId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "remittance_id required"
    ]);
    exit;
}

$headerStmt = $conn->prepare("
    SELECT
        r.id AS remittance_id,
        r.collector_id,
        r.remittance_date,
        r.total_amount,
        r.transaction_count,
        r.status AS remittance_status,
        r.received_at,
        u.fullname AS collector_name
    FROM remittances r
    LEFT JOIN users u ON u.id = r.collector_id
    WHERE r.id = ?
    LIMIT 1
");

if (!$headerStmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$headerStmt->bind_param("i", $remittanceId);
$headerStmt->execute();
$headerResult = $headerStmt->get_result();
$header = $headerResult ? $headerResult->fetch_assoc() : null;
$headerStmt->close();

if (!$header) {
    echo json_encode([
        "status" => "error",
        "message" => "Remittance not found"
    ]);
    exit;
}

$paymentsStmt = $conn->prepare("
    SELECT
        p.id AS payment_id,
        borrower.fullname AS borrower_name,
        l.loan_account_number,
        l.id AS loan_id,
        p.amount AS payment_amount,
        p.payment_date
    FROM payments p
    LEFT JOIN users AS borrower ON borrower.id = p.borrower_id
    LEFT JOIN loans l ON l.id = p.loan_id
    WHERE p.remittance_id = ?
    ORDER BY p.payment_date DESC, p.id DESC
");

if (!$paymentsStmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$paymentsStmt->bind_param("i", $remittanceId);
$paymentsStmt->execute();
$paymentsResult = $paymentsStmt->get_result();
$payments = [];

while ($row = $paymentsResult->fetch_assoc()) {
    $payments[] = [
        "payment_id" => (int)$row['payment_id'],
        "borrower_name" => $row['borrower_name'] ?: 'Borrower',
        "loan_account_number" => resolveLoanAccountNumber($row),
        "payment_amount" => round((float)$row['payment_amount'], 2),
        "payment_date" => $row['payment_date'],
    ];
}

$paymentsStmt->close();

echo json_encode([
    "status" => "success",
    "remittance_id" => (int)$header['remittance_id'],
    "collector_id" => (int)$header['collector_id'],
    "collector_name" => $header['collector_name'] ?: 'Unknown Collector',
    "remittance_date" => $header['remittance_date'],
    "total_amount" => round((float)$header['total_amount'], 2),
    "transaction_count" => (int)$header['transaction_count'],
    "remittance_status" => $header['remittance_status'],
    "received_at" => $header['received_at'],
    "payments" => $payments
]);
?>
