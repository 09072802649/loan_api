<?php
header('Content-Type: application/json');

require_once "db_connect.php";
require_once "loan_account_helper.php";

ensureLoanAccountNumberColumn($conn);

$input = json_decode(file_get_contents("php://input"), true) ?? [];

$borrowerId = 0;
if (isset($_GET['borrower_id'])) {
    $borrowerId = (int)$_GET['borrower_id'];
} elseif (isset($_POST['borrower_id'])) {
    $borrowerId = (int)$_POST['borrower_id'];
} elseif (isset($input['borrower_id'])) {
    $borrowerId = (int)$input['borrower_id'];
}

if ($borrowerId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "borrower_id required"
    ]);
    exit;
}

$sql = "
    SELECT
        u.id AS borrower_id,
        u.fullname,
        u.email,
        u.phone,
        u.address,
        l.id AS loan_id,
        l.loan_account_number,
        l.status AS loan_status
    FROM users u
    LEFT JOIN loans l ON l.id = (
        SELECT l2.id
        FROM loans l2
        WHERE l2.borrower_id = u.id
        ORDER BY
            CASE
                WHEN l2.status = 'ongoing' THEN 0
                WHEN l2.status = 'approved' THEN 1
                WHEN l2.status = 'pending' THEN 2
                WHEN l2.status = 'completed' THEN 3
                ELSE 4
            END,
            l2.created_at DESC,
            l2.id DESC
        LIMIT 1
    )
    WHERE u.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $borrowerId);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$profile = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$profile) {
    echo json_encode([
        "status" => "error",
        "message" => "Borrower not found"
    ]);
    exit;
}

$loanAccountNumber = null;
if (!empty($profile['loan_id'])) {
    $resolvedLoanAccountNumber = resolveLoanAccountNumber($profile);
    $loanAccountNumber = $resolvedLoanAccountNumber !== '' ? $resolvedLoanAccountNumber : null;
}

$accountStatus = "No Active Loan";
if (!empty($profile['loan_id'])) {
    $loanStatus = strtolower(trim((string)($profile['loan_status'] ?? '')));

    if ($loanStatus === 'ongoing' || $loanStatus === 'approved') {
        $accountStatus = "Active";
    } elseif ($loanStatus !== '') {
        $accountStatus = ucwords(str_replace('_', ' ', $loanStatus));
    }
}

echo json_encode([
    "status" => "success",
    "fullname" => $profile['fullname'] ?? '',
    "email" => $profile['email'] ?? '',
    "phone" => $profile['phone'] ?? '',
    "address" => $profile['address'] ?? '',
    "loan_account_number" => $loanAccountNumber,
    "account_status" => $accountStatus
]);
?>
