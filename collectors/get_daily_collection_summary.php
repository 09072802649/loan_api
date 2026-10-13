<?php
header('Content-Type: application/json');

require_once "../db_connect.php";
require_once "../payment_helper.php";
require_once "../loan_account_helper.php";

ensurePaymentStatusColumn($conn);
ensureLoanAccountNumberColumn($conn);

session_start();

$collectorId = 0;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $collectorId = (int)$_SESSION['user_id'];
}

if ($collectorId <= 0) {
    $collectorId = isset($_GET['collector_id']) ? (int)$_GET['collector_id'] : (int)($_POST['collector_id'] ?? 0);
}

if ($collectorId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "collector_id required"
    ]);
    exit;
}

$summaryStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(p.amount), 0) AS total_collected,
        COUNT(p.id) AS transactions,
        COUNT(DISTINCT p.borrower_id) AS borrowers_paid
    FROM payments p
    WHERE p.collector_id = ?
      AND DATE(p.payment_date) = CURDATE()
");

if (!$summaryStmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$summaryStmt->bind_param("i", $collectorId);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summary = $summaryResult ? $summaryResult->fetch_assoc() : null;
$summaryStmt->close();

$transactionsStmt = $conn->prepare("
    SELECT
        borrower.fullname AS borrower_name,
        l.loan_account_number,
        l.id AS loan_id,
        p.amount AS payment_amount,
        p.payment_date
    FROM payments p
    INNER JOIN users AS borrower ON borrower.id = p.borrower_id
    INNER JOIN loans l ON l.id = p.loan_id
    WHERE p.collector_id = ?
      AND DATE(p.payment_date) = CURDATE()
    ORDER BY p.payment_date DESC, p.id DESC
");

if (!$transactionsStmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$transactionsStmt->bind_param("i", $collectorId);
$transactionsStmt->execute();
$transactionsResult = $transactionsStmt->get_result();
$transactions = [];

while ($row = $transactionsResult->fetch_assoc()) {
    $transactions[] = [
        "borrower_name" => $row['borrower_name'],
        "loan_account_number" => resolveLoanAccountNumber($row),
        "payment_amount" => round((float)$row['payment_amount'], 2),
        "payment_date" => $row['payment_date'],
    ];
}

$transactionsStmt->close();

echo json_encode([
    "status" => "success",
    "date" => date('Y-m-d'),
    "collector_id" => $collectorId,
    "daily_collection" => round((float)($summary['total_collected'] ?? 0), 2),
    "total_collected" => round((float)($summary['total_collected'] ?? 0), 2),
    "transactions" => (int)($summary['transactions'] ?? 0),
    "borrowers_paid" => (int)($summary['borrowers_paid'] ?? 0),
    "transaction_count" => (int)($summary['transactions'] ?? 0),
    "borrower_count" => (int)($summary['borrowers_paid'] ?? 0),
    "transaction_list" => $transactions,
]);
?>
