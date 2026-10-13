<?php

function normalizeLoanInterestRate($interest_rate)
{
    return ((float)$interest_rate) / 100;
}

function normalizeLoanPaymentFrequency($frequency)
{
    $normalizedFrequency = strtolower(trim((string)$frequency));

    if (!in_array($normalizedFrequency, ['daily', 'weekly', 'monthly'], true)) {
        return 'monthly';
    }

    return $normalizedFrequency;
}

function computeLoanNumberOfPayments($loan_term, $frequency)
{
    $loanTerm = max(1, (int)$loan_term);
    $normalizedFrequency = normalizeLoanPaymentFrequency($frequency);

    switch ($normalizedFrequency) {
        case 'daily':
            return $loanTerm * 26;
        case 'weekly':
            return $loanTerm * 4;
        case 'monthly':
        default:
            return $loanTerm;
    }
}

function loanTableHasColumn(mysqli $conn, string $columnName): bool
{
    static $columnCache = [];

    if (array_key_exists($columnName, $columnCache)) {
        return $columnCache[$columnName];
    }

    $escapedColumnName = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM loans LIKE '" . $escapedColumnName . "'");
    $columnExists = $result instanceof mysqli_result && $result->num_rows > 0;

    if ($result instanceof mysqli_result) {
        $result->free();
    }

    $columnCache[$columnName] = $columnExists;
    return $columnExists;
}

function computeLoanFlatInterest($loan_amount, $interest_rate, $loan_term, $frequency)
{
    $loanAmount = (float)$loan_amount;
    $loanTerm = max(1, (int)$loan_term);
    $normalizedFrequency = normalizeLoanPaymentFrequency($frequency);
    $numberOfPayments = computeLoanNumberOfPayments($loanTerm, $normalizedFrequency);

    $rate = normalizeLoanInterestRate($interest_rate);
    $interest = round($loanAmount * $rate, 2);
    $totalPayable = round($loanAmount + $interest, 2);
    $paymentPerSchedule = $numberOfPayments > 0
        ? round($totalPayable / $numberOfPayments, 2)
        : 0.0;

    return [
        'interest' => $interest,
        'total_payable' => $totalPayable,
        'number_of_payments' => $numberOfPayments,
        'payment_per_schedule' => $paymentPerSchedule,
    ];
}

function buildLoanComputationDetails(array $loan, $remaining_balance = null, $total_paid = null)
{
    $loanAmount = isset($loan['principal_amount'])
        ? (float)$loan['principal_amount']
        : (float)($loan['loan_amount'] ?? 0);
    $interestRate = (float)($loan['interest_rate'] ?? 0);
    $loanTerm = max(1, (int)($loan['loan_term'] ?? 1));
    $paymentFrequency = normalizeLoanPaymentFrequency($loan['payment_frequency'] ?? 'monthly');
    $computed = computeLoanFlatInterest($loanAmount, $interestRate, $loanTerm, $paymentFrequency);
    $storedTotalPayable = $computed['total_payable'];
    $storedPaymentPerSchedule = $computed['payment_per_schedule'];
    $storedNumberOfPayments = $computed['number_of_payments'];
    $resolvedInterest = $computed['interest'];

    if ($remaining_balance === null) {
        if (array_key_exists('balance', $loan) && $loan['balance'] !== null) {
            $remainingBalance = (float)$loan['balance'];
        } elseif ($total_paid !== null) {
            $remainingBalance = round($storedTotalPayable - (float)$total_paid, 2);
        } else {
            $remainingBalance = $storedTotalPayable;
        }
    } else {
        $remainingBalance = (float)$remaining_balance;
    }

    return [
        'loan_amount' => round($loanAmount, 2),
        'interest_rate' => $interestRate,
        'loan_term' => $loanTerm,
        'payment_frequency' => $paymentFrequency,
        'interest' => $resolvedInterest,
        'total_payable' => $storedTotalPayable,
        'number_of_payments' => $storedNumberOfPayments,
        'payment_per_schedule' => $storedPaymentPerSchedule,
        'remaining_balance' => round($remainingBalance, 2),
    ];
}
?>
