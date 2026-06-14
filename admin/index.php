<?php
if (session_id() == '') {
    session_start();
}

if (isset($_SESSION['admin_id'])) {
    header('Location: /ivoteph/admin/admin/index.php');
    exit();
}

header('Location: /ivoteph/admin/auth/login.php');
exit();
?>