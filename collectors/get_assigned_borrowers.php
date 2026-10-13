<?php

header("Content-Type: application/json");
include "../db_connect.php";
require_once "../loan_helper.php";
require_once "../payment_helper.php";
require_once "../loan_account_helper.php";

ensurePaymentStatusColumn($conn);
ensureLoanAccountNumberColumn($conn);

$collector_id = isset($_GET['collector_id']) ? (int)$_GET['collector_id'] : (int)($_POST['collector_id'] ?? 0);

if ($collector_id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "collector_id required"
    ]);
    exit;
}

$sql = "
    SELECT
        u.id AS borrower_id,
        u.fullname
    FROM users u
    WHERE u.collector_id = ?
      AND u.role = 'borrower'
    ORDER BY u.fullname ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $collector_id);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$borrowers = [];
$activeBorrowers = [];
$archiveBorrowers = [];

$loanStmt = $conn->prepare("
    SELECT
        l.id AS loan_id,
        l.loan_account_number,
        l.principal_amount,
        l.interest_rate,
        l.loan_term,
        l.payment_frequency,
        l.total_amount,
        l.installment_amount,
        l.balance,
        l.status AS loan_status,
        l.due_date
    FROM loans l
    WHERE l.borrower_id = ?
    ORDER BY
        CASE
            WHEN l.status = 'ongoing' THEN 0
            WHEN l.status = 'approved' THEN 1
            WHEN l.status = 'completed' THEN 2
            ELSE 3
        END,
        l.created_at DESC,
        l.id DESC
    LIMIT 1
");

$paymentStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_paid
    FROM payments
    WHERE loan_id = ?
      AND status = 'approved'
");

// Next pending installment date
$dueDateStmt = $conn->prepare("
    SELECT due_date
    FROM loan_installments
    WHERE loan_id = ?
      AND status = 'pending'
    ORDER BY due_date ASC
    LIMIT 1
");

if (!$loanStmt || !$paymentStmt || !$dueDateStmt) {
    $stmt->close();
    if ($loanStmt)    $loanStmt->close();
    if ($paymentStmt) $paymentStmt->close();
    if ($dueDateStmt) $dueDateStmt->close();
    echo json_encode([
        "status"  => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

while ($row = $result->fetch_assoc()) {
    $loanRow = null;
    $loanDetails = null;
    $totalPaid = 0.0;

    $borrowerId = (int)$row['borrower_id'];
    $loanStmt->bind_param("i", $borrowerId);
    $loanStmt->execute();
    $loanResult = $loanStmt->get_result();
    $loanRow = $loanResult ? $loanResult->fetch_assoc() : null;

    if ($loanRow) {
        $loanId = (int)$loanRow['loan_id'];

        // Payments total
        $paymentStmt->bind_param("i", $loanId);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $paymentRow    = $paymentResult ? $paymentResult->fetch_assoc() : null;
        $totalPaid     = $paymentRow ? (float)$paymentRow['total_paid'] : 0.0;

        // Pass null for balance when stored value is <= 0 so the helper
        // computes remaining = total_amount - total_paid instead of returning 0
        $storedBalance   = (float)($loanRow['balance'] ?? 0);
        $balanceForHelper = $storedBalance > 0 ? $storedBalance : null;

        $loanDetails = buildLoanComputationDetails(
            $loanRow,
            $balanceForHelper,
            $totalPaid
        );

        // Next pending installment date
        $nextDueDate = null;
        $dueDateStmt->bind_param("i", $loanId);
        $dueDateStmt->execute();
        $dueDateResult = $dueDateStmt->get_result();
        $dueDateRow    = $dueDateResult ? $dueDateResult->fetch_assoc() : null;
        if ($dueDateRow) {
            $nextDueDate = $dueDateRow['due_date'];
        }
    }

    // Compute remaining balance: prefer computed over stale stored column
    $computedRemaining = $loanDetails['remaining_balance'] ?? 0;
    $loanTotalAmount   = (float)($loanRow['total_amount'] ?? 0);
    $dynamicBalance    = $loanTotalAmount > 0
        ? round(max(0, $loanTotalAmount - $totalPaid), 2)
        : $computedRemaining;
    // Use the larger of the two (ensures we never under-report)
    $remainingBalance  = max($dynamicBalance, $computedRemaining);

    $borrowers[] = [
        "borrower_id"          => $borrowerId,
        "fullname"             => $row['fullname'],
        "borrower_name"        => $row['fullname'],
        "loan_id"              => !empty($loanRow['loan_id']) ? (int)$loanRow['loan_id'] : null,
        "loan_account_number"  => $loanRow ? resolveLoanAccountNumber($loanRow) : '',
        "loan_amount"          => $loanDetails['loan_amount']          ?? 0,
        "principal_amount"     => $loanDetails['loan_amount']          ?? 0,
        "interest_rate"        => $loanDetails['interest_rate']        ?? 0,
        "loan_term"            => $loanDetails['loan_term']            ?? 0,
        "payment_frequency"    => $loanDetails['payment_frequency']    ?? '',
        "interest"             => $loanDetails['interest']             ?? 0,
        "total_payable"        => $loanDetails['total_payable']        ?? 0,
        "total_amount"         => $loanDetails['total_payable']        ?? 0,
        "installment_amount"   => $loanDetails['payment_per_schedule'] ?? (float)($loanRow['installment_amount'] ?? 0),
        "payment_per_schedule" => $loanDetails['payment_per_schedule'] ?? 0,
        // ── Dynamically computed balance (not loans.balance) ──
        "remaining_balance"    => $remainingBalance,
        "balance"              => $remainingBalance,
        // ── Debug fields (temporary — remove after confirming values are correct) ──
        "debug_total_amount"    => (float)($loanRow['total_amount'] ?? 0),
        "debug_total_paid"      => round($totalPaid, 2),
        "debug_remaining_balance" => $remainingBalance,

        "loan_status"          => $loanRow['loan_status'] ?? null,
        "status"               => $loanRow['loan_status'] ?? null,
        "borrower_section"     => (($loanRow['loan_status'] ?? '') === 'completed') ? 'archive' : 'active',
        "is_archive"           => (($loanRow['loan_status'] ?? '') === 'completed'),
        "can_request_reloan"   => (($loanRow['loan_status'] ?? '') === 'completed'),
        "total_paid"           => round($totalPaid, 2),
        "amount_paid"          => round($totalPaid, 2),
        // ── Next due date from loan_installments ──
        "next_due"             => $nextDueDate ?? null,
        "next_due_date"        => $nextDueDate ?? null,
        "due_date"             => $nextDueDate ?? ($loanRow['due_date'] ?? null),
    ];

    $borrowerEntry = $borrowers[count($borrowers) - 1];
    if (($loanRow['loan_status'] ?? '') === 'completed') {
        $archiveBorrowers[] = $borrowerEntry;
    } else {
        $activeBorrowers[] = $borrowerEntry;
    }
}

$loanStmt->close();
$paymentStmt->close();
$dueDateStmt->close();
$stmt->close();

echo json_encode([
    "status" => "success",
    "data" => $borrowers,
    "active_borrowers" => $activeBorrowers,
    "archive_borrowers" => $archiveBorrowers
]);

?>
