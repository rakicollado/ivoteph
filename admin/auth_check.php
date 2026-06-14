<?php
require_once dirname(__FILE__) . '/helpers/functions.php';

if (session_id() == '') {
    session_start();
}

$admin_session_timeout = 1800;

if (isset($_SESSION['ADMIN_LAST_ACTIVITY'])) {
    if ((time() - $_SESSION['ADMIN_LAST_ACTIVITY']) > $admin_session_timeout) {
        $_SESSION = array();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: /ivoteph/admin/auth/login.php?error=session_expired');
        exit();
    }
}

$_SESSION['ADMIN_LAST_ACTIVITY'] = time();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if (!isset($_SESSION['admin_id']) || trim($_SESSION['admin_id']) == '') {
    header('Location: /ivoteph/admin/auth/login.php?error=login_required');
    exit();
}

$auth_admin_id = $_SESSION['admin_id'];
$auth_admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
$auth_admin_email = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : '';
$auth_admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'Admin';
?>