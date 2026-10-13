<?php
/**
 * Migration: Add loan calculation columns to the loans table
 * and ensure loan_installments table has the required structure.
 *
 * Run this ONCE by visiting: http://localhost/loan_api/migrate_loan_schema.php
 */

require_once "db_connect.php";

$results = [];

// ── loans table: add missing columns ────────────────────────────────────────

$alter_statements = [
    "ALTER TABLE loans ADD COLUMN IF NOT EXISTS loan_account_number VARCHAR(20) NULL AFTER id",
    "ALTER TABLE loans ADD COLUMN IF NOT EXISTS loan_term INT(11) NOT NULL DEFAULT 1 AFTER interest_rate",
    "ALTER TABLE loans ADD COLUMN IF NOT EXISTS payment_frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'monthly' AFTER loan_term",
    "ALTER TABLE loans ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER payment_frequency",
    "ALTER TABLE loans ADD COLUMN IF NOT EXISTS installment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount",
    "ALTER TABLE loans MODIFY COLUMN IF EXISTS status ENUM('pending','approved','rejected','ongoing','paid','completed') DEFAULT 'pending'",
];

foreach ($alter_statements as $sql) {
    if ($conn->query($sql)) {
        $results[] = "✔ OK: $sql";
    }
    else {
        // Duplicate column errors (1060) are non-fatal — column already exists
        if ($conn->errno === 1060 || $conn->errno === 1091) {
            $results[] = "⚠ Column already exists (skipped): $sql";
        }
        else {
            $results[] = "✘ Error ({$conn->errno}): {$conn->error} — $sql";
        }
    }
}

// ── loan_installments table: create if it does not exist ────────────────────

$create_installments = "
CREATE TABLE IF NOT EXISTS loan_installments (
    id                 INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    loan_id            INT(11) NOT NULL,
    installment_number INT(11) NOT NULL,
    due_date           DATE NOT NULL,
    amount             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status             ENUM('pending','paid','overdue') NOT NULL DEFAULT 'pending',
    paid_at            DATETIME NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_loan_id (loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($create_installments)) {
    $results[] = "✔ loan_installments table ready.";
}
else {
    $results[] = "✘ loan_installments: {$conn->error}";
}

// ── Output ───────────────────────────────────────────────────────────────────
echo "<pre>" . implode("\n", $results) . "\n\nMigration complete.</pre>";
?>
