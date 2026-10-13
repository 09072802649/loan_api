<?php

function getNextUnpaidInstallment(mysqli $conn, $loanId)
{
    $loanId = (int)$loanId;
    if ($loanId <= 0) {
        return [
            'installment_number' => null,
            'due_date' => null,
        ];
    }

    $stmt = $conn->prepare("
        SELECT installment_number, due_date
        FROM loan_installments
        WHERE loan_id = ?
          AND status <> 'paid'
        ORDER BY installment_number ASC, due_date ASC
        LIMIT 1
    ");

    if (!$stmt) {
        return [
            'installment_number' => null,
            'due_date' => null,
        ];
    }

    $stmt->bind_param("i", $loanId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return [
        'installment_number' => $row ? (int)$row['installment_number'] : null,
        'due_date' => $row['due_date'] ?? null,
    ];
}

function createLoanInstallmentSchedule(
    mysqli $conn,
    int $loanId,
    int $numberOfPayments,
    string $paymentFrequency,
    string $startDate,
    float $installmentAmount
): bool {
    if ($loanId <= 0 || $numberOfPayments <= 0) {
        return true;
    }

    $dueDate = new DateTime($startDate);
    
    // For daily frequency, ensure the VERY FIRST installment is not on a Sunday
    if ($paymentFrequency === 'daily') {
        while ((int)$dueDate->format('N') === 7) {
            $dueDate->modify('+1 day');
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO loan_installments (loan_id, installment_number, due_date, amount, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");

    if (!$stmt) {
        return false;
    }

    for ($installmentNumber = 1; $installmentNumber <= $numberOfPayments; $installmentNumber++) {
        if ($installmentNumber > 1) {
            switch ($paymentFrequency) {
                case 'daily':
                    $dueDate->modify('+1 day');
                    while ((int)$dueDate->format('N') === 7) {
                        $dueDate->modify('+1 day');
                    }
                    break;
                case 'weekly':
                    $dueDate->modify('+1 week');
                    break;
                case 'monthly':
                default:
                    $dueDate->modify('+1 month');
                    break;
            }
        }

        $dueDateString = $dueDate->format('Y-m-d');
        $stmt->bind_param('iisd', $loanId, $installmentNumber, $dueDateString, $installmentAmount);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
    }

    $stmt->close();
    return true;
}
?>
