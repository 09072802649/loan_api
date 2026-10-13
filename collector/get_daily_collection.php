<?php
session_start();
header('Content-Type: application/json');

require_once "../db_connect.php";

$collectorId = 0;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $collectorId = (int)$_SESSION['user_id'];
}

if ($collectorId <= 0) {
    $collectorId = isset($_GET['collector_id']) ? (int)$_GET['collector_id'] : (int)($_POST['collector_id'] ?? 0);
}

if ($collectorId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "collector_id required"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total_collected,
        COUNT(id) AS transactions,
        COUNT(DISTINCT borrower_id) AS borrowers_paid
    FROM payments
    WHERE collector_id = ?
      AND DATE(payment_date) = CURDATE()
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $collectorId);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$summary = $result ? $result->fetch_assoc() : null;
$stmt->close();

echo json_encode([
    "status" => "success",
    "daily_collection" => round((float)($summary['total_collected'] ?? 0), 2),
    "total_collected" => round((float)($summary['total_collected'] ?? 0), 2),
    "transactions" => (int)($summary['transactions'] ?? 0),
    "borrowers_paid" => (int)($summary['borrowers_paid'] ?? 0),
]);
?>
