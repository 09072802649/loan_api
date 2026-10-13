<?php
session_start();
header('Content-Type: application/json');

require_once "../db_connect.php";
require_once "../collector_daily_transactions_helper.php";

$collectorId = 0;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    $collectorId = (int)$_SESSION['user_id'];
}

if ($collectorId <= 0) {
    $collectorId = isset($_GET['collector_id']) ? (int)$_GET['collector_id'] : (int)($_POST['collector_id'] ?? 0);
}

if ($collectorId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'collector_id required'
    ]);
    exit;
}

try {
    $history = fetchCollectorDailyTransactions($conn, $collectorId);
    $latestDate = $history[0]['date'] ?? null;
    $latestTotal = $history[0]['total_collected'] ?? 0.0;
    $latestTransactions = $history[0]['number_of_transactions'] ?? 0;

    echo json_encode([
        'status' => 'success',
        'collector_id' => $collectorId,
        'date' => $latestDate,
        'total_collected' => round((float)$latestTotal, 2),
        'number_of_transactions' => (int)$latestTransactions,
        'history' => $history,
        'data' => $history,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
