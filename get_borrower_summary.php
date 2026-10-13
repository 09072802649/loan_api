<?php
header('Content-Type: application/json');

require_once "db_connect.php";
require_once "loan_account_helper.php";
require_once "payment_helper.php";

ensurePaymentStatusColumn($conn);
ensureLoanAccountNumberColumn($conn);

// ── 1. Validate borrower_id ───────────────────────────────────────────────────
$borrower_id = 0;
if (isset($_GET['borrower_id'])) {
    $borrower_id = intval($_GET['borrower_id']);
} elseif (isset($_POST['borrower_id'])) {
    $borrower_id = intval($_POST['borrower_id']);
}

if ($borrower_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid borrower ID"]);
    exit;
}

error_log("Borrower Summary API called for borrower_id=" . $borrower_id);

// ── 2. Fetch user profile (for email / phone / address) ──────────────────────
$uStmt = $conn->prepare("SELECT fullname, email, phone, address FROM users WHERE id = ? LIMIT 1");
$user  = null;
if ($uStmt) {
    $uStmt->bind_param("i", $borrower_id);
    $uStmt->execute();
    $uRes = $uStmt->get_result();
    $user = $uRes ? $uRes->fetch_assoc() : null;
    $uStmt->close();
}

// ── 3. Get latest active loan ─────────────────────────────────────────────────
$lStmt = $conn->prepare("
    SELECT *
    FROM loans
    WHERE borrower_id = ?
      AND status != 'completed'
    ORDER BY
        CASE
            WHEN status = 'ongoing'  THEN 0
            WHEN status = 'approved' THEN 1
            WHEN status = 'pending'  THEN 2
            ELSE 3
        END,
        id DESC
    LIMIT 1
");

$loan = null;
if ($lStmt) {
    $lStmt->bind_param("i", $borrower_id);
    $lStmt->execute();
    $lRes = $lStmt->get_result();
    $loan = ($lRes && $lRes->num_rows > 0) ? $lRes->fetch_assoc() : null;
    $lStmt->close();
}

// Orphan-loan fallback: loan linked only by borrower_name when borrower_id is null
if (!$loan && $user) {
    $rStmt = $conn->prepare("
        SELECT * FROM loans
        WHERE (borrower_id IS NULL OR borrower_id = 0)
          AND borrower_name = ?
          AND status != 'completed'
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($rStmt) {
        $rStmt->bind_param("s", $user['fullname']);
        $rStmt->execute();
        $rRes  = $rStmt->get_result();
        $loan  = ($rRes && $rRes->num_rows > 0) ? $rRes->fetch_assoc() : null;
        $rStmt->close();

        if ($loan) {
            // Auto-repair: link orphan loan to this borrower
            $fix = $conn->prepare("UPDATE loans SET borrower_id = ? WHERE id = ?");
            if ($fix) {
                $loanIdFix = (int)$loan['id'];
                $fix->bind_param("ii", $borrower_id, $loanIdFix);
                $fix->execute();
                $fix->close();
            }
        }
    }
}

if (!$loan) {
    echo json_encode([
        "status"        => "no_loan",
        "borrower_name" => $user['fullname'] ?? '',
        "email"         => $user['email']    ?? '',
        "phone"         => $user['phone']    ?? '',
        "address"       => $user['address']  ?? '',
        "message"       => "No active loan available",
    ]);
    exit;
}

error_log("Loan summary API raw row: " . json_encode($loan));

$loan_id = (int)$loan['id'];

// ── 4. Amount paid (approved payments only) ───────────────────────────────────
$pStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_paid, COUNT(*) AS paid_count
    FROM payments
    WHERE loan_id = ? AND status = 'approved'
");
$total_paid        = 0.0;
$paid_installments = 0;
if ($pStmt) {
    $pStmt->bind_param("i", $loan_id);
    $pStmt->execute();
    $pRow = $pStmt->get_result()->fetch_assoc();
    $total_paid        = (float)($pRow['total_paid'] ?? 0);
    $paid_installments = (int)($pRow['paid_count']   ?? 0);
    $pStmt->close();
}

// ── 5. Total installments (from loan_installments table) ─────────────────────
$tiStmt = $conn->prepare("
    SELECT COUNT(*) AS total_installments FROM loan_installments WHERE loan_id = ?
");
$total_installments = 0;
if ($tiStmt) {
    $tiStmt->bind_param("i", $loan_id);
    $tiStmt->execute();
    $tiRow = $tiStmt->get_result()->fetch_assoc();
    $total_installments = (int)($tiRow['total_installments'] ?? 0);
    $tiStmt->close();
}

// ── 6. Remaining installments (pending rows) ──────────────────────────────────
$riStmt = $conn->prepare("
    SELECT COUNT(*) AS remaining FROM loan_installments
    WHERE loan_id = ? AND status = 'pending'
");
$remaining_installments = 0;
if ($riStmt) {
    $riStmt->bind_param("i", $loan_id);
    $riStmt->execute();
    $riRow = $riStmt->get_result()->fetch_assoc();
    $remaining_installments = (int)($riRow['remaining'] ?? 0);
    $riStmt->close();
}

// ── 7. Resolve loan values with fallback computation ──────────────────────────
$principal      = (float)($loan['principal_amount'] ?? 0);
$interestRate   = (float)($loan['interest_rate']    ?? 0);
$loanTerm       = (int)  ($loan['loan_term']         ?? 0);
$frequency      = (string)($loan['payment_frequency'] ?? '');

$storedTotal    = (float)($loan['total_amount'] ?? 0);
$total_payable  = $storedTotal > 0
    ? $storedTotal
    : round($principal + $principal * $interestRate / 100, 2);

$storedInstall  = (float)($loan['installment_amount'] ?? 0);
$installment    = $storedInstall > 0
    ? $storedInstall
    : ($total_installments > 0 ? round($total_payable / $total_installments, 2) : 0.0);

// Remaining balance: stored balance preferred; fall back to total - paid
$storedBalance      = (float)($loan['balance'] ?? 0);
$remaining_balance  = $storedBalance > 0
    ? $storedBalance
    : round(max(0, $total_payable - $total_paid), 2);

// Loan account number
$loanAccountNumber = resolveLoanAccountNumber([
    'loan_account_number' => $loan['loan_account_number'] ?? '',
    'loan_id'             => $loan_id,
]);

// Account status label
$loanStatus    = strtolower(trim((string)($loan['status'] ?? '')));
$accountStatus = "No Active Loan";
if ($loanStatus === 'ongoing' || $loanStatus === 'approved') {
    $accountStatus = "Active";
} elseif ($loanStatus !== '') {
    $accountStatus = ucwords(str_replace('_', ' ', $loanStatus));
}

$borrower_name = $loan['borrower_name'] ?? ($user['fullname'] ?? '');
$progress      = $total_installments > 0 ? round($paid_installments / $total_installments, 4) : 0;

// ── 8. Return API response ────────────────────────────────────────────────────
echo json_encode([
    "status" => "success",

    // ── Android dashboard required fields ──
    "borrower_name"          => $borrower_name,
    "loan_account_number"    => $loanAccountNumber,
    "loanAccountNumber"      => $loanAccountNumber,
    "principal"              => round($principal, 2),
    "interest_rate"          => $interestRate,
    "total_payable"          => round($total_payable, 2),
    "installment_amount"     => round($installment, 2),
    "amount_paid"            => round($total_paid, 2),
    "remaining_balance"      => round($remaining_balance, 2),
    "balance"                => round($remaining_balance, 2),
    "remaining_installments" => $remaining_installments,
    "total_installments"     => $total_installments,
    "paid_installments"      => $paid_installments,
    "loan_term"              => $loanTerm,
    "payment_frequency"      => $frequency,
    "progress"               => $progress,
    "account_status"         => $accountStatus,

    // ── Legacy / other clients ──
    "fullname"            => $user['fullname'] ?? $borrower_name,
    "email"               => $user['email']    ?? '',
    "phone"               => $user['phone']    ?? '',
    "address"             => $user['address']  ?? '',
    "total_loan"          => round($principal, 2),
    "installment"         => round($installment, 2),
    "frequency"           => strtolower($frequency),
    "remaining_balance"   => round($remaining_balance, 2),

    // ── data sub-object (backward compat) ──
    "data" => [
        "borrower_id"            => $borrower_id,
        "borrower_name"          => $borrower_name,
        "name"                   => $borrower_name,
        "fullname"               => $user['fullname'] ?? $borrower_name,
        "email"                  => $user['email']    ?? '',
        "phone"                  => $user['phone']    ?? '',
        "address"                => $user['address']  ?? '',
        "loan_id"                => $loan_id,
        "loan_account_number"    => $loanAccountNumber,
        "loanAccountNumber"      => $loanAccountNumber,
        "account_status"         => $accountStatus,
        "principal"              => round($principal, 2),
        "principal_amount"       => round($principal, 2),
        "loan_amount"            => round($principal, 2),
        "total_loan"             => round($principal, 2),
        "interest_rate"          => $interestRate,
        "total_payable"          => round($total_payable, 2),
        "total_amount"           => round($total_payable, 2),
        "installment_amount"     => round($installment, 2),
        "installment"            => round($installment, 2),
        "payment_per_schedule"   => round($installment, 2),
        "amount_paid"            => round($total_paid, 2),
        "total_paid"             => round($total_paid, 2),
        "remaining_balance"      => round($remaining_balance, 2),
        "balance"                => round($remaining_balance, 2),
        "remaining_installments" => $remaining_installments,
        "total_installments"     => $total_installments,
        "paid_installments"      => $paid_installments,
        "loan_term"              => $loanTerm,
        "payment_frequency"      => $frequency,
        "frequency"              => strtolower($frequency),
        "progress"               => $progress,
    ],
]);
?>
