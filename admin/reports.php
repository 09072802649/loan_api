<?php
include 'auth_check.php';
include "../db_connect.php";
require_once "../payment_helper.php";

ensurePaymentStatusColumn($conn);

$totalLoans = $conn->query("SELECT SUM(principal_amount) as total FROM loans")->fetch_assoc();
$totalPayments = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'approved'")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Local overrides if any (none needed now) */
    </style>
</head>
<body>

<?php include 'layout/sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-header">
            <div class="greeting">
                <h1>Reports &amp; Analytics</h1>
                <p>Comprehensive system financial performance overview</p>
            </div>
            <div class="header-actions">
                <div class="search-bar">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
            </div>
        </header>

        <div class="content">

            <!-- STATS GRID -->
            <div class="stats-grid">
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #6A5AE0; background: rgba(106, 90, 224, 0.1);"><i class="ph ph-bank"></i></div>
                    <div class="stat-title">Total Loan Released</div>
                    <div class="stat-value">₱ <?php echo number_format($totalLoans['total'], 2); ?></div>
                </div>

                <div class="stat-card primary">
                    <div class="stat-icon"><i class="ph ph-money"></i></div>
                    <div class="stat-title">Total Payments Collected</div>
                    <div class="stat-value">₱ <?php echo number_format($totalPayments['total'], 2); ?></div>
                </div>

            </div>

            <!-- DAILY SUMMARY -->
            <section class="section-block">
                <div class="daily-summary-grid">
                    <div class="summary-card primary">
                        <div class="summary-label">Total Collection Today</div>
                        <div class="summary-value" id="dailyCollectionValue">PHP 0.00</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-label">Total Transactions</div>
                        <div class="summary-value" id="dailyTransactionsValue">0</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-label">Pending Verification</div>
                        <div class="summary-value" id="dailyPendingValue">0</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-label">Approved Payments Today</div>
                        <div class="summary-value" id="dailyApprovedValue">0</div>
                    </div>
                </div>
            </section>

            <!-- CHARTS -->
            <div class="charts-grid" style="margin-top: 28px;">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Monthly Collections</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="loanActivityChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Loan Status Distribution</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="paymentPieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- COLLECTOR PERFORMANCE -->
            <section class="section-block performance-card">
                <div class="section-header">
                    <div>
                        <div class="section-title">Collector Performance</div>
                        <div class="section-subtitle">Today's collection breakdown by collector</div>
                    </div>
                </div>
                <div class="table-container" style="margin-top: 0;">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Collector Name</th>
                                <th>Transactions</th>
                                <th>Total Collected</th>
                            </tr>
                        </thead>
                        <tbody id="collectorPerformanceBody">
                            <tr>
                                <td colspan="3" style="text-align:center; padding: 40px; color: var(--text-muted);">Loading collector performance...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- TOP COLLECTORS -->
            <section class="section-block">
                <div class="leaderboard-card">
                    <div class="section-header" style="margin-bottom: 10px;">
                        <div>
                            <div class="section-title">Top Collectors Today</div>
                            <div class="section-subtitle">Ranked by total collections for the current day</div>
                        </div>
                    </div>
                    <ul class="leaderboard-list" id="topCollectorsList">
                        <li class="section-subtitle">Loading top collectors...</li>
                    </ul>
                </div>
            </section>

        </div>
    </main>

    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#888888';

        function formatCurrency(value) {
            return 'PHP ' + Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Monthly Collections Chart
        const ctxLine = document.getElementById('loanActivityChart').getContext('2d');
        const gradientLine = ctxLine.createLinearGradient(0, 0, 0, 400);
        gradientLine.addColorStop(0, 'rgba(106, 90, 224, 0.5)');
        gradientLine.addColorStop(1, 'rgba(106, 90, 224, 0.05)');

        const loanActivityChart = new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Collections',
                    data: [],
                    borderColor: '#6A5AE0',
                    backgroundColor: gradientLine,
                    borderWidth: 3,
                    pointBackgroundColor: '#FFFFFF',
                    pointBorderColor: '#6A5AE0',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#FFFFFF',
                        titleColor: '#333333',
                        bodyColor: '#666666',
                        borderColor: '#EEEEEE',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                    }
                },
                scales: {
                    x: { grid: { display: false, drawBorder: false } },
                    y: { grid: { color: '#F4F6FB', drawBorder: false }, beginAtZero: true }
                }
            }
        });

        // Loan Status Pie Chart
        const ctxPie = document.getElementById('paymentPieChart').getContext('2d');
        const paymentPieChart = new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' }
                    }
                }
            }
        });

        async function loadCharts() {
            const response = await fetch('api/get_chart_data.php', { cache: 'no-store' });
            const payload = await response.json();
            if (payload.status === 'success') {
                const chartData = payload.data || {};
                const monthlyCollections = chartData.monthly_collections || {};
                const loanStatuses = chartData.loan_statuses || {};

                loanActivityChart.data.labels = monthlyCollections.labels || [];
                loanActivityChart.data.datasets[0].data = monthlyCollections.values || [];
                loanActivityChart.update();

                paymentPieChart.data.labels = loanStatuses.labels || [];
                paymentPieChart.data.datasets[0].data = loanStatuses.values || [];
                paymentPieChart.data.datasets[0].backgroundColor = loanStatuses.colors || [];
                paymentPieChart.update();
            }
        }

        async function loadDailySummary() {
            const response = await fetch('get_daily_collection_summary.php', { cache: 'no-store' });
            const payload = await response.json();
            if (payload.status === 'success') {
                document.getElementById('dailyCollectionValue').textContent = formatCurrency(payload.total_collection ?? 0);
                document.getElementById('dailyTransactionsValue').textContent = payload.total_transactions ?? 0;
                document.getElementById('dailyPendingValue').textContent = payload.pending_payments ?? 0;
                document.getElementById('dailyApprovedValue').textContent = payload.approved_today ?? 0;
            }
        }

        async function loadCollectorPerformance() {
            const today = new Date().toISOString().split('T')[0];
            const response = await fetch('get_collector_daily_totals.php?date=' + today, { cache: 'no-store' });
            const payload = await response.json();
            const tableBody = document.getElementById('collectorPerformanceBody');
            
            if (payload.status === 'success' && payload.collectors.length > 0) {
                tableBody.innerHTML = payload.collectors.map(c => `
                    <tr>
                        <td style="font-weight:600;">${c.name}</td>
                        <td>${c.transactions}</td>
                        <td style="font-weight:700; color:var(--text-main);">${formatCurrency(c.total)}</td>
                    </tr>
                `).join('');
            } else {
                tableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; padding: 30px; color: var(--text-muted);">No collection data for today.</td></tr>`;
            }
        }

        async function loadTopCollectors() {
            const today = new Date().toISOString().split('T')[0];
            const response = await fetch('get_collector_daily_totals.php?date=' + today, { cache: 'no-store' });
            const payload = await response.json();
            const list = document.getElementById('topCollectorsList');

            if (payload.status !== 'success' || !Array.isArray(payload.collectors) || payload.collectors.length === 0) {
                list.innerHTML = '<li class="section-subtitle">No collection data for today.</li>';
                return;
            }

            const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
            const topFive = payload.collectors
                .slice()
                .sort((a, b) => Number(b.total || 0) - Number(a.total || 0))
                .slice(0, 5);

            list.innerHTML = topFive.map((collector, index) => {
                const name = collector.name || collector.collector_name || 'Unknown Collector';
                const total = Number(collector.total || collector.total_collection || 0);
                const medal = medals[index] || (index + 1) + '.';
                return `
                    <li class="leaderboard-item">
                        <div>
                            <span class="leaderboard-rank">${medal}</span>
                            <span class="leaderboard-name">${name}</span>
                        </div>
                        <div class="leaderboard-amount">${formatCurrency(total)}</div>
                    </li>
                `;
            }).join('');
        }

        function refreshReports() {
            loadCharts();
            loadDailySummary();
            loadCollectorPerformance();
            loadTopCollectors();
        }

        refreshReports();
        setInterval(refreshReports, 30000);
    </script>
<?php include 'notif_dropdown.php'; ?>
</body>
</html>
