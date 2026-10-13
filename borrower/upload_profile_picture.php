<?php
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/borrower_profile_helper.php';

function saveBorrowerProfileImageAsJpg(array $file, string $targetPath): bool
{
    $contents = @file_get_contents($file['tmp_name']);
    if ($contents !== false && function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $image = @imagecreatefromstring($contents);
        if ($image !== false) {
            $saved = imagejpeg($image, $targetPath, 90);
            imagedestroy($image);
            if ($saved) {
                return true;
            }
        }
    }

    return move_uploaded_file($file['tmp_name'], $targetPath);
}

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

$file = $_FILES['image_file'] ?? ($_FILES['image'] ?? ($_FILES['profile_image'] ?? null));
if (!$file || !isset($file['tmp_name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode([
        'status' => 'error',
        'message' => 'image_file is required'
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

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid image file'
        ]);
        exit;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/profile';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new Exception('Unable to create upload folder');
    }

    $relativePath = 'uploads/profile/profile_' . $borrowerId . '.jpg';
    $targetPath = dirname(__DIR__) . '/' . $relativePath;

    if (!saveBorrowerProfileImageAsJpg($file, $targetPath)) {
        throw new Exception('Failed to save profile picture');
    }

    if (!borrowerPersistProfileImage($conn, $borrowerId, $relativePath)) {
        if (file_exists($targetPath)) {
            @unlink($targetPath);
        }
        throw new Exception('Failed to update profile image');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile picture updated',
        'data' => [
            'profile_image' => $relativePath
        ],
        'profile_image' => $relativePath,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
