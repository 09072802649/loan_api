<?php

require_once __DIR__ . '/loan_helper.php';

function loanRequestsTableHasColumn(mysqli $conn, string $columnName): bool
{
    $escapedColumnName = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM loan_requests LIKE '" . $escapedColumnName . "'");
    $columnExists = $result instanceof mysqli_result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->free();
    }

    return $columnExists;
}

function ensureLoanRequestsComputedColumns(mysqli $conn): bool
{
    $columnDefinitions = [
        'interest_rate' => "ALTER TABLE loan_requests ADD COLUMN interest_rate DECIMAL(10,2) NULL AFTER principal_amount",
        'loan_term' => "ALTER TABLE loan_requests ADD COLUMN loan_term INT(11) NULL AFTER interest_rate",
        'payment_frequency' => "ALTER TABLE loan_requests ADD COLUMN payment_frequency ENUM('daily','weekly','monthly') NULL AFTER loan_term",
        'total_amount' => "ALTER TABLE loan_requests ADD COLUMN total_amount DECIMAL(10,2) NULL AFTER payment_frequency",
        'installment_amount' => "ALTER TABLE loan_requests ADD COLUMN installment_amount DECIMAL(10,2) NULL AFTER total_amount",
        'number_of_payments' => "ALTER TABLE loan_requests ADD COLUMN number_of_payments INT(11) NULL AFTER installment_amount",
    ];

    foreach ($columnDefinitions as $columnName => $sql) {
        if (loanRequestsTableHasColumn($conn, $columnName)) {
            continue;
        }

        if ($conn->query($sql) !== true) {
            return false;
        }
    }

    return true;
}

function ensureLoanRequestsTable(mysqli $conn): bool
{
    static $ensured = false;

    if ($ensured) {
        return true;
    }

    $sql = "CREATE TABLE IF NOT EXISTS loan_requests (
        id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        borrewer_id INT(11) NOT NULL,
        collector_id INT(11) NOT NULL,
        principal_amount DECIMAL(12,2) NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $ensured = $conn->query($sql) === true;
    if (!$ensured) {
        return false;
    }

    $ensured = ensureLoanRequestsComputedColumns($conn);
    return $ensured;
}

function fetchLatestCompletedLoanTemplate(mysqli $conn, int $borrowerId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            id,
            collector_id,
            interest_rate,
            loan_term,
            payment_frequency
        FROM loans
        WHERE borrower_id = ?
          AND status = 'completed'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $borrowerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $loan = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $loan ?: null;
}

function fetchLatestBorrowerLoan(mysqli $conn, int $borrowerId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            id,
            collector_id,
            interest_rate,
            loan_term,
            payment_frequency,
            status
        FROM loans
        WHERE borrower_id = ?
        ORDER BY
            CASE
                WHEN status = 'ongoing' THEN 0
                WHEN status = 'approved' THEN 1
                WHEN status = 'pending' THEN 2
                WHEN status = 'completed' THEN 3
                ELSE 4
            END,
            created_at DESC,
            id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $borrowerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $loan = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $loan ?: null;
}

function resolveReloanRequestTerms(array $request, ?array $loanTemplate = null): array
{
    $principalAmount = isset($request['principal_amount'])
        ? (float)$request['principal_amount']
        : (float)($request['loan_amount'] ?? 0);
    $interestRate = array_key_exists('interest_rate', $request) && $request['interest_rate'] !== null && $request['interest_rate'] !== ''
        ? (float)$request['interest_rate']
        : (float)($loanTemplate['interest_rate'] ?? 0);
    $loanTerm = array_key_exists('loan_term', $request) && $request['loan_term'] !== null && $request['loan_term'] !== ''
        ? max(1, (int)$request['loan_term'])
        : max(1, (int)($loanTemplate['loan_term'] ?? 1));
    $paymentFrequencySource = array_key_exists('payment_frequency', $request) && $request['payment_frequency'] !== null && $request['payment_frequency'] !== ''
        ? $request['payment_frequency']
        : ($loanTemplate['payment_frequency'] ?? 'monthly');
    $paymentFrequency = normalizeLoanPaymentFrequency($paymentFrequencySource);
    $principalAmount = round(max(0, $principalAmount), 2);
    $interestRate = round(max(0, $interestRate), 2);
    $computedLoan = computeLoanFlatInterest($principalAmount, $interestRate, $loanTerm, $paymentFrequency);

    return [
        'principal_amount' => $principalAmount,
        'interest_rate' => $interestRate,
        'loan_term' => $loanTerm,
        'payment_frequency' => $paymentFrequency,
        'interest' => $computedLoan['interest'],
        'total_payable' => $computedLoan['total_payable'],
        'total_amount' => $computedLoan['total_payable'],
        'installment_amount' => $computedLoan['payment_per_schedule'],
        'payment_per_schedule' => $computedLoan['payment_per_schedule'],
        'number_of_payments' => $computedLoan['number_of_payments'],
    ];
}


