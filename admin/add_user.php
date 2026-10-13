<?php
require_once 'auth_check.php';
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_users.php');
    exit();
}

header('Content-Type: application/json');

$allowedRoles = ['admin', 'collector', 'borrower'];
$allowedStatuses = ['active', 'inactive'];

$fullname = trim((string)($_POST['fullname'] ?? ''));
$username = trim((string)($_POST['username'] ?? ''));
$plainPassword = (string)($_POST['password'] ?? '');
$phone = trim((string)($_POST['phone'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$role = strtolower(trim((string)($_POST['role'] ?? '')));
$status = strtolower(trim((string)($_POST['status'] ?? 'active')));

if ($fullname === '' || $username === '' || $plainPassword === '' || $role === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please complete all required fields.'
    ]);
    exit();
}

if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Username must be at least 3 characters and contain only letters, numbers, and underscores.'
    ]);
    exit();
}

if ($phone !== '' && !preg_match('/^[0-9]+$/', $phone)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Phone number must contain numbers only.'
    ]);
    exit();
}

if (strlen($plainPassword) < 6) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Password must be at least 6 characters long.'
    ]);
    exit();
}

$password = password_hash($plainPassword, PASSWORD_DEFAULT);


if (!in_array($role, $allowedRoles, true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid user role selected.'
    ]);
    exit();
}

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'active';
}

$userCheck = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
if (!$userCheck) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to validate this user right now.'
    ]);
    exit();
}

$userCheck->bind_param('s', $username);
$userCheck->execute();
$existingUser = $userCheck->get_result();
$duplicate = $existingUser ? $existingUser->fetch_assoc() : null;
$userCheck->close();

if ($duplicate) {
    echo json_encode([
        'status' => 'error',
        'message' => 'That username is already in use.'
    ]);
    exit();
}

$columns = [];
$columnCheck = $conn->query("SHOW COLUMNS FROM users");
if ($columnCheck) {
    while ($column = $columnCheck->fetch_assoc()) {
        $columns[] = strtolower((string)($column['Field'] ?? ''));
    }
}

$insertColumns = ['fullname', 'username', 'password', 'role'];
$insertValues = [
    $fullname,
    $username,
    $password,
    $role,
];
$paramTypes = 'ssss';

if (in_array('phone', $columns, true)) {
    $insertColumns[] = 'phone';
    $insertValues[] = $phone;
    $paramTypes .= 's';
}

if (in_array('address', $columns, true)) {
    $insertColumns[] = 'address';
    $insertValues[] = $address;
    $paramTypes .= 's';
}

if (in_array('status', $columns, true)) {
    $insertColumns[] = 'status';
    $insertValues[] = $status;
    $paramTypes .= 's';
}

$placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
$sql = "INSERT INTO users (" . implode(', ', $insertColumns) . ") VALUES (" . $placeholders . ")";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to create this user right now.'
    ]);
    exit();
}

$bindValues = array_merge([$paramTypes], $insertValues);
$bindReferences = [];
foreach ($bindValues as $index => $value) {
    $bindReferences[$index] = & $bindValues[$index];
}
call_user_func_array([$stmt, 'bind_param'], $bindReferences);

if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to create this user right now.'
    ]);
    exit();
}

$userId = (int)$conn->insert_id;
$stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'User created successfully.',
    'user_id' => $userId,
    'role' => $role
]);
?>
