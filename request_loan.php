<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

include "db_connect.php";
require_once "notification_helper.php";
require_once "loan_helper.php";
require_once "loan_account_helper.php";

ensure_notifications_table($conn);
ensureLoanAccountNumberColumn($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true) ?? [];

$borrower_name = trim($input['borrower_name'] ?? $_POST['borrower_name'] ?? '');
$borrower_contact = trim($input['borrower_contact'] ?? $_POST['borrower_contact'] ?? '');
$borrower_address = trim($input['borrower_address'] ?? $_POST['borrower_address'] ?? '');
$borrower_id = isset($input['borrower_id']) ? (int)$input['borrower_id'] : (int)($_POST['borrower_id'] ?? 0);
$collector_id = $input['collector_id'] ?? $_POST['collector_id'] ?? null;
$principal_amount = $input['principal_amount'] ?? $input['loan_amount'] ?? $_POST['principal_amount'] ?? $_POST['loan_amount'] ?? null;
$interest_rate = $input['interest_rate'] ?? $_POST['interest_rate'] ?? null;
$loan_term = $input['loan_term'] ?? $_POST['loan_term'] ?? null;
$payment_frequency = strtolower(trim((string)($input['payment_frequency'] ?? $_POST['payment_frequency'] ?? '')));
$start_date = $input['start_date'] ?? $_POST['start_date'] ?? null;

if (
    $borrower_name === '' ||
    $borrower_contact === '' ||
    $borrower_address === '' ||
    !$collector_id ||
    $principal_amount === null || $principal_amount === '' ||
    $interest_rate === null || $interest_rate === '' ||
    !$loan_term ||
    !$payment_frequency ||
    !$start_date
) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields"
    ]);
    exit;
}

$principalAmount = (float)$principal_amount;
$interestRate = (float)$interest_rate;
$loanTerm = (int)$loan_term;

if ($principalAmount <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "loan_amount must be greater than 0"
    ]);
    exit;
}

if ($interestRate < 0) {
    echo json_encode([
        "status" => "error",
        "message" => "interest_rate must be greater than or equal to 0"
    ]);
    exit;
}

if ($loanTerm <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "loan_term must be greater than 0"
    ]);
    exit;
}

$allowed_frequencies = ['daily', 'weekly', 'monthly'];
if (!in_array($payment_frequency, $allowed_frequencies, true)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid payment_frequency"
    ]);
    exit;
}

$computedLoan = computeLoanFlatInterest(
    $principalAmount,
    $interestRate,
    $loanTerm,
    $payment_frequency
);
$loanAccountNumber = generateLoanAccountNumber($conn);
$supportsNumberOfPayments = loanTableHasColumn($conn, 'number_of_payments');

if ($supportsNumberOfPayments) {
    $sql = "INSERT INTO loans
        (
            loan_account_number,
            borrower_id,
            borrower_name,
            borrower_contact,
            borrower_address,
            collector_id,
            principal_amount,
            interest_rate,
            loan_term,
            payment_frequency,
            start_date,
            total_amount,
            installment_amount,
            balance,
            number_of_payments,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
} else {
    $sql = "INSERT INTO loans
        (
            loan_account_number,
            borrower_id,
            borrower_name,
            borrower_contact,
            borrower_address,
            collector_id,
            principal_amount,
            interest_rate,
            loan_term,
            payment_frequency,
            start_date,
            total_amount,
            installment_amount,
            balance,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
}

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

if ($supportsNumberOfPayments) {
    $stmt->bind_param(
        "sisssiddissdddi",
        $loanAccountNumber,
        $borrower_id,
        $borrower_name,
        $borrower_contact,
        $borrower_address,
        $collector_id,
        $principalAmount,
        $interestRate,
        $loanTerm,
        $payment_frequency,
        $start_date,
        $computedLoan['total_payable'],
        $computedLoan['payment_per_schedule'],
        $computedLoan['total_payable'],
        $computedLoan['number_of_payments']
    );
} else {
    $stmt->bind_param(
        "sisssiddissddd",
        $loanAccountNumber,
        $borrower_id,
        $borrower_name,
        $borrower_contact,
        $borrower_address,
        $collector_id,
        $principalAmount,
        $interestRate,
        $loanTerm,
        $payment_frequency,
        $start_date,
        $computedLoan['total_payable'],
        $computedLoan['payment_per_schedule'],
        $computedLoan['total_payable']
    );
}

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    exit;
}

$loan_id = $conn->insert_id;
$stmt->close();

create_notification(
    $conn,
    'New loan request submitted',
    $borrower_name . ' submitted a new loan request for PHP ' . number_format((float)$principal_amount, 2) . '.',
    'loan_request',
    $loan_id
);

echo json_encode([
    "status" => "success",
    "message" => "Loan request submitted successfully",
    "loan_id" => $loan_id,
    "loan_amount" => round($principalAmount, 2),
    "principal_amount" => round($principalAmount, 2),
    "interest_rate" => round($interestRate, 2),
    "interest" => $computedLoan['interest'],
    "total_payable" => $computedLoan['total_payable'],
    "total_amount" => $computedLoan['total_payable'],
    "number_of_payments" => $computedLoan['number_of_payments'],
    "installment_amount" => $computedLoan['payment_per_schedule'],
    "payment_per_schedule" => $computedLoan['payment_per_schedule']
]);

?>
