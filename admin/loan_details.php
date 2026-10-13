<?php
require_once "auth_check.php";
require_once "../db_connect.php";
require_once "../loan_helper.php";

$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($loan_id <= 0) {
    die("Invalid Loan ID provided.");
}

// Join users to get the collector details; borrower details are natively in loans
$sql = "SELECT loans.*,
               collector.fullname AS collector_name
        FROM loans
        LEFT JOIN users AS collector ON loans.collector_id = collector.id
        WHERE loans.id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Prepare Error: " . $conn->error);
}
$stmt->bind_param("i", $loan_id);
if (!$stmt->execute()) {
    die("SQL Execute Error: " . $stmt->error);
}
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Loan request not found.");
}

$loan = $result->fetch_assoc();
$loanDetails = buildLoanComputationDetails($loan, $loan['balance'] ?? null);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Details - GoCa$h</title>
    <!-- Phosphor Icons for modern look -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        /* Modal Overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #fff;
            border-radius: 20px;
            padding: 35px 40px;
            width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            position: relative;
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }
        .modal-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        .modal-sub {
            font-size: 13px;
            color: #888;
            margin-bottom: 22px;
        }
        .modal-close {
            position: absolute;
            top: 18px; right: 20px;
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #aaa;
            line-height: 1;
        }
        .modal-close:hover { color: #333; }
        .modal-input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #E8ECF0;
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            color: #333;
            background: #FAFBFC;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .modal-input:focus {
            border-color: #6A5AE0;
            box-shadow: 0 0 0 4px rgba(106,90,224,0.08);
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        .btn-assign {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #6A5AE0, #8B5CF6);
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-assign:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-cancel {
            flex: 1;
            padding: 12px;
            border: 1.5px solid #E8ECF0;
            border-radius: 12px;
            background: white;
            color: #666;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-cancel:hover { background: #F4F6FB; }
        .details-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-width: 800px;
            margin: 0 auto;
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-item {
            margin-bottom: 15px;
        }
        .detail-label {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .detail-value {
            color: var(--text-main);
            font-size: 16px;
            font-weight: 600;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .actions-bar {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            justify-content: flex-end;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="brand">GoCa$h</div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="ph ph-squares-four"></i> Dashboard</a></li>
            <li><a href="manage_users.php"><i class="ph ph-users"></i> Manage Users</a></li>
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
        </ul>
    </aside>

    <!-- MAIN CONTAINER -->
    <main class="main-wrapper">
        
        <!-- HEADER -->
        <header class="top-header">
            <div class="greeting">
                <h1>Loan Request Details</h1>
                <p>Review comprehensive borrower and loan data</p>
            </div>
            
            <div class="header-actions">
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" onclick="window.location.href='<?php echo $returnUrl; ?>'"><i class="ph ph-arrow-left"></i></button>
            </div>
        </header>

        <!-- CONTENT -->
        <div class="content">

            <div class="details-card">
                
                <h2 class="section-title">Borrower Information</h2>
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Borrower Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($loan['borrower_name']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Borrower Contact</div>
                        <div class="detail-value"><?php echo htmlspecialchars($loan['borrower_contact'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="detail-item" style="grid-column: span 2;">
                        <div class="detail-label">Borrower Address</div>
                        <div class="detail-value"><?php echo htmlspecialchars($loan['borrower_address'] ?: 'N/A'); ?></div>
                    </div>
                </div>

                <h2 class="section-title">Loan Request Data</h2>
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Collector Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($loan['collector_name']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <?php if ($loan['status'] === 'pending') { ?>
                                <span class='badge badge-warning'>Pending</span>
                            <?php
}
else if ($loan['status'] === 'rejected') { ?>
                                <span class='badge badge-danger'>Rejected</span>
                            <?php
}
else if ($loan['status'] === 'approved') { ?>
                                <span class='badge badge-success'>Approved</span>
                            <?php
}
else if ($loan['status'] === 'ongoing') { ?>
                                <span class='badge badge-info'>Ongoing</span>
                            <?php
}
else if ($loan['status'] === 'completed') { ?>
                                <span class='badge badge-primary'>Completed</span>
                            <?php
}?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Principal Amount</div>
                        <div class="detail-value">₱ <?php echo number_format($loan['principal_amount'], 2); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Interest Rate</div>
                        <div class="detail-value"><?php echo number_format($loan['interest_rate'], 2); ?>%</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Amount</div>
                        <div class="detail-value" style="color:var(--primary); font-size:18px;">₱ <?php echo number_format($loanDetails['total_payable'], 2); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Due Date</div>
                        <div class="detail-value"><?php echo date("M d, Y", strtotime($loan['due_date'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date Requested</div>
                        <div class="detail-value"><?php echo date("M d, Y", strtotime($loan['created_at'])); ?></div>
                    </div>
                </div>

                <div class="actions-bar">
                    <a href="loans.php" class="btn" style="background:#f1f5f9; color:#475569;">Back</a>
                    
                    <?php if ($loan['status'] === 'pending') { ?>
                        <a href="reject_loan.php?id=<?php echo $loan['id']; ?>" class="btn btn-danger" onclick="return confirm('Reject Loan?');"><i class="ph ph-x" style="margin-right:5px;"></i> Reject</a>
                        <button type="button" class="btn btn-success" onclick="openApproveModal(<?php echo $loan['id']; ?>, '<?php echo htmlspecialchars(addslashes($loan['borrower_name'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($loan['borrower_contact'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($loan['borrower_address'] ?? '')); ?>')"><i class="ph ph-check" style="margin-right:5px;"></i> Approve</button>
                    <?php
}
else if ($loan['status'] === 'rejected') { ?>
                        <a href="delete_loan_request.php?id=<?php echo $loan['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete Request?');"><i class="ph ph-trash" style="margin-right:5px;"></i> Delete</a>
                    <?php
}?>
                </div>

            </div>

        </div>
    </main>

    <!-- APPROVE LOAN MODAL -->
    <div class="modal-overlay" id="approveModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeApproveModal()">&#x2715;</button>
            <div class="modal-title">Create Borrower Account</div>
            <div class="modal-sub" id="approveModalSub">Create login credentials to approve this loan</div>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'username_exists'): ?>
                <div style="color:#e74c3c; font-size:13px; background:#fdecea; padding:10px; border-radius:8px; margin-bottom:15px;">
                    This username (email) is already registered. Please use another.
                </div>
            <?php
endif; ?>

            <form method="POST" action="approve_loan.php" id="approveForm" onsubmit="return validatePasswords()">
                <input type="hidden" name="loan_id" id="modalLoanId">
                <input type="hidden" name="borrower_name" id="modalBorrowerName">
                <input type="hidden" name="borrower_contact" id="modalBorrowerContact">
                <input type="hidden" name="borrower_address" id="modalBorrowerAddress">

                <div style="margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:5px;">Username (Email)</label>
                    <input type="email" name="username" class="modal-input" placeholder="borrower@example.com" required>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:5px;">Password</label>
                    <input type="password" name="password" id="modalPassword" class="modal-input" placeholder="Enter password" required>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:5px;">Confirm Password</label>
                    <input type="password" id="modalConfirmPassword" class="modal-input" placeholder="Re-enter password" required>
                    <div id="passwordError" style="color:#e74c3c; font-size:12px; margin-top:5px; display:none;">Passwords do not match.</div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeApproveModal()">Cancel</button>
                    <button type="submit" class="btn-assign">
                        <i class="ph ph-check" style="margin-right:5px;"></i> Approve & Create
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApproveModal(loanId, name, contact, address) {
            document.getElementById('modalLoanId').value = loanId;
            document.getElementById('modalBorrowerName').value = name;
            document.getElementById('modalBorrowerContact').value = contact;
            document.getElementById('modalBorrowerAddress').value = address;
            document.getElementById('approveModalSub').innerHTML = 'Borrower: <strong>' + name + '</strong>';
            document.getElementById('approveModal').classList.add('active');
        }
        function closeApproveModal() {
            document.getElementById('approveModal').classList.remove('active');
            document.getElementById('approveForm').reset();
            document.getElementById('passwordError').style.display = 'none';
        }
        document.getElementById('approveModal').addEventListener('click', function(e) {
            if (e.target === this) closeApproveModal();
        });
        function validatePasswords() {
            const p1 = document.getElementById('modalPassword').value;
            const p2 = document.getElementById('modalConfirmPassword').value;
            if (p1 !== p2) {
                document.getElementById('passwordError').style.display = 'block';
                return false;
            }
            document.getElementById('passwordError').style.display = 'none';
            return true;
        }

        <?php if (isset($_GET['error']) && isset($_GET['loan_id'])): ?>
            // Re-open if failed validation
            openApproveModal(
                <?php echo (int)$_GET['loan_id']; ?>, 
                '<?php echo htmlspecialchars(addslashes($_GET['name'] ?? '')); ?>', 
                '<?php echo htmlspecialchars(addslashes($_GET['contact'] ?? '')); ?>', 
                '<?php echo htmlspecialchars(addslashes($_GET['addr'] ?? '')); ?>'
            );
        <?php
endif; ?>
    </script>
    <?php include 'notif_dropdown.php'; ?>
</body>
</html>
```
