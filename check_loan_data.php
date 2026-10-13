<?php
include 'db_connect.php';
$res = $conn->query("SELECT start_date, due_date, status FROM loans WHERE borrower_id = 15 AND status = 'ongoing'");
if ($res) {
    echo json_encode($res->fetch_assoc());
}
?>
