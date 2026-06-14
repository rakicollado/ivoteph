<?php
require_once dirname(__FILE__) . '/../helpers/functions.php';

if (session_id() == '') {
    session_start();
}

$error = '';

if (isset($_SESSION['admin_id'])) {
    header('Location: /ivoteph/admin/admin/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = '';
    $password = '';

    if (isset($_POST['email'])) {
        $email = trim($_POST['email']);
    }

    if (isset($_POST['password'])) {
        $password = $_POST['password'];
    }

    if ($email == '' || $password == '') {
        $error = 'Please enter your email and password.';
    } else {
        $pdo = db();

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
        $stmt->execute(array(':email' => $email));
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && verify_password_compat($password, $admin['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['admin_name'];
            $_SESSION['admin_email'] = $admin['email'];

            if (isset($admin['role'])) {
                $_SESSION['admin_role'] = $admin['role'];
            } else {
                $_SESSION['admin_role'] = 'Admin';
            }

            $_SESSION['ADMIN_LAST_ACTIVITY'] = time();

            log_admin_action($admin['admin_name'], 'Login');

            header('Location: ../index.php');
            exit();
        } else {
            $error = 'Invalid admin email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>iVotePH Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="ivote-login-page">
    <div class="ivote-login-card">
        <div class="ivote-login-logo">
            <img src="../assets/img/ivoteph-logo.png" alt="iVotePH Logo">
        </div>

        <h1 class="ivote-login-title">Admin Login</h1>
        <p class="ivote-login-subtitle">Secure access to the iVotePH election management system.</p>

        <?php if ($error != '') { ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php } ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'login_required') { ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Please log in first to access the admin dashboard.
            </div>
        <?php } ?>

        <?php if (isset($_GET['error']) && $_GET['error'] == 'session_expired') { ?>
            <div class="alert alert-warning">
                <i class="bi bi-clock-fill me-1"></i>
                Your admin session expired. Please log in again.
            </div>
        <?php } ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'logout') { ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-1"></i>
                You have been logged out successfully.
            </div>
        <?php } ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-envelope-fill text-primary"></i>
                    </span>
                    <input type="email" name="email" class="form-control" placeholder="admin@ivoteph.test" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-lock-fill text-primary"></i>
                    </span>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-ivote w-100">
                <i class="bi bi-shield-lock-fill me-1"></i>
                Login to Dashboard
            </button>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted">
                iVotePH Admin System<br>
                Academic Election Simulation
            </small>
        </div>
    </div>
</div>

</body>
</html>