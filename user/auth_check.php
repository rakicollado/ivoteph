<?php
if (session_id() == '') {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

$session_timeout = 1800;

if (isset($_SESSION['LAST_ACTIVITY'])) {
    if ((time() - $_SESSION['LAST_ACTIVITY']) > $session_timeout) {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        header('Location: login.php?error=session_expired');
        exit();
    }
}

$_SESSION['LAST_ACTIVITY'] = time();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if (!isset($_SESSION['voter_id']) || trim($_SESSION['voter_id']) == '') {
    header('Location: login.php?error=login_required');
    exit();
}

$auth_voter_id = $_SESSION['voter_id'];

$sql = "
    SELECT
        rv.voter_id,
        rv.first_name,
        rv.last_name,
        rv.birth_date,
        rv.email,
        rv.profile_status,
        rv.registration_status,
        a.account_status,
        a.is_active
    FROM registered_voters rv
    INNER JOIN accounts a ON rv.voter_id = a.voter_id
    WHERE rv.voter_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    session_destroy();
    header('Location: login.php?error=server_error');
    exit();
}

mysqli_stmt_bind_param($stmt, 's', $auth_voter_id);
mysqli_stmt_execute($stmt);

mysqli_stmt_bind_result(
    $stmt,
    $db_voter_id,
    $db_first_name,
    $db_last_name,
    $db_birth_date,
    $db_email,
    $db_profile_status,
    $db_registration_status,
    $db_account_status,
    $db_is_active
);

if (!mysqli_stmt_fetch($stmt)) {
    mysqli_stmt_close($stmt);
    session_destroy();

    header('Location: login.php?error=login_required');
    exit();
}

mysqli_stmt_close($stmt);

if ($db_is_active != 1 || $db_account_status != 'Active') {
    session_destroy();

    header('Location: login.php?error=inactive');
    exit();
}

if ($db_profile_status != 'Complete' || $db_registration_status != 'Registered') {
    session_destroy();

    header('Location: login.php?error=not_registered');
    exit();
}

$auth_voter_id = $db_voter_id;
$auth_first_name = $db_first_name;
$auth_last_name = $db_last_name;
$auth_birth_date = $db_birth_date;
$auth_email = $db_email;
$auth_profile_status = $db_profile_status;
$auth_registration_status = $db_registration_status;

$_SESSION['voter_id'] = $db_voter_id;
$_SESSION['voter_name'] = trim($db_first_name . ' ' . $db_last_name);
?>