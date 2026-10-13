<?php

function ensure_admin_profile_columns(mysqli $conn): void
{
    $queries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER role",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER password",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL AFTER created_at",
    ];

    foreach ($queries as $query) {
        $conn->query($query);
    }
}

?>
