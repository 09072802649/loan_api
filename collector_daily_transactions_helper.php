<?php

function resolvePaymentsDateColumn(mysqli $conn): string
{
    static $resolvedColumn = null;

    if ($resolvedColumn !== null) {
        return $resolvedColumn;
    }

    $preferredColumns = ['payment_date', 'created_at'];

    foreach ($preferredColumns as $column) {
        $check = $conn->query("SHOW COLUMNS FROM payments LIKE '" . $conn->real_escape_string($column) . "'");
        if ($check && $check->num_rows > 0) {
            $resolvedColumn = $column;
            return $resolvedColumn;
        }
    }

    $resolvedColumn = 'payment_date';
    return $resolvedColumn;
}

function normalizeReportDate(string $date): ?string
{
    $date = trim($date);
    if ($date === '') {
        return null;
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return null;
    }

    return $date;
}

function normalizeReportMonth(string $month): ?string
{
    $month = trim($month);
    if ($month === '') {
        return null;
    }

    $parsed = DateTime::createFromFormat('Y-m', $month);
    if (!$parsed || $parsed->format('Y-m') !== $month) {
        return null;
    }

    return $month;
}

function getMonthDateRange(string $month): array
{
    $start = $month . '-01';
    $startDate = new DateTimeImmutable($start);
    $endDate = $startDate->modify('first day of next month');

    return [
        'start' => $startDate->format('Y-m-d'),
        'end' => $endDate->format('Y-m-d'),
    ];
}

function fetchDailyCollectionCalendar(mysqli $conn, string $month): array
{
    $range = getMonthDateRange($month);
    $dateColumn = resolvePaymentsDateColumn($conn);
    $stmt = $conn->prepare("
        SELECT
            DATE($dateColumn) AS transaction_date,
            COALESCE(SUM(amount), 0) AS total_collection,
            COUNT(id) AS number_of_transactions
        FROM payments
        WHERE collector_id IS NOT NULL
          AND collector_id > 0
          AND $dateColumn >= ?
          AND $dateColumn < ?
        GROUP BY DATE($dateColumn)
        ORDER BY transaction_date ASC
    ");

    if (!$stmt) {
        throw new Exception('Failed to load calendar data');
    }

    $stmt->bind_param('ss', $range['start'], $range['end']);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'date' => $row['transaction_date'],
            'total_collection' => round((float)$row['total_collection'], 2),
            'number_of_transactions' => (int)$row['number_of_transactions'],
        ];
    }

    $stmt->close();

    return $rows;
}

function fetchCollectorTotalsForDate(mysqli $conn, string $date): array
{
    $dateColumn = resolvePaymentsDateColumn($conn);
    $stmt = $conn->prepare("
        SELECT
            u.id AS collector_id,
            u.fullname AS collector_name,
            COALESCE(SUM(p.amount), 0) AS total_collection,
            COUNT(p.id) AS number_of_transactions
        FROM payments p
        INNER JOIN users u ON p.collector_id = u.id
        WHERE DATE(p.$dateColumn) = ?
        GROUP BY u.id, u.fullname
        ORDER BY total_collection DESC, collector_name ASC
    ");

    if (!$stmt) {
        throw new Exception('Failed to load collector totals');
    }

    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'collector_id' => (int)$row['collector_id'],
            'collector_name' => $row['collector_name'],
            'total_collection' => round((float)$row['total_collection'], 2),
            'number_of_transactions' => (int)$row['number_of_transactions'],
        ];
    }

    $stmt->close();

    return $rows;
}

function fetchCollectorDailyTransactions(mysqli $conn, int $collectorId): array
{
    $dateColumn = resolvePaymentsDateColumn($conn);
    $stmt = $conn->prepare("
        SELECT
            DATE($dateColumn) AS transaction_date,
            COALESCE(SUM(amount), 0) AS total_collected,
            COUNT(id) AS number_of_transactions
        FROM payments
        WHERE collector_id = ?
        GROUP BY DATE($dateColumn)
        ORDER BY transaction_date DESC
    ");

    if (!$stmt) {
        throw new Exception('Failed to load daily transactions');
    }

    $stmt->bind_param('i', $collectorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'date' => $row['transaction_date'],
            'transaction_date' => $row['transaction_date'],
            'collector_id' => $collectorId,
            'total_collected' => round((float)$row['total_collected'], 2),
            'number_of_transactions' => (int)$row['number_of_transactions'],
            'transactions' => (int)$row['number_of_transactions'],
        ];
    }

    $stmt->close();

    return $rows;
}
?>
