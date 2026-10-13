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
    $profile = borrowerFetchProfilePayload($conn, $borrowerId);
    if (!$profile) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Borrower not found'
        ]);
        exit;
    }

    $data = [
        'fullname' => $profile['fullname'],
        'email' => $profile['email'],
        'phone' => $profile['phone'],
        'address' => $profile['address'],
        'loan_account_number' => $profile['loan_account_number'],
        'status' => $profile['status'],
        'profile_image' => $profile['profile_image'],
    ];

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'fullname' => $data['fullname'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'address' => $data['address'],
        'loan_account_number' => $data['loan_account_number'],
        'status_label' => $data['status'],
        'profile_status' => $data['status'],
        'profile_image' => $data['profile_image'],
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
