<?php
header('Content-Type: application/json');

require_once "db_connect.php";
require_once "loan_helper.php";
require_once "installment_helper.php";
require_once "loan_account_helper.php";
require_once "notification_helper.php";

ensureLoanAccountNumberColumn($conn);
ensure_notifications_table($conn);

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true) ?? [];

// ── Read required fields from request ────────────────────────────────────────
$borrower_id      = (int)($input['borrower_id']      ?? $_POST['borrower_id']      ?? 0);
$collector_id     = (int)($input['collector_id']     ?? $_POST['collector_id']     ?? 0);
$principal_amount = (float)($input['principal_amount'] ?? $input['loan_amount']
                          ?? $_POST['principal_amount'] ?? $_POST['loan_amount'] ?? 0);
$start_date       = trim($input['start_date'] ?? $_POST['start_date'] ?? date('Y-m-d'));

// Borrower name / contact / address (kept for borrower_name column)
$borrower_name    = trim($input['borrower_name']    ?? $_POST['borrower_name']    ?? '');
$borrower_contact = trim($input['borrower_contact'] ?? $_POST['borrower_contact'] ?? '');
$borrower_address = trim($input['borrower_address'] ?? $_POST['borrower_address'] ?? '');

// ── Basic input validation ────────────────────────────────────────────────────
if ($borrower_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Valid borrower_id is required"]);
    exit;
}

if ($collector_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Valid collector_id is required"]);
    exit;
}

if ($principal_amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Loan amount must be greater than 0"]);
    exit;
}

// ── STEP 1: Detect borrower type (new vs reloan) ──────────────────────────────
$countStmt = $conn->prepare("SELECT COUNT(*) AS loan_count FROM loans WHERE borrower_id = ?");
if (!$countStmt) {
    echo json_encode(["status" => "error", "message" => "DB error: " . $conn->error]);
    exit;
}
$countStmt->bind_param("i", $borrower_id);
$countStmt->execute();
$countRes   = $countStmt->get_result();
$countRow   = $countRes ? $countRes->fetch_assoc() : null;
$loanCount  = $countRow ? (int)$countRow['loan_count'] : 0;
$countStmt->close();

$isNewBorrower = ($loanCount === 0);

// ── STEP 2: Validate loan amount for new borrowers ────────────────────────────
if ($isNewBorrower) {
    if ($principal_amount < 1500 || $principal_amount > 2000) {
        echo json_encode([
            "status"  => "error",
            "message" => "New borrowers can only loan between 1500 and 2000 pesos",
        ]);
        exit;
    }
}

// ── STEP 3: Force fixed GoCash loan settings (override any app values) ────────
$interest_rate     = 20;       // 20% fixed
$loan_term         = 2;        // 2 months fixed
$payment_frequency = "daily";  // daily fixed

// ── STEP 4: Compute loan values ───────────────────────────────────────────────
$interest_amount = round($principal_amount * ($interest_rate / 100), 2);
$total_payable   = round($principal_amount + $interest_amount, 2);
$balance         = $total_payable; // balance starts at full total

// Use the existing helper to compute # of payments and installment amount
$computedLoan    = computeLoanFlatInterest($principal_amount, $interest_rate, $loan_term, $payment_frequency);
$installment_amount = $computedLoan['payment_per_schedule'];
$number_of_payments = $computedLoan['number_of_payments'];

// If borrower_name not sent, look it up from users table
if ($borrower_name === '') {
    $uStmt = $conn->prepare("SELECT fullname FROM users WHERE id = ? LIMIT 1");
    if ($uStmt) {
        $uStmt->bind_param("i", $borrower_id);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        $uRow = $uRes ? $uRes->fetch_assoc() : null;
        $borrower_name = $uRow ? $uRow['fullname'] : '';
        $uStmt->close();
    }
}

// ── STEP 5: Generate loan account number ─────────────────────────────────────
// Format: GC-YYYY-000001  (auto-incremented)
$loanAccountNumber = generateLoanAccountNumber($conn);

// ── STEP 6: Insert loan record ────────────────────────────────────────────────
// status is forced to 'approved' per GoCash rules
$supportsNumberOfPayments = loanTableHasColumn($conn, 'number_of_payments');

if ($supportsNumberOfPayments) {
    $sql = "INSERT INTO loans (
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
} else {
    $sql = "INSERT INTO loans (
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
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
        $principal_amount,
        $interest_rate,
        $loan_term,
        $payment_frequency,
        $start_date,
        $total_payable,
        $installment_amount,
        $balance,
        $number_of_payments
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
        $principal_amount,
        $interest_rate,
        $loan_term,
        $payment_frequency,
        $start_date,
        $total_payable,
        $installment_amount,
        $balance
    );
}

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Execute failed: " . $stmt->error]);
    exit;
}

$loan_id = $conn->insert_id;
$stmt->close();

// ── Notify admin ──────────────────────────────────────────────────────────────
create_notification(
    $conn,
    'New loan created',
    $borrower_name . ' received a new loan of PHP ' . number_format($principal_amount, 2) . '.',
    'loan_request',
    $loan_id
);

// ── STEP 7: Generate installment schedule ─────────────────────────────────────
$startDateForSchedule = (new DateTime($start_date))->format('Y-m-d');
createLoanInstallmentSchedule(
    $conn,
    $loan_id,
    $number_of_payments,
    $payment_frequency,
    $startDateForSchedule,
    (float)$installment_amount
);
// ── STEP 8: Return success response ──────────────────────────────────────────
echo json_encode([
    "status"             => "success",
    "message"            => "Loan created successfully",
    "loan_id"            => $loan_id,
    "loan_account_number"=> $loanAccountNumber,
    "borrower_type"      => $isNewBorrower ? "new" : "reloan",
    "principal"          => round($principal_amount, 2),
    "interest_rate"      => $interest_rate,
    "interest_amount"    => $interest_amount,
    "total_payable"      => $total_payable,
    "balance"            => $balance,
    "installment_amount" => round($installment_amount, 2),
    "loan_term"          => $loan_term,
    "payment_frequency"  => $payment_frequency,
    "number_of_payments" => $number_of_payments,
]);
?>
