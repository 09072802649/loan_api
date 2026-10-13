<?php
require_once 'auth_check.php';
require_once '../db_connect.php';

$allowedRoles = ['admin', 'collector', 'borrower'];
$role = $_GET['role'] ?? '';

if (!in_array($role, $allowedRoles, true)) {
    header("Location: manage_users.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$safeRole = ucfirst($role);

$statusColumnExists = false;
$statusColumnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($statusColumnCheck && $statusColumnCheck->num_rows > 0) {
    $statusColumnExists = true;
}

$statusSelect = $statusColumnExists ? "u.status" : "'Active' AS status";
$sql = "
    SELECT
        u.id,
        u.fullname,
        u.username,
        u.phone,
        $statusSelect
    FROM users u
    WHERE u.role = ?
";

$paramTypes = "s";
$params = [$role];

if ($search !== '') {
    $sql .= "
        AND (
            u.fullname LIKE ?
            OR u.username LIKE ?
            OR u.phone LIKE ?
        )
    ";
    $searchTerm = '%' . $search . '%';
    $paramTypes .= "sss";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY u.id DESC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to load users.");
}

$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$users = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($safeRole); ?> Users - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .list-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .list-toolbar-left,
        .list-toolbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .search-form .search-bar {
            min-width: min(100%, 320px);
        }
        .search-form .search-bar input {
            min-width: 220px;
        }
        .table-meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .table-meta p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }
        .empty-state {
            text-align: center;
            padding: 42px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 42px;
            display: block;
            margin-bottom: 10px;
            color: #A0AEC0;
        }
        .btn-secondary {
            background: #E2E8F0;
            color: #334155;
        }
        .action-stack {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
            .search-form .search-bar,
            .search-form .search-bar input {
                width: 100%;
                min-width: 0;
            }
        }
    </style>
</head>
<body>
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

    <main class="main-wrapper">
        <header class="top-header">
            <div class="greeting">
                <h1><?php echo htmlspecialchars($safeRole); ?> Users</h1>
                <p>Manage <?php echo htmlspecialchars(strtolower($safeRole)); ?> accounts with focused search and quick actions.</p>
            </div>

            <div class="header-actions">
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
            </div>
        </header>

        <div class="content">
            <?php if (isset($_GET['updated'])) { ?>
                <div style="background:rgba(16,185,129,0.1);color:#10B981;border:1px solid rgba(16,185,129,0.2);border-radius:12px;padding:12px 18px;margin-bottom:18px;font-weight:500;">
                    <i class="ph ph-check-circle"></i> User updated successfully.
                </div>
            <?php } ?>
            <?php if (isset($_GET['created'])) { ?>
                <div style="background:rgba(16,185,129,0.1);color:#10B981;border:1px solid rgba(16,185,129,0.2);border-radius:12px;padding:12px 18px;margin-bottom:18px;font-weight:500;">
                    <i class="ph ph-check-circle"></i> User created successfully.
                </div>
            <?php } ?>

            <div class="list-toolbar">
                <div class="list-toolbar-left">
                    <a href="manage_users.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Back</a>
                    <button type="button" class="btn btn-success" onclick="openAddUserModal('<?php echo htmlspecialchars($role, ENT_QUOTES); ?>')"><i class="ph ph-plus"></i> Add User</button>
                </div>

                <div class="list-toolbar-right">
                    <form class="search-form" method="GET">
                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                        <div class="search-bar">
                            <i class="ph ph-magnifying-glass"></i>
                            <input
                                type="text"
                                name="search"
                                value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by name, username, or phone"
                            >
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if ($search !== '') { ?>
                            <a href="manage_users_list.php?role=<?php echo urlencode($role); ?>" class="btn btn-secondary">Clear</a>
                        <?php } ?>
                    </form>
                </div>
            </div>

            <div class="table-container">
                <div class="table-meta">
                    <p>Showing <?php echo (int)$users->num_rows; ?> <?php echo htmlspecialchars(strtolower($safeRole)); ?> user<?php echo $users->num_rows === 1 ? '' : 's'; ?><?php echo $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?>.</p>
                </div>

                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Phone</th>
                            <th>Account Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows === 0) { ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="ph ph-users"></i>
                                        No users found for this filter.
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>

                        <?php while ($row = $users->fetch_assoc()) { ?>
                            <?php
                            $status = trim((string)($row['status'] ?? ''));
                            if ($status === '') {
                                $status = 'Active';
                            }
                            $statusClass = strtolower($status) === 'inactive' ? 'badge-warning' : 'badge-success';
                            $returnUrl = 'manage_users_list.php?role=' . urlencode($role);
                            if ($search !== '') {
                                $returnUrl .= '&search=' . urlencode($search);
                            }
                            ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?: 'N/A'); ?></td>
                                <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                                <td>
                                    <div class="action-stack">
                                        <a href="edit_user.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                        <a
                                            href="delete_user.php?id=<?php echo (int)$row['id']; ?>&redirect=<?php echo urlencode($returnUrl); ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Delete this user?')"
                                        >
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php $addUserDefaultRole = $role; ?>
    <?php include 'add_user_modal.php'; ?>
    <?php include 'notif_dropdown.php'; ?>
</body>
</html>
<?php
$stmt->close();
?>
