<?php
if (session_id() === '') {
    session_start();
}

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

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
    echo json_encode(array(
        'success' => false,
        'message' => 'Database connection failed.'
    ));
    exit;
}

mysqli_set_charset($conn, 'utf8');

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS profile_change_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        voter_id VARCHAR(50) NOT NULL,
        request_field VARCHAR(100) NOT NULL,
        request_message TEXT NOT NULL,
        request_status ENUM('Pending', 'Approved', 'Rejected', 'Resolved') NOT NULL DEFAULT 'Pending',
        admin_response TEXT NULL,
        reviewed_by VARCHAR(100) NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        user_seen_at DATETIME NULL,
        INDEX idx_voter_id (voter_id),
        INDEX idx_request_status (request_status),
        INDEX idx_created_at (created_at)
    )
");


function profile_request_column_exists($conn, $table_name, $column_name)
{
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);
    $column_name = preg_replace('/[^A-Za-z0-9_]/', '', $column_name);

    if ($table_name === '' || $column_name === '') {
        return false;
    }

    $table_name_sql = mysqli_real_escape_string($conn, $table_name);
    $column_name_sql = mysqli_real_escape_string($conn, $column_name);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `" . $table_name_sql . "` LIKE '" . $column_name_sql . "'");

    if ($result && mysqli_num_rows($result) > 0) {
        mysqli_free_result($result);
        return true;
    }

    if ($result) {
        mysqli_free_result($result);
    }

    return false;
}

if (!profile_request_column_exists($conn, 'profile_change_requests', 'user_seen_at')) {
    mysqli_query($conn, "ALTER TABLE profile_change_requests ADD user_seen_at DATETIME NULL");
}

function get_logged_voter_id($conn)
{
    $possible_session_keys = array(
        'voter_id',
        'voterId',
        'logged_voter_id',
        'user_voter_id',
        'account_voter_id',
        'registered_voter_id'
    );

    foreach ($possible_session_keys as $key) {
        if (isset($_SESSION[$key]) && trim($_SESSION[$key]) !== '') {
            return trim($_SESSION[$key]);
        }
    }

    if (isset($_POST['voter_id']) && trim($_POST['voter_id']) !== '') {
        return trim($_POST['voter_id']);
    }

    $possible_account_keys = array(
        'account_id',
        'user_id',
        'id'
    );

    foreach ($possible_account_keys as $key) {
        if (isset($_SESSION[$key]) && trim($_SESSION[$key]) !== '') {
            $account_id = mysqli_real_escape_string($conn, trim($_SESSION[$key]));

            $check_accounts = mysqli_query($conn, "SHOW TABLES LIKE 'accounts'");

            if ($check_accounts && mysqli_num_rows($check_accounts) > 0) {
                $columns_result = mysqli_query($conn, "SHOW COLUMNS FROM accounts");
                $columns = array();

                if ($columns_result) {
                    while ($column = mysqli_fetch_assoc($columns_result)) {
                        $columns[] = $column['Field'];
                    }
                }

                $id_column = '';

                if (in_array('account_id', $columns)) {
                    $id_column = 'account_id';
                } elseif (in_array('id', $columns)) {
                    $id_column = 'id';
                }

                if ($id_column !== '' && in_array('voter_id', $columns)) {
                    $query = mysqli_query($conn, "
                        SELECT voter_id
                        FROM accounts
                        WHERE `" . $id_column . "` = '" . $account_id . "'
                        LIMIT 1
                    ");

                    if ($query && mysqli_num_rows($query) > 0) {
                        $row = mysqli_fetch_assoc($query);

                        if (isset($row['voter_id']) && trim($row['voter_id']) !== '') {
                            return trim($row['voter_id']);
                        }
                    }
                }
            }
        }
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid request method.'
    ));
    exit;
}

$voter_id = get_logged_voter_id($conn);
$profile_request_action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($profile_request_action === 'mark_profile_notifications_seen') {
    if ($voter_id === '') {
        echo json_encode(array(
            'success' => false,
            'message' => 'Voter ID was not found.'
        ));
        exit;
    }

    $stmt_seen = mysqli_prepare($conn, "
        UPDATE profile_change_requests
        SET user_seen_at = NOW()
        WHERE voter_id = ?
        AND request_status IN ('Approved', 'Rejected', 'Resolved')
        AND user_seen_at IS NULL
    ");

    if ($stmt_seen) {
        mysqli_stmt_bind_param($stmt_seen, 's', $voter_id);
        mysqli_stmt_execute($stmt_seen);
        mysqli_stmt_close($stmt_seen);
    }

    echo json_encode(array(
        'success' => true,
        'message' => 'Notifications marked as seen.'
    ));
    exit;
}

$request_field = isset($_POST['request_field']) ? trim($_POST['request_field']) : '';
$request_message = isset($_POST['request_message']) ? trim($_POST['request_message']) : '';

if ($voter_id === '') {
    echo json_encode(array(
        'success' => false,
        'message' => 'Voter ID was not found. Please log in again.'
    ));
    exit;
}

if ($request_field === '' || $request_message === '') {
    echo json_encode(array(
        'success' => false,
        'message' => 'Please select the information to change and enter your correction details.'
    ));
    exit;
}

$sql = "
    INSERT INTO profile_change_requests
        (voter_id, request_field, request_message, request_status, created_at)
    VALUES
        (?, ?, ?, 'Pending', NOW())
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Failed to prepare request.'
    ));
    exit;
}

mysqli_stmt_bind_param($stmt, 'sss', $voter_id, $request_field, $request_message);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(array(
        'success' => true,
        'message' => 'Your profile change request has been submitted to the admin.'
    ));
} else {
    echo json_encode(array(
        'success' => false,
        'message' => 'Failed to submit your request.'
    ));
}

mysqli_stmt_close($stmt);
?>