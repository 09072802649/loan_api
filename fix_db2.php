<?php
require_once "db_connect.php";

$conn->query("ALTER TABLE loans MODIFY status ENUM('pending','approved','rejected','ongoing','paid','completed') DEFAULT 'pending'") or die($conn->error);

// Retroactively fix the missing borrower for Saturinas Rica Mhay
$dummy_email = "09123456789@borrower.local";
$dummy_pwd = password_hash("123456", PASSWORD_DEFAULT);

// Check if she already exists to prevent duplicate inserts on multiple reloads
$res = $conn->query("SELECT id FROM users WHERE phone = '09123456789'");
if ($res->num_rows == 0) {
    if ($conn->query("INSERT INTO users (fullname, phone, address, role, email, password) VALUES ('Saturinas Rica Mhay', '09123456789', 'Kay Talise Street', 'borrower', '$dummy_email', '$dummy_pwd')")) {
        $new_user_id = $conn->insert_id;
        $conn->query("UPDATE loans SET borrower_id = $new_user_id WHERE id = 7");
        echo "Retroactive fix applied successfully for Saturinas Rica Mhay!";
    }
    else {
        echo "Insert failed: " . $conn->error;
    }
}
else {
    $row = $res->fetch_assoc();
    $conn->query("UPDATE loans SET borrower_id = " . $row['id'] . " WHERE id = 7");
    echo "User already exists. Link updated!";
}
?>
