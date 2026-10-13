<?php
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/db_connect.php';

function formatArchiveLoanAmount($value): string
{
    $formatted = number_format((float)$value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

$collectorId = isset($_GET['collector_id']) ? (int)$_GET['collector_id'] : (int)($_POST['collector_id'] ?? 0);

if ($collectorId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'collector_id required'
    ]);
    exit;
}

$completedDateColumn = 'created_at';
$updatedAtCheck = $conn->query("SHOW COLUMNS FROM loans LIKE 'updated_at'");
if ($updatedAtCheck && $updatedAtCheck->num_rows > 0) {
    $completedDateColumn = 'updated_at';
}

$sql = "
    SELECT
        l.id AS loan_id,
        l.borrower_id,
        u.id AS user_id,
        u.fullname,
        l.principal_amount AS loan_amount,
        DATE(l.$completedDateColumn) AS completed_date
    FROM loans l
    INNER JOIN users u ON u.id = l.borrower_id
    INNER JOIN (
        SELECT borrower_id, MAX(id) AS latest_loan_id
        FROM loans
        WHERE collector_id = ?
        GROUP BY borrower_id
    ) latest ON latest.latest_loan_id = l.id
    WHERE l.collector_id = ?
      AND l.status = 'completed'
    ORDER BY l.$completedDateColumn DESC, l.id DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load archive borrowers'
    ]);
    exit;
}

$stmt->bind_param('ii', $collectorId, $collectorId);

if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load archive borrowers'
    ]);
    exit;
}

$result = $stmt->get_result();
$borrowers = [];

while ($row = $result->fetch_assoc()) {
    $borrowers[] = [
        'loan_id'            => (int)$row['loan_id'],
        'borrower_id'        => (int)$row['borrower_id'],
        'id'                 => (int)$row['user_id'],
        'fullname'           => (string)($row['fullname'] ?? ''),
        'loan_amount'        => formatArchiveLoanAmount($row['loan_amount'] ?? 0),
        'completed_date'     => (string)($row['completed_date'] ?? ''),
        'can_request_reloan' => true,
    ];
}

$stmt->close();

echo json_encode([
    'status' => 'success',
    'data' => $borrowers
]);

