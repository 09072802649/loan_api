<?php
include "../db_connect.php";
require_once "../notification_helper.php";
require_once "../loan_helper.php";
require_once "../installment_helper.php";
require_once "../loan_account_helper.php";
require_once "../reloan_helper.php";

header('Content-Type: application/json');
ensure_notifications_table($conn);
ensureLoanAccountNumberColumn($conn);
ensureLoanRequestsTable($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$requestSource = strtolower(trim((string)($input['request_source'] ?? 'loan')));
$loan_id = isset($input['loan_id']) ? (int)$input['loan_id'] : 0;
$requestId = isset($input['request_id']) ? (int)$input['request_id'] : $loan_id;
$email = trim($input['email'] ?? $input['username'] ?? '');
$password = $input['password'] ?? '';

if (($requestSource === 'loan' && $loan_id <= 0) || ($requestSource === 'reloan' && $requestId <= 0)) {
    echo json_encode([
        'status' => 'error',
        'message' => $requestSource === 'reloan' ? 'Invalid request_id' : 'Invalid loan_id'
    ]);
    exit;
}

if ($requestSource === 'reloan') {
    $reloanComputedRequestColumns = loanRequestsTableHasColumn($conn, 'number_of_payments')
        ? "
            lr.interest_rate,
            lr.loan_term,
            lr.payment_frequency,
            lr.total_amount,
            lr.installment_amount,
            lr.number_of_payments,
        "
        : "
            NULL AS interest_rate,
            NULL AS loan_term,
            NULL AS payment_frequency,
            NULL AS total_amount,
            NULL AS installment_amount,
            NULL AS number_of_payments,
        ";
    $requestStmt = $conn->prepare("
        SELECT
            lr.id,
            lr.borrower_id,
            lr.collector_id,
            lr.principal_amount,
            {$reloanComputedRequestColumns}
            lr.status,
            u.fullname AS borrower_name,
            u.phone AS borrower_contact,
            u.address AS borrower_address
        FROM loan_requests lr
        INNER JOIN users u ON u.id = lr.borrower_id
        WHERE lr.id = ?
          AND lr.status = 'pending'
        LIMIT 1
    ");

    if (!$requestStmt) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to prepare reloan lookup'
        ]);
        exit;
    }

    $requestStmt->bind_param('i', $requestId);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    $reloanRequest = $requestResult ? $requestResult->fetch_assoc() : null;
    $requestStmt->close();

    if (!$reloanRequest) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Reloan request not found or already processed'
        ]);
        exit;
    }

    $loanTemplate = fetchLatestCompletedLoanTemplate($conn, (int)$reloanRequest['borrower_id']);
    if (!$loanTemplate) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Completed loan history not found for this borrower'
        ]);
        exit;
    }

    $resolvedReloan = resolveReloanRequestTerms($reloanRequest, $loanTemplate);
    $principalAmount = (float)$resolvedReloan['principal_amount'];
    if ($principalAmount <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid reloan amount'
        ]);
        exit;
    }
    $interestRate = (float)$resolvedReloan['interest_rate'];
    $loanTerm = (int)$resolvedReloan['loan_term'];
    $paymentFrequency = $resolvedReloan['payment_frequency'];
    $startDate = date('Y-m-d');
    $balance = $resolvedReloan['total_payable'];
    $loanAccountNumber = generateLoanAccountNumber($conn);
    $supportsNumberOfPayments = loanTableHasColumn($conn, 'number_of_payments');

    $conn->begin_transaction();

    try {
        if ($supportsNumberOfPayments) {
            $insertLoan = $conn->prepare("
                INSERT INTO loans (
                    loan_account_number,
                    borrower_id,
                    borrower_name,
                    borrower_contact,
                    borrower_address,
                    collector_id,
                    principal_amount,
                    interest_rate,
                    loan_term,
                    payment_frequency,
                    start_date,
                    total_amount,
                    installment_amount,
                    balance,
                    number_of_payments,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ongoing')
            ");
        } else {
            $insertLoan = $conn->prepare("
                INSERT INTO loans (
                    loan_account_number,
                    borrower_id,
                    borrower_name,
                    borrower_contact,
                    borrower_address,
                    collector_id,
                    principal_amount,
                    interest_rate,
                    loan_term,
                    payment_frequency,
                    start_date,
                    total_amount,
                    installment_amount,
                    balance,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ongoing')
            ");
        }

        if (!$insertLoan) {
            throw new Exception('Failed to create reloan record');
        }

        $borrowerId = (int)$reloanRequest['borrower_id'];
        $collectorId = (int)$reloanRequest['collector_id'];
        $borrowerName = (string)($reloanRequest['borrower_name'] ?? 'Borrower');
        $borrowerContact = (string)($reloanRequest['borrower_contact'] ?? '');
        $borrowerAddress = (string)($reloanRequest['borrower_address'] ?? '');
        $installmentAmount = (float)$resolvedReloan['installment_amount'];
        $totalAmount = (float)$resolvedReloan['total_amount'];
        $numberOfPayments = (int)$resolvedReloan['number_of_payments'];

        if ($supportsNumberOfPayments) {
            $insertLoan->bind_param(
                'sisssiddissdddi',
                $loanAccountNumber,
                $borrowerId,
                $borrowerName,
                $borrowerContact,
                $borrowerAddress,
                $collectorId,
                $principalAmount,
                $interestRate,
                $loanTerm,
                $paymentFrequency,
                $startDate,
                $totalAmount,
                $installmentAmount,
                $balance,
                $numberOfPayments
            );
        } else {
            $insertLoan->bind_param(
                'sisssiddissddd',
                $loanAccountNumber,
                $borrowerId,
                $borrowerName,
                $borrowerContact,
                $borrowerAddress,
                $collectorId,
                $principalAmount,
                $interestRate,
                $loanTerm,
                $paymentFrequency,
                $startDate,
                $totalAmount,
                $installmentAmount,
                $balance
            );
        }

        if (!$insertLoan->execute()) {
            $insertLoan->close();
            throw new Exception('Failed to create reloan record');
        }

        $newLoanId = (int)$conn->insert_id;
        $insertLoan->close();

        if (!createLoanInstallmentSchedule(
            $conn,
            $newLoanId,
            $numberOfPayments,
            $paymentFrequency,
            $startDate,
            $installmentAmount
        )) {
            throw new Exception('Failed to create installment schedule');
        }

        $approveRequest = $conn->prepare("UPDATE loan_requests SET status = 'approved' WHERE id = ? AND status = 'pending'");
        if (!$approveRequest) {
            throw new Exception('Failed to update reloan request');
        }
        $approveRequest->bind_param('i', $requestId);
        if (!$approveRequest->execute() || $approveRequest->affected_rows !== 1) {
            $approveRequest->close();
            throw new Exception('Failed to update reloan request');
        }
        $approveRequest->close();

        create_notification(
            $conn,
            'Re-loan approved',
            $borrowerName . ' re-loan request was approved.',
            'reloan_approved',
            $newLoanId
        );

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Reloan approved successfully',
            'loan_id' => $newLoanId
        ]);
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

if ($email === '' || $password === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Email and password are required'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid email address'
    ]);
    exit;
}

$stmt_loan = $conn->prepare(
    "SELECT principal_amount, interest_rate, loan_term, payment_frequency, start_date,
            borrower_name, borrower_contact, borrower_address, collector_id
     FROM loans
     WHERE id = ? AND status = 'pending'"
);

if (!$stmt_loan) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to prepare loan lookup'
    ]);
    exit;
}

