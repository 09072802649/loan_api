<?php
include 'auth_check.php';
include "../db_connect.php";

/* SECURITY: Only admins can reach this file (auth_check already validates session) */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_users.php");
    exit();
}

$borrower_id = intval($_POST['borrower_id']);
$collector_id = intval($_POST['collector_id']);

/* VALIDATION: Ensure the selected collector actually exists and has the correct role */
$check = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'collector'");
$check->bind_param("i", $collector_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    header("Location: manage_users.php?error=invalid_collector");
    exit();
}

/* ENSURE borrower is not assigning themselves */
if ($borrower_id === $collector_id) {
    header("Location: manage_users.php?error=self_assign");
    exit();
}

/* UPDATE: Assign the collector to the borrower */
$stmt = $conn->prepare("UPDATE users SET collector_id = ? WHERE id = ? AND role = 'borrower'");
$stmt->bind_param("ii", $collector_id, $borrower_id);
$stmt->execute();

header("Location: manage_users.php?success=1");
exit();
?>
