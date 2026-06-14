<?php
require_once dirname(__FILE__) . '/../auth_check.php';

require_admin();

date_default_timezone_set('Asia/Manila');

$page_title = 'Election Schedule Control';
$page_subtitle = 'Set the voting window. Voting opens and closes automatically based on the saved date and time.';

$pdo = db();
$errors = array();

function election_post_value($key)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

function election_quote_col($column)
{
    return '`' . str_replace('`', '', $column) . '`';
}

function election_table_columns($pdo)
{
    $columns = array();

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM elections");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $columns[$row['Field']] = $row;
        }
    } catch (Exception $e) {
        $columns = array();
    }

    return $columns;
}

function election_pick_column($columns, $choices)
{
    foreach ($choices as $choice) {
        if (isset($columns[$choice])) {
            return $choice;
        }
    }

    return '';
}

function election_uses_lowercase_status($columns, $status_col)
{
    if (!isset($columns[$status_col])) {
        return false;
    }

    $type = strtolower($columns[$status_col]['Type']);

    if (strpos($type, "'open'") !== false) {
        return true;
    }

    return false;
}

function election_ui_status($status)
{
    $status = strtolower(trim((string) $status));

    if ($status == 'open') {
        return 'Open';
    }

    if ($status == 'closed') {
        return 'Closed';
    }

    return 'Draft';
}

function election_db_status($status, $lowercase)
{
    $status = election_ui_status($status);

    if ($lowercase) {
        return strtolower($status);
    }

    return $status;
}

function election_now_db()
{
    return date('Y-m-d H:i:s');
}

function election_db_datetime($value)
{
    $value = trim((string) $value);

    if ($value == '') {
        return false;
    }

    $value = str_replace('T', ' ', $value);

    if (strlen($value) == 16) {
        $value .= ':00';
    }

    $time = strtotime($value);

    if ($time === false) {
        return false;
    }

    return date('Y-m-d H:i:s', $time);
}

function election_datetime_input($value)
{
    if ($value == '' || $value == null || $value == '0000-00-00 00:00:00') {
        return '';
    }

    $time = strtotime($value);

    if ($time === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $time);
}

function election_display_datetime($value)
{
    if ($value == '' || $value == null || $value == '0000-00-00 00:00:00') {
        return '-';
    }

    $time = strtotime($value);

    if ($time === false) {
        return '-';
    }

    return date('M d, Y h:i A', $time);
}

function election_js_datetime($value)
{
    if ($value == '' || $value == null || $value == '0000-00-00 00:00:00') {
        return '';
    }

    $time = strtotime($value);

    if ($time === false) {
        return '';
    }

    return date('Y-m-d\TH:i:s', $time) . '+08:00';
}

