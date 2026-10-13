<?php
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/db_connect.php';
require_once dirname(__DIR__) . '/notification_helper.php';
require_once dirname(__DIR__) . '/reloan_helper.php';

ensure_notifications_table($conn);
ensureLoanRequestsTable($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$borrowerId = isset($input['borrower_id']) ? (int)$input['borrower_id'] : 0;
$collectorId = isset($input['collector_id']) ? (int)$input['collector_id'] : 0;
$loanAmount = isset($input['loan_amount'])
    ? (float)$input['loan_amount']
    : (isset($input['principal_amount']) ? (float)$input['principal_amount'] : 0.0);
$interestRateInput = $input['interest_rate'] ?? null;
$loanTermInput = $input['loan_term'] ?? null;
$paymentFrequencyInput = isset($input['payment_frequency'])
    ? strtolower(trim((string)$input['payment_frequency']))
    : '';
$previewOnly = filter_var($input['preview_only'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($borrowerId <= 0 || $collectorId <= 0 || $loanAmount <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'borrower_id, collector_id, and loan_amount are required'
    ]);
    exit;
}

$borrowerStmt = $conn->prepare("
    SELECT id, fullname, collector_id
    FROM users
    WHERE id = ?
      AND role = 'borrower'
    LIMIT 1
");

if (!$borrowerStmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to validate borrower'
    ]);
    exit;
}

$borrowerStmt->bind_param('i', $borrowerId);
$borrowerStmt->execute();
$borrowerResult = $borrowerStmt->get_result();
$borrower = $borrowerResult ? $borrowerResult->fetch_assoc() : null;
$borrowerStmt->close();

if (!$borrower) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Borrower not found'
    ]);
    exit;
}

$assignedCollectorId = isset($borrower['collector_id']) ? (int)$borrower['collector_id'] : 0;
if ($assignedCollectorId > 0 && $assignedCollectorId !== $collectorId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Borrower is not assigned to this collector'
    ]);
    exit;
}

$latestLoan = fetchLatestBorrowerLoan($conn, $borrowerId);
if (!$latestLoan || strtolower((string)($latestLoan['status'] ?? '')) !== 'completed') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Borrower is not eligible for reloan'
    ]);
    exit;
}

$loanTemplate = fetchLatestCompletedLoanTemplate($conn, $borrowerId);
if (!$loanTemplate) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Completed loan history not found for this borrower'
    ]);
    exit;
}

$requestTerms = [
    'principal_amount' => $loanAmount,
];

if ($interestRateInput !== null && $interestRateInput !== '') {
    $interestRate = (float)$interestRateInput;
    if ($interestRate < 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'interest_rate must be greater than or equal to 0'
        ]);
        exit;
    }

    $requestTerms['interest_rate'] = $interestRate;
}

if ($loanTermInput !== null && $loanTermInput !== '') {
    $loanTerm = (int)$loanTermInput;
    if ($loanTerm <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'loan_term must be greater than 0'
        ]);
        exit;
    }

    $requestTerms['loan_term'] = $loanTerm;
}

if ($paymentFrequencyInput !== '') {
    if (!in_array($paymentFrequencyInput, ['daily', 'weekly', 'monthly'], true)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid payment_frequency'
        ]);
        exit;
    }

    $requestTerms['payment_frequency'] = $paymentFrequencyInput;
}

$computedLoan = resolveReloanRequestTerms($requestTerms, $loanTemplate);

if ($previewOnly) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Reloan preview generated successfully',
        'borrower_id' => $borrowerId,
        'collector_id' => $collectorId,
        'loan_amount' => $computedLoan['principal_amount'],
        'principal_amount' => $computedLoan['principal_amount'],
        'interest_rate' => $computedLoan['interest_rate'],
        'loan_term' => $computedLoan['loan_term'],
        'payment_frequency' => $computedLoan['payment_frequency'],
        'interest' => $computedLoan['interest'],
        'total_payable' => $computedLoan['total_payable'],
        'total_amount' => $computedLoan['total_amount'],
        'number_of_payments' => $computedLoan['number_of_payments'],
        'installment_amount' => $computedLoan['installment_amount'],
        'payment_per_schedule' => $computedLoan['payment_per_schedule']
    ]);
    exit;
}

$pendingRequestStmt = $conn->prepare("
    SELECT id
    FROM loan_requests
    WHERE borrower_id = ?
      AND status = 'pending'
    LIMIT 1
");

if (!$pendingRequestStmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to validate existing requests'
    ]);
    exit;
}

$pendingRequestStmt->bind_param('i', $borrowerId);
$pendingRequestStmt->execute();
$pendingResult = $pendingRequestStmt->get_result();
$hasPendingRequest = $pendingResult && $pendingResult->fetch_assoc();
$pendingRequestStmt->close();

if ($hasPendingRequest) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Borrower already has a pending reloan request'
    ]);
    exit;
}

$supportsComputedColumns = loanRequestsTableHasColumn($conn, 'number_of_payments');

if ($supportsComputedColumns) {
    $stmt = $conn->prepare("
        INSERT INTO loan_requests (
            borrower_id,
            collector_id,
            principal_amount,
            interest_rate,
            loan_term,
            payment_frequency,
            total_amount,
            installment_amount,
            number_of_payments,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
} else {
    $stmt = $conn->prepare("
        INSERT INTO loan_requests (borrower_id, collector_id, principal_amount, status)
        VALUES (?, ?, ?, 'pending')
    ");
}

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to create reloan request'
    ]);
    exit;
}

if ($supportsComputedColumns) {
    $stmt->bind_param(
        'iiddisddi',
        $borrowerId,
        $collectorId,
        $computedLoan['principal_amount'],
        $computedLoan['interest_rate'],
        $computedLoan['loan_term'],
        $computedLoan['payment_frequency'],
        $computedLoan['total_amount'],
        $computedLoan['installment_amount'],
        $computedLoan['number_of_payments']
    );
} else {
    $stmt->bind_param('iid', $borrowerId, $collectorId, $computedLoan['principal_amount']);
}
$created = $stmt->execute();
$requestId = (int)$conn->insert_id;
$stmt->close();

if (!$created) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to create reloan request'
    ]);
    exit;
}

create_notification(
    $conn,
    'New reloan request submitted',
    ($borrower['fullname'] ?? 'Borrower') . ' submitted a reloan request for PHP ' . number_format($loanAmount, 2) . '.',
    'reloan_request',
    $requestId
);

echo json_encode([
    'status' => 'success',
    'message' => 'Reloan request submitted successfully',
    'request_id' => $requestId,
    'loan_amount' => $computedLoan['principal_amount'],
    'principal_amount' => $computedLoan['principal_amount'],
    'interest_rate' => $computedLoan['interest_rate'],
    'loan_term' => $computedLoan['loan_term'],
    'payment_frequency' => $computedLoan['payment_frequency'],
    'interest' => $computedLoan['interest'],
    'total_payable' => $computedLoan['total_payable'],
    'total_amount' => $computedLoan['total_amount'],
    'number_of_payments' => $computedLoan['number_of_payments'],
    'installment_amount' => $computedLoan['installment_amount'],
    'payment_per_schedule' => $computedLoan['payment_per_schedule']
]);
