<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(["status" => "missing_fields"]);
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $storedPassword = (string)($row['password'] ?? '');
        $passwordMatches = password_verify($password, $storedPassword) || $password === $storedPassword;

        if ($passwordMatches) {
            echo json_encode([
                "status"   => "success",
                "role"     => $row['role'],
                "id"       => $row['id'],
                "name"     => $row['fullname'],
                "fullname" => $row['fullname']
            ]);
        } else {
            echo json_encode(["status" => "wrong_password"]);
        }
    } else {
        echo json_encode(["status" => "user_not_found"]);
    }
} else {
    echo json_encode(["status" => "invalid_request"]);
}
?>
