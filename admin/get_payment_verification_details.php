<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once "../db_connect.php";
require_once "../loan_helper.php";
require_once "../payment_helper.php";
require_once "../loan_account_helper.php";

ensurePaymentStatusColumn($conn);
ensureLoanAccountNumberColumn($conn);

$paymentId = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : (int)($_POST['payment_id'] ?? 0);

if ($paymentId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'payment_id required'
    ]);
    exit;
}

$sql = "
    SELECT
        p.id AS payment_id,
        p.amount AS payment_amount,
        p.payment_date,
        p.proof_image,
        p.status AS payment_status,
        borrower.fullname AS borrower_name,
        collector.fullname AS collector_name,
        l.id AS loan_id,
        l.loan_account_number,
        l.principal_amount,
        l.interest_rate,
        l.loan_term,
        l.payment_frequency,
        l.total_amount,
        l.installment_amount,
        l.balance
    FROM payments p
    INNER JOIN loans l ON l.id = p.loan_id
    INNER JOIN users AS borrower ON borrower.id = p.borrower_id
    LEFT JOIN users AS collector ON collector.id = p.collector_id
    WHERE p.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Prepare failed: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('i', $paymentId);

if (!$stmt->execute()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Execute failed: ' . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$payment = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$payment) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Payment not found'
    ]);
    exit;
}

$loan_amount = isset($payment['principal_amount']) ? (float)$payment['principal_amount'] : 0.0;
$interest_rate = isset($payment['interest_rate']) ? (float)$payment['interest_rate'] : 0.0;
$loan_term = isset($payment['loan_term']) ? max(1, (int)$payment['loan_term']) : 1;
$payment_frequency = $payment['payment_frequency'] ?? 'monthly';

$loanComputation = computeLoanFlatInterest($loan_amount, $interest_rate, $loan_term, $payment_frequency);
$interest = $loanComputation['interest'];
$total_payable = $loanComputation['total_payable'];
$number_of_payments = $loanComputation['number_of_payments'];
$installment_amount = $loanComputation['payment_per_schedule'];

// Remaining balance based on verified approved payments for this loan
$verifiedPaymentsStmt = $conn->prepare(
    "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE loan_id = ? AND status = 'approved'"
);
if ($verifiedPaymentsStmt) {
    $verifiedPaymentsStmt->bind_param('i', $payment['loan_id']);
    $verifiedPaymentsStmt->execute();
    $verifiedPaymentsResult = $verifiedPaymentsStmt->get_result();
    $verifiedRow = $verifiedPaymentsResult ? $verifiedPaymentsResult->fetch_assoc() : null;
    $verifiedPaymentsStmt->close();
    $total_paid = isset($verifiedRow['total_paid']) ? (float)$verifiedRow['total_paid'] : 0.0;
} else {
    $total_paid = 0.0;
}
$remaining_balance = round($total_payable - $total_paid, 2);

echo json_encode([
    'status' => 'success',
    'payment_id' => (int)$payment['payment_id'],
    'borrower_name' => $payment['borrower_name'],
    'loan_account_number' => resolveLoanAccountNumber($payment),
    'loan_amount' => round($loan_amount, 2),
    'interest_rate' => round($interest_rate, 2),
    'loan_term' => $loan_term,
    'payment_frequency' => $payment_frequency,
    'total_payable' => round($total_payable, 2),
    'installment_amount' => round($installment_amount, 2),
    'remaining_balance' => round($remaining_balance, 2),
    'payment_amount' => round((float)$payment['payment_amount'], 2),
    'payment_date' => $payment['payment_date'],
    'collector_name' => $payment['collector_name'] ?: 'Unassigned',
    'proof_image' => $payment['proof_image'],
    'payment_status' => $payment['payment_status']
]);
?>
