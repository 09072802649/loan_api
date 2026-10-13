<?php
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/borrower_profile_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

$borrowerId = borrowerRequestInt('borrower_id');
$oldPassword = (string)borrowerRequestValue('old_password', '');
$newPassword = (string)borrowerRequestValue('new_password', '');

if ($borrowerId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'borrower_id required'
    ]);
    exit;
}

if ($oldPassword === '' || $newPassword === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'old_password and new_password are required'
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT password
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Failed to load borrower account');
    }

    $stmt->bind_param('i', $borrowerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $borrower = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$borrower) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Borrower not found'
        ]);
        exit;
    }

    $storedPassword = (string)($borrower['password'] ?? '');
    $passwordMatches = password_verify($oldPassword, $storedPassword);

    if (!$passwordMatches) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Old password incorrect'
        ]);
        exit;
    }

    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("
        UPDATE users
        SET password = ?
        WHERE id = ?
    ");

    if (!$updateStmt) {
        throw new Exception('Update failed');
    }

    $updateStmt->bind_param('si', $newPasswordHash, $borrowerId);
    $updated = $updateStmt->execute();
    $updateStmt->close();

    if (!$updated) {
        throw new Exception('Update failed');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Password updated successfully'
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Update failed'
    ]);
}
