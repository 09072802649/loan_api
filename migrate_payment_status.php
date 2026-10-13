<?php
require_once "db_connect.php";
require_once "payment_helper.php";

$result = ensurePaymentStatusColumn($conn);

echo "<pre>";
echo $result
    ? "payments.status column is ready.\nExisting payments were marked approved for backward compatibility.\n"
    : "Unable to update payments table: " . htmlspecialchars($conn->error, ENT_QUOTES) . "\n";
echo "</pre>";
?>