function election_runtime_status($election)
{
    if (!$election) {
        return 'Closed';
    }

    $stored_status = election_ui_status($election['election_status']);
    $now = time();
    $start = strtotime($election['start_datetime']);
    $end = strtotime($election['end_datetime']);

    if ($stored_status == 'Closed') {
        return 'Closed';
    }

    if ($stored_status == 'Open') {
        if ($end !== false && $now > $end) {
            return 'Closed';
        }

        if ($start !== false && $now < $start) {
            return 'Scheduled';
        }

        return 'Open';
    }

    if ($stored_status == 'Draft') {
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

    return $stored_status;
}

function election_badge_class_custom($status)
{
    $status = strtolower(trim((string) $status));

    if ($status == 'open') {
        return 'text-bg-success';
    }

    if ($status == 'scheduled') {
        return 'text-bg-primary';
    }

    if ($status == 'closed') {
        return 'text-bg-danger';
    }

    return 'text-bg-secondary';
}


function election_safe_audit($action)
{
    try {
        audit_log($action);
    } catch (Exception $e) {
        /* Audit logging should not block election schedule updates. */
    }
}

function election_create_default_if_empty($pdo, $columns, $title_col, $start_col, $end_col, $status_col, $status_lowercase)
{
    try {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM elections")->fetchColumn();

        if ($count > 0) {
            return;
        }

        $insert_columns = array();
        $insert_values = array();
        $params = array();

        $insert_columns[] = election_quote_col($title_col);
        $insert_values[] = ':title';
        $params[':title'] = '2026 National and Local Election Simulation';

        $insert_columns[] = election_quote_col($start_col);
        $insert_values[] = ':start_datetime';
        $params[':start_datetime'] = date('Y-m-d H:i:s', strtotime('+1 day 01:00'));

        $insert_columns[] = election_quote_col($end_col);
        $insert_values[] = ':end_datetime';
        $params[':end_datetime'] = date('Y-m-d H:i:s', strtotime('+1 day 03:00'));

        $insert_columns[] = election_quote_col($status_col);
        $insert_values[] = ':status';
        $params[':status'] = election_db_status('Draft', $status_lowercase);

        if (isset($columns['created_at'])) {
            $insert_columns[] = '`created_at`';
            $insert_values[] = ':created_at';
            $params[':created_at'] = election_now_db();
        }

        $sql = "
            INSERT INTO elections
            (" . implode(', ', $insert_columns) . ")
            VALUES
            (" . implode(', ', $insert_values) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (Exception $e) {
    }
}

function election_get_single($pdo, $title_col, $start_col, $end_col, $status_col)
{
    try {
        $stmt = $pdo->query("
            SELECT
                election_id,
                " . election_quote_col($title_col) . " AS election_title,
                " . election_quote_col($start_col) . " AS start_datetime,
                " . election_quote_col($end_col) . " AS end_datetime,
                " . election_quote_col($status_col) . " AS election_status
            FROM elections
            ORDER BY election_id ASC
            LIMIT 1
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function election_sync_status($pdo, $title_col, $start_col, $end_col, $status_col, $status_lowercase)
{
    try {
        $election = election_get_single($pdo, $title_col, $start_col, $end_col, $status_col);

        if (!$election) {
            return false;
        }

        $now = time();
        $start = strtotime($election['start_datetime']);
        $end = strtotime($election['end_datetime']);
        $stored_status = election_ui_status($election['election_status']);

        if ($start === false || $end === false) {
            return $election;
        }

        if ($stored_status != 'Closed' && $now >= $start && $now <= $end) {
            $stmt = $pdo->prepare("
                UPDATE elections
                SET " . election_quote_col($status_col) . " = :status
                WHERE election_id = :election_id
            ");

            $stmt->execute(array(
                ':status' => election_db_status('Open', $status_lowercase),
                ':election_id' => $election['election_id']
            ));
        }

        if ($now > $end) {
            $stmt = $pdo->prepare("
                UPDATE elections
                SET " . election_quote_col($status_col) . " = :status
                WHERE election_id = :election_id
            ");

            $stmt->execute(array(
                ':status' => election_db_status('Closed', $status_lowercase),
                ':election_id' => $election['election_id']
            ));
        }

        return election_get_single($pdo, $title_col, $start_col, $end_col, $status_col);
    } catch (Exception $e) {
        return false;
    }
}

$columns = election_table_columns($pdo);

$title_col = election_pick_column($columns, array('election_name', 'election_title', 'title'));
$start_col = election_pick_column($columns, array('start_datetime', 'start_date', 'starts_at'));
$end_col = election_pick_column($columns, array('end_datetime', 'end_date', 'ends_at'));
$status_col = election_pick_column($columns, array('election_status', 'status'));

if ($title_col == '' || $start_col == '' || $end_col == '' || $status_col == '') {
    $errors[] = 'The elections table is missing required columns.';
}

$status_lowercase = false;

if (count($errors) == 0) {
    $status_lowercase = election_uses_lowercase_status($columns, $status_col);

    election_create_default_if_empty($pdo, $columns, $title_col, $start_col, $end_col, $status_col, $status_lowercase);
    election_sync_status($pdo, $title_col, $start_col, $end_col, $status_col, $status_lowercase);
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'status' && count($errors) == 0) {
    $election = election_sync_status($pdo, $title_col, $start_col, $end_col, $status_col, $status_lowercase);
    $runtime_status = election_runtime_status($election);

    header('Content-Type: application/json');

    echo json_encode(array(
        'server_time' => date('Y-m-d\TH:i:s') . '+08:00',
        'display_time' => date('M d, Y h:i:s A'),
        'runtime_status' => $runtime_status,
        'stored_status' => $election ? election_ui_status($election['election_status']) : 'Closed',
        'start_datetime' => $election ? election_js_datetime($election['start_datetime']) : '',
        'end_datetime' => $election ? election_js_datetime($election['end_datetime']) : ''
    ));

    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && count($errors) == 0) {
    verify_csrf();

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $election_id = election_post_value('election_id');

    if ($election_id == '') {
        $errors[] = 'Election record is missing.';
    }

    if ($action == 'save_schedule') {
        $election_title = election_post_value('election_title');
        $start_datetime = election_db_datetime(election_post_value('start_datetime'));
        $end_datetime = election_db_datetime(election_post_value('end_datetime'));
        if ($election_title == '') {
            $errors[] = 'Election title is required.';
        }

        if ($start_datetime === false) {
            $errors[] = 'Voting start date and time is required.';
        }

        if ($end_datetime === false) {
            $errors[] = 'Voting end date and time is required.';
        }

        if ($start_datetime !== false && $end_datetime !== false) {
            if (strtotime($end_datetime) <= strtotime($start_datetime)) {
                $errors[] = 'Voting end must be later than voting start.';
            }
        }

        if (count($errors) == 0) {
            try {
                $now = time();
                $end_time = strtotime($end_datetime);

                /*
                    Saving a schedule means the election is schedule-controlled.
                    Stored status stays Open so the user side can find the election,
                    while the start/end datetime decide whether voting is currently
                    Scheduled, Open, or Closed.
                */
                $final_status = 'Open';

                if ($end_time !== false && $now > $end_time) {
                    $final_status = 'Closed';
                }

                $stmt = $pdo->prepare("
                    UPDATE elections
                    SET
                        " . election_quote_col($title_col) . " = :title,
                        " . election_quote_col($start_col) . " = :start_datetime,
                        " . election_quote_col($end_col) . " = :end_datetime,
                        " . election_quote_col($status_col) . " = :status
                    WHERE election_id = :election_id
                ");

                $stmt->execute(array(
                    ':title' => $election_title,
                    ':start_datetime' => $start_datetime,
                    ':end_datetime' => $end_datetime,
                    ':status' => election_db_status($final_status, $status_lowercase),
                    ':election_id' => $election_id
                ));

                election_safe_audit('Updated official election schedule.');
                flash('success', 'Election schedule saved. Voting will automatically open at the start time and close at the end time.');
                header('Location: elections.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Unable to save election schedule.';
            }
        }
    }

    if ($action == 'open_now') {
        if (count($errors) == 0) {
            try {
                $now = election_now_db();

                $stmt = $pdo->prepare("
                    SELECT " . election_quote_col($end_col) . " AS end_datetime
                    FROM elections
                    WHERE election_id = :election_id
                    LIMIT 1
                ");

                $stmt->execute(array(':election_id' => $election_id));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                $new_end = date('Y-m-d H:i:s', strtotime('+2 hours'));

                if ($row && strtotime($row['end_datetime']) > strtotime($now)) {
                    $new_end = $row['end_datetime'];
                }

                $stmt = $pdo->prepare("
                    UPDATE elections
                    SET
                        " . election_quote_col($start_col) . " = :start_datetime,
                        " . election_quote_col($end_col) . " = :end_datetime,
                        " . election_quote_col($status_col) . " = :status
                    WHERE election_id = :election_id
                ");

                $stmt->execute(array(
                    ':start_datetime' => $now,
                    ':end_datetime' => $new_end,
                    ':status' => election_db_status('Open', $status_lowercase),
                    ':election_id' => $election_id
                ));

                election_safe_audit('Opened official voting schedule.');
                flash('success', 'Voting has been opened now.');
                header('Location: elections.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Unable to open voting.';
            }
        }
    }

    if ($action == 'close_now') {
        if (count($errors) == 0) {
            try {
                $now = election_now_db();

                $stmt = $pdo->prepare("
                    UPDATE elections
                    SET
                        " . election_quote_col($end_col) . " = :end_datetime,
                        " . election_quote_col($status_col) . " = :status
                    WHERE election_id = :election_id
                ");

                $stmt->execute(array(
                    ':end_datetime' => $now,
                    ':status' => election_db_status('Closed', $status_lowercase),
                    ':election_id' => $election_id
                ));

                election_safe_audit('Closed official voting schedule.');
                flash('success', 'Voting has been closed now.');
                header('Location: elections.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Unable to close voting.';
            }
        }
    }

    if (count($errors) > 0) {
        foreach ($errors as $error) {
            flash('danger', $error);
        }
    }
}

$election = false;

if (count($errors) == 0) {
    $election = election_sync_status($pdo, $title_col, $start_col, $end_col, $status_col, $status_lowercase);
}

$runtime_status = election_runtime_status($election);
$flashes = consume_flash();

require_once dirname(__FILE__) . '/../includes/header.php';
require_once dirname(__FILE__) . '/../includes/sidebar.php';
?>

<style>
    /* Layout fix for the election schedule editor after removing access-mode controls. */
    .ivote-election-page .ivote-election-editor-card {
        overflow: hidden;
    }

    .ivote-election-page .ivote-election-editor-form {
        padding: 0;
    }

    .ivote-election-page .ivote-election-editor-form .row {
        padding: 26px;
    }

    .ivote-election-page .ivote-election-card-title {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 26px;
        border-bottom: 1px solid #e6edf7;
    }

    .ivote-election-page .ivote-election-card-title span {
        display: block;
        color: #0646a8;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .ivote-election-page .ivote-election-card-title h3 {
        margin: 0;
        color: #111827;
        font-size: 25px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .ivote-election-page .ivote-election-card-pill {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 20px;
        border-radius: 999px;
        background: #f1f5fb;
        color: #475467;
        font-size: 13px;
        font-weight: 900;
        white-space: nowrap;
    }

    .ivote-election-page .ivote-election-editor-card .form-label {
        color: #111827;
        font-weight: 900;
    }

    .ivote-election-page .ivote-election-editor-card .form-control {
        min-height: 54px;
        border-radius: 16px;
        border-color: #d9e3f2;
        box-shadow: none;
    }

    .ivote-election-page .ivote-election-actions-row-final {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 16px !important;
        align-items: stretch !important;
        padding: 24px 26px !important;
        background: #f8fafc !important;
        border-top: 1px solid #e6edf7 !important;
    }

    .ivote-election-page .ivote-election-action-divider-final {
        display: none !important;
    }

    .ivote-election-page .ivote-election-action-form {
        display: block !important;
        width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
    }

    .ivote-election-page .ivote-election-action-btn {
        width: 100% !important;
        min-width: 0 !important;
        min-height: 56px !important;
        margin: 0 !important;
        border-radius: 16px !important;
        font-size: 15px !important;
        font-weight: 950 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        white-space: nowrap !important;
        box-shadow: 0 10px 22px rgba(16, 24, 40, 0.08) !important;
    }

    @media (max-width: 992px) {
        .ivote-election-page .ivote-election-card-title {
            flex-direction: column;
        }

        .ivote-election-page .ivote-election-actions-row-final {
            grid-template-columns: 1fr !important;
        }
    }
</style>


<div class="ivote-management-page ivote-election-page">

    <?php if (count($flashes) > 0) { ?>
        <div class="ivote-flash-wrap">
            <?php foreach ($flashes as $message) { ?>
                <div class="alert alert-<?php echo e($message['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo e($message['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (count($errors) > 0) { ?>
        <div class="ivote-flash-wrap">
            <?php foreach ($errors as $error) { ?>
                <div class="alert alert-danger">
                    <?php echo e($error); ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($election) { ?>

        <div class="ivote-election-control-shell">
            <div class="ivote-election-hero-card">
                <div class="ivote-election-hero-left">
                    <div class="ivote-election-eyebrow">Official Voting Event</div>
                    <h2><?php echo e($election['election_title']); ?></h2>
                    <p>
                        Voting opens and closes automatically based on Philippine Standard Time.
                    </p>
                </div>

                <div class="ivote-election-hero-right">
                    <span id="ivoteRuntimeBadge" class="badge <?php echo election_badge_class_custom($runtime_status); ?>">
                        <?php echo e($runtime_status); ?>
                    </span>
                </div>
            </div>

            <div class="ivote-election-info-grid">
                <div class="ivote-election-info-card">
                    <span>Philippine Time</span>
                    <strong id="ivotePHClock"><?php echo e(date('M d, Y h:i:s A')); ?></strong>
                    <small>Asia/Manila, UTC+8</small>
                </div>

                <div class="ivote-election-info-card">
                    <span>Voting Start</span>
                    <strong><?php echo e(election_display_datetime($election['start_datetime'])); ?></strong>
                    <small>Automatic opening time</small>
                </div>

                <div class="ivote-election-info-card">
                    <span>Voting End</span>
                    <strong><?php echo e(election_display_datetime($election['end_datetime'])); ?></strong>
                    <small>Automatic closing time</small>
                </div>

                <div class="ivote-election-info-card">
                    <span>Live Timer</span>
                    <strong id="ivoteElectionTimer" class="ivote-election-timer"
                        data-status="<?php echo e(election_ui_status($election['election_status'])); ?>"
                        data-start="<?php echo e(election_js_datetime($election['start_datetime'])); ?>"
                        data-end="<?php echo e(election_js_datetime($election['end_datetime'])); ?>">
                        Loading...
                    </strong>
                    <small id="ivoteTimerCaption">Real-time schedule monitor</small>
                </div>
            </div>

            <div class="ivote-card ivote-election-editor-card">
                <div class="ivote-election-card-title">
                    <div>
                        <span>Schedule Settings</span>
                        <h3>Edit Official Voting Window</h3>
                    </div>

                    <div class="ivote-election-card-pill">
                        One election only
                    </div>
                </div>

                <form method="POST" action="elections.php" class="ivote-election-editor-form" id="officialElectionForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_schedule">
                    <input type="hidden" name="election_id" value="<?php echo e($election['election_id']); ?>">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Election Title</label>
                            <input type="text" name="election_title" class="form-control"
                                value="<?php echo e($election['election_title']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Voting Start</label>
                            <input type="datetime-local" name="start_datetime" class="form-control"
                                value="<?php echo e(election_datetime_input($election['start_datetime'])); ?>" required>
                            <small class="text-muted">Example: June 11, 2026 01:00 AM</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Voting End</label>
                            <input type="datetime-local" name="end_datetime" class="form-control"
                                value="<?php echo e(election_datetime_input($election['end_datetime'])); ?>" required>
                            <small class="text-muted">Example: June 11, 2026 03:00 AM</small>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0 rounded-4">
                                <i class="bi bi-info-circle me-1"></i>
                                After saving, voting follows this schedule automatically. If the start time is in the
                                future, the election is shown as Scheduled. It opens when the start time arrives and closes
                                when the end time passes.
                            </div>
                        </div>
                    </div>
                </form>

                <div class="ivote-election-actions-row-final">
                    <button type="submit" form="officialElectionForm" class="btn btn-ivote ivote-election-action-btn">
                        <i class="bi bi-save me-1"></i>
                        Save Schedule
                    </button>

                    <div class="ivote-election-action-divider-final"></div>

                    <form method="POST" action="elections.php" class="ivote-election-action-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="open_now">
                        <input type="hidden" name="election_id" value="<?php echo e($election['election_id']); ?>">
                        <button type="submit" class="btn btn-success ivote-election-action-btn">
                            <i class="bi bi-unlock me-1"></i>
                            Open Voting Now
                        </button>
                    </form>

                    <form method="POST" action="elections.php" class="ivote-election-action-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="close_now">
                        <input type="hidden" name="election_id" value="<?php echo e($election['election_id']); ?>">
                        <button type="submit" class="btn btn-danger ivote-election-action-btn">
                            <i class="bi bi-lock me-1"></i>
                            Close Voting Now
                        </button>
                    </form>
                </div>

            <?php } else { ?>

                <div class="alert alert-danger">
                    Election schedule could not be loaded.
                </div>

            <?php } ?>

        </div>

        <script>
            function ivotePadNumber(number) {
                number = parseInt(number, 10);

                if (number < 10) {
                    return '0' + number;
                }

                return '' + number;
            }

            function ivoteFormatDuration(seconds) {
                seconds = Math.max(0, parseInt(seconds, 10));

                var days = Math.floor(seconds / 86400);
                var hours = Math.floor((seconds % 86400) / 3600);
                var minutes = Math.floor((seconds % 3600) / 60);
                var secs = seconds % 60;

                if (days > 0) {
                    return days + 'd ' + ivotePadNumber(hours) + 'h ' + ivotePadNumber(minutes) + 'm ' + ivotePadNumber(secs) + 's';
                }

                return ivotePadNumber(hours) + 'h ' + ivotePadNumber(minutes) + 'm ' + ivotePadNumber(secs) + 's';
            }

            function ivoteFormatPHClock(dateObj) {
                var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                var month = months[dateObj.getMonth()];
                var day = ivotePadNumber(dateObj.getDate());
                var year = dateObj.getFullYear();

                var hour = dateObj.getHours();
                var minute = ivotePadNumber(dateObj.getMinutes());
                var second = ivotePadNumber(dateObj.getSeconds());
                var ampm = hour >= 12 ? 'PM' : 'AM';

                hour = hour % 12;

                if (hour == 0) {
                    hour = 12;
                }

                return month + ' ' + day + ', ' + year + ' ' + ivotePadNumber(hour) + ':' + minute + ':' + second + ' ' + ampm;
            }

            function ivoteSetBadge(status) {
                var badge = document.getElementById('ivoteRuntimeBadge');

                if (!badge) {
                    return;
                }

                badge.className = 'badge';

                if (status == 'Open') {
                    badge.className += ' text-bg-success';
                } else if (status == 'Scheduled') {
                    badge.className += ' text-bg-primary';
                } else if (status == 'Closed') {
                    badge.className += ' text-bg-danger';
                } else {
                    badge.className += ' text-bg-secondary';
                }

                badge.innerHTML = status;
            }

            function ivoteUpdateElectionTimers() {
                var serverNow = new Date(window.ivoteServerNowText).getTime();
                var offset = serverNow - window.ivotePageLoadedAt;
                var now = new Date(new Date().getTime() + offset);
                var nowMs = now.getTime();

                var clock = document.getElementById('ivotePHClock');

                if (clock) {
                    clock.innerHTML = ivoteFormatPHClock(now);
                }

                var timer = document.getElementById('ivoteElectionTimer');
                var caption = document.getElementById('ivoteTimerCaption');

                if (!timer) {
                    return;
                }

                var status = timer.getAttribute('data-status');
                var startText = timer.getAttribute('data-start');
                var endText = timer.getAttribute('data-end');

                if (!startText || !endText) {
                    timer.innerHTML = 'No schedule set';
                    ivoteSetBadge('Closed');
                    return;
                }

                var startMs = new Date(startText).getTime();
                var endMs = new Date(endText).getTime();

                if (status == 'Closed') {
                    timer.innerHTML = 'Voting is closed';
                    ivoteSetBadge('Closed');

                    if (caption) {
                        caption.innerHTML = 'Voting access is disabled.';
                    }
                } else if (nowMs < startMs) {
                    timer.innerHTML = 'Starts in ' + ivoteFormatDuration((startMs - nowMs) / 1000);
                    ivoteSetBadge('Scheduled');

                    if (caption) {
                        caption.innerHTML = 'Voting will open automatically.';
                    }
                } else if (nowMs >= startMs && nowMs <= endMs) {
                    timer.innerHTML = 'Ends in ' + ivoteFormatDuration((endMs - nowMs) / 1000);
                    ivoteSetBadge('Open');

                    if (caption) {
                        caption.innerHTML = 'Voting is currently open.';
                    }
                } else {
                    timer.innerHTML = 'Voting schedule ended';
                    ivoteSetBadge('Closed');

                    if (caption) {
                        caption.innerHTML = 'Voting was automatically closed.';
                    }
                }
            }

            function ivoteSyncElectionStatus() {
                var xhr = new XMLHttpRequest();

                xhr.open('GET', 'elections.php?ajax=status&_=' + new Date().getTime(), true);

                xhr.onreadystatechange = function () {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);

                            window.ivoteServerNowText = data.server_time;
                            window.ivotePageLoadedAt = new Date().getTime();

                            var timer = document.getElementById('ivoteElectionTimer');

                            if (timer) {
                                timer.setAttribute('data-status', data.stored_status);
                                timer.setAttribute('data-start', data.start_datetime);
                                timer.setAttribute('data-end', data.end_datetime);
                            }

                            ivoteSetBadge(data.runtime_status);
                            ivoteUpdateElectionTimers();
                        } catch (e) {
                        }
                    }
                };

                xhr.send();
            }

            window.ivoteServerNowText = '<?php echo date('Y-m-d\TH:i:s'); ?>+08:00';
            window.ivotePageLoadedAt = new Date().getTime();

            ivoteUpdateElectionTimers();
            setInterval(ivoteUpdateElectionTimers, 1000);
            setInterval(ivoteSyncElectionStatus, 5000);
        </script>

        <?php
        require_once dirname(__FILE__) . '/../includes/footer.php';
        ?>