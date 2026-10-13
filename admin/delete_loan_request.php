<?php
include "../db_connect.php";
require_once "../reloan_helper.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = strtolower(trim((string)($_GET['source'] ?? 'loan')));

ensureLoanRequestsTable($conn);

if ($id > 0) {
    if ($source === 'reloan') {
        $stmt = $conn->prepare("DELETE FROM loan_requests WHERE id = ? AND status = 'rejected'");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // Only delete if it is actually rejected
        $stmt = $conn->prepare("DELETE FROM loans WHERE id=? AND status='rejected'");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

header("Location: loans.php");
exit;
?>
