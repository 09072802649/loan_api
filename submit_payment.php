<?php
header('Content-Type: application/json');

require_once "db_connect.php";
require_once "notification_helper.php";
require_once "payment_helper.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

$borrowerId = isset($_POST['borrower_id']) ? (int)$_POST['borrower_id'] : 0;
$loanId$ = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0;
$collectorId = isset($_POST['collector_id']) ? (int)$_POST['collector_id'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;

if ($borrowerId <= 0 || $loanId <= 0 || $collectorId <= 0 || $amount <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing borrower_id, loan_id, collector_id, or amount"
    ]);
    exit;
}

$loanLookup = $conn->prepare("SELECT borrower_id FROM loans WHERE id = ? LIMIT 1");
if (!$loanLookup) {
    echo json_encode([
        "status" => "error",
        "message" => "Unable to validate loan"
    ]);
    exit;
}

$loanLookup->bind_param("i", $loanId);
$loanLookup->execute();
$loanResult = $loanLookup->get_result();
$loanRow = $loanResult ? $loanResult->fetch_assoc() : null;
$loanLookup->close();

if (!$loanRow || (int)$loanRow['borrower_id'] !== $borrowerId) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid borrower_id or loan_id"
    ]);
    exit;
}

if (!isset($_FILES['proof_image'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Image upload failed"
    ]);
    exit;
}

$proofImage = $_FILES['proof_image'];

if (($proofImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode([
        "status" => "error",
        "message" => "Image upload failed"
    ]);
    exit;
}

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'payments' . DIRECTORY_SEPARATOR;

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
    echo json_encode([
        "status" => "error",
        "message" => "Image upload failed"
    ]);
    exit;
}

$filename = time() . "_" . basename($proofImage["name"]);
$targetPath = $uploadDir . $filename;
$storedPath = "uploads/payments/" . $filename;

if (!move_uploaded_file($proofImage["tmp_name"], $targetPath)) {
    echo json_encode([
        "status" => "error",
        "message" => "Image upload failed"
    ]);
    exit;
}

ensurePaymentStatusColumn($conn);
ensure_notifications_table($conn);

$conn->begin_transaction();

try {
    $sql = "INSERT INTO payments (borrower_id, loan_id, collector_id, amount, proof_image, status)
            VALUES (?, ?, ?, ?, ?, 'approved')";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to save payment");
    }

    $stmt->bind_param("iiids", $borrowerId, $loanId, $collectorId, $amount, $storedPath);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Failed to save payment");
    }

    $paymentId = (int)$conn->insert_id;
    $stmt->close();

    finalizeApprovedPayment($conn, $paymentId, true);

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Payment submitted and approved successfully"
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage() ?: "Image upload failed"
    ]);
    exit;
}
?>
