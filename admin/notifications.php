<?php
require_once 'auth_check.php';
require_once '../db_connect.php';
require_once '../notification_helper.php';

ensure_notifications_table($conn);
sync_overdue_notifications($conn);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    mark_all_notifications_read($conn);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all_read'])) {
        mark_all_notifications_read($conn);
        header('Location: notifications.php');
        exit;
    }

    if (isset($_POST['mark_read_id'])) {
        $notificationId = (int)$_POST['mark_read_id'];
        $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $notificationId);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: notifications.php');
        exit;
    }
}

$sql = "SELECT *
        FROM notifications
        ORDER BY CASE WHEN status = 'unread' THEN 0 ELSE 1 END, created_at DESC";
$result = $conn->query($sql);
$unreadCount = get_unread_notification_count($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - GoCa$h</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .notification-shell {
            display: grid;
            gap: 22px;
        }
        .notification-toolbar,
        .notification-card {
            background: var(--card-bg);
            border-radius: var(--radius-main);
            box-shadow: var(--shadow-soft);
        }
        .notification-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 26px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .notification-summary h2 {
            margin: 0;
            font-size: 20px;
        }
        .notification-summary p {
            margin: 8px 0 0;
            color: var(--text-muted);
            font-size: 14px;
        }
        .notification-list {
            display: grid;
            gap: 18px;
        }
        .notification-card {
            padding: 24px 26px;
            border: 1px solid rgba(90, 141, 238, 0.08);
        }
        .notification-card.unread {
            border-left: 5px solid #F59E0B;
        }
        .notification-card.read {
            opacity: 0.95;
        }
        .notification-meta {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .notification-title {
            margin: 0;
            font-size: 18px;
            color: var(--text-main);
        }
        .notification-message {
            margin: 0 0 18px;
            color: #5b6475;
            line-height: 1.6;
        }
        .notification-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .notification-date {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
        }
        .notification-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .empty-state {
            background: var(--card-bg);
            border-radius: var(--radius-main);
            box-shadow: var(--shadow-soft);
            padding: 48px 30px;
            text-align: center;
            color: var(--text-muted);
        }
        .badge-info {
            background: rgba(90, 141, 238, 0.12);
            color: #5A8DEE;
        }
        .badge-danger {
            background: rgba(244, 63, 94, 0.1);
            color: #F43F5E;
        }
        .nav-badge {
            margin-left: auto;
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            border-radius: 999px;
            background: #F43F5E;
            color: white;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .icon-badge-wrap {
            position: relative;
        }
        .icon-badge {
            position: absolute;
            top: -4px;
            right: -2px;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            background: #F43F5E;
            color: white;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 720px) {
            .notification-toolbar,
            .notification-meta {
                align-items: flex-start;
            }
            .notification-actions {
                width: 100%;
            }
            .notification-actions .btn,
            .notification-actions form {
                width: 100%;
            }
            .notification-actions form .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
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

    <main class="main-wrapper">
        <header class="top-header">
            <div class="greeting">
                <h1>Notifications</h1>
                <p>Monitor loan alerts, overdue reminders, and system messages</p>
            </div>

            <div class="header-actions">
                <button class="icon-btn icon-badge-wrap" id="notifBellBtn"><i class="ph ph-bell"></i><?php if ($unreadCount > 0) { ?><span class="icon-badge" id="topNotificationBadge"><?php echo $unreadCount; ?></span><?php } ?></button>
                <button class="icon-btn" onclick="window.location.href='profile.php'"><i class="ph ph-user"></i></button>
            </div>
        </header>

        <div class="content">
            <div class="notification-shell">
                <div class="notification-toolbar">
                    <div class="notification-summary">
                        <h2><?php echo $unreadCount; ?> unread notification<?php echo $unreadCount === 1 ? '' : 's'; ?></h2>
                        <p>Unread alerts are shown first so urgent admin actions stay visible.</p>
                    </div>
                    <form method="POST">
                        <button type="submit" name="mark_all_read" value="1" class="btn btn-primary">Mark all as read</button>
                    </form>
                </div>

                <?php if ($result && $result->num_rows > 0) { ?>
                    <div class="notification-list">
                        <?php while ($notification = $result->fetch_assoc()) {
                            $viewLink = notification_view_link($notification);
                            $typeLabel = notification_type_label($notification['type']);
                            $isUnread = $notification['status'] === 'unread';
                        ?>
                            <article class="notification-card <?php echo $isUnread ? 'unread' : 'read'; ?>">
                                <div class="notification-meta">
                                    <div>
                                        <h3 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                        <div class="notification-tags">
                                            <span class="badge badge-info"><?php echo htmlspecialchars($typeLabel); ?></span>
                                            <span class="badge <?php echo notification_badge_class($notification['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($notification['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="notification-date"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></div>
                                </div>

                                <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>

                                <div class="notification-actions">
                                    <?php if ($viewLink !== null) { ?>
                                        <a href="<?php echo htmlspecialchars($viewLink); ?>" class="btn btn-primary">View related record</a>
                                    <?php } ?>

                                    <?php if ($isUnread) { ?>
                                        <form method="POST">
                                            <input type="hidden" name="mark_read_id" value="<?php echo (int)$notification['id']; ?>">
                                            <button type="submit" class="btn btn-success">Mark as read</button>
                                        </form>
                                    <?php } else { ?>
                                        <span class="badge badge-primary">Already read</span>
                                    <?php } ?>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="empty-state">
                        <i class="ph ph-bell-slash" style="font-size:42px;"></i>
                        <p>No notifications yet.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </main>
    <?php include 'notif_dropdown.php'; ?>
</body>
</html>
