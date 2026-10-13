<?php
header('Content-Type: application/json');
require_once "db_connect.php";
require_once "payment_helper.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);
ensurePaymentStatusColumn($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!isset($_POST['borrower_id'])) {
        echo json_encode([
            "status" => "error",
            "message" => "Missing borrower_id"
        ]);
        exit;
    }

    $borrower_id = $_POST['borrower_id'];

    $sql = "SELECT id, amount, payment_date, status FROM payments WHERE borrower_id = ? ORDER BY payment_date DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode([
            "status" => "error",
            "message" => "Prepare failed: " . $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("i", $borrower_id);

    if (!$stmt->execute()) {
        echo json_encode([
            "status" => "error",
            "message" => "Execute failed: " . $stmt->error
        ]);
        exit;
    }

    $result = $stmt->get_result();

    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = [
            'id' => $row['id'],
            'amount' => $row['amount'],
            'payment_date' => $row['payment_date'],
            'status' => $row['status']
        ];
    }

    echo json_encode([
        "status" => "success",
        "payments" => $payments
    ]);

    $stmt->close();
}
else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
}
?>