$stmt_loan->bind_param('i', $loan_id);
$stmt_loan->execute();
$res_loan = $stmt_loan->get_result();

if ($res_loan->num_rows === 0) {
    $stmt_loan->close();
    echo json_encode([
        'status' => 'error',
        'message' => 'Loan not found or already processed'
    ]);
    exit;
}

$loan = $res_loan->fetch_assoc();
$stmt_loan->close();

$principal_amount = (float)$loan['principal_amount'];
$interest_rate = (float)$loan['interest_rate'];
$loan_term = max(1, (int)$loan['loan_term']);
$payment_frequency = normalizeLoanPaymentFrequency($loan['payment_frequency'] ?? 'monthly');
$start_date = $loan['start_date'];
$collector_id = (int)$loan['collector_id'];

// Prefer stored values from the loans table. Do not recompute interest/total here.
$total_amount = isset($loan['total_amount']) && is_numeric($loan['total_amount']) ? (float)$loan['total_amount'] : 0.0;
$installment_amount = isset($loan['installment_amount']) && is_numeric($loan['installment_amount']) ? (float)$loan['installment_amount'] : 0.0;
$number_of_payments = loanTableHasColumn($conn, 'number_of_payments') && isset($loan['number_of_payments'])
    ? (int)$loan['number_of_payments']
    : computeLoanNumberOfPayments($loan_term, $payment_frequency);
