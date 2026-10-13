<?php
include 'auth_check.php';
include "../db_connect.php";
require_once "../payment_helper.php";

ensurePaymentStatusColumn($conn);



$searchTerm = trim((string)($_GET['search'] ?? ''));
$today = date('Y-m-d');

$statsSql = "
    SELECT
        COALESCE(SUM(CASE WHEN status = 'approved' AND DATE(payment_date) = CURDATE() THEN amount ELSE 0 END), 0) AS total_collected_today,
        COUNT(*) AS total_proofs
    FROM payments
";
$statsResult = $conn->query($statsSql);
$stats = $statsResult ? $statsResult->fetch_assoc() : [
    'total_collected_today' => 0,
    'total_proofs' => 0,
];

$sql = "
    SELECT
        p.id,
        p.loan_id,
        p.amount,
        p.proof_image,
        p.payment_date,
        p.status,
        borrower.fullname AS borrower_name,
        collector.fullname AS collector_name
    FROM payments p
    JOIN users AS borrower ON borrower.id = p.borrower_id
    LEFT JOIN users AS collector ON collector.id = p.collector_id
    WHERE 1 = 1
";

$paramTypes = '';
$params = [];

if ($searchTerm !== '') {
    $searchLike = '%' . $searchTerm . '%';
    $sql .= "
        AND (
            borrower.fullname LIKE ?
            OR collector.fullname LIKE ?
            OR CAST(p.amount AS CHAR) LIKE ?
        )
    ";
    $paramTypes .= 'sss';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= " ORDER BY p.payment_date DESC, p.id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

if (!empty($params)) {
    $bindValues = array_merge([$paramTypes], $params);
    $bindReferences = [];
    foreach ($bindValues as $index => $value) {
        $bindReferences[$index] = &$bindValues[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindReferences);
}

$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Proof - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            border: 1px solid #E8ECF0;
        }
        .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 10px;
        }
        .summary-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
        }
        .summary-value.success { color: #059669; }
        .summary-value.warning { color: #D97706; }
        .summary-value.danger { color: #DC2626; }
        .payments-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-form .search-bar {
            min-width: 280px;
        }
        .secondary-btn {
            border: 1px solid #D7DEE7;
            background: white;
            color: #475569;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .secondary-btn:hover {
            background: #F8FAFC;
        }
        .proof-thumb {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            border: 1px solid #E2E8F0;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .proof-thumb:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.26);
        }
        .action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .proof-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.75);
            z-index: 1200;
            padding: 20px;
        }
        .proof-modal.active {
            display: flex;
        }
        .proof-modal-card {
            background: white;
            border-radius: 18px;
            width: min(900px, 100%);
            max-height: calc(100vh - 40px);
            padding: 18px;
            position: relative;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.3);
        }
        .proof-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 999px;
            background: #0F172A;
            color: white;
            cursor: pointer;
        }
        .proof-modal-image {
            width: 100%;
            max-height: calc(100vh - 120px);
            object-fit: contain;
            border-radius: 12px;
            background: #F8FAFC;
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 12px;
        }
        .detail-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 14px;
        }
        .detail-card.full {
            grid-column: 1 / -1;
        }
        .detail-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #64748B;
            margin-bottom: 6px;
        }
        .detail-value {
            font-size: 15px;
            font-weight: 700;
            color: #0F172A;
            word-break: break-word;
        }
        .details-section {
            margin-top: 18px;
        }
        .details-section:first-of-type {
            margin-top: 20px;
        }
        .section-heading {
            font-size: 13px;
            font-weight: 800;
            color: #0F766E;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .view-btn {
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            padding: 9px 14px;
            font-weight: 700;
            cursor: pointer;
            background: white;
            color: #0F172A;
        }
        .view-btn:hover {
            background: #F8FAFC;
        }
        .proof-viewer-header {
            margin-bottom: 14px;
        }
        .proof-viewer-title {
            font-size: 18px;
            font-weight: 800;
            color: #0F172A;
        }
        .proof-viewer-meta {
            font-size: 13px;
            color: #64748B;
            margin-top: 4px;
        }
        .proof-viewer-grid {
            display: grid;
            grid-template-columns: minmax(0, 260px) minmax(0, 1fr);
            gap: 18px;
            align-items: flex-start;
            margin-top: 10px;
        }
        .proof-viewer-info {
            display: grid;
            gap: 8px;
            font-size: 14px;
            color: #0F172A;
        }
        .proof-viewer-label {
            font-weight: 600;
            color: #64748B;
            margin-right: 4px;
        }
        .proof-viewer-image-wrap {
            border-radius: 12px;
            overflow: hidden;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
        }
        .proof-viewer-image {
            width: 100%;
            max-height: 420px;
            object-fit: contain;
            display: block;
        }
        @media (max-width: 768px) {
            .proof-viewer-grid {
                grid-template-columns: 1fr;
            }
        }
        .empty-state {
            text-align: center;
            padding: 36px 20px;
            color: var(--text-muted);
        }
        @media (max-width: 640px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
            .search-form .search-bar {
                min-width: 100%;
            }
            .details-grid {
                grid-template-columns: 1fr;
            }
            .detail-card.full {
                grid-column: auto;
            }
        }
    </style>
