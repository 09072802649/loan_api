<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);
    exit;
}

require_once "../db_connect.php";
require_once "../remittance_helper.php";

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

$remittanceId = isset($input['remittance_id']) ? (int)$input['remittance_id'] : 0;

if ($remittanceId <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "remittance_id required"
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE remittances
    SET status = 'received',
        received_at = NOW()
    WHERE id = ?
      AND status = 'pending'
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $remittanceId);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$affected = $stmt->affected_rows;
$stmt->close();

if ($affected !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "Remittance not found or already received"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Remittance marked as received"
]);
?>
