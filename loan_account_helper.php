<?php

function ensureLoanAccountNumberColumn(mysqli $conn)
{
    static $ensured = false;

    if ($ensured) {
        return true;
    }

    $check = $conn->query("SHOW COLUMNS FROM loans LIKE 'loan_account_number'");
    if ($check && $check->num_rows > 0) {
        $ensured = true;
        return true;
    }

    $sql = "ALTER TABLE loans
            ADD COLUMN loan_account_number VARCHAR(20) NULL
            AFTER id";

    if ($conn->query($sql)) {
        $ensured = true;
        return true;
    }

    return false;
}

function generateLoanAccountNumber(mysqli $conn)
{
    $year = date("Y");
    $prefix = "GC-" . $year . "-";
    $like = $prefix . "%";

    $stmt = $conn->prepare("
        SELECT loan_account_number
        FROM loans
        WHERE loan_account_number LIKE ?
        ORDER BY loan_account_number DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return $prefix . "000001";
    }

    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $nextSequence = 1;
    if (!empty($row['loan_account_number']) && preg_match('/(\d{6})$/', $row['loan_account_number'], $matches)) {
        $nextSequence = ((int)$matches[1]) + 1;
    }

    return $prefix . str_pad((string)$nextSequence, 6, "0", STR_PAD_LEFT);
}

function resolveLoanAccountNumber(array $loan)
{
    $accountNumber = trim((string)($loan['loan_account_number'] ?? ''));

    if ($accountNumber !== '') {
        return $accountNumber;
    }

    if (!empty($loan['loan_account'])) {
        return (string)$loan['loan_account'];
    }

    if (!empty($loan['loan_id'])) {
        return (string)$loan['loan_id'];
    }

    if (!empty($loan['id'])) {
        return (string)$loan['id'];
    }

    return '';
}
?>
