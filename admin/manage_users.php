<?php
require_once 'auth_check.php';
require_once '../db_connect.php';

$roleCards = [
    'admin' => [
        'title' => 'Admin',
        'count_label' => 'Total Admins',
        'icon' => 'ph-shield-check',
        'card_class' => 'primary',
    ],
    'collector' => [
        'title' => 'Collectors',
        'count_label' => 'Total Collectors',
        'icon' => 'ph-user-list',
        'card_class' => '',
    ],
    'borrower' => [
        'title' => 'Borrowers',
        'count_label' => 'Total Borrowers',
        'icon' => 'ph-users-three',
        'card_class' => '',
    ],
];

$roleCounts = [];
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = ?");

foreach ($roleCards as $role => $config) {
    $total = 0;

    if ($countStmt) {
        $countStmt->bind_param("s", $role);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countRow = $countResult ? $countResult->fetch_assoc() : null;
        $total = isset($countRow['total']) ? (int)$countRow['total'] : 0;
    }

    $roleCounts[$role] = $total;
}

if ($countStmt) {
    $countStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .role-card-link {
            text-decoration: none;
            color: inherit;
        }
        .role-card-link .stat-card {
            min-height: 200px;
            justify-content: space-between;
        }
        .role-card-link .stat-card:not(.primary) .stat-icon.collector {
            color: #10B981;
            background: rgba(16, 185, 129, 0.12);
        }
        .role-card-link .stat-card:not(.primary) .stat-icon.borrower {
            color: #5A8DEE;
            background: rgba(90, 141, 238, 0.12);
        }
        .role-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 22px;
            font-size: 14px;
            font-weight: 600;
            color: #667085;
        }
        .role-card-link .stat-card.primary .role-card-footer {
            color: rgba(255, 255, 255, 0.9);
        }
        .page-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .section-card {
            background: var(--card-bg);
            border-radius: var(--radius-main);
            box-shadow: var(--shadow-soft);
            padding: 28px;
            transition: var(--transition);
        }
        .section-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }
        .section-card h2 {
            margin: 0 0 10px;
            font-size: 20px;
        }
        .section-card p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.18);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .top-header {
                align-items: flex-start;
                gap: 16px;
            }
            .header-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>

<?php include 'layout/sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-header">
            <div class="greeting">
                <h1>Manage Users</h1>
                <p>Welcome back to your smart dashboard</p>
            </div>

            <div class="header-actions">
                <div class="page-actions">
                    <button type="button" class="btn btn-success" onclick="openAddUserModal()"><i class="ph ph-plus"></i> Add User</button>
                    <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                    <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
                </div>
            </div>
        </header>

        <div class="content">
            <?php if (isset($_GET['created'])) { ?>
                <div class="alert-success"><i class="ph ph-check-circle"></i> User created successfully.</div>
            <?php } ?>

            <div class="stats-grid">
                <?php foreach ($roleCards as $role => $config) { ?>
                    <a class="role-card-link" href="manage_users_list.php?role=<?php echo urlencode($role); ?>">
                        <article class="stat-card <?php echo $config['card_class']; ?>">
                            <div>
                                <div class="stat-icon <?php echo htmlspecialchars($role); ?>">
                                    <i class="ph <?php echo htmlspecialchars($config['icon']); ?>"></i>
                                </div>
                                <div class="stat-title"><?php echo htmlspecialchars($config['title']); ?></div>
                                <div class="stat-value"><?php echo number_format($roleCounts[$role]); ?></div>
                            </div>
                            <div class="role-card-footer">
                                <span><?php echo htmlspecialchars($config['count_label']); ?>: <?php echo number_format($roleCounts[$role]); ?></span>
                                <i class="ph ph-arrow-right"></i>
                            </div>
                        </article>
                    </a>
                <?php } ?>
            </div>

            <section class="section-card">
                <h2>Role-Based Navigation</h2>
                <p>Each card opens a dedicated user list for that role, with search tools and action controls kept on the next screen so this page stays clean and easy to scan.</p>
            </section>
        </div>
    </main>

    <?php $addUserDefaultRole = ''; ?>
    <?php include 'add_user_modal.php'; ?>
    <?php include 'notif_dropdown.php'; ?>
</body>
</html>
