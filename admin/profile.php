<?php
require_once "auth_check.php";
require_once "../db_connect.php";
require_once "profile_helper.php";

ensure_admin_profile_columns($conn);

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullname === '' || $username === '') {
            $errorMessage = 'Full name and username are required.';
        }
        elseif (strlen($username) < 3) {
            $errorMessage = 'Username must be at least 3 characters.';
        }
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errorMessage = 'Username may only contain letters, numbers, and underscores.';
        }
        elseif ($phone !== '' && !preg_match('/^[0-9]+$/', $phone)) {
            $errorMessage = 'Phone number must contain numbers only.';
        }
        else {
            $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            if ($checkUser) {
                $checkUser->bind_param("si", $username, $adminId);
                $checkUser->execute();
                $userResult = $checkUser->get_result();
                $existingUser = $userResult ? $userResult->fetch_assoc() : null;
                $checkUser->close();

                if ($existingUser) {
                    $errorMessage = 'That username is already in use.';
                }
            }

            if ($errorMessage === '') {
                $updateProfile = $conn->prepare(
                    "UPDATE users SET fullname = ?, phone = ?, username = ? WHERE id = ? AND role = 'admin'"
                );
                if ($updateProfile) {
                    $updateProfile->bind_param("sssi", $fullname, $phone, $username, $adminId);
                    if ($updateProfile->execute()) {
                        $_SESSION['admin_name'] = $fullname;
                        $successMessage = 'Profile information updated successfully.';
                    }
                    else {
                        $errorMessage = 'Unable to update profile information.';
                    }
                    $updateProfile->close();
                }
                else {
                    $errorMessage = 'Unable to update profile information.';
                }
            }
        }
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword === '' || $confirmPassword === '') {
            $errorMessage = 'New passwords do not match.';
        }
        elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'New passwords do not match.';
        }
        elseif (strlen($newPassword) < 6) {
            $errorMessage = 'New password must be at least 6 characters long.';
        }
        elseif ($currentPassword === '') {
            $errorMessage = 'Current password is incorrect.';
        }
        else {
            $passwordLookup = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            if ($passwordLookup) {
                $passwordLookup->bind_param("i", $adminId);
                $passwordLookup->execute();
                $passwordResult = $passwordLookup->get_result();
                $adminRow = $passwordResult ? $passwordResult->fetch_assoc() : null;
                $passwordLookup->close();

                if (!$adminRow || !password_verify($currentPassword, $adminRow['password'])) {
                    $errorMessage = 'Current password is incorrect.';
                }
                elseif (password_verify($newPassword, $adminRow['password'])) {
                    $errorMessage = 'New password cannot be the same as the old password.';
                }
                else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updatePassword = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($updatePassword) {
                        $updatePassword->bind_param("si", $newHash, $adminId);
                        if ($updatePassword->execute()) {
                            $successMessage = 'Password successfully updated.';
                        }
                        else {
                            $errorMessage = 'Unable to change password.';
                        }
                        $updatePassword->close();
                    }
                    else {
                        $errorMessage = 'Unable to change password.';
                    }
                }
            }
            else {
                $errorMessage = 'Unable to change password.';
            }
        }
    }
}

$stmt = $conn->prepare(
    "SELECT id, fullname, username, phone, role, status, created_at, last_login
     FROM users
     WHERE id = ? AND role = 'admin'
     LIMIT 1"
);

if (!$stmt) {
    die("Unable to load admin profile.");
}

$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

