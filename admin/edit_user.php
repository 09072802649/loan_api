<?php
require_once 'auth_check.php';
require_once '../db_connect.php';

$allowedRoles = ['admin', 'collector', 'borrower'];
$userId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);

if ($userId <= 0) {
    header("Location: manage_users.php");
    exit();
}

$statusColumnExists = false;
$statusColumnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($statusColumnCheck && $statusColumnCheck->num_rows > 0) {
    $statusColumnExists = true;
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = trim($_POST['status'] ?? 'Active');

    if ($fullname === '' || $username === '') {
        $errorMessage = 'Please provide a full name and username.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $errorMessage = 'Invalid role selected.';
    } else {
        $userCheck = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
        if ($userCheck) {
            $userCheck->bind_param("si", $username, $userId);
            $userCheck->execute();
            $userResult = $userCheck->get_result();
            $duplicate = $userResult ? $userResult->fetch_assoc() : null;
            $userCheck->close();

            if ($duplicate) {
                $errorMessage = 'That username is already in use.';
            }
        }
    }

    if ($errorMessage === '') {
        if ($statusColumnExists) {
            $updateStmt = $conn->prepare("UPDATE users SET fullname = ?, username = ?, phone = ?, role = ?, status = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("sssssi", $fullname, $username, $phone, $role, $status, $userId);
            }
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET fullname = ?, username = ?, phone = ?, role = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("ssssi", $fullname, $username, $phone, $role, $userId);
            }
        }

        if (!isset($updateStmt) || !$updateStmt) {
            $errorMessage = 'Unable to update this user right now.';
        } elseif ($updateStmt->execute()) {
            $updateStmt->close();
            header("Location: manage_users_list.php?role=" . urlencode($role) . "&updated=1");
            exit();
        } else {
            $errorMessage = 'Unable to update this user right now.';
            $updateStmt->close();
        }
    }
}

$selectSql = $statusColumnExists
    ? "SELECT id, fullname, username, phone, role, status FROM users WHERE id = ? LIMIT 1"
    : "SELECT id, fullname, username, phone, role, 'Active' AS status FROM users WHERE id = ? LIMIT 1";

$userStmt = $conn->prepare($selectSql);
if (!$userStmt) {
    die("Unable to load user.");
}

$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult ? $userResult->fetch_assoc() : null;
$userStmt->close();

if (!$user || !in_array($user['role'], $allowedRoles, true)) {
    header("Location: manage_users.php");
    exit();
}

$currentStatus = trim((string)($user['status'] ?? ''));
if ($currentStatus === '') {
    $currentStatus = 'Active';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .form-card {
            background: var(--card-bg);
            border-radius: var(--radius-main);
            box-shadow: var(--shadow-soft);
            padding: 28px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 20px;
        }
        .form-field.full {
            grid-column: 1 / -1;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .form-input {
            width: 100%;
            box-sizing: border-box;
            padding: 13px 15px;
            border: 1.5px solid #E8ECF0;
            border-radius: 14px;
            font-size: 15px;
            font-family: inherit;
            color: #333;
            background: #FAFBFC;
            outline: none;
        }
        .form-input:focus {
            border-color: #6A5AE0;
            box-shadow: 0 0 0 4px rgba(106, 90, 224, 0.08);
            background: #fff;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        .btn-secondary {
            background: #E2E8F0;
            color: #334155;
        }
        .alert-error {
            background: rgba(244, 63, 94, 0.1);
            color: #F43F5E;
            border: 1px solid rgba(244, 63, 94, 0.18);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-field.full {
                grid-column: auto;
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
                <h1>Edit User</h1>
                <p>Update user information without changing the existing database structure.</p>
            </div>

            <div class="header-actions">

            </div>
        </header>

        <div class="content">
            <?php if ($errorMessage !== '') { ?>
                <div class="alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php } ?>

            <section class="form-card">
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">

                    <div class="form-grid">
                        <div class="form-field full">
                            <label class="form-label" for="fullname">Full Name</label>
                            <input class="form-input" type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>

                        <div class="form-field">
                            <label class="form-label" for="username">Username</label>
                            <input class="form-input" type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required autocomplete="username">
                        </div>

                        <div class="form-field">
                            <label class="form-label" for="phone">Phone</label>
                            <input class="form-input" type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>

                        <div class="form-field">
                            <label class="form-label" for="role">Role</label>
                            <select class="form-input" id="role" name="role" required>
                                <?php foreach ($allowedRoles as $roleOption) { ?>
                                    <option value="<?php echo htmlspecialchars($roleOption); ?>" <?php echo $user['role'] === $roleOption ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($roleOption)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <?php if ($statusColumnExists) { ?>
                            <div class="form-field">
                                <label class="form-label" for="status">Account Status</label>
                                <select class="form-input" id="status" name="status">
                                    <option value="Active" <?php echo strcasecmp($currentStatus, 'Active') === 0 ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo strcasecmp($currentStatus, 'Inactive') === 0 ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="manage_users_list.php?role=<?php echo urlencode($user['role']); ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </section>
        </div>
    </main>
    <?php include 'notif_dropdown.php'; ?>
</body>
</html>
