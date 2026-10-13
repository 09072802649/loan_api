<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);
    exit;
}

require_once "../../db_connect.php";
require_once "../../payment_helper.php";

ensurePaymentStatusColumn($conn);

$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthlyCollections = array_fill(0, 12, 0);

$monthlyQuery = "
    SELECT
        MONTH(payment_date) AS month_number,
        COALESCE(SUM(amount), 0) AS total_collection
    FROM payments
    WHERE status = 'approved'
    GROUP BY MONTH(payment_date)
    ORDER BY MONTH(payment_date)
";

$monthlyResult = $conn->query($monthlyQuery);
if ($monthlyResult) {
    while ($row = $monthlyResult->fetch_assoc()) {
        $monthIndex = (int)$row['month_number'] - 1;
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $monthlyCollections[$monthIndex] = (float)$row['total_collection'];
        }
    }
}

$statusCounts = [];
$statusLabels = [];
$statusValues = [];
$statusColors = [
    'pending' => '#F59E0B',
    'approved' => '#5A8DEE',
    'rejected' => '#F43F5E',
    'ongoing' => '#10B981',
    'completed' => '#6A5AE0',
    'paid' => '#14B8A6',
];

$statusQuery = "
    SELECT status, COUNT(*) AS total
    FROM loans
    GROUP BY status
";

$statusResult = $conn->query($statusQuery);
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $status = (string)$row['status'];
        $statusCounts[$status] = (int)$row['total'];
    }
}

foreach ($statusCounts as $status => $total) {
    $statusLabels[] = ucfirst($status);
    $statusValues[] = $total;
}

$statusColorsOut = [];
foreach ($statusCounts as $status => $_total) {
    $statusColorsOut[] = $statusColors[$status] ?? '#94A3B8';
}

echo json_encode([
    "status" => "success",
    "data" => [
        "monthly_collections" => [
            "labels" => $monthLabels,
            "values" => $monthlyCollections
        ],
        "loan_statuses" => [
            "labels" => $statusLabels,
            "values" => $statusValues,
            "colors" => $statusColorsOut
        ]
    ]
]);
?>
