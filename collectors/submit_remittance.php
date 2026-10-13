<?php
header('Content-Type: application/json');

require_once "../db_connect.php";
require_once "../payment_helper.php";
require_once "../remittance_helper.php";

ensurePaymentStatusColumn($conn);
ensureRemittanceSchema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$collectorId = isset($input['collector_id']) ? (int)$input['collector_id'] : 0;

if ($collectorId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "collector_id required"
    ]);
    exit;
}

$summaryStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total_amount,
        COUNT(*) AS transaction_count
    FROM payments
    WHERE collector_id = ?
      AND DATE(payment_date) = CURDATE()
      AND status = 'approved'
      AND remitted = 0
");

if (!$summaryStmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$summaryStmt->bind_param("i", $collectorId);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summary = $summaryResult ? $summaryResult->fetch_assoc() : null;
$summaryStmt->close();

$totalAmount = round((float)($summary['total_amount'] ?? 0), 2);
$transactionCount = (int)($summary['transaction_count'] ?? 0);

if ($transactionCount <= 0 || $totalAmount <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "No approved unremitted payments available for today"
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $insertStmt = $conn->prepare("
        INSERT INTO remittances (collector_id, remittance_date, total_amount, transaction_count, status)
        VALUES (?, NOW(), ?, ?, 'pending')
    ");
    if (!$insertStmt) {
        throw new Exception("Failed to create remittance");
    }

    $insertStmt->bind_param("idi", $collectorId, $totalAmount, $transactionCount);
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        throw new Exception("Failed to create remittance");
    }

    $remittanceId = $conn->insert_id;
    $insertStmt->close();

    $updateStmt = $conn->prepare("
        UPDATE payments
        SET remitted = 1,
            remittance_id = ?
        WHERE collector_id = ?
          AND DATE(payment_date) = CURDATE()
          AND status = 'approved'
          AND remitted = 0
    ");
    if (!$updateStmt) {
        throw new Exception("Failed to tag remitted payments");
    }

    $updateStmt->bind_param("ii", $remittanceId, $collectorId);
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        throw new Exception("Failed to tag remitted payments");
    }
    $updateStmt->close();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "remittance_id" => (int)$remittanceId,
        "collector_id" => $collectorId,
        "remittance_date" => date('Y-m-d H:i:s'),
        "total_amount" => $totalAmount,
        "transaction_count" => $transactionCount,
        "status_label" => "pending"
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
