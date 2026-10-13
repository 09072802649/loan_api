<?php

function ensurePaymentStatusColumn(mysqli $conn)
{
    static $ensured = false;

    if ($ensured) {
        return true;
    }

    $check = $conn->query("SHOW COLUMNS FROM payments LIKE 'status'");
    if ($check && $check->num_rows > 0) {
        // Column exists — ensure ENUM includes 'approved' and repair any blank-status rows
        $conn->query("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
        $conn->query("UPDATE payments SET status = 'approved' WHERE status = '' OR status IS NULL");

        // ── Safety-net: heal any loans that are fully paid but still 'ongoing' ──
        // 1. Re-sync total_paid from actual approved payments
        $conn->query("
            UPDATE loans l
            SET l.total_paid = (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                WHERE p.loan_id = l.id AND p.status = 'approved'
            )
        ");
        // 2. Recalculate remaining_balance from principal
        $conn->query("
            UPDATE loans
            SET remaining_balance = GREATEST(0, principal_amount - total_paid)
        ");
        // 3. Mark completed where fully paid
        $conn->query("
            UPDATE loans
            SET status = 'completed'
            WHERE remaining_balance <= 0
              AND status != 'completed'
        ");
        // Keep legacy balance column in sync
        $conn->query("UPDATE loans SET balance = remaining_balance");

        $ensured = true;
        return true;
    }

    $sql = "ALTER TABLE payments
            ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'
            AFTER proof_image";

    if ($conn->query($sql)) {
        $conn->query("UPDATE payments SET status = 'approved'");
        $ensured = true;
        return true;
    }

    return false;
}

function getApprovedPaymentCondition($alias = 'p')
{
    $alias = trim($alias);
    return ($alias !== '' ? $alias . '.' : '') . "status = 'approved'";
}

function getPaymentApprovalContext(mysqli $conn, int $paymentId): array
{
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.loan_id,
            p.borrower_id,
            p.amount,
            p.status,
            u.fullname AS borrower_name
        FROM payments p
        LEFT JOIN users u ON u.id = p.borrower_id
        WHERE p.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('Failed to load payment');
    }

    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$payment) {
        throw new Exception('Payment not found');
    }

    return $payment;
}

function finalizeApprovedPayment(mysqli $conn, int $paymentId, bool $statusAlreadyApproved = false): array
{
    $payment = getPaymentApprovalContext($conn, $paymentId);

    if ($statusAlreadyApproved) {
        if (($payment['status'] ?? '') !== 'approved') {
            throw new Exception('Payment is not marked approved');
        }
    } else {
        if (($payment['status'] ?? '') !== 'pending') {
            throw new Exception('Payment has already been verified');
        }

        $updatePaymentStmt = $conn->prepare("UPDATE payments SET status = 'approved' WHERE id = ? AND status = 'pending'");
        if (!$updatePaymentStmt) {
            throw new Exception('Failed to update payment status');
        }

        $updatePaymentStmt->bind_param('i', $paymentId);
        if (!$updatePaymentStmt->execute() || $updatePaymentStmt->affected_rows !== 1) {
            $updatePaymentStmt->close();
            throw new Exception('Failed to update payment status');
        }
        $updatePaymentStmt->close();

        $payment['status'] = 'approved';
    }

    // ── Re-compute the true remaining balance and status ──
    $amount = (float)$payment['amount'];
    $loanId = (int)$payment['loan_id'];

    // 1. Fetch loan details
    $getLoanStmt = $conn->prepare("
        SELECT principal_amount, interest_rate, loan_term, payment_frequency
        FROM loans WHERE id = ?
    ");
    $getLoanStmt->bind_param('i', $loanId);
    $getLoanStmt->execute();
    $loanRes = $getLoanStmt->get_result();
    $loanData = $loanRes->fetch_assoc();
    $getLoanStmt->close();

    // 2. Fetch total paid so far for this loan (including the payment we just approved)
    $paidStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total_paid
        FROM payments
        WHERE loan_id = ? AND status = 'approved'
    ");
    $paidStmt->bind_param('i', $loanId);
    $paidStmt->execute();
    $paidRes = $paidStmt->get_result();
    $paidData = $paidRes->fetch_assoc();
    $paidStmt->close();

    $total_paid = (float)$paidData['total_paid'];

    // 3. Compute total payable based on GoCash rules
    $principal     = (float)($loanData['principal_amount'] ?? 0);
    $interest_rate = (float)($loanData['interest_rate'] ?? 20.0);
    $interest      = round($principal * ($interest_rate / 100), 2);
    $total_payable = round($principal + $interest, 2);

    // 4. Compute remaining balance
    $remaining_balance = round($total_payable - $total_paid, 2);

    // 5. Add protection
    if ($remaining_balance < 0) {
        $remaining_balance = 0.0;
    }

    // 6. Determine status
    if ($remaining_balance <= 0) {
        $remaining_balance = 0.0;
        $status = 'completed';
    } else {
        $status = 'ongoing';
    }

    // 7a. Increment total_paid on loans
    $incrStmt = $conn->prepare("
        UPDATE loans SET total_paid = total_paid + ? WHERE id = ?
    ");
    if ($incrStmt) {
        $incrStmt->bind_param('di', $amount, $loanId);
        $incrStmt->execute();
        $incrStmt->close();
    }

    // 7b. Recalculate remaining_balance from actual payments sum
    $conn->query("
        UPDATE loans l
        SET l.remaining_balance = GREATEST(0,
            l.principal_amount - (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                WHERE p.loan_id = l.id AND p.status = 'approved'
            )
        )
        WHERE l.id = $loanId
    ");

    // 7c. Keep legacy balance column in sync + set status
    $loanStmt = $conn->prepare("
        UPDATE loans
        SET balance = ?,
            status  = ?
        WHERE id = ?
    ");
    if (!$loanStmt) {
        throw new Exception('Failed to update loan balance');
    }

    $loanStmt->bind_param('dsi', $remaining_balance, $status, $loanId);
    if (!$loanStmt->execute()) {
        $loanStmt->close();
        throw new Exception('Failed to update loan balance');
    }
    $loanStmt->close();

    if (function_exists('create_notification')) {
        create_notification(
            $conn,
            'Payment approved',
            'Payment for ' . (($payment['borrower_name'] ?? '') ?: 'Borrower') . ' was approved.',
            'payment_recorded',
            $paymentId
        );
    }

    return $payment;
}

function resolvePaymentProofUrl($proofImage)
{
    $proofImage = trim((string)$proofImage);

    if ($proofImage === '') {
        return '';
    }

    if (strpos($proofImage, 'uploads/') === 0) {
        return '../' . $proofImage;
    }

    return '../uploads/' . $proofImage;
}
?>
