<?php
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/borrower_profile_helper.php';

$borrowerId = borrowerRequestInt('borrower_id');
if ($borrowerId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'borrower_id required'
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

    echo json_encode([
        'status' => 'success',
        'data' => [
            'fullname' => (string)($borrower['fullname'] ?? ''),
            'email' => (string)($borrower['email'] ?? ''),
            'phone' => (string)($borrower['phone'] ?? ''),
            'address' => (string)($borrower['address'] ?? ''),
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

