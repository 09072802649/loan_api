<?php
header('Content-Type: application/json');
require_once "db_connect.php";
require_once "payment_helper.php";

ensurePaymentStatusColumn($conn);

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

$sql = "SELECT
            p.id,
            p.loan_id,
            p.amount,
            p.payment_date,
            p.proof_image,
            p.status
        FROM payments p
        INNER JOIN loans l ON p.loan_id = l.id
        WHERE p.loan_id = ?
          AND l.borrower_id = ?
        ORDER BY p.payment_date DESC, p.id DESC";

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
$payments = [];

while ($row = $result->fetch_assoc()) {
    $payments[] = [
        "id" => $row['id'],
        "loan_id" => $row['loan_id'],
        "amount" => $row['amount'],
        "payment_date" => $row['payment_date'],
        "proof_image" => $row['proof_image'],
        "status" => $row['status']
    ];
}

$stmt->close();

echo json_encode([
    "status" => "success",
    "loan_id" => $loanId,
    "payments" => $payments
]);
?>
