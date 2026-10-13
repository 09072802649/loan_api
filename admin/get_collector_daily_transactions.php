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

$date = normalizeReportDate((string)($_GET['date'] ?? $_POST['date'] ?? ''));
if ($date === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid date'
    ]);
    exit;
}

try {
    $collectors = fetchCollectorTotalsForDate($conn, $date);
    $totalCollection = 0.0;
    $totalTransactions = 0;

    foreach ($collectors as $collector) {
        $totalCollection += (float)$collector['total_collection'];
        $totalTransactions += (int)$collector['number_of_transactions'];
    }

    echo json_encode([
        'status' => 'success',
        'date' => $date,
        'total_collection' => round($totalCollection, 2),
        'total_transactions' => $totalTransactions,
        'collectors' => $collectors,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
