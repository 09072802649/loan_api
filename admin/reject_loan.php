<?php
include "../db_connect.php";
require_once "../notification_helper.php";
require_once "../reloan_helper.php";

ensure_notifications_table($conn);
ensureLoanRequestsTable($conn);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = strtolower(trim((string)($_GET['source'] ?? 'loan')));

if ($id > 0) {
    if ($source === 'reloan') {
        $borrowerName = 'Borrower';
        $lookup = $conn->prepare("
            SELECT u.fullname AS borrower_name
            FROM loan_requests lr
            INNER JOIN users u ON u.id = lr.borrower_id
            WHERE lr.id = ?
            LIMIT 1
        ");
        if ($lookup) {
            $lookup->bind_param('i', $id);
            $lookup->execute();
            $result = $lookup->get_result();
            $request = $result ? $result->fetch_assoc() : null;
            if (!empty($request['borrower_name'])) {
                $borrowerName = $request['borrower_name'];
            }
            $lookup->close();
        }

        $stmt = $conn->prepare("UPDATE loan_requests SET status = 'rejected' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }

        create_notification(
            $conn,
            'Re-loan rejected',
            $borrowerName . "'s re-loan request was rejected.",
            'reloan_rejected',
            $id
        );

        header("Location: loans.php");
        exit;
    }

    $borrowerName = 'Borrower';
    $lookup = $conn->prepare('SELECT borrower_name FROM loans WHERE id = ? LIMIT 1');
    if ($lookup) {
        $lookup->bind_param('i', $id);
        $lookup->execute();
        $result = $lookup->get_result();
        $loan = $result ? $result->fetch_assoc() : null;
        if (!empty($loan['borrower_name'])) {
            $borrowerName = $loan['borrower_name'];
        }
        $lookup->close();
    }

    $stmt = $conn->prepare("UPDATE loans SET status='rejected' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    create_notification(
        $conn,
        'Loan rejected',
        $borrowerName . "'s loan request was rejected.",
        'loan_rejected',
        $id
    );
}

header("Location: loans.php");
exit;
?>
