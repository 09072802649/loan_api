<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);
    exit;
}

require_once "../../db_connect.php";

$allowedRoles = ['admin', 'collector', 'borrower'];
$role = isset($_GET['role']) ? $_GET['role'] : '';
$roleFilter = in_array($role, $allowedRoles, true) ? $role : '';

$sql = "
    SELECT
        u.id,
        u.fullname,
        u.email,
        u.phone,
        u.role,
        l.total_amount,
        l.balance
    FROM users u
    LEFT JOIN (
        SELECT l1.borrower_id, l1.total_amount, l1.balance
        FROM loans l1
        INNER JOIN (
            SELECT borrower_id, MAX(id) AS latest_loan_id
            FROM loans
            WHERE borrower_id IS NOT NULL
            GROUP BY borrower_id
        ) latest ON latest.latest_loan_id = l1.id
    ) l ON l.borrower_id = u.id
";

if ($roleFilter !== '') {
    $sql .= " WHERE u.role = ?";
}

$sql .= " ORDER BY u.id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

if ($roleFilter !== '') {
    $stmt->bind_param("s", $roleFilter);
}

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();
$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = [
        "id" => (int)$row['id'],
        "fullname" => $row['fullname'],
        "email" => $row['email'],
        "phone" => $row['phone'],
        "role" => $row['role'],
        "total_amount" => $row['total_amount'] !== null ? (float)$row['total_amount'] : null,
        "balance" => $row['balance'] !== null ? (float)$row['balance'] : null
    ];
}

$stmt->close();

echo json_encode([
    "status" => "success",
    "users" => $users
]);
?>