</head>
<body>

<?php include 'layout/sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-header">
            <div class="greeting">
                <h1>Payment Proof</h1>
                <p>Monitor all payment proof submissions from collectors</p>
            </div>

            <div class="header-actions">
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
            </div>
        </header>

        <div class="content">
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-label">Total Collected Today</div>
                    <div class="summary-value success">PHP <?php echo number_format((float)$stats['total_collected_today'], 2); ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Proof of Payment</div>
                    <div class="summary-value"><?php echo (int)$stats['total_proofs']; ?></div>
                </div>
            </div>

            <div class="payments-toolbar">
                <form class="search-form" method="GET">
                    <div class="search-bar">
                        <i class="ph ph-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES); ?>"
                            placeholder="Search borrower, collector, or amount">
                    </div>
                    <button type="submit" class="secondary-btn">Search</button>
                    <?php if ($searchTerm !== '') { ?>
                        <a class="secondary-btn" href="payments.php">Clear</a>
                    <?php } ?>
                </form>
            </div>

            <div class="table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Collector</th>
                            <th>Amount</th>
                            <th>Proof</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0) { ?>
                            <?php while ($row = $result->fetch_assoc()) { ?>
                                <?php $proofUrl = resolvePaymentProofUrl($row['proof_image']); ?>
                                <tr id="payment-row-<?php echo (int)$row['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($row['borrower_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['collector_name'] ?: 'Unassigned'); ?></td>
                                    <td style="color:var(--text-main); font-weight:700;">PHP <?php echo number_format((float)$row['amount'], 2); ?></td>
                                    <td>
                                        <?php if ($proofUrl !== '') { ?>
                                            <img
                                                src="<?php echo htmlspecialchars($proofUrl, ENT_QUOTES); ?>"
                                                class="proof-thumb"
                                                alt="Proof image"
                                                data-proof-url="<?php echo htmlspecialchars($proofUrl, ENT_QUOTES); ?>"
                                                onclick="openProofModal(this.dataset.proofUrl)">
                                        <?php } else { ?>
                                            <span style="color: var(--text-muted); font-size: 0.9em;">No Image</span>
                                        <?php } ?>
                                    </td>
                                    <td style="color:var(--text-muted);"><?php echo date("M d, Y h:i A", strtotime($row['payment_date'])); ?></td>
                                    <td>
                                        <div class="action-row">
                                            <button
                                                class="view-btn"
                                                type="button"
                                                onclick="openProofViewer(this)"
                                                data-proof-url="<?php echo htmlspecialchars($proofUrl, ENT_QUOTES); ?>"
                                                data-borrower="<?php echo htmlspecialchars($row['borrower_name'], ENT_QUOTES); ?>"
                                                data-collector="<?php echo htmlspecialchars($row['collector_name'] ?: 'Unassigned', ENT_QUOTES); ?>"
                                                data-amount="<?php echo htmlspecialchars(number_format((float)$row['amount'], 2), ENT_QUOTES); ?>"
                                                data-date="<?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['payment_date'])), ENT_QUOTES); ?>"
                                            >
                                                View
                                            </button>
                                            <button
                                                class="view-btn"
                                                type="button"
                                                onclick="openPaymentDetails(<?php echo (int)$row['id']; ?>)"
                                            >
                                                Verify
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="6" class="empty-state">No payment proofs found.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="proof-modal" id="proofModal">
        <div class="proof-modal-card">
            <button class="proof-modal-close" type="button" onclick="closeProofModal()">×</button>
            <div class="proof-viewer-header">
                <div class="proof-viewer-title">Payment Proof</div>
                <div class="proof-viewer-meta">Full-size proof image and payment details</div>
            </div>
            <div class="proof-viewer-grid">
                <div class="proof-viewer-info">
                    <div><span class="proof-viewer-label">Borrower:</span><span id="proofBorrower">N/A</span></div>
                    <div><span class="proof-viewer-label">Collector:</span><span id="proofCollector">N/A</span></div>
                    <div><span class="proof-viewer-label">Amount:</span><span id="proofAmount">N/A</span></div>
                    <div><span class="proof-viewer-label">Date:</span><span id="proofDate">N/A</span></div>
                </div>
                <div class="proof-viewer-image-wrap">
                    <img src="" alt="Full proof" id="proofModalImage" class="proof-viewer-image">
                </div>
            </div>
        </div>
    </div>

    <div class="proof-modal" id="paymentDetailsModal">
        <div class="proof-modal-card">
            <button class="proof-modal-close" type="button" onclick="closePaymentDetails()">×</button>
            <div style="padding-right:44px;">
                <div style="font-size:20px; font-weight:800; color:#0F172A;">Payment Verification Details</div>
                <div style="font-size:13px; color:#64748B; margin-top:4px;">Loan and payment snapshot for admin review</div>
            </div>
            <div class="details-grid" id="paymentDetailsGrid">
                <div class="detail-card full">
                    <div class="detail-label">Loading</div>
                    <div class="detail-value">Fetching payment details...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openProofModal(imageUrl) {
            const modal = document.getElementById('proofModal');
            const image = document.getElementById('proofModalImage');
            image.src = imageUrl;
            modal.classList.add('active');
        }

        function closeProofModal() {
            const modal = document.getElementById('proofModal');
            const image = document.getElementById('proofModalImage');
            modal.classList.remove('active');
            image.src = '';
        }

        document.getElementById('proofModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeProofModal();
            }
        });

        function openProofViewer(button) {
            const dataset = button.dataset;
            const imageUrl = dataset.proofUrl || '';
            document.getElementById('proofBorrower').textContent = dataset.borrower || 'N/A';
            document.getElementById('proofCollector').textContent = dataset.collector || 'N/A';
            document.getElementById('proofAmount').textContent = dataset.amount ? '₱ ' + dataset.amount : 'N/A';
            document.getElementById('proofDate').textContent = dataset.date || 'N/A';
            openProofModal(imageUrl);
        }

        function formatCurrency(value) {
            const amount = Number(value || 0);
            return 'PHP ' + amount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function setPaymentDetailsHtml(data) {
            const proofImage = data.proof_image
                ? `<img src="${data.proof_image.startsWith('uploads/') ? '../' + data.proof_image : '../uploads/' + data.proof_image}" alt="Proof image" class="proof-modal-image" style="max-height:320px; margin-top:10px;">`
                : '<div class="detail-value">No proof image uploaded.</div>';

            return `
                <div class="detail-card full" style="padding:0; border:none; background:transparent;">
                    <div class="details-section">
                        <div class="section-heading">Loan Details</div>
                        <div class="details-grid">
                            <div class="detail-card">
                                <div class="detail-label">Borrower Name</div>
                                <div class="detail-value">${data.borrower_name || 'N/A'}</div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Loan Account Number</div>
                                <div class="detail-value">${data.loan_account_number || 'N/A'}</div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Loan Amount</div>
                                <div class="detail-value">${formatCurrency(data.loan_amount)}</div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Interest Rate</div>
                                <div class="detail-value">${data.interest_rate || 0}%</div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Loan Term</div>
                                <div class="detail-value">${data.loan_term || 'N/A'}</div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Payment Frequency</div>
                                <div class="detail-value">${data.payment_frequency || 'N/A'}</div>
                            </div>
                            <div class="detail-card full">
                                <div class="detail-label">Total Payable</div>
                                <div class="detail-value">${formatCurrency(data.total_payable)}</div>
                            </div>
                            <div class="detail-card full">
                                <div class="detail-label">Remaining Balance</div>
                                <div class="detail-value">${formatCurrency(data.remaining_balance)}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="detail-card full" style="padding:0; border:none; background:transparent;">
                    <div class="details-section">
                        <div class="section-heading">Payment Verification</div>
                        <div class="details-grid">
                            <div class="detail-card">
                                <div class="detail-label">Payment Amount</div>
                                <div class="detail-value">${formatCurrency(data.payment_amount)}</div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Payment Date</div>
                                <div class="detail-value">${data.payment_date || 'N/A'}</div>
                            </div>
                            <div class="detail-card full">
                                <div class="detail-label">Collector Name</div>
                                <div class="detail-value">${data.collector_name || 'N/A'}</div>
                            </div>
                            <div class="detail-card full">
                                <div class="detail-label">Proof of Payment Image</div>
                                ${proofImage}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        async function openPaymentDetails(paymentId) {
            const modal = document.getElementById('paymentDetailsModal');
            const grid = document.getElementById('paymentDetailsGrid');

            grid.innerHTML = `
                <div class="detail-card full">
                    <div class="detail-label">Loading</div>
                    <div class="detail-value">Fetching payment details...</div>
                </div>
            `;
            modal.classList.add('active');

            try {
                const response = await fetch('get_payment_verification_details.php?payment_id=' + encodeURIComponent(paymentId), {
                    cache: 'no-store'
                });
                const data = await response.json();

                if (data.status !== 'success') {
                    throw new Error(data.message || 'Unable to load payment details');
                }

                grid.innerHTML = setPaymentDetailsHtml(data);
            } catch (error) {
                grid.innerHTML = `
                    <div class="detail-card full">
                        <div class="detail-label">Error</div>
                        <div class="detail-value">${error.message || 'Unable to load payment details.'}</div>
                    </div>
                `;
            }
        }

        function closePaymentDetails() {
            document.getElementById('paymentDetailsModal').classList.remove('active');
        }

        document.getElementById('paymentDetailsModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closePaymentDetails();
            }
        });

    </script>
<?php include 'notif_dropdown.php'; ?>
</body>
</html>
<?php
$stmt->close();
?>
