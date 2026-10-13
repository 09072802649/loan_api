<?php
require_once "auth_check.php";
require_once "../db_connect.php";
require_once "../payment_helper.php";

ensurePaymentStatusColumn($conn);

$borrower_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($borrower_id <= 0) {
    die("Invalid Borrower ID provided.");
}

// 1. Fetch Borrower Details
$stmt = $conn->prepare("SELECT fullname, phone, address FROM users WHERE id = ? AND role = 'borrower'");
$stmt->bind_param("i", $borrower_id);
if (!$stmt->execute())
    die("Error fetching user: " . $stmt->error);
$user_res = $stmt->get_result();
if ($user_res->num_rows === 0)
    die("Borrower not found.");
$borrower = $user_res->fetch_assoc();
$stmt->close();

// 2. Fetch Assigned Collector (from active loan if exists)
$stmt = $conn->prepare("
    SELECT users.fullname AS collector_name 
    FROM loans 
    JOIN users ON loans.collector_id = users.id 
    WHERE loans.borrower_id = ? 
    ORDER BY loans.created_at DESC LIMIT 1
");
$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$col_res = $stmt->get_result();
$collector_name = ($col_res->num_rows > 0) ? $col_res->fetch_assoc()['collector_name'] : "Unassigned";
$stmt->close();

// 3. Fetch Loan Summary
$stmt = $conn->prepare("
    SELECT 
        COUNT(loans.id) AS total_loans,
        SUM(loans.total_amount) AS total_borrowed,
        SUM(IFNULL(sub.total_paid, 0)) AS total_paid
    FROM loans
    LEFT JOIN (
        SELECT loan_id, SUM(amount) AS total_paid FROM payments WHERE status = 'approved' GROUP BY loan_id
    ) sub ON loans.id = sub.loan_id
    WHERE loans.borrower_id = ?
");
$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_borrowed = $summary['total_borrowed'] ?? 0;
$total_paid = $summary['total_paid'] ?? 0;

$loanDetails = [
    'principal_amount' => $total_borrowed,
    // Note: since this is a summary of all loans, we don't have a single interest rate. 
    // We should not use buildLoanComputationDetails for a SUM aggregate, as it expects a single loan.
    // Let's revert and do sum of total_amount - total_paid.
];

// Reverting to safe aggregate subtraction
$stmt = $conn->prepare("
    SELECT SUM(total_amount) AS total_payable FROM loans WHERE borrower_id = ?
");
$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$payable_res = $stmt->get_result()->fetch_assoc();
$total_payable = $payable_res['total_payable'] ?? 0;
$stmt->close();

$remaining_balance = max(0, $total_payable - $total_paid);

// 4. Fetch Loan History
$stmt = $conn->prepare("SELECT id, total_amount, status, due_date FROM loans WHERE borrower_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $borrower_id);
$stmt->execute();
$history_res = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Profile - GoCa$h</title>
    <!-- Phosphor Icons for modern look -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6A5AE0, #8B5CF6);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            margin: 0 auto 15px auto;
        }
        .profile-name { font-size: 20px; font-weight: 700; color: var(--text-main); margin-bottom: 5px; }
        .profile-role { font-size: 13px; color: var(--primary); font-weight: 600; text-transform: uppercase; }
        
        .info-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
        .info-label { color: var(--text-muted); font-weight: 500; }
        .info-value { color: var(--text-main); font-weight: 600; text-align: right; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #FAFBFC;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #E8ECF0;
        }
        .stat-title { font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-bottom: 8px; }
        .stat-value { font-size: 22px; font-weight: 700; color: var(--primary); }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="brand">GoCa$h</div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
            <li><a href="manage_users.php" class="active"><i class="ph ph-users"></i> Manage Users</a></li>
            <li><a href="loans.php"><i class="ph ph-wallet"></i> Loans</a></li>


            <li><a href="payments.php"><i class="ph ph-credit-card"></i> Payments</a></li>
            <li><a href="daily_collections.php"><i class="ph ph-calendar-blank"></i> Daily Collections</a></li>
            <li><a href="reports.php"><i class="ph ph-chart-line-up"></i> Reports</a></li>
            <li><a href="profile.php"><i class="ph ph-user-circle-gear"></i> Profile</a></li>
            <li style="margin-top: auto;"><a href="logout.php"><i class="ph ph-sign-out"></i> Logout</a></li>
        </ul>
    </aside>

    <!-- MAIN CONTAINER -->
    <main class="main-wrapper">
        
        <!-- HEADER -->
        <header class="top-header">
            <div class="greeting">
                <h1>Borrower Profile</h1>
                <p>Detailed view of borrower activity</p>
            </div>
            <div class="header-actions">
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" onclick="window.location.href='users.php'"><i class="ph ph-arrow-left"></i></button>
            </div>
        </header>

        <!-- CONTENT -->
        <div class="content">

            <div class="profile-container">
                
                <!-- LEFT CARD: User Info -->
                <div class="card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($borrower['fullname'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($borrower['fullname']); ?></div>
                        <div class="profile-role">Borrower</div>
                    </div>
                    
                    <div class="section-title" style="font-size:14px; margin-bottom:15px; color:#333;">Contact Information</div>
                    <div class="info-row">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?php echo htmlspecialchars($borrower['phone']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($borrower['address'] ?: 'N/A'); ?></div>
                    </div>
                    
                    <div class="section-title" style="font-size:14px; margin-top:25px; margin-bottom:15px; color:#333;">Assignment</div>
                    <div class="info-row">
                        <div class="info-label">Current Collector</div>
                        <div class="info-value" style="color:#6A5AE0;"><?php echo htmlspecialchars($collector_name); ?></div>
                    </div>
                </div>

                <!-- RIGHT CARD: Loan Stats & History -->
                <div>
                    <!-- Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-title">Total Loans</div>
                            <div class="stat-value"><?php echo $summary['total_loans']; ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-title">Total Borrowed</div>
                            <div class="stat-value">₱ <?php echo number_format($total_borrowed, 2); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-title">Total Paid</div>
                            <div class="stat-value" style="color:#10B981;">₱ <?php echo number_format($total_paid, 2); ?></div>
                        </div>
                        <div class="stat-card" style="border-left: 4px solid #F59E0B;">
                            <div class="stat-title">Remaining Balance</div>
                            <div class="stat-value" style="color:#F59E0B;">₱ <?php echo number_format($remaining_balance, 2); ?></div>
                        </div>
                    </div>

                    <!-- History Table -->
                    <div class="card">
                        <h2 style="font-size:16px; margin-bottom:20px; color:#333;">Loan History</h2>
                        <table class="modern-table" style="box-shadow:none; border-radius:0;">
                            <thead>
                                <tr>
                                    <th>Loan ID</th>
                                    <th>Total Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history_res->num_rows > 0): ?>
                                    <?php while ($h = $history_res->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?php echo $h['id']; ?></strong></td>
                                        <td style="font-weight:600; color:var(--text-main);">₱ <?php echo number_format($h['total_amount'], 2); ?></td>
                                        <td><?php echo $h['due_date'] ? date("M d, Y", strtotime($h['due_date'])) : '---'; ?></td>
                                        <td>
                                            <?php if ($h['status'] === 'pending')
            echo "<span class='badge badge-warning'>Pending</span>"; ?>
                                            <?php if ($h['status'] === 'rejected')
            echo "<span class='badge badge-danger'>Rejected</span>"; ?>
                                            <?php if ($h['status'] === 'approved')
            echo "<span class='badge badge-success'>Approved</span>"; ?>
                                            <?php if ($h['status'] === 'ongoing')
            echo "<span class='badge badge-info'>Ongoing</span>"; ?>
                                            <?php if ($h['status'] === 'completed')
            echo "<span class='badge badge-primary'>Completed</span>"; ?>
                                        </td>
                                        <td>
                                            <a href="loan_details.php?id=<?php echo $h['id']; ?>" class="btn btn-primary btn-sm"><i class="ph ph-eye"></i> View</a>
                                        </td>
                                    </tr>
                                    <?php
    endwhile; ?>
                                <?php
else: ?>
                                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#888;">No loan history found.</td></tr>
                                <?php
endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <?php include 'notif_dropdown.php'; ?>
</body>
</html>



