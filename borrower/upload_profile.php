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
if ($borrowerId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'borrower_id required'
    ]);
    exit;
}

$file = $_FILES['profile_image'] ?? null;
if (!isset($_FILES['profile_image']) || !$file || !isset($file['tmp_name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No file uploaded'
    ]);
    exit;
}

if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
    echo json_encode([
        'status' => 'error',
        'message' => 'File too large'
    ]);
    exit;
}

$allowedExtensions = ['jpg', 'jpeg', 'png'];
$fileExtension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
$imageInfo = @getimagesize($file['tmp_name']);
$allowedMimeTypes = ['image/jpeg', 'image/png'];
$mimeType = is_array($imageInfo) ? strtolower((string)($imageInfo['mime'] ?? '')) : '';

if (!$imageInfo || !in_array($mimeType, $allowedMimeTypes, true) || !in_array($fileExtension, $allowedExtensions, true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid file type'
    ]);
    exit;
}

try {
    $borrower = borrowerFetchUserRecord($conn, $borrowerId);
    if (!$borrower) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Borrower not found'
        ]);
        exit;
    }

    $uploadDir = __DIR__ . '/../Profiles/';
    if (!file_exists($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new Exception('Failed to create profile storage folder');
    }

    $fileName = 'profile_' . $borrowerId . '.jpg';
    $filePath = $uploadDir . $fileName;
    $relativePath = 'Profiles/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to save profile image'
        ]);
        exit;
    }

    $stmt = $conn->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
    if (!$stmt) {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        throw new Exception('Failed to update borrower profile image');
    }

    $stmt->bind_param('si', $relativePath, $borrowerId);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        throw new Exception('Failed to update borrower profile image');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile image uploaded',
        'image_path' => $relativePath,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
