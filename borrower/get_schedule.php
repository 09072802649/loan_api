<?php
header('Content-Type: application/json');
require_once '../db_connect.php';

// ── Input ────────────────────────────────────────────────────────────────────
$borrower_id = isset($_POST['borrower_id']) ? (int)$_POST['borrower_id'] : 0;

if ($borrower_id <= 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing or invalid borrower_id'
    ]);
    exit;
}

// ── STEP 2: Get the borrower's active (ongoing) loan ─────────────────────────
$loanStmt = $conn->prepare(
    "SELECT
         id             AS loan_id,
         borrower_id,
         principal_amount,
         total_amount    AS total_payable,
         start_date,
         end_date
     FROM loans
     WHERE borrower_id = ?
       AND status = 'ongoing'
     LIMIT 1"
);

if (!$loanStmt) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Prepare failed (loan): ' . $conn->error
    ]);
    exit;
}

$loanStmt->bind_param('i', $borrower_id);
$loanStmt->execute();
$loanResult = $loanStmt->get_result();
$loan       = $loanResult ? $loanResult->fetch_assoc() : null;
$loanStmt->close();

if (!$loan) {
    echo json_encode(['status' => 'no_loan']);
    exit;
}

$loan_id = (int)$loan['loan_id'];

// ── STEP 3: Get the full installment schedule ─────────────────────────────────
$instStmt = $conn->prepare(
    "SELECT
         id                 AS installment_id,
         installment_number,
         due_date,
         amount,
         status
     FROM loan_installments
     WHERE loan_id = ?
     ORDER BY due_date ASC, installment_number ASC"
);

if (!$instStmt) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Prepare failed (installments): ' . $conn->error
    ]);
    exit;
}

$instStmt->bind_param('i', $loan_id);
$instStmt->execute();
$instResult = $instStmt->get_result();

$installments = [];
while ($row = $instResult->fetch_assoc()) {
    $installments[] = [
        'installment_number' => (int)$row['installment_number'],
        'due_date'           => $row['due_date'],
        'amount'             => (float)$row['amount'],
        'status'             => $row['status']
    ];
}
$instStmt->close();

// ── STEP 4: Determine next pending payment ────────────────────────────────────
$nextStmt = $conn->prepare(
    "SELECT
         amount,
         due_date,
         status
     FROM loan_installments
     WHERE loan_id = ?
       AND status = 'pending'
     ORDER BY due_date ASC
     LIMIT 1"
);

$next_payment = null;

if ($nextStmt) {
    $nextStmt->bind_param('i', $loan_id);
    $nextStmt->execute();
    $nextResult = $nextStmt->get_result();
    $nextRow    = $nextResult ? $nextResult->fetch_assoc() : null;
    $nextStmt->close();

    if ($nextRow) {
        $next_payment = [
            'amount'   => (float)$nextRow['amount'],
            'due_date' => $nextRow['due_date'],
            'status'   => $nextRow['status']
        ];
    }
}

// ── STEP 5: Build and return the JSON response ────────────────────────────────
echo json_encode([
    'status'      => 'success',
    'loan_period' => [
        'start_date' => $loan['start_date'],
        'end_date'   => $loan['end_date']
    ],
    'next_payment' => $next_payment,
    'installments' => $installments
]);
?>
