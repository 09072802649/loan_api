<?php
include 'db_connect.php';

$email = "admin@gmail.com";
$password = "123456";
$fullname = "System Admin";
$role = "admin";

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $fullname, $email, $hashed_password, $role);

if ($stmt->execute()) {
    echo "Admin created successfully<br>";
    echo "Email: $email<br>";
    echo "Password: $password<br>";
}
else {
    echo "Error: " . $conn->error;
}
?>