$accountStatus = !empty($admin['status']) ? ucfirst($admin['status']) : 'Active';
$createdAt = !empty($admin['created_at']) ? date("M d, Y h:i A", strtotime($admin['created_at'])) : 'N/A';
$lastLogin = !empty($admin['last_login']) ? date("M d, Y h:i A", strtotime($admin['last_login'])) : 'No login recorded yet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .profile-layout {
            display: grid;
            gap: 24px;
        }
        .profile-card {
            background: var(--card-bg);
            border-radius: var(--radius-main);
            box-shadow: var(--shadow-soft);
            padding: 28px;
        }
        .profile-card-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .profile-card-header h2 {
            margin: 0;
            font-size: 20px;
            color: var(--text-main);
        }
        .profile-card-header p {
            margin: 8px 0 0;
            color: var(--text-muted);
            font-size: 14px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 20px;
        }
        .profile-info-box {
            background: #f8faff;
            border: 1px solid #edf1f7;
            border-radius: 16px;
            padding: 18px 20px;
        }
        .profile-info-box.full {
            grid-column: 1 / -1;
        }
        .profile-label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .profile-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
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
            padding: 13px 15px;
            border: 1.5px solid #E8ECF0;
            border-radius: 14px;
            font-size: 15px;
            font-family: inherit;
            color: #333;
            background: #FAFBFC;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }
        .form-input:focus {
            border-color: #6A5AE0;
            box-shadow: 0 0 0 4px rgba(106, 90, 224, 0.08);
            background: #fff;
        }
        .profile-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 22px;
        }
        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }
        .status-chip.active {
            background: rgba(16, 185, 129, 0.12);
            color: #10B981;
        }
        .status-chip.inactive {
            background: rgba(244, 63, 94, 0.1);
            color: #F43F5E;
        }
        .alert-box {
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.18);
        }
        .alert-error {
            background: rgba(244, 63, 94, 0.1);
            color: #F43F5E;
            border: 1px solid rgba(244, 63, 94, 0.18);
        }
        @media (max-width: 768px) {
            .profile-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
            .profile-info-box.full,
            .form-field.full {
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
                <h1>Admin Profile</h1>
                <p>View account details, update your information, and manage security settings</p>
            </div>

            <div class="header-actions">
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i></button>
                <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
            </div>
        </header>

        <div class="content">
            <?php if ($successMessage !== '') { ?>
                <div class="alert-box alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php
}?>

            <?php if ($errorMessage !== '') { ?>
                <div class="alert-box alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php
}?>

            <div class="profile-layout">
                <section class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <h2>Personal Information</h2>
                            <p>Review your current admin details and update contact information.</p>
                        </div>
                        <span class="status-chip <?php echo strtolower($accountStatus); ?>">
                            <i class="ph ph-shield-check"></i>
                            <?php echo htmlspecialchars($accountStatus); ?>
                        </span>
                    </div>

                    <div class="profile-grid" style="margin-bottom: 24px;">
                        <div class="profile-info-box">
                            <span class="profile-label">Admin Full Name</span>
                            <div class="profile-value"><?php echo htmlspecialchars($admin['fullname']); ?></div>
                        </div>
                        <div class="profile-info-box">
                            <span class="profile-label">Username</span>
                            <div class="profile-value"><?php echo htmlspecialchars($admin['username']); ?></div>
                        </div>
                        <div class="profile-info-box">
                            <span class="profile-label">Contact Number</span>
                            <div class="profile-value"><?php echo htmlspecialchars($admin['phone'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="profile-info-box">
                            <span class="profile-label">Account Role</span>
                            <div class="profile-value"><?php echo ucfirst(htmlspecialchars($admin['role'])); ?></div>
                        </div>
                        <div class="profile-info-box full">
                            <span class="profile-label">Date Account Created</span>
                            <div class="profile-value"><?php echo htmlspecialchars($createdAt); ?></div>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-grid">
                            <div class="form-field full">
                                <label class="form-label" for="fullname">Full Name</label>
                                <input class="form-input" type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($admin['fullname']); ?>" required>
                            </div>
                            <div class="form-field">
                                <label class="form-label" for="username">Username</label>
                                <input class="form-input" type="text" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required autocomplete="username">
                            </div>
                            <div class="form-field">
                                <label class="form-label" for="phone">Contact Number</label>
                                <input class="form-input" type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>">
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button type="submit" class="btn btn-primary">Save Profile Changes</button>
                        </div>
                    </form>
                </section>

                <section class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <h2>Account Information</h2>
                            <p>Key account metadata for the current admin session.</p>
                        </div>
                    </div>

                    <div class="profile-grid">
                        <div class="profile-info-box">
                            <span class="profile-label">Admin ID</span>
                            <div class="profile-value">#<?php echo (int)$admin['id']; ?></div>
                        </div>
                        <div class="profile-info-box">
                            <span class="profile-label">Role</span>
                            <div class="profile-value"><?php echo ucfirst(htmlspecialchars($admin['role'])); ?></div>
                        </div>
                        <div class="profile-info-box">
                            <span class="profile-label">Last Login Date</span>
                            <div class="profile-value"><?php echo htmlspecialchars($lastLogin); ?></div>
                        </div>
                        <div class="profile-info-box">
                            <span class="profile-label">Account Status</span>
                            <div class="profile-value"><?php echo htmlspecialchars($accountStatus); ?></div>
                        </div>
                    </div>
                </section>

                <section class="profile-card">
                    <div class="profile-card-header">
                        <div>
                            <h2>Security Settings</h2>
                            <p>Change your password using secure password hashing.</p>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-grid">
                            <div class="form-field full">
                                <label class="form-label" for="current_password">Current Password</label>
                                <input class="form-input" type="password" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-field">
                                <label class="form-label" for="new_password">New Password</label>
                                <input class="form-input" type="password" id="new_password" name="new_password" required>
                            </div>
                            <div class="form-field">
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <input class="form-input" type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button type="submit" class="btn btn-success">Change Password</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </main>
    <?php include 'notif_dropdown.php'; ?>
</body>
</html>
