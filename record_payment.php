<?php

header("Content-Type: application/json");

include "db_connect.php";
require_once "notification_helper.php";
require_once "payment_helper.php";

ensure_notifications_table($conn);
ensurePaymentStatusColumn($conn);

/* CHECK REQUEST METHOD */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

/* DEBUG LOG */

error_log("POST DATA: " . print_r($_POST, true));

/* READ INPUT */

$borrower_id = isset($_POST['borrower_id']) ? intval($_POST['borrower_id']) : 0;
$loan_id = isset($_POST['loan_id']) ? intval($_POST['loan_id']) : 0;
$collector_id = isset($_POST['collector_id']) ? intval($_POST['collector_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

if ($borrower_id <= 0 || $loan_id <= 0 || $collector_id <= 0 || $amount <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing borrower_id, loan_id, collector_id or amount"
    ]);
    exit;
}

/* CHECK IMAGE */

if (!isset($_FILES['proof_image'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Proof image required"
    ]);
    exit;
}

/* DEBUG */

error_log("PAYMENT DEBUG borrower=$borrower_id loan=$loan_id collector=$collector_id amount=$amount");

/* VALIDATE LOAN */

$stmt = $conn->prepare("SELECT borrower_id FROM loans WHERE id = ?");
$stmt->bind_param("i", $loan_id);
$stmt->execute();

$result = $stmt->get_result();
$loan = $result->fetch_assoc();

if (!$loan) {
    echo json_encode([
        "status" => "error",
        "message" => "Loan not found"
    ]);
    exit;
}

if ((int)$loan['borrower_id'] !== $borrower_id) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid borrower_id or loan_id"
    ]);
    exit;
}

/* PREVENT DUPLICATE PAYMENT */

$check = $conn->prepare("
    SELECT id 
    FROM payments 
    WHERE loan_id=? 
    AND amount=? 
    AND DATE(payment_date)=CURDATE()
");

$check->bind_param("id", $loan_id, $amount);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Duplicate payment detected today"
    ]);
    exit;
}

/* IMAGE UPLOAD */

$uploadDir = "uploads/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$imageName = time() . "_" . basename($_FILES["proof_image"]["name"]);
$targetFile = $uploadDir . $imageName;

if (!move_uploaded_file($_FILES["proof_image"]["tmp_name"], $targetFile)) {
    echo json_encode([
        "status" => "error",
        "message" => "Image upload failed"
    ]);
    exit;
}

/* INSERT PAYMENT */

$conn->begin_transaction();

try {

    $sql = "INSERT INTO payments 
            (loan_id, borrower_id, collector_id, amount, proof_image, payment_date, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'approved')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiids", $loan_id, $borrower_id, $collector_id, $amount, $imageName);
    $stmt->execute();

    $payment_id = $conn->insert_id;

    /* STEP 1 — Increment total_paid */

    $incrStmt = $conn->prepare("
        UPDATE loans
        SET total_paid = total_paid + ?
        WHERE id = ?
    ");
    $incrStmt->bind_param("di", $amount, $loan_id);
    $incrStmt->execute();
    $incrStmt->close();

    /* STEP 2 — Recalculate remaining_balance using actual SUM from payments
       (avoids relying on total_amount which may be 0 / stale)            */

    $conn->query("
        UPDATE loans l
        SET l.remaining_balance = GREATEST(0,
            l.principal_amount - (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                WHERE p.loan_id = l.id
                  AND p.status = 'approved'
            )
        )
        WHERE l.id = $loan_id
    ");

    /* STEP 3 — Mark completed when fully paid */

    $conn->query("
        UPDATE loans
        SET status = 'completed'
        WHERE id = $loan_id
          AND remaining_balance <= 0
          AND status != 'completed'
    ");

    /* INSERT ADMIN NOTIFICATION (user_id = 5) */

    $adminUserId = 5;
    $notifTitle  = 'New Payment Recorded';
    $notifMsg    = 'Payment of ₱' . number_format($amount, 2) . ' received from borrower ID ' . $borrower_id;

    create_notification($conn, $notifTitle, $notifMsg, 'payment_recorded', $payment_id, $adminUserId);

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Payment recorded successfully"
    ]);

}
catch (Exception $e) {

    $conn->rollback();

    if (file_exists($targetFile)) {
        unlink($targetFile);
    }

    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

?>