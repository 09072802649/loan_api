<?php
require_once "../db_connect.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirect = $_GET['redirect'] ?? 'manage_users.php';

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

if (
    !is_string($redirect) ||
    $redirect === '' ||
    preg_match('/^https?:/i', $redirect) ||
    str_contains($redirect, '..') ||
    str_starts_with($redirect, '/')
) {
    $redirect = 'manage_users.php';
}

header("Location: " . $redirect);
exit();
?>
