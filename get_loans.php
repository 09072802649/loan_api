<?php
include 'db_connect.php';
require_once 'loan_helper.php';
require_once 'loan_account_helper.php';

ensureLoanAccountNumberColumn($conn);

$user_id = $_GET['user_id'] ?? '';

$startDateSelect = loanTableHasColumn($conn, 'start_date') ? 'l.start_date' : "NULL AS start_date";
$endDateSelect = loanTableHasColumn($conn, 'end_date') ? 'l.end_date' : "NULL AS end_date";

$sql = "SELECT 
            l.id AS loan_id, 
            l.loan_account_number, 
            l.principal_amount, 
            l.interest_rate, 
            l.loan_term, 
            l.payment_frequency, 
            l.total_amount, 
            l.installment_amount, 
            l.balance, 
            l.status, 
            l.due_date, 
            {$startDateSelect}, 
            {$endDateSelect},
            l.created_at,
            COALESCE(SUM(p.amount), 0) AS total_paid
        FROM loans l
        LEFT JOIN payments p ON p.loan_id = l.id AND p.status = 'approved'
        WHERE l.borrower_id=?
        GROUP BY l.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$loans = [];

while ($row = $result->fetch_assoc()) {
    $totalPaid = isset($row['total_paid']) ? (float)$row['total_paid'] : 0.0;
    $totalAmount = isset($row['total_amount']) && is_numeric($row['total_amount']) ? (float)$row['total_amount'] : 0.0;
    $installmentAmount = isset($row['installment_amount']) && is_numeric($row['installment_amount']) ? (float)$row['installment_amount'] : 0.0;
    $numberOfPayments = computeLoanNumberOfPayments((int)($row['loan_term'] ?? 0), $row['payment_frequency'] ?? 'monthly');
    $remainingBalance = isset($row['balance']) && is_numeric($row['balance']) ? (float)$row['balance'] : round(max(0, $totalAmount - $totalPaid), 2);

    $loans[] = [
        "loan_id" => (int)$row['loan_id'],
        "loan_account_number" => resolveLoanAccountNumber($row),
        "loan_amount" => isset($row['principal_amount']) ? (float)$row['principal_amount'] : 0,
        "principal_amount" => isset($row['principal_amount']) ? (float)$row['principal_amount'] : 0,
        "interest_rate" => isset($row['interest_rate']) ? (float)$row['interest_rate'] : 0,
        "loan_term" => isset($row['loan_term']) ? (int)$row['loan_term'] : 0,
        "payment_frequency" => $row['payment_frequency'] ?? 'monthly',
        "interest" => null,
        "total_payable" => $totalAmount,
        "total_amount" => $totalAmount,
        "number_of_payments" => $numberOfPayments,
        "payment_per_schedule" => $installmentAmount,
        "remaining_balance" => $remainingBalance,
        "status" => $row['status'],
        "due_date" => $row['due_date'],
        "start_date" => $row['start_date'],
        "end_date" => $row['end_date'],
        "created_at" => $row['created_at']
    ];
}

echo json_encode([
    "status" => "success",
    "loans" => $loans
]);
?>
