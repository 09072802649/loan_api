<?php

function ensureRemittanceSchema(mysqli $conn)
{
    static $ensured = false;

    if ($ensured) {
        return true;
    }

    $tableSql = "
        CREATE TABLE IF NOT EXISTS remittances (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            collector_id INT(11) NOT NULL,
            remittance_date DATETIME NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            transaction_count INT(11) NOT NULL DEFAULT 0,
            status ENUM('pending','received') NOT NULL DEFAULT 'pending',
            received_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_collector_id (collector_id),
            INDEX idx_remittance_date (remittance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($tableSql)) {
        return false;
    }

    $paymentAlterStatements = [
        "ALTER TABLE payments ADD COLUMN IF NOT EXISTS remitted TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
        "ALTER TABLE payments ADD COLUMN IF NOT EXISTS remittance_id INT(11) NULL AFTER remitted",
    ];

    foreach ($paymentAlterStatements as $sql) {
        if (!$conn->query($sql)) {
            return false;
        }
    }

    $ensured = true;
    return true;
}

function resolveRemittanceStatus(array $row)
{
    return strtolower((string)($row['status'] ?? 'pending'));
}
?>
