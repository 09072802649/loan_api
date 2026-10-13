<?php

function ensure_notifications_table(mysqli $conn): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(100) NOT NULL DEFAULT 'system_message',
        related_id INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('read','unread') NOT NULL DEFAULT 'unread'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) !== true) {
        return false;
    }

    // Add user_id column if it does not exist yet (for existing tables)
    $check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_id'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN user_id INT(11) DEFAULT NULL AFTER id");
    }

    return true;
}

function get_unread_notification_count(mysqli $conn): int
{
    if (!ensure_notifications_table($conn)) {
        return 0;
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE status = 'unread'");
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

function mark_all_notifications_read(mysqli $conn): bool
{
    if (!ensure_notifications_table($conn)) {
        return false;
    }

    return $conn->query("UPDATE notifications SET status = 'read' WHERE status = 'unread'") === true;
}

function create_notification(mysqli $conn, string $title, string $message, string $type, ?int $related_id = null, ?int $user_id = null): bool
{
    if (!ensure_notifications_table($conn)) {
        return false;
    }

    $stmt = $conn->prepare(
        'INSERT INTO notifications (user_id, title, message, type, related_id, status) VALUES (?, ?, ?, ?, ?, "unread")'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('isssi', $user_id, $title, $message, $type, $related_id);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function sync_overdue_notifications(mysqli $conn): void
{
    if (!ensure_notifications_table($conn)) {
        return;
    }

    $sql = "SELECT
                li.id AS installment_id,
                li.loan_id,
                li.installment_number,
                li.due_date,
                l.borrower_name
            FROM loan_installments li
            INNER JOIN loans l ON l.id = li.loan_id
            LEFT JOIN notifications n
                ON n.type = 'overdue_payment'
               AND n.related_id = li.id
            WHERE li.status <> 'paid'
              AND li.due_date < CURDATE()
              AND n.id IS NULL";

    $result = $conn->query($sql);
    if (!$result) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $borrowerName = $row['borrower_name'] ?: 'Borrower';
        $loanId = (int)$row['loan_id'];
        $installmentId = (int)$row['installment_id'];
        $installmentNumber = (int)$row['installment_number'];
        $dueDate = date('M d, Y', strtotime($row['due_date']));

        create_notification(
            $conn,
            'Overdue payment alert',
            $borrowerName . ' has an overdue installment #' . $installmentNumber . ' due on ' . $dueDate . '.',
            'overdue_payment',
            $installmentId
        );
    }

    $result->free();
}

function notification_badge_class(string $status): string
{
    return $status === 'unread' ? 'badge-warning' : 'badge-primary';
}

function notification_type_label(string $type): string
{
    $labels = [
        'loan_request' => 'Loan Request',
        'loan_approved' => 'Loan Approved',
        'loan_rejected' => 'Loan Rejected',
        'reloan_request' => 'Re-loan Request',
        'reloan_approved' => 'Re-loan Approved',
        'reloan_rejected' => 'Re-loan Rejected',
        'payment_recorded' => 'Payment',
        'overdue_payment' => 'Overdue',
        'system_message' => 'System'
    ];

    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function notification_view_link(array $notification): ?string
{
    $type = $notification['type'] ?? '';
    $relatedId = isset($notification['related_id']) ? (int)$notification['related_id'] : 0;

    if (in_array($type, ['loan_request', 'loan_approved', 'loan_rejected'], true) && $relatedId > 0) {
        return 'loan_details.php?id=' . $relatedId;
    }

    if (in_array($type, ['reloan_request', 'reloan_rejected'], true)) {
        return 'loans.php';
    }

    if ($type === 'reloan_approved' && $relatedId > 0) {
        return 'loan_details.php?id=' . $relatedId;
    }

    if ($type === 'payment_recorded') {
        return 'payments.php';
    }

    if ($type === 'overdue_payment' && $relatedId > 0) {
        $stmt = $GLOBALS['conn']->prepare('SELECT loan_id FROM loan_installments WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $relatedId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if (!empty($row['loan_id'])) {
                return 'loan_details.php?id=' . (int)$row['loan_id'];
            }
        }
    }

    return null;
}
?>
