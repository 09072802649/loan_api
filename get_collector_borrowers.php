<?php
header("Content-Type: application/json");

include "db_connect.php";
require_once "loan_helper.php";
require_once "payment_helper.php";
require_once "loan_account_helper.php";

ensurePaymentStatusColumn($conn);

$collector_id = isset($_GET['collector_id']) ? intval($_GET['collector_id']) : 0;

if ($collector_id <= 0) {
    echo json_encode([
        "status"  => "error",
        "message" => "collector_id required"
    ]);
    exit;
}

/* ── Fetch active loans for this collector ── */

$sql = "
    SELECT
        l.id               AS loan_id,
        l.borrower_id,
        l.loan_account_number,
        l.principal_amount,
        l.interest_rate,
        l.loan_term,
        l.payment_frequency,
        l.total_amount,
        l.installment_amount,
        l.remaining_balance,
        l.balance,
        l.total_paid,
        l.start_date,
        l.due_date,
        l.status           AS loan_status,
        u.fullname
    FROM loans l
    JOIN users u ON u.id = l.borrower_id
    WHERE l.collector_id = ?
      AND l.status = 'ongoing'
    ORDER BY l.id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $collector_id);
$stmt->execute();
$result = $stmt->get_result();

/* ── Prepared statement: total paid per loan from payments table ── */
$paymentStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_paid
    FROM payments
    WHERE loan_id = ? AND status = 'approved'
");

/* ── Prepared statement: next pending installment due date ── */
$dueDateStmt = $conn->prepare("
    SELECT installment_number, due_date
    FROM loan_installments
    WHERE loan_id = ?
      AND status = 'pending'
    ORDER BY due_date ASC
    LIMIT 1
");

$borrowers = [];

while ($row = $result->fetch_assoc()) {
    $loanId     = (int)$row['loan_id'];
    $borrowerId = (int)$row['borrower_id'];

    /* — Real total paid from payments table — */
    $totalPaid = 0.0;
    if ($paymentStmt) {
        $paymentStmt->bind_param("i", $loanId);
        $paymentStmt->execute();
        $payRow    = $paymentStmt->get_result()->fetch_assoc();
        $totalPaid = $payRow ? (float)$payRow['total_paid'] : 0.0;
    }

    /* — Next installment info — */
    $nextDueDate       = null;
    $installmentNumber = 1;
    if ($dueDateStmt) {
        $dueDateStmt->bind_param("i", $loanId);
        $dueDateStmt->execute();
        $dueRow = $dueDateStmt->get_result()->fetch_assoc();
        if ($dueRow) {
            $nextDueDate       = $dueRow['due_date'];
            $installmentNumber = (int)$dueRow['installment_number'];
        }
    }

    /* — Compute loan summary via helper (uses principal_amount + interest_rate etc.) — */
    $storedBalance    = (float)($row['remaining_balance'] ?? $row['balance'] ?? 0);
    $balanceForHelper = $storedBalance > 0 ? $storedBalance : null;

    $loanDetails = buildLoanComputationDetails($row, $balanceForHelper, $totalPaid);

    /* — Remaining balance: use stored DB value (we keep it in sync now) — */
    $remainingBalance = $storedBalance > 0
        ? $storedBalance
        : ($loanDetails['remaining_balance'] ?? 0);

    $borrowers[] = [
        // Identity
        "borrower_id"          => $borrowerId,
        "loan_id"              => $loanId,
        "fullname"             => (string)($row['fullname'] ?? ''),
        "loan_account_number"  => resolveLoanAccountNumber($row),

        // Loan terms
        "loan_amount"          => $loanDetails['loan_amount']          ?? (float)$row['principal_amount'],
        "principal_amount"     => $loanDetails['loan_amount']          ?? (float)$row['principal_amount'],
        "interest_rate"        => $loanDetails['interest_rate']        ?? 0,
        "loan_term"            => $loanDetails['loan_term']            ?? 0,
        "payment_frequency"    => $loanDetails['payment_frequency']    ?? '',
        "interest"             => $loanDetails['interest']             ?? 0,

        // Summary figures the Android Record Payment screen needs
        "total_payable"        => $loanDetails['total_payable']        ?? 0,
        "total_amount"         => $loanDetails['total_payable']        ?? 0,
        "payment_per_schedule" => $loanDetails['payment_per_schedule'] ?? (float)($row['installment_amount'] ?? 0),
        "installment_amount"   => $loanDetails['payment_per_schedule'] ?? (float)($row['installment_amount'] ?? 0),
        "number_of_payments"   => $loanDetails['number_of_payments']   ?? 0,
        "remaining_balance"    => round($remainingBalance, 2),
        "balance"              => round($remainingBalance, 2),
        "total_paid"           => round($totalPaid, 2),
        "amount_paid"          => round($totalPaid, 2),

        // Installment / due date
        "installment_number"   => $installmentNumber,
        "next_due"             => $nextDueDate,
        "next_due_date"        => $nextDueDate,
        "due_date"             => $nextDueDate ?? ($row['due_date'] ?? null),

        // Status
        "loan_status"          => $row['loan_status'] ?? 'ongoing',
        "status"               => $row['loan_status'] ?? 'ongoing',
    ];
}

if ($paymentStmt) $paymentStmt->close();
if ($dueDateStmt) $dueDateStmt->close();
$stmt->close();

echo json_encode([
    "status" => "success",
    "data"   => $borrowers
]);