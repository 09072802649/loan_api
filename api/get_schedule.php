<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../loan_helper.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$borrower_id = 0;
if (isset($_GET['borrower_id'])) {
    $borrower_id = (int)$_GET['borrower_id'];
} elseif (isset($_POST['borrower_id'])) {
    $borrower_id = (int)$_POST['borrower_id'];
} elseif (isset($input['borrower_id'])) {
    $borrower_id = (int)$input['borrower_id'];
}

if ($borrower_id <= 0) {
    echo json_encode(["status" => "error", "message" => "borrower_id is required"]);
    exit;
}

$loanStmt = $conn->prepare(
        "SELECT
                 id,
                 borrower_id,                 borrower_name,                 principal_amount,
                 interest_rate,
                 loan_term,
                 payment_frequency,
                 total_amount,
                 installment_amount,
                 balance,
                 created_at
         FROM loans
         WHERE borrower_id = ?
             AND status = 'ongoing'
         ORDER BY id DESC
         LIMIT 1"
);

if (!$loanStmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed (loan): " . $conn->error]);
    exit;
}

$loanStmt->bind_param('i', $borrower_id);
if (!$loanStmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Execute failed (loan): " . $loanStmt->error]);
    exit;
}

$loanResult = $loanStmt->get_result();
$loan = $loanResult ? $loanResult->fetch_assoc() : null;
$loanStmt->close();

if (!$loan) {
    echo json_encode(["status" => "no_loan"]);
    exit;
}

$loan_id = (int)$loan['id'];

// Use stored values from the loans table. Do not recompute interest or totals here.
$loanTotalAmount = isset($loan['total_amount']) && is_numeric($loan['total_amount']) ? (float)$loan['total_amount'] : 0.0;
$loanBalance = isset($loan['balance']) && is_numeric($loan['balance']) ? (float)$loan['balance'] : 0.0;
$loanInstallments = computeLoanNumberOfPayments((int)($loan['loan_term'] ?? 0), $loan['payment_frequency'] ?? 'monthly');
$loanInstallmentAmount = isset($loan['installment_amount']) && is_numeric($loan['installment_amount'])
    ? (float)$loan['installment_amount']
    : ($loanInstallments > 0 ? round($loanTotalAmount / $loanInstallments, 2) : 0.0);

$instStmt = $conn->prepare(
    "SELECT
         installment_number,
         due_date,
         amount,
         status
     FROM loan_installments
     WHERE loan_id = ?
     ORDER BY installment_number ASC"
);
if (!$instStmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed (schedules): " . $conn->error]);
    exit;
}

$instStmt->bind_param('i', $loan_id);
$instStmt->execute();
$instResult = $instStmt->get_result();
$installments = [];
while ($row = $instResult->fetch_assoc()) {
    $installments[] = [
        'installment_number' => isset($row['installment_number']) ? (int)$row['installment_number'] : null,
        'due_date' => $row['due_date'],
        'amount' => (float)$loanInstallmentAmount,
        'status' => $row['status']
    ];
}
$instStmt->close();

$next_payment = null;
$nextStmt = $conn->prepare(
    "SELECT
         due_date,
         amount,
         status
     FROM loan_installments
     WHERE loan_id = ?
       AND status = 'pending'
     ORDER BY due_date ASC
     LIMIT 1"
);
if ($nextStmt) {
    $nextStmt->bind_param('i', $loan_id);
    $nextStmt->execute();
    $nextResult = $nextStmt->get_result();
    $nextRow = $nextResult ? $nextResult->fetch_assoc() : null;
    $nextStmt->close();
    if ($nextRow) {
        $next_payment = [
            'due_date' => $nextRow['due_date'],
            'amount' => (float)$loanInstallmentAmount,
            'status' => $nextRow['status']
        ];
    }
}

$response = [
    'status' => 'success',
    'borrower_name' => $loan['borrower_name'] ?? '',
    'loan' => [
        'principal_amount' => isset($loan['principal_amount']) ? (float)$loan['principal_amount'] : 0,
        'interest_rate' => isset($loan['interest_rate']) ? (float)$loan['interest_rate'] : 0,
        'loan_term' => isset($loan['loan_term']) ? (int)$loan['loan_term'] : 0,
        'payment_frequency' => $loan['payment_frequency'] ?? 'monthly',
        'total_amount' => $loanTotalAmount,
        'installment_amount' => $loanInstallmentAmount,
        'balance' => $loanBalance
    ],
    'next_payment' => $next_payment,
    'installments' => $installments
];

echo json_encode($response);
exit;
