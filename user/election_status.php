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

function election_status_table_columns($conn)
{
    $columns = array();
    $result = mysqli_query($conn, "SHOW COLUMNS FROM elections");

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[$row['Field']] = $row;
        }

        mysqli_free_result($result);
    }

    return $columns;
}

function election_status_pick_column($columns, $choices)
{
    foreach ($choices as $choice) {
        if (isset($columns[$choice])) {
            return $choice;
        }
    }

    return '';
}

function election_status_get_single($conn, $title_col, $start_col, $end_col, $status_col)
{
    $title_col = preg_replace('/[^A-Za-z0-9_]/', '', $title_col);
    $start_col = preg_replace('/[^A-Za-z0-9_]/', '', $start_col);
    $end_col = preg_replace('/[^A-Za-z0-9_]/', '', $end_col);
    $status_col = preg_replace('/[^A-Za-z0-9_]/', '', $status_col);

    $result = mysqli_query($conn, "
        SELECT
            election_id,
            `" . $title_col . "` AS election_title,
            `" . $start_col . "` AS start_datetime,
            `" . $end_col . "` AS end_datetime,
            `" . $status_col . "` AS election_status
        FROM elections
        ORDER BY election_id ASC
        LIMIT 1
    ");

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $row;
    }

    if ($result) {
        mysqli_free_result($result);
    }

    return false;
}

function election_status_ui_status($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === 'open') {
        return 'Open';
    }

    if ($status === 'closed') {
        return 'Closed';
    }

    return 'Draft';
}

function election_status_runtime_status($election)
{
    if (!$election) {
        return 'Closed';
    }

    $stored_status = election_status_ui_status($election['election_status']);
    $now = time();
    $start = strtotime($election['start_datetime']);
    $end = strtotime($election['end_datetime']);

    if ($stored_status === 'Closed') {
        return 'Closed';
    }

    if ($stored_status === 'Open') {
        if ($end !== false && $now > $end) {
            return 'Closed';
        }

        if ($start !== false && $now < $start) {
            return 'Scheduled';
        }

        return 'Open';
    }

    if ($start !== false && $end !== false) {
        if ($now < $start) {
            return 'Scheduled';
        }

        if ($now >= $start && $now <= $end) {
            return 'Open';
        }

        if ($now > $end) {
            return 'Closed';
        }
    }

    return 'Scheduled';
}

function election_status_js_datetime($value)
{
    if ($value === null || trim((string) $value) === '' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    $time = strtotime($value);

    if ($time === false) {
        return '';
    }

    return date('Y-m-d\TH:i:s', $time) . '+08:00';
}

$election = false;
$columns = election_status_table_columns($conn);

$title_col = election_status_pick_column($columns, array('election_name', 'election_title', 'title'));
$start_col = election_status_pick_column($columns, array('start_datetime', 'start_date', 'starts_at'));
$end_col = election_status_pick_column($columns, array('end_datetime', 'end_date', 'ends_at'));
$status_col = election_status_pick_column($columns, array('election_status', 'status'));

if ($title_col !== '' && $start_col !== '' && $end_col !== '' && $status_col !== '') {
    $election = election_status_get_single($conn, $title_col, $start_col, $end_col, $status_col);
}

$runtime_status = election_status_runtime_status($election);

echo json_encode(array(
    'success' => true,
    'server_time' => date('Y-m-d\TH:i:s') . '+08:00',
    'runtime_status' => $runtime_status,
    'start_datetime' => $election ? election_status_js_datetime($election['start_datetime']) : '',
    'end_datetime' => $election ? election_status_js_datetime($election['end_datetime']) : ''
));
exit;
?>