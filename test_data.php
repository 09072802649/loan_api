<?php
require_once "db_connect.php";

$loans = $conn->query("SELECT * FROM loans")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT * FROM users")->fetch_all(MYSQLI_ASSOC);

echo "LOANS:\n";
print_r($loans);
echo "\nUSERS:\n";
print_r($users);
?>
