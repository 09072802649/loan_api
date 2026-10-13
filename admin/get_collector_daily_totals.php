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
    $responseCollectors = [];
    $totalCollection = 0.0;
    $totalTransactions = 0;

    foreach ($collectors as $collector) {
        $collectorTotal = round((float)$collector['total_collection'], 2);
        $collectorTransactions = (int)($collector['number_of_transactions'] ?? 0);
        $totalCollection += $collectorTotal;
        $totalTransactions += $collectorTransactions;

        $responseCollectors[] = [
            'name' => $collector['collector_name'],
            'total' => $collectorTotal,
            'transactions' => $collectorTransactions,
            'collector_name' => $collector['collector_name'],
            'total_collection' => $collectorTotal,
            'number_of_transactions' => $collectorTransactions,
        ];
    }

    echo json_encode([
        'status' => 'success',
        'date' => $date,
        'total_collection' => round($totalCollection, 2),
        'total_transactions' => $totalTransactions,
        'collectors' => $responseCollectors,
        'message' => count($responseCollectors) === 0 ? 'No collections recorded.' : null,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
