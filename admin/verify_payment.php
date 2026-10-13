<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once "../db_connect.php";
require_once "../notification_helper.php";
require_once "../payment_helper.php";

ensure_notifications_table($conn);
ensurePaymentStatusColumn($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$paymentId = isset($input['payment_id']) ? (int)$input['payment_id'] : 0;
$action = strtolower(trim((string)($input['action'] ?? '')));

if ($paymentId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid payment verification request'
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $payment = getPaymentApprovalContext($conn, $paymentId);
    if (($payment['status'] ?? '') !== 'pending') {
        throw new Exception('Payment has already been verified');
    }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';

    if ($newStatus === 'approved') {
        finalizeApprovedPayment($conn, $paymentId, false);
    } else {
        $updatePaymentStmt = $conn->prepare("UPDATE payments SET status = 'rejected' WHERE id = ? AND status = 'pending'");
        if (!$updatePaymentStmt) {
            throw new Exception('Failed to update payment status');
        }
        $updatePaymentStmt->bind_param('i', $paymentId);
        if (!$updatePaymentStmt->execute() || $updatePaymentStmt->affected_rows !== 1) {
            $updatePaymentStmt->close();
            throw new Exception('Failed to update payment status');
        }
        $updatePaymentStmt->close();

        create_notification(
            $conn,
            'Payment rejected',
            'Payment for ' . (($payment['borrower_name'] ?? '') ?: 'Borrower') . ' was rejected.',
            'payment_recorded',
            $paymentId
        );
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => $newStatus === 'approved'
            ? 'Payment approved successfully'
            : 'Payment rejected successfully'
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
