<?php
header("Content-Type: application/json");

include "db_connect.php";
require_once "loan_helper.php";
require_once "payment_helper.php";

ensurePaymentStatusColumn($conn);

$loan_id = isset($_GET['loan_id']) ? intval($_GET['loan_id']) : 0;

if ($loan_id <= 0) {
    echo json_encode(["status" => "error", "message" => "loan_id required"]);
    exit;
}

/* ── Fetch loan row ── */
$stmt = $conn->prepare("
    SELECT
        l.id               AS loan_id,
        l.borrower_id,
        l.principal_amount,
        l.interest_rate,
        l.loan_term,
        l.payment_frequency,
        l.total_amount,
        l.installment_amount,
        l.remaining_balance,
        l.balance,
        l.total_paid,
        l.due_date,
        l.status           AS loan_status,
        u.fullname
    FROM loans l
    JOIN users u ON u.id = l.borrower_id
    WHERE l.id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Query failed"]);
    exit;
}

$stmt->bind_param("i", $loan_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(["status" => "error", "message" => "Loan not found"]);
    exit;
}

/* ── Real total paid from payments table ── */
$payStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_paid
    FROM payments
    WHERE loan_id = ? AND status = 'approved'
");
$payStmt->bind_param("i", $loan_id);
$payStmt->execute();
$totalPaid = (float)$payStmt->get_result()->fetch_assoc()['total_paid'];
$payStmt->close();

/* ── Next pending installment ── */
$dueStmt = $conn->prepare("
    SELECT installment_number, due_date
    FROM loan_installments
    WHERE loan_id = ? AND status = 'pending'
    ORDER BY due_date ASC
    LIMIT 1
");
$dueStmt->bind_param("i", $loan_id);
$dueStmt->execute();
$dueRow = $dueStmt->get_result()->fetch_assoc();
$dueStmt->close();

$nextDueDate       = $dueRow['due_date'] ?? ($row['due_date'] ?? null);
$installmentNumber = isset($dueRow['installment_number']) ? (int)$dueRow['installment_number'] : 1;

/* ── Compute loan financials ── */
$storedBalance    = (float)($row['remaining_balance'] ?? $row['balance'] ?? 0);
$balanceForHelper = $storedBalance > 0 ? $storedBalance : null;
$loanDetails      = buildLoanComputationDetails($row, $balanceForHelper, $totalPaid);

$remainingBalance = $storedBalance > 0
    ? $storedBalance
    : ($loanDetails['remaining_balance'] ?? 0);

echo json_encode([
    "status"             => "success",
    "loan_id"            => (int)$row['loan_id'],
    "borrower_id"        => (int)$row['borrower_id'],
    "fullname"           => (string)($row['fullname'] ?? ''),
    "loan_amount"        => $loanDetails['loan_amount']          ?? (float)$row['principal_amount'],
    "principal_amount"   => $loanDetails['loan_amount']          ?? (float)$row['principal_amount'],
    "interest_rate"      => $loanDetails['interest_rate']        ?? 0,
    "loan_term"          => $loanDetails['loan_term']            ?? 0,
    "payment_frequency"  => $loanDetails['payment_frequency']    ?? '',
    "interest"           => $loanDetails['interest']             ?? 0,
    "total_payable"      => $loanDetails['total_payable']        ?? 0,
    "payment_per_schedule" => $loanDetails['payment_per_schedule'] ?? (float)($row['installment_amount'] ?? 0),
    "installment_amount" => $loanDetails['payment_per_schedule'] ?? (float)($row['installment_amount'] ?? 0),
    "number_of_payments" => $loanDetails['number_of_payments']   ?? 0,
    "remaining_balance"  => round($remainingBalance, 2),
    "total_paid"         => round($totalPaid, 2),
    "installment_number" => $installmentNumber,
    "due_date"           => $nextDueDate,
    "loan_status"        => $row['loan_status'] ?? 'ongoing',
]);
