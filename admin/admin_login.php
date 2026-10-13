<?php
session_start();
include "../db_connect.php";
require_once "profile_helper.php";

ensure_admin_profile_columns($conn);

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $sql = "SELECT * FROM users WHERE username=? AND role='admin'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $updateLogin = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            if ($updateLogin) {
                $updateLogin->bind_param("i", $row['id']);
                $updateLogin->execute();
                $updateLogin->close();
            }
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_name'] = $row['fullname'];
            header("Location: dashboard.php");
            exit();
        }
        else {
            $error = "Wrong password";
        }
    }
    else {
        $error = "Admin not found";
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoCa$h Admin Login</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: url('assets/login_bg.png') center center / cover no-repeat;
            font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        .login-wrapper {
            width: 100%;
            max-width: 460px;
            padding: 20px;
        }

        .login-card {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 50px 45px;
            box-shadow: 12px 12px 30px rgba(0, 0, 0, 0.06), -12px -12px 30px rgba(255, 255, 255, 0.9);
            text-align: center;
        }

        .logo-area {
            margin-bottom: 30px;
        }

        .logo-area img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 15px;
            border-radius: 20px;
        }

        .logo-fallback {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, #6A5AE0, #8B5CF6);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin-bottom: 15px;
        }

        .brand-name {
            font-size: 26px;
            font-weight: 800;
            background: linear-gradient(135deg, #6A5AE0, #8B5CF6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 0.5px;
        }

        .brand-sub {
            font-size: 14px;
            color: #888888;
            margin-top: 5px;
        }

        .divider {
            height: 1px;
            background: #F0F2F5;
            margin: 28px 0;
        }

        .form-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 22px;
            text-align: left;
        }

        .error-alert {
            background: rgba(244, 63, 94, 0.08);
            color: #F43F5E;
            border: 1px solid rgba(244, 63, 94, 0.2);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-group {
            position: relative;
            margin-bottom: 18px;
        }

        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #AAAAAA;
            font-size: 20px;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            border: 1.5px solid #E8ECF0;
            border-radius: 14px;
            font-size: 15px;
            font-family: inherit;
            color: #333;
            background: #FAFBFC;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input::placeholder {
            color: #BBBBBB;
        }

        .form-input:focus {
            border-color: #6A5AE0;
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(106, 90, 224, 0.08);
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #6A5AE0, #8B5CF6);
            color: white;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(106, 90, 224, 0.35);
            margin-top: 8px;
            letter-spacing: 0.3px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(106, 90, 224, 0.45);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 28px;
            font-size: 13px;
            color: #AAAAAA;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">

            <!-- LOGO AREA -->
            <div class="logo-area">
                <div class="logo-fallback">
                    <i class="ph ph-currency-circle-dollar"></i>
                </div>
                <div class="brand-name">GoCa$h Admin</div>
                <div class="brand-sub">Loan Monitoring System</div>
            </div>

            <div class="divider"></div>

            <div class="form-title">Sign in to your account</div>

            <!-- ERROR MESSAGE -->
            <?php if (isset($error)): ?>
            <div class="error-alert">
                <i class="ph ph-warning-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php
endif; ?>

            <!-- LOGIN FORM — backend logic unchanged -->
            <form method="POST">

                <div class="input-group">
                    <i class="ph ph-user"></i>
                    <input type="text" name="username" class="form-input" placeholder="Username" required autocomplete="username">
                </div>

                <div class="input-group">
                    <i class="ph ph-lock"></i>
                    <input type="password" name="password" class="form-input" placeholder="Password" required>
                </div>

                <button type="submit" name="login" class="btn-login">
                    Sign In
                </button>

            </form>

            <div class="login-footer">
                &copy; <?php echo date("Y"); ?> GoCa$h &mdash; All rights reserved
            </div>

        </div>
    </div>

</body>
</html>
