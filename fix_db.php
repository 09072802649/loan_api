<?php
require_once "db_connect.php";

$conn->query("ALTER TABLE loans ADD COLUMN balance DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount") or die($conn->error);
$conn->query("ALTER TABLE loans MODIFY status ENUM('pending','approved','rejected','ongoing','paid','completed') DEFAULT 'pending'") or die($conn->error);

// Retroactively fix the missing borrower for Saturinas Rica Mhay
$dummy_email = "09123456789@borrower.local";
$dummy_pwd = password_hash("123456", PASSWORD_DEFAULT);
$conn->query("INSERT INTO users (fullname, phone, address, role, email, password) VALUES ('Saturinas Rica Mhay', '09123456789', 'Kay Talise Street', 'borrower', '$dummy_email', '$dummy_pwd')");
$new_user_id = $conn->insert_id;

$conn->query("UPDATE loans SET borrower_id = $new_user_id, balance = total_amount WHERE id = 7");

echo "Schema updated and past loan fixed!";
?>