$balance = isset($loan['balance']) && is_numeric($loan['balance']) ? (float)$loan['balance'] : $total_amount;
$borrower_id = null;

$conn->begin_transaction();

try {
    $name = $loan['borrower_name'] ?? '';
    $phone = $loan['borrower_contact'] ?? '';
    $address = $loan['borrower_address'] ?? '';

    $stmt_check = $conn->prepare('SELECT id FROM users WHERE email = ?');
    if (!$stmt_check) {
        throw new Exception('Failed to validate email');
    }
    $stmt_check->bind_param('s', $email);
    $stmt_check->execute();
    $email_res = $stmt_check->get_result();

    if ($email_res->num_rows > 0) {
        $stmt_check->close();
        $conn->rollback();
        echo json_encode([
            'status' => 'error',
            'message' => 'Username/email already exists'
        ]);
        exit;
    }
    $stmt_check->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt_user = $conn->prepare(
        "INSERT INTO users (fullname, phone, address, role, email, password)
         VALUES (?, ?, ?, 'borrower', ?, ?)"
    );
    if (!$stmt_user) {
        throw new Exception('Failed to create borrower account');
    }
    $stmt_user->bind_param('sssss', $name, $phone, $address, $email, $hashed_password);
    if (!$stmt_user->execute()) {
        throw new Exception('Failed to create borrower account');
    }
    $borrower_id = $conn->insert_id;
    $stmt_user->close();

    if ($collector_id > 0) {
        $stmt_assign = $conn->prepare('UPDATE users SET collector_id = ? WHERE id = ?');
        if (!$stmt_assign) {
            throw new Exception('Failed to assign collector');
        }
        $stmt_assign->bind_param('ii', $collector_id, $borrower_id);
        if (!$stmt_assign->execute()) {
            throw new Exception('Failed to assign collector');
        }
        $stmt_assign->close();
    }

    if (loanTableHasColumn($conn, 'number_of_payments')) {
        $stmt_update = $conn->prepare(
            "UPDATE loans
             SET total_amount = ?, installment_amount = ?, balance = ?, number_of_payments = ?, status = 'ongoing', borrower_id = ?
             WHERE id = ?"
        );
    } else {
        $stmt_update = $conn->prepare(
            "UPDATE loans
             SET total_amount = ?, installment_amount = ?, balance = ?, status = 'ongoing', borrower_id = ?
             WHERE id = ?"
        );
    }
    if (!$stmt_update) {
        throw new Exception('Failed to update loan');
    }
    if (loanTableHasColumn($conn, 'number_of_payments')) {
        $stmt_update->bind_param('dddiii', $total_amount, $installment_amount, $balance, $number_of_payments, $borrower_id, $loan_id);
    } else {
        $stmt_update->bind_param('dddii', $total_amount, $installment_amount, $balance, $borrower_id, $loan_id);
    }
    if (!$stmt_update->execute()) {
        throw new Exception('Failed to update loan');
    }
    $stmt_update->close();

    $due_date = !empty($start_date) ? new DateTime($start_date) : new DateTime();
    $startDateForSchedule = !empty($start_date) ? (new DateTime($start_date))->format('Y-m-d') : (new DateTime())->format('Y-m-d');
    if (!createLoanInstallmentSchedule(
        $conn,
        $loan_id,
        $number_of_payments,
        $payment_frequency,
        $startDateForSchedule,
        (float)$installment_amount
    )) {
        throw new Exception('Failed to create installment schedule');
    }

    create_notification(
        $conn,
        'Loan approved',
        $name . ' has been approved and the borrower account was created.',
        'loan_approved',
        $loan_id
    );

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Account successfully registered'
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
