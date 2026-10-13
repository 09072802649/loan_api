<?php
header('Content-Type: application/json');
require_once "db_connect.php";
require_once "loan_helper.php";
require_once "payment_helper.php";
require_once "installment_helper.php";
require_once "loan_account_helper.php";

ensureLoanAccountNumberColumn($conn);

ensurePaymentStatusColumn($conn);

$borrowerId = isset($_GET['borrower_id']) ? (int)$_GET['borrower_id'] : (int)($_POST['borrower_id'] ?? 0);
$collectorId = isset($_GET['collector_id']) ? (int)$_GET['collector_id'] : (int)($_POST['collector_id'] ?? 0);

if ($borrowerId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "borrower_id required"
    ]);
    exit;
}

$sql = "SELECT
            u.id AS borrower_id,
            u.fullname AS borrower_name,
            u.phone AS borrower_contact,
            u.address AS borrower_address,
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
            l.start_date,
            l.created_at,
            COALESCE(SUM(CASE WHEN p.status = 'approved' THEN p.amount ELSE 0 END), 0) AS total_paid
        FROM users u
        INNER JOIN loans l ON l.borrower_id = u.id
        LEFT JOIN payments p ON p.loan_id = l.id
        WHERE u.id = ?";

if ($collectorId > 0) {
    $sql .= " AND u.collector_id = ?";
}

$sql .= "
        GROUP BY
            u.id, u.fullname, u.phone, u.address,
            l.id, l.principal_amount, l.interest_rate, l.loan_term,
            l.payment_frequency, l.total_amount, l.balance, l.status,
            l.start_date, l.created_at
        ORDER BY
            CASE
                WHEN l.status = 'ongoing' THEN 0
                WHEN l.status = 'approved' THEN 1
                WHEN l.status = 'completed' THEN 2
                ELSE 3
            END,
            l.created_at DESC,
            l.id DESC
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

if ($collectorId > 0) {
    $stmt->bind_param("ii", $borrowerId, $collectorId);
} else {
    $stmt->bind_param("i", $borrowerId);
}

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
        "message" => "No loan found for this borrower."
    ]);
    exit;
}

$loanDetails = buildLoanComputationDetails(
    $loan,
    $loan['balance'] ?? null,
    $loan['total_paid'] ?? 0
);
$nextInstallment = getNextUnpaidInstallment($conn, $loan['loan_id']);

echo json_encode([
    "status" => "success",
    "borrower_id" => (int)$loan['borrower_id'],
    "borrower_name" => $loan['borrower_name'],
    "borrower_contact" => $loan['borrower_contact'] ?? '',
    "borrower_address" => $loan['borrower_address'] ?? '',
    "loan_id" => (int)$loan['loan_id'],
    "loan_account_number" => resolveLoanAccountNumber($loan),
    "loan_amount" => $loanDetails['loan_amount'],
    "principal_amount" => $loanDetails['loan_amount'],
    "interest_rate" => $loanDetails['interest_rate'],
    "loan_term" => $loanDetails['loan_term'],
    "payment_frequency" => $loanDetails['payment_frequency'],
    "interest" => $loanDetails['interest'],
    "total_payable" => $loanDetails['total_payable'],
    "total_amount" => $loanDetails['total_payable'],
    "number_of_payments" => $loanDetails['number_of_payments'],
    "installment_amount" => $loanDetails['payment_per_schedule'],
    "payment_per_schedule" => $loanDetails['payment_per_schedule'],
    "installment_number" => $nextInstallment['installment_number'],
    "due_date" => $nextInstallment['due_date'],
    "remaining_balance" => $loanDetails['remaining_balance'],
    "total_paid" => round((float)$loan['total_paid'], 2),
    "loan_status" => $loan['loan_status'],
    "start_date" => $loan['start_date'],
    "created_at" => $loan['created_at']
]);
?>
