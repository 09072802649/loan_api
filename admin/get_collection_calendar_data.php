<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once "../db_connect.php";
require_once "../collector_daily_transactions_helper.php";

$month = normalizeReportMonth((string)($_GET['month'] ?? $_POST['month'] ?? date('Y-m')));
if ($month === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid month'
    ]);
    exit;
}

try {
    $calendar = fetchDailyCollectionCalendar($conn, $month);

    echo json_encode([
        'status' => 'success',
        'month' => $month,
        'calendar' => $calendar,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
