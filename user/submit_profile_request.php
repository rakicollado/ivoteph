<?php
ob_start();

if (session_id() === '') {
    session_start();
}

date_default_timezone_set('Asia/Manila');

function ivoteph_profile_request_json($success, $message)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json');

    echo json_encode(array(
        'success' => $success,
        'message' => $message
    ));

    exit;
}

$conn = null;

$connection_files = array(
    __DIR__ . '/db_connect.php',
    __DIR__ . '/../db_connect.php',
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../admin/config/config.php',
    __DIR__ . '/../admin/db_connect.php',
    __DIR__ . '/../admin/config.php',
    __DIR__ . '/../admin/includes/db_connect.php'
);

foreach ($connection_files as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

if (!isset($conn) || !$conn) {
    $conn = mysqli_connect('localhost', 'root', '', 'ivoteph');
}

if (!$conn) {
    ivoteph_profile_request_json(false, 'Database connection failed.');
}

mysqli_set_charset($conn, 'utf8');

function ivoteph_profile_request_column_exists($conn, $table_name, $column_name)
{
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);
    $column_name = preg_replace('/[^A-Za-z0-9_]/', '', $column_name);

    if ($table_name === '' || $column_name === '') {
        return false;
    }

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `" . $table_name . "` LIKE '" . mysqli_real_escape_string($conn, $column_name) . "'");

    if ($result && mysqli_num_rows($result) > 0) {
        mysqli_free_result($result);
        return true;
    }

    if ($result) {
        mysqli_free_result($result);
    }

    return false;
}

$create_table_sql = "
    CREATE TABLE IF NOT EXISTS profile_change_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        voter_id VARCHAR(50) NOT NULL,
        request_field VARCHAR(100) NOT NULL,
        request_message TEXT NOT NULL,
        request_status ENUM('Pending', 'Approved', 'Rejected', 'Resolved') NOT NULL DEFAULT 'Pending',
        admin_response TEXT NULL,
        reviewed_by VARCHAR(100) NULL,
        reviewed_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        INDEX idx_voter_id (voter_id),
        INDEX idx_request_status (request_status),
        INDEX idx_created_at (created_at)
    )
";

if (!mysqli_query($conn, $create_table_sql)) {
    ivoteph_profile_request_json(false, 'Could not create profile request table.');
}

$required_columns = array(
    'request_field' => "ALTER TABLE profile_change_requests ADD request_field VARCHAR(100) NOT NULL DEFAULT ''",
    'request_message' => "ALTER TABLE profile_change_requests ADD request_message TEXT NULL",
    'request_status' => "ALTER TABLE profile_change_requests ADD request_status ENUM('Pending', 'Approved', 'Rejected', 'Resolved') NOT NULL DEFAULT 'Pending'",
    'admin_response' => "ALTER TABLE profile_change_requests ADD admin_response TEXT NULL",
    'reviewed_by' => "ALTER TABLE profile_change_requests ADD reviewed_by VARCHAR(100) NULL",
    'reviewed_at' => "ALTER TABLE profile_change_requests ADD reviewed_at DATETIME NULL",
    'created_at' => "ALTER TABLE profile_change_requests ADD created_at DATETIME NOT NULL",
    'updated_at' => "ALTER TABLE profile_change_requests ADD updated_at DATETIME NULL"
);

foreach ($required_columns as $column_name => $alter_sql) {
    if (!ivoteph_profile_request_column_exists($conn, 'profile_change_requests', $column_name)) {
        mysqli_query($conn, $alter_sql);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ivoteph_profile_request_json(false, 'Invalid request method.');
}

$voter_id = '';

if (isset($_POST['voter_id']) && trim($_POST['voter_id']) !== '') {
    $voter_id = trim($_POST['voter_id']);
}

if ($voter_id === '') {
    $session_keys = array(
        'voter_id',
        'voterId',
        'logged_voter_id',
        'user_voter_id',
        'account_voter_id',
        'registered_voter_id'
    );

    foreach ($session_keys as $key) {
        if (isset($_SESSION[$key]) && trim((string) $_SESSION[$key]) !== '') {
            $voter_id = trim((string) $_SESSION[$key]);
            break;
        }
    }
}

$request_field = isset($_POST['request_field']) ? trim($_POST['request_field']) : '';
$request_message = isset($_POST['request_message']) ? trim($_POST['request_message']) : '';

if ($voter_id === '') {
    ivoteph_profile_request_json(false, 'Voter ID was not found. Please log in again.');
}

if ($request_field === '' || $request_message === '') {
    ivoteph_profile_request_json(false, 'Please select the information to change and enter your correction details.');
}

$sql_insert = "
    INSERT INTO profile_change_requests
        (voter_id, request_field, request_message, request_status, created_at, updated_at)
    VALUES
        (?, ?, ?, 'Pending', NOW(), NOW())
";

$stmt_insert = mysqli_prepare($conn, $sql_insert);

if (!$stmt_insert) {
    ivoteph_profile_request_json(false, 'Failed to prepare profile request.');
}

mysqli_stmt_bind_param($stmt_insert, 'sss', $voter_id, $request_field, $request_message);

if (mysqli_stmt_execute($stmt_insert)) {
    mysqli_stmt_close($stmt_insert);
    ivoteph_profile_request_json(true, 'Your profile change request has been submitted to the admin.');
}

mysqli_stmt_close($stmt_insert);
ivoteph_profile_request_json(false, 'Failed to submit your request.');
?>