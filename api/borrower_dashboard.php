<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$borrower_id = 0;
if (isset($_GET['borrower_id'])) {
    $borrower_id = (int)$_GET['borrower_id'];
} elseif (isset($_POST['borrower_id'])) {
    $borrower_id = (int)$_POST['borrower_id'];
} elseif (isset($input['borrower_id'])) {
    $borrower_id = (int)$input['borrower_id'];
}

if ($borrower_id <= 0) {
    echo json_encode(["status" => "error", "message" => "borrower_id is required"]);
    exit;
}

error_log("Borrower ID received: " . $borrower_id);

echo json_encode($response);
exit;

error_log("Borrower Dashboard Request: " . $borrower_id);

$stmt = $conn->prepare("SELECT * FROM loans WHERE borrower_id = ? ORDER BY id DESC LIMIT 1");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $loan = $result->fetch_assoc();
    echo json_encode([
        "status" => "success",
        "loan" => $loan
    ]);
} else {
    echo json_encode([
        "status" => "no_loan"
    ]);
}

$stmt->close();
exit;
