<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $fullname = $_POST['fullname'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';

    if (empty($fullname) || empty($username) || empty($password) || empty($role)) {
        echo json_encode(["status" => "missing_fields"]);
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (fullname,username,password,role,phone,address)
            VALUES (?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $fullname, $username, $hashedPassword, $role, $phone, $address);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    }
    else {
        echo json_encode(["status" => "error"]);
    }

}
else {
    echo json_encode(["status" => "invalid_request"]);
}
?>
