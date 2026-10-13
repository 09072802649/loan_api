<?php
include 'auth_check.php';
include '../db_connect.php';
require_once '../notification_helper.php';

ensure_notifications_table($conn);
sync_overdue_notifications($conn);
$unreadCount = get_unread_notification_count($conn);

// --- Dashboard Stats (server-side, so they show correctly on first load) ---
$collectionQuery = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total_collections
    FROM payments
    WHERE status = 'approved'
");
$totalCollections = $collectionQuery ? (float)$collectionQuery->fetch_assoc()['total_collections'] : 0;

$paymentQuery = $conn->query("
    SELECT COUNT(*) AS total_payments
    FROM payments
    WHERE status = 'approved'
");
$totalPayments = $paymentQuery ? (int)$paymentQuery->fetch_assoc()['total_payments'] : 0;

// Recent Activity data (payments, loans, loan_requests)
$recentActivity = [];

// Latest payments
$paymentsRecent = $conn->query("
    SELECT
        p.id,
        p.amount,
        p.payment_date AS event_date,
        'payment' AS event_type,
        borrower.fullname AS borrower_name,
        collector.fullname AS collector_name
    FROM payments p
    JOIN users AS borrower ON borrower.id = p.borrower_id
    LEFT JOIN users AS collector ON collector.id = p.collector_id
    ORDER BY p.payment_date DESC, p.id DESC
    LIMIT 5
");

if ($paymentsRecent) {
    while ($row = $paymentsRecent->fetch_assoc()) {
        $recentActivity[] = [
            'event_date' => $row['event_date'],
            'label' => 'Payment received',
            'amount' => (float)$row['amount'],
            'borrower_name' => $row['borrower_name'] ?? '',
            'collector_name' => $row['collector_name'] ?? '',
            'color' => '#22c55e',
            'icon' => 'ph-receipt'
        ];
    }
}

// Latest loan requests
$loanRequestsRecent = $conn->query("
    SELECT
        lr.id,
        lr.principal_amount,
        lr.created_at AS event_date,
        'loan_request' AS event_type,
        u.fullname AS borrower_name
    FROM loan_requests lr
    JOIN users u ON u.id = lr.borrower_id
    ORDER BY lr.created_at DESC, lr.id DESC
    LIMIT 5
");

if ($loanRequestsRecent) {
    while ($row = $loanRequestsRecent->fetch_assoc()) {
        $recentActivity[] = [
            'event_date' => $row['event_date'],
            'label' => 'Loan request submitted',
            'amount' => (float)$row['principal_amount'],
            'borrower_name' => $row['borrower_name'] ?? '',
            'collector_name' => '',
            'color' => '#3b82f6',
            'icon' => 'ph-file-plus'
        ];
    }
}

// Latest approved loans
$loansRecent = $conn->query("
    SELECT
        l.id,
        l.principal_amount,
        l.created_at AS event_date,
        'loan_approved' AS event_type,
        l.borrower_name,
        COALESCE(u.fullname, '') AS collector_name
    FROM loans l
    LEFT JOIN users u ON l.collector_id = u.id
    WHERE l.status = 'approved'
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT 5
");

if ($loansRecent) {
    while ($row = $loansRecent->fetch_assoc()) {
        $recentActivity[] = [
            'event_date' => $row['event_date'],
            'label' => 'Loan approved',
            'amount' => (float)$row['principal_amount'],
            'borrower_name' => $row['borrower_name'] ?? '',
            'collector_name' => $row['collector_name'] ?? '',
            'color' => '#eab308',
            'icon' => 'ph-check-circle'
        ];
    }
}

// Sort combined list by most recent date
usort($recentActivity, function ($a, $b) {
    return strtotime($b['event_date'] ?? '1970-01-01') <=> strtotime($a['event_date'] ?? '1970-01-01');
});

// Keep only latest 5
$recentActivity = array_slice($recentActivity, 0, 5);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoCa$h Admin Dashboard</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .recent-activity-card {
            background: var(--card-bg);
            border-radius: var(--radius-main);
            box-shadow: var(--shadow-soft);
            padding: 22px 24px;
            margin-top: 20px;
        }
        .recent-activity-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .recent-activity-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
        }
        .recent-activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .recent-activity-item {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .recent-activity-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            margin-top: 6px;
            flex-shrink: 0;
        }
        .recent-activity-content {
            flex: 1;
        }
        .recent-activity-line {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
        }
        .recent-activity-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }
    </style>
</head>
<body>

<?php include 'layout/sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-header">
            <div class="greeting">
                <h1>Hello, Admin!</h1>
                <p>Welcome back to your smart dashboard</p>
            </div>

            <div class="header-actions">
                <div class="search-bar">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn">
                    <i class="ph ph-bell"></i>
                    <?php if ($unreadCount > 0) { ?>
                        <span class="icon-badge" id="topNotificationBadge"><?php echo $unreadCount; ?></span>
                    <?php } ?>
                </button>
                <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
            </div>
        </header>

        <div class="content">

            <!-- TOTAL COLLECTIONS highlight card -->
            <div class="stat-card primary collection-highlight">
                <div class="stat-icon"><i class="ph ph-currency-circle-dollar"></i></div>
                <div>
                    <div class="stat-title">Total Collections</div>
                    <div class="stat-value" id="totalCollectionsValue">PHP <?php echo number_format($totalCollections, 2); ?></div>
                </div>
            </div>

            <!-- SECONDARY STATS -->
            <div class="stats-grid" style="margin-top: 20px;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-users-three"></i></div>
                    <div class="stat-title">Total Users</div>
                    <div class="stat-value" id="totalUsersValue">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-file-text"></i></div>
                    <div class="stat-title">Total Loans</div>
                    <div class="stat-value" id="totalLoansValue">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="color: #10B981; background: rgba(16, 185, 129, 0.1);"><i class="ph ph-receipt"></i></div>
                    <div class="stat-title">Total Payments</div>
                    <div class="stat-value" id="totalPaymentsValue"><?php echo $totalPayments; ?></div>
                </div>
            </div>

            <!-- MIDDLE: Loan Requests + Quick Actions -->
            <div class="dashboard-middle">

                <!-- RIGHT: Quick Actions -->
                <div class="dashboard-quick-side">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions-grid">
                        <a href="manage_users.php" class="quick-card">
                            <div class="quick-icon">👥</div>
                            <div class="quick-title">Manage Users</div>
                        </a>
                        <a href="reports.php" class="quick-card">
                            <div class="quick-icon">📊</div>
                            <div class="quick-title">Reports</div>
                        </a>
                        <a href="payments.php" class="quick-card">
                            <div class="quick-icon">💰</div>
                            <div class="quick-title">View Payments</div>
                        </a>
                        <a href="daily_collections.php" class="quick-card">
                            <div class="quick-icon">📅</div>
                            <div class="quick-title">Daily Collections</div>
                        </a>
                    </div>
                </div>

            </div>

            <!-- BOTTOM: Recent Activity -->
            <section class="section-block">
                <div class="recent-activity-card">
                    <div class="recent-activity-header">
                        <div class="recent-activity-title">Recent Activity</div>
                    </div>
                    <?php if (!empty($recentActivity)) { ?>
                        <ul class="recent-activity-list">
                            <?php foreach ($recentActivity as $activity) { ?>
                                <li class="recent-activity-item">
                                    <span class="recent-activity-dot" style="background: <?php echo htmlspecialchars($activity['color'], ENT_QUOTES); ?>"></span>
                                    <div class="recent-activity-content">
                                        <div class="recent-activity-line">
                                            <?php echo htmlspecialchars($activity['label'], ENT_QUOTES); ?>
                                            <?php if (!empty($activity['amount'])) { ?>
                                                — <?php echo '₱ ' . number_format($activity['amount'], 2); ?>
                                            <?php } ?>
                                        </div>
                                        <div class="recent-activity-meta">
                                            <?php if (!empty($activity['borrower_name'])) { ?>
                                                Borrower: <?php echo htmlspecialchars($activity['borrower_name'], ENT_QUOTES); ?>
                                            <?php } ?>
                                            <?php if (!empty($activity['collector_name'])) { ?>
                                                <?php if (!empty($activity['borrower_name'])) { ?> · <?php } ?>
                                                Collector: <?php echo htmlspecialchars($activity['collector_name'], ENT_QUOTES); ?>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } else { ?>
                        <div class="recent-activity-meta">No recent activity yet.</div>
                    <?php } ?>
                </div>
            </section>

        </div>
    </main>

    <script>
        function formatCurrency(value) {
            return 'PHP ' + Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        async function loadDashboardStats() {
            const response = await fetch('api/get_dashboard_stats.php', { cache: 'no-store' });
            const payload = await response.json();
            if (payload.status !== 'success') {
                return;
            }

            const stats = payload.data || {};
            document.getElementById('totalUsersValue').textContent = stats.total_users ?? 0;
            document.getElementById('totalLoansValue').textContent = stats.total_loans ?? 0;
            document.getElementById('totalPaymentsValue').textContent = stats.total_payments ?? 0;
            document.getElementById('totalCollectionsValue').textContent = formatCurrency(stats.total_collections ?? 0);
        }

        async function loadNotificationCount() {
            const response = await fetch('get_notification_count.php', { cache: 'no-store' });
            const payload = await response.json();
            if (payload.status !== 'success') {
                return;
            }

            const unreadCount = Number(payload.unread_count || 0);
            const sidebarLink = document.querySelector('a[href="notifications.php"]');
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            const topButton = document.querySelector('.icon-badge-wrap');
            const topBadge = document.getElementById('topNotificationBadge');

            if (unreadCount > 0) {
                if (sidebarLink && !sidebarBadge) {
                    sidebarLink.insertAdjacentHTML('beforeend', '<span class="nav-badge" id="sidebarNotificationBadge"></span>');
                }
                if (topButton && !topBadge) {
                    topButton.insertAdjacentHTML('beforeend', '<span class="icon-badge" id="topNotificationBadge"></span>');
                }

                const nextSidebarBadge = document.getElementById('sidebarNotificationBadge');
                const nextTopBadge = document.getElementById('topNotificationBadge');
                if (nextSidebarBadge) {
                    nextSidebarBadge.textContent = unreadCount;
                }
                if (nextTopBadge) {
                    nextTopBadge.textContent = unreadCount;
                }
            } else {
                if (sidebarBadge) {
                    sidebarBadge.remove();
                }
                if (topBadge) {
                    topBadge.remove();
                }
            }
        }

        async function refreshDashboard() {
            await Promise.all([
                loadDashboardStats(),
                loadNotificationCount()
            ]);
        }

        refreshDashboard();
        setInterval(refreshDashboard, 5000);
    </script>
<?php include 'notif_dropdown.php'; ?>
</body>
</html>
