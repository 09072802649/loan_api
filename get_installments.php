<?php
header('Content-Type: application/json');
require_once "db_connect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

$borrowerId = isset($_POST['borrower_id']) ? (int)$_POST['borrower_id'] : 0;
$loanId = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0;

if ($borrowerId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing borrower_id"
    ]);
    exit;
}

if ($loanId <= 0) {
    $loanLookup = $conn->prepare(
        "SELECT id
         FROM loans
         WHERE borrower_id = ?
         ORDER BY
            CASE
                WHEN status = 'ongoing' THEN 0
                WHEN status = 'approved' THEN 1
                WHEN status = 'completed' THEN 2
                ELSE 3
            END,
            created_at DESC,
            id DESC
         LIMIT 1"
    );

    if (!$loanLookup) {
        echo json_encode([
            "status" => "error",
            "message" => "Prepare failed: " . $conn->error
        ]);
        exit;
    }

    $loanLookup->bind_param("i", $borrowerId);
    $loanLookup->execute();
    $loanResult = $loanLookup->get_result();
    $loanRow = $loanResult ? $loanResult->fetch_assoc() : null;
    $loanLookup->close();

    if (!$loanRow) {
        echo json_encode([
            "status" => "error",
            "message" => "No loan found for this borrower."
        ]);
        exit;
    }

    $loanId = (int)$loanRow['id'];
}

require_once "loan_helper.php";

$sql = "SELECT
            li.id,
            li.loan_id,
            li.installment_number,
            li.due_date,
            li.amount,
            li.status,
            l.principal_amount,
            l.interest_rate,
            l.loan_term,
            l.payment_frequency,
            l.total_amount,
            l.installment_amount AS stored_installment_amount,
            l.balance
        FROM loan_installments li
        INNER JOIN loans l ON li.loan_id = l.id
        WHERE li.loan_id = ?
          AND l.borrower_id = ?
        ORDER BY li.installment_number ASC, li.due_date ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("ii", $loanId, $borrowerId);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$installments = [];

while ($row = $result->fetch_assoc()) {
    $loanDetails = buildLoanComputationDetails($row);

    // Prefer stored installment from loans table when available
    $storedInstallment = isset($row['stored_installment_amount']) && is_numeric($row['stored_installment_amount'])
        ? (float)$row['stored_installment_amount']
        : $loanDetails['payment_per_schedule'];

    $installments[] = [
        "id" => $row['id'],
        "loan_id" => $row['loan_id'],
        "installment_number" => $row['installment_number'],
        "due_date" => $row['due_date'],
        "amount" => $storedInstallment,
        "status" => $row['status']
    ];
}

$stmt->close();

echo json_encode([
    "status" => "success",
    "loan_id" => $loanId,
    "installments" => $installments
]);
?>
