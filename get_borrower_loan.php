<?php
header('Content-Type: application/json');
require_once "db_connect.php";
require_once "loan_helper.php";
require_once "installment_helper.php";
require_once "loan_account_helper.php";

ensureLoanAccountNumberColumn($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

$loanId = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : 0;

if ($loanId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing or invalid loan_id"
    ]);
    exit;
}

$startDateSelect = loanTableHasColumn($conn, 'start_date') ? 'l.start_date' : "NULL AS start_date";
$endDateSelect = loanTableHasColumn($conn, 'end_date') ? 'l.end_date' : "NULL AS end_date";

$sql = "SELECT
            l.id AS loan_id,
            l.loan_account_number,
            l.principal_amount,
            l.interest_rate,
            l.loan_term,
            l.payment_frequency,
            {$startDateSelect},
            {$endDateSelect},
            l.total_amount,
            l.installment_amount,
            l.balance,
            l.status AS loan_status,
            u.fullname AS borrower_name,
            u.phone AS borrower_contact,
            COALESCE(SUM(p.amount), 0) AS total_paid
        FROM loans l
        INNER JOIN users u ON l.borrower_id = u.id
        LEFT JOIN payments p ON p.loan_id = l.id AND p.status = 'approved'
        WHERE l.id = ?
        GROUP BY
            l.id, l.loan_account_number, l.principal_amount, l.interest_rate, l.loan_term,
            l.payment_frequency, l.total_amount, l.installment_amount, l.balance, l.status,
            u.fullname, u.phone, l.created_at
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $loanId);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$loan = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$loan) {
    echo json_encode([
        "status" => "error",
        "message" => "Loan not found."
    ]);
    exit;
}

// Prefer stored DB values for totals and installment amounts
$nextInstallment = getNextUnpaidInstallment($conn, $loan['loan_id']);

// ── GoCash Business Rules Calculation ─────────────────────────────────────────

$principal        = isset($loan['principal_amount']) ? (float)$loan['principal_amount'] : 0.0;
$interest_rate    = isset($loan['interest_rate'])    ? (float)$loan['interest_rate']    : 20.0;
$loan_term_months = isset($loan['loan_term'])        ? (int)$loan['loan_term']          : 2;
$freq             = $loan['payment_frequency'] ?? 'daily';

// 1. Compute interest
$interest_amount = round($principal * ($interest_rate / 100), 2);

// 2. Compute total payable
$total_payable = round($principal + $interest_amount, 2);

// 3. Determine loan period (52 days for 2-month daily loan)
$display_loan_term = $loan_term_months;
if ($freq === 'daily' && $loan_term_months == 2) {
    $display_loan_term = 52;
}

// Installment amount
$installment_amount = ($display_loan_term > 0) ? round($total_payable / $display_loan_term, 2) : 0.0;

// 4. Compute total paid
$total_paid = isset($loan['total_paid']) ? (float)$loan['total_paid'] : 0.0;

// 5. Compute remaining balance & prevent negative
$remaining_balance = round($total_payable - $total_paid, 2);
if ($remaining_balance < 0) {
    $remaining_balance = 0.0;
}

// 6. Fix loan status based on balance
$status = 'ongoing';
if ($remaining_balance == 0) {
    $status = 'completed';
}

echo json_encode([
    "status" => "success",
    "data" => [
        "loan_id"              => (int)$loan['loan_id'],
        "loan_account_number"  => resolveLoanAccountNumber($loan),
        "borrower_name"        => $loan['borrower_name'],
        "borrower_contact"     => $loan['borrower_contact'] ?? '',
        "loan_amount"          => $principal,
        "principal_amount"     => $principal,
        "interest_rate"        => $interest_rate,
        "loan_term"            => $display_loan_term,
        "payment_frequency"    => $freq,
        "interest"             => $interest_amount,
        "total_payable"        => $total_payable,
        "total_amount"         => $total_payable,
        "number_of_payments"   => $display_loan_term,
        "installment_amount"   => $installment_amount,
        "payment_per_schedule" => $installment_amount,
        "total_paid"           => $total_paid,
        "amount_paid"          => $total_paid,
        "remaining_balance"    => $remaining_balance,
        "status"               => $status,
        "loan_status"          => $status,
        "start_date"           => $loan['start_date'],
        "end_date"             => $loan['end_date'],
        "installment_number"   => $nextInstallment['installment_number'] ?? null,
        "due_date"             => $nextInstallment['due_date'] ?? null
    ]
]);

?>
