<?php
require_once "auth_check.php";
require_once '../db_connect.php';
require_once '../notification_helper.php';
require_once '../loan_helper.php';
require_once '../reloan_helper.php';

ensure_notifications_table($conn);
sync_overdue_notifications($conn);
ensureLoanRequestsTable($conn);

$reloanComputedRequestColumns = loanRequestsTableHasColumn($conn, 'number_of_payments');

$sql = "
    SELECT *
    FROM (
        SELECT
            l.id AS request_id,
            l.id AS loan_id,
            'loan' AS request_source,
            'NEW LOAN' AS loan_type,
            l.borrower_id,
            l.borrower_name,
            l.borrower_contact,
            l.borrower_address,
            COALESCE(u.fullname, 'N/A') AS collector_name,
            l.principal_amount,
            l.interest_rate,
            l.loan_term,
            l.payment_frequency,
            l.start_date,
            l.total_amount,
            l.installment_amount,
            NULL AS number_of_payments,
            l.balance,
            l.status,
            l.created_at
        FROM loans l
        LEFT JOIN users u ON l.collector_id = u.id
        WHERE l.status IN ('pending', 'rejected')

        UNION ALL

        SELECT
            lr.id AS request_id,
            NULL AS loan_id,
            'reloan' AS request_source,
            'RELOAN' AS loan_type,
            lr.borrower_id,
            bu.fullname AS borrower_name,
            COALESCE(bu.phone, '') AS borrower_contact,
            COALESCE(bu.address, '') AS borrower_address,
            COALESCE(cu.fullname, 'N/A') AS collector_name,
            lr.principal_amount,
            " . ($reloanComputedRequestColumns
                ? "COALESCE(lr.interest_rate, template.interest_rate, 0)"
                : "COALESCE(template.interest_rate, 0)") . " AS interest_rate,
            " . ($reloanComputedRequestColumns
                ? "COALESCE(lr.loan_term, template.loan_term, 1)"
                : "COALESCE(template.loan_term, 1)") . " AS loan_term,
            " . ($reloanComputedRequestColumns
                ? "COALESCE(lr.payment_frequency, template.payment_frequency, 'monthly')"
                : "COALESCE(template.payment_frequency, 'monthly')") . " AS payment_frequency,
            CURDATE() AS start_date,
            " . ($reloanComputedRequestColumns ? "lr.total_amount" : "NULL") . " AS total_amount,
            " . ($reloanComputedRequestColumns ? "lr.installment_amount" : "NULL") . " AS installment_amount,
            " . ($reloanComputedRequestColumns ? "lr.number_of_payments" : "NULL") . " AS number_of_payments,
            NULL AS balance,
            lr.status,
            lr.created_at
        FROM loan_requests lr
        INNER JOIN users bu ON lr.borrower_id = bu.id
        LEFT JOIN users cu ON lr.collector_id = cu.id
        LEFT JOIN loans template ON template.id = (
            SELECT l2.id
            FROM loans l2
            WHERE l2.borrower_id = lr.borrower_id
              AND l2.status = 'completed'
            ORDER BY l2.created_at DESC, l2.id DESC
            LIMIT 1
        )
        WHERE lr.status IN ('pending', 'rejected')
    ) AS requests
    ORDER BY created_at DESC, request_id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("SQL Error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Requests - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
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
            border-radius: var(--radius-main);
            padding: 28px 32px;
            width: min(520px, calc(100vw - 32px));
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            box-shadow: var(--shadow-soft);
            position: relative;
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
            top: 18px;
            right: 20px;
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
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 20px;
        }
        .detail-item {
            background: #FAFBFC;
            border: 1px solid #E8ECF0;
            border-radius: 14px;
            padding: 14px 16px;
        }
        .detail-item.full {
            grid-column: 1 / -1;
        }
        .detail-label {
            font-size: 12px;
            color: #7A7A7A;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .detail-value {
            font-size: 15px;
            color: #222;
            font-weight: 600;
            word-break: break-word;
        }
        @media (max-width: 640px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            .detail-item.full {
                grid-column: auto;
            }
        }
        .badge {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .badge-pending {
            background: rgba(245, 158, 11, 0.12);
            color: #D97706;
        }
        .badge-loan {
            background: rgba(106, 90, 224, 0.1);
            color: #6A5AE0;
        }
        .badge-reloan {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        .action-btns {
            display: flex;
            gap: 8px;
        }
        .btn-approve {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 7px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-approve:hover {
            background: #10B981;
            color: white;
        }
        .btn-reject {
            background: rgba(239, 68, 68, 0.08);
            color: #DC2626;
            border: 1px solid rgba(239, 68, 68, 0.15);
            padding: 7px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-reject:hover {
            background: #EF4444;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .btn-view {
            background: rgba(148, 163, 184, 0.1);
            color: #475569;
            border: 1px solid rgba(148, 163, 184, 0.2);
            padding: 7px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-view:hover {
            background: #64748B;
            color: white;
        }
    </style>
</head>
<body>

    <?php include 'layout/sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-header">
            <div class="greeting">
                <h1>Loan Requests</h1>
                <p>Monitor pending and rejected loan requests, including re-loan requests.</p>
            </div>

            <div class="header-actions">
                <div class="search-bar">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" placeholder="Search requests...">
                </div>
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
            </div>
        </header>

        <div class="content">
            <div class="table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Borrower Name</th>
                            <th>Collector Name</th>
                            <th>Loan Type</th>
                            <th>Principal Amount</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Date Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) { ?>
                        <?php $loanDetails = buildLoanComputationDetails($row, $row['balance'] ?? null); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['borrower_name'] ?? 'N/A'); ?></strong></td>
                            <td style="color: #6A5AE0; font-weight: 600;"><?php echo htmlspecialchars($row['collector_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge badge-primary"><?php echo htmlspecialchars($row['loan_type'] ?? 'NEW LOAN'); ?></span>
                            </td>
                            <td style="color: var(--text-main); font-weight: 700;">PHP <?php echo number_format((float)$row['principal_amount'], 2); ?></td>
                            <td style="color: var(--text-main); font-weight: 700;">PHP <?php echo number_format($loanDetails['total_payable'], 2); ?></td>
                            <td>
                                <?php if ($row['status'] === 'pending') { ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php } else { ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php } ?>
                            </td>
                            <td style="color:var(--text-muted);">
                                <?php echo !empty($row['created_at']) ? date("M d, Y", strtotime($row['created_at'])) : 'N/A'; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        onclick="openViewModal(this)"
                                        data-borrower-name="<?php echo htmlspecialchars($row['borrower_name'] ?? '', ENT_QUOTES); ?>"
                                        data-borrower-contact="<?php echo htmlspecialchars($row['borrower_contact'] ?? '', ENT_QUOTES); ?>"
                                        data-borrower-address="<?php echo htmlspecialchars($row['borrower_address'] ?? '', ENT_QUOTES); ?>"
                                        data-collector-name="<?php echo htmlspecialchars($row['collector_name'] ?? 'N/A', ENT_QUOTES); ?>"
                                        data-loan-type="<?php echo htmlspecialchars($row['loan_type'] ?? 'NEW LOAN', ENT_QUOTES); ?>"
                                        data-principal-amount="<?php echo number_format((float)$row['principal_amount'], 2); ?>"
                                        data-interest-rate="<?php echo number_format((float)$row['interest_rate'], 2); ?>%"
                                        data-loan-term="<?php echo (int)$row['loan_term']; ?>"
                                        data-payment-frequency="<?php echo htmlspecialchars(ucfirst($row['payment_frequency'] ?? 'N/A'), ENT_QUOTES); ?>"
                                        data-start-date="<?php echo !empty($row['start_date']) ? date('M d, Y', strtotime($row['start_date'])) : 'N/A'; ?>"
                                        data-date-requested="<?php echo !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'N/A'; ?>"
                                    ><i class="ph ph-eye" style="margin-right:3px;"></i> View</button>

                                    <?php if ($row['status'] === 'pending') { ?>
                                        <?php if (($row['request_source'] ?? 'loan') === 'reloan') { ?>
                                            <button
                                                type="button"
                                                class="btn btn-success btn-sm"
                                                onclick="approveReloan(<?php echo (int)$row['request_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['borrower_name'] ?? ''), ENT_QUOTES); ?>')"
                                            ><i class="ph ph-check" style="margin-right:3px;"></i> Approve</button>
                                            <a href="reject_loan.php?id=<?php echo (int)$row['request_id']; ?>&source=reloan" class="btn btn-danger btn-sm" onclick="return confirm('Decline Re-loan Request?');"><i class="ph ph-x" style="margin-right:3px;"></i> Decline</a>
                                        <?php } else { ?>
                                            <button
                                                type="button"
                                                class="btn btn-success btn-sm"
                                                onclick="openApproveModal(<?php echo (int)$row['request_id']; ?>, 'loan', '<?php echo htmlspecialchars(addslashes($row['borrower_name'] ?? ''), ENT_QUOTES); ?>')"
                                            ><i class="ph ph-check" style="margin-right:3px;"></i> Approve</button>
                                            <a href="reject_loan.php?id=<?php echo (int)$row['request_id']; ?>&source=loan" class="btn btn-danger btn-sm" onclick="return confirm('Reject Loan?');"><i class="ph ph-x" style="margin-right:3px;"></i> Reject</a>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <a href="delete_loan_request.php?id=<?php echo (int)$row['request_id']; ?>&source=<?php echo urlencode($row['request_source'] ?? 'loan'); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete Request?');"><i class="ph ph-trash" style="margin-right:3px;"></i> Delete</a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-overlay" id="viewModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeViewModal()">&#x2715;</button>
            <div class="modal-title">Loan Request Details</div>
            <div class="modal-sub">Full borrower and request information</div>

            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Borrower Name</div>
                    <div class="detail-value" id="viewBorrowerName">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Borrower Contact</div>
                    <div class="detail-value" id="viewBorrowerContact">N/A</div>
                </div>
                <div class="detail-item full">
                    <div class="detail-label">Borrower Address</div>
                    <div class="detail-value" id="viewBorrowerAddress">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Collector Name</div>
                    <div class="detail-value" id="viewCollectorName">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Loan Type</div>
                    <div class="detail-value" id="viewLoanType">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Principal Amount</div>
                    <div class="detail-value" id="viewPrincipalAmount">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Interest Rate</div>
                    <div class="detail-value" id="viewInterestRate">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Loan Term</div>
                    <div class="detail-value" id="viewLoanTerm">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Payment Frequency</div>
                    <div class="detail-value" id="viewPaymentFrequency">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Start Date</div>
                    <div class="detail-value" id="viewStartDate">N/A</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date Requested</div>
                    <div class="detail-value" id="viewDateRequested">N/A</div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="approveModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeApproveModal()">&#x2715;</button>
            <div class="modal-title">Create Borrower Account</div>
            <div class="modal-sub" id="approveModalSub">Create login credentials to approve this loan</div>

            <form id="approveForm">
                <input type="hidden" id="modalLoanId">
                <input type="hidden" id="modalRequestSource" value="loan">

                <div style="margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:5px;">Username (Email)</label>
                    <input type="email" id="modalEmail" class="modal-input" placeholder="borrower@example.com" required>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:5px;">Password</label>
                    <input type="password" id="modalPassword" class="modal-input" placeholder="Enter password" required>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="font-size:13px; font-weight:600; color:#555; display:block; margin-bottom:5px;">Confirm Password</label>
                    <input type="password" id="modalConfirmPassword" class="modal-input" placeholder="Re-enter password" required>
                    <div id="passwordError" style="color:#e74c3c; font-size:12px; margin-top:5px; display:none;">Passwords do not match.</div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeApproveModal()">Cancel</button>
                    <button type="submit" class="btn-assign" id="approveSubmitBtn">
                        <i class="ph ph-check" style="margin-right:5px;"></i> Approve & Create
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function setText(id, value) {
            document.getElementById(id).textContent = value && value !== '' ? value : 'N/A';
        }

        function openViewModal(button) {
            const data = button.dataset;
            setText('viewBorrowerName', data.borrowerName);
            setText('viewBorrowerContact', data.borrowerContact);
            setText('viewBorrowerAddress', data.borrowerAddress);
            setText('viewCollectorName', data.collectorName);
            setText('viewLoanType', data.loanType);
            setText('viewPrincipalAmount', 'PHP ' + (data.principalAmount || '0.00'));
            setText('viewInterestRate', data.interestRate);
            setText('viewLoanTerm', data.loanTerm);
            setText('viewPaymentFrequency', data.paymentFrequency);
            setText('viewStartDate', data.startDate);
            setText('viewDateRequested', data.dateRequested);
            document.getElementById('viewModal').classList.add('active');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        function openApproveModal(loanId, requestSource, name) {
            document.getElementById('modalLoanId').value = loanId;
            document.getElementById('modalRequestSource').value = requestSource || 'loan';
            document.getElementById('approveModalSub').innerHTML = 'Borrower: <strong>' + name + '</strong>';
            document.getElementById('approveModal').classList.add('active');
        }

        function closeApproveModal() {
            document.getElementById('approveModal').classList.remove('active');
            document.getElementById('approveForm').reset();
            document.getElementById('modalRequestSource').value = 'loan';
            document.getElementById('passwordError').style.display = 'none';
            document.getElementById('approveSubmitBtn').disabled = false;
        }

        function approveReloan(requestId, borrowerName) {
            if (!confirm('Approve re-loan request for ' + borrowerName + '?')) {
                return;
            }

            fetch('approve_loan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    request_source: 'reloan'
                })
            })
            .then(function(res) {
                return res.json();
            })
            .then(function(data) {
                if (data.status === 'success') {
                    alert(data.message || 'Reloan approved successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Unable to approve reloan');
                }
            })
            .catch(function() {
                alert('Unable to approve reloan');
            });
        }

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
        });

        document.getElementById('approveModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApproveModal();
            }
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

        document.getElementById('approveForm').addEventListener('submit', function(event) {
            event.preventDefault();

            if (!validatePasswords()) {
                return;
            }

            const loanId = document.getElementById('modalLoanId').value;
            const requestSource = document.getElementById('modalRequestSource').value || 'loan';
            const email = document.getElementById('modalEmail').value.trim();
            const password = document.getElementById('modalPassword').value;
            const submitButton = document.getElementById('approveSubmitBtn');

            submitButton.disabled = true;

            fetch('approve_loan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    loan_id: loanId,
                    request_source: requestSource,
                    email: email,
                    password: password
                })
            })
            .then(function(res) {
                return res.json();
            })
            .then(function(data) {
                if (data.status === 'success') {
                    alert('Account successfully registered');
                    location.reload();
                } else {
                    alert(data.message || 'Unable to approve loan');
                    submitButton.disabled = false;
                }
            })
            .catch(function() {
                alert('Unable to approve loan');
                submitButton.disabled = false;
            });
        });
    </script>
<?php include 'notif_dropdown.php'; ?>
</body>
</html>




