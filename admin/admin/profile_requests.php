<?php
require_once dirname(__FILE__) . '/../auth_check.php';
require_admin();

$page_title = 'Profile Requests';
$page_subtitle = 'Review voter-submitted profile correction requests.';
$activePage = 'profile_requests';

$pdo = db();

function pcr_admin_h($value)
{
    if ($value === null || $value === '') {
        return 'N/A';
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pcr_admin_badge_class($status)
{
    if ($status === 'Approved') {
        return 'success';
    }

    if ($status === 'Rejected') {
        return 'danger';
    }

    if ($status === 'Resolved') {
        return 'primary';
    }

    return 'warning';
}

function pcr_admin_count_query($pdo, $sql)
{
    try {
        $stmt = $pdo->query($sql);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function pcr_admin_table_exists($pdo, $table_name)
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table_name));
        return $stmt->fetchColumn() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

function pcr_admin_column_exists($pdo, $table_name, $column_name)
{
    try {
        $safe_table = str_replace('`', '', $table_name);
        $stmt = $pdo->query("SHOW COLUMNS FROM `" . $safe_table . "` LIKE " . $pdo->quote($column_name));
        return $stmt->fetchColumn() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

function pcr_admin_select_column($pdo, $table_name, $table_alias, $column_name, $alias_name)
{
    if (pcr_admin_table_exists($pdo, $table_name) && pcr_admin_column_exists($pdo, $table_name, $column_name)) {
        return $table_alias . ".`" . $column_name . "` AS `" . $alias_name . "`";
    }

    return "'' AS `" . $alias_name . "`";
}

function pcr_admin_select_first_column($pdo, $table_name, $table_alias, $columns, $alias_name)
{
    if (pcr_admin_table_exists($pdo, $table_name)) {
        foreach ($columns as $column_name) {
            if (pcr_admin_column_exists($pdo, $table_name, $column_name)) {
                return $table_alias . ".`" . $column_name . "` AS `" . $alias_name . "`";
            }
        }
    }

    return "'' AS `" . $alias_name . "`";
}

function pcr_admin_format_datetime($value)
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

function pcr_admin_short_text($value, $limit)
{
    $value = trim((string) $value);

    if ($value === '') {
        return 'N/A';
    }

    if (strlen($value) <= $limit) {
        return $value;
    }

    return substr($value, 0, $limit) . '...';
}

function pcr_admin_default_response($status)
{
    if ($status === 'Approved') {
        return 'Your profile change request has been approved by the admin. Please check your voter profile or wait for the correction to be applied.';
    }

    if ($status === 'Resolved') {
        return 'Your profile change request has been marked as resolved. The admin has already taken action on your request.';
    }

    if ($status === 'Rejected') {
        return 'Your profile change request was rejected after admin review. Please submit a clearer request or contact the election administrator for assistance.';
    }

    return 'Your profile change request is still pending admin review.';
}

try {
    $pdo->exec("
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
} catch (Exception $e) {
}

try {
    if (pcr_admin_table_exists($pdo, 'profile_change_requests')) {
        if (!pcr_admin_column_exists($pdo, 'profile_change_requests', 'user_seen_at')) {
            $pdo->exec("ALTER TABLE profile_change_requests ADD user_seen_at DATETIME NULL");
        }

        if (!pcr_admin_column_exists($pdo, 'profile_change_requests', 'updated_at')) {
            $pdo->exec("ALTER TABLE profile_change_requests ADD updated_at DATETIME NULL");
        }
    }
} catch (Exception $e) {
}

$notice = '';
$notice_type = 'info';

if (isset($_GET['updated']) && $_GET['updated'] !== '') {
    $notice = 'Request status updated to ' . trim($_GET['updated']) . '. The voter can see it in the notification bell.';
    $notice_type = 'success';
}

if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $notice = 'Profile request deleted successfully.';
    $notice_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = (int) $_POST['request_id'];

    if ($request_id <= 0) {
        $notice = 'Invalid request ID.';
        $notice_type = 'danger';
    } elseif (isset($_POST['delete_request']) && $_POST['delete_request'] == '1') {
        try {
            $stmt_delete = $pdo->prepare("DELETE FROM profile_change_requests WHERE request_id = ?");

            if ($stmt_delete->execute(array($request_id))) {
                header('Location: profile_requests.php?deleted=1');
                exit();
            }

            $notice = 'Failed to delete request.';
            $notice_type = 'danger';
        } catch (Exception $e) {
            $notice = 'Failed to delete request.';
            $notice_type = 'danger';
        }
    } else {
        if (isset($_POST['quick_status']) && trim($_POST['quick_status']) !== '') {
            $request_status = trim($_POST['quick_status']);
        } else {
            $request_status = isset($_POST['request_status']) ? trim($_POST['request_status']) : 'Pending';
        }

        $allowed_statuses = array('Pending', 'Approved', 'Rejected', 'Resolved');

        if (!in_array($request_status, $allowed_statuses)) {
            $request_status = 'Pending';
        }

        $admin_response = isset($_POST['admin_response']) ? trim($_POST['admin_response']) : '';

        if ($admin_response === '') {
            $admin_response = pcr_admin_default_response($request_status);
        }

        $reviewed_by = 'System Administrator';

        if (isset($_SESSION['admin_name']) && $_SESSION['admin_name'] !== '') {
            $reviewed_by = $_SESSION['admin_name'];
        } elseif (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] !== '') {
            $reviewed_by = $_SESSION['admin_username'];
        }

        try {
            $user_seen_sql = '';

            if (pcr_admin_column_exists($pdo, 'profile_change_requests', 'user_seen_at')) {
                $user_seen_sql = ', user_seen_at = NULL';
            }

            $stmt_update = $pdo->prepare("
                UPDATE profile_change_requests
                SET request_status = ?,
                    admin_response = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                    " . $user_seen_sql . "
                WHERE request_id = ?
            ");

            if ($stmt_update->execute(array($request_status, $admin_response, $reviewed_by, $request_id))) {
                header('Location: profile_requests.php?updated=' . urlencode($request_status));
                exit();
            }

            $notice = 'Failed to update request.';
            $notice_type = 'danger';
        } catch (Exception $e) {
            $notice = 'Failed to update request.';
            $notice_type = 'danger';
        }
    }
}

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'All';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$allowed_filters = array('All', 'Pending', 'Approved', 'Rejected', 'Resolved');

if (!in_array($status_filter, $allowed_filters)) {
    $status_filter = 'All';
}

$registered_voters_exists = pcr_admin_table_exists($pdo, 'registered_voters');
$voter_addresses_exists = pcr_admin_table_exists($pdo, 'voter_addresses');

$rv_join = '';
$va_join = '';

if ($registered_voters_exists) {
    $rv_join = "LEFT JOIN registered_voters rv ON pcr.voter_id = rv.voter_id";
}

if ($voter_addresses_exists) {
    $va_join = "LEFT JOIN voter_addresses va ON pcr.voter_id = va.voter_id";
}

$select_full_name = pcr_admin_select_first_column($pdo, 'registered_voters', 'rv', array('full_name', 'name', 'voter_name'), 'full_name_direct');
$select_first_name = pcr_admin_select_column($pdo, 'registered_voters', 'rv', 'first_name', 'first_name');
$select_middle_name = pcr_admin_select_column($pdo, 'registered_voters', 'rv', 'middle_name', 'middle_name');
$select_last_name = pcr_admin_select_column($pdo, 'registered_voters', 'rv', 'last_name', 'last_name');
$select_email = pcr_admin_select_first_column($pdo, 'registered_voters', 'rv', array('email', 'email_address'), 'email');
$select_mobile = pcr_admin_select_first_column($pdo, 'registered_voters', 'rv', array('mobile_number', 'contact_number', 'phone_number'), 'mobile_number');

$select_region = pcr_admin_select_column($pdo, 'voter_addresses', 'va', 'region', 'region');
$select_province = pcr_admin_select_column($pdo, 'voter_addresses', 'va', 'province', 'province');
$select_city = pcr_admin_select_first_column($pdo, 'voter_addresses', 'va', array('city_municipality', 'city', 'municipality'), 'city_municipality');
$select_barangay = pcr_admin_select_column($pdo, 'voter_addresses', 'va', 'barangay', 'barangay');
$select_specific_address = pcr_admin_select_first_column($pdo, 'voter_addresses', 'va', array('specific_address', 'street_address', 'address'), 'specific_address');

$where_parts = array();

if ($status_filter !== 'All') {
    $where_parts[] = "pcr.request_status = " . $pdo->quote($status_filter);
}

if ($search !== '') {
    $safe_search = $pdo->quote('%' . $search . '%');
    $search_parts = array(
        "pcr.voter_id LIKE " . $safe_search,
        "pcr.request_field LIKE " . $safe_search,
        "pcr.request_message LIKE " . $safe_search,
        "pcr.admin_response LIKE " . $safe_search
    );

    if ($registered_voters_exists) {
        if (pcr_admin_column_exists($pdo, 'registered_voters', 'first_name')) {
            $search_parts[] = "rv.first_name LIKE " . $safe_search;
        }

        if (pcr_admin_column_exists($pdo, 'registered_voters', 'middle_name')) {
            $search_parts[] = "rv.middle_name LIKE " . $safe_search;
        }

        if (pcr_admin_column_exists($pdo, 'registered_voters', 'last_name')) {
            $search_parts[] = "rv.last_name LIKE " . $safe_search;
        }

        if (pcr_admin_column_exists($pdo, 'registered_voters', 'full_name')) {
            $search_parts[] = "rv.full_name LIKE " . $safe_search;
        }

        if (pcr_admin_column_exists($pdo, 'registered_voters', 'email')) {
            $search_parts[] = "rv.email LIKE " . $safe_search;
        }

        if (pcr_admin_column_exists($pdo, 'registered_voters', 'mobile_number')) {
            $search_parts[] = "rv.mobile_number LIKE " . $safe_search;
        }
    }

    $where_parts[] = "(" . implode(" OR ", $search_parts) . ")";
}

$where_sql = '';

if (count($where_parts) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_parts);
}

$sql_requests = "
    SELECT
        pcr.request_id,
        pcr.voter_id,
        pcr.request_field,
        pcr.request_message,
        pcr.request_status,
        pcr.admin_response,
        pcr.reviewed_by,
        pcr.reviewed_at,
        pcr.created_at,
        " . $select_full_name . ",
        " . $select_first_name . ",
        " . $select_middle_name . ",
        " . $select_last_name . ",
        " . $select_email . ",
        " . $select_mobile . ",
        " . $select_region . ",
        " . $select_province . ",
        " . $select_city . ",
        " . $select_barangay . ",
        " . $select_specific_address . "
    FROM profile_change_requests pcr
    " . $rv_join . "
    " . $va_join . "
    " . $where_sql . "
    ORDER BY
        CASE
            WHEN pcr.request_status = 'Pending' THEN 1
            WHEN pcr.request_status = 'Approved' THEN 2
            WHEN pcr.request_status = 'Rejected' THEN 3
            WHEN pcr.request_status = 'Resolved' THEN 4
            ELSE 5
        END,
        pcr.created_at DESC
";

$requests = array();

try {
    $stmt_requests = $pdo->query($sql_requests);
    $requests = $stmt_requests->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = array();
}

$count_all = pcr_admin_count_query($pdo, "SELECT COUNT(*) FROM profile_change_requests");
$count_pending = pcr_admin_count_query($pdo, "SELECT COUNT(*) FROM profile_change_requests WHERE request_status = 'Pending'");
$count_approved = pcr_admin_count_query($pdo, "SELECT COUNT(*) FROM profile_change_requests WHERE request_status = 'Approved'");
$count_rejected = pcr_admin_count_query($pdo, "SELECT COUNT(*) FROM profile_change_requests WHERE request_status = 'Rejected'");
$count_resolved = pcr_admin_count_query($pdo, "SELECT COUNT(*) FROM profile_change_requests WHERE request_status = 'Resolved'");
$filtered_count = count($requests);

require_once dirname(__FILE__) . '/../includes/header.php';
require_once dirname(__FILE__) . '/../includes/sidebar.php';
?>

<style>
    .profileRequestStats {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .profileRequestFilterGrid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) 260px auto auto;
        gap: 14px;
        align-items: end;
    }

    .profileRequestCardList {
        display: grid;
        gap: 18px;
    }

    .profileRequestCard {
        border: 1px solid #dfe7f3;
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(16, 24, 40, 0.06);
        overflow: hidden;
    }

    .profileRequestCardHeader {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px;
        background: #f8fbff;
        border-bottom: 1px solid #edf2f8;
    }

    .profileRequestVoterId {
        color: #0647b8;
        font-weight: 950;
        white-space: nowrap;
    }

    .profileRequestName {
        color: #101828;
        font-size: 18px;
        font-weight: 950;
    }

    .profileRequestSubtext {
        color: #667085;
        font-size: 13px;
    }

    .profileRequestBadge {
        border-radius: 999px;
        padding: 8px 14px;
        font-weight: 950;
        font-size: 12px;
    }

    .profileRequestCardBody {
        padding: 20px;
    }

    .profileRequestInfoGrid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }

    .profileRequestInfoBox {
        background: #f7f9fd;
        border: 1px solid #e1e8f3;
        border-radius: 18px;
        padding: 14px;
        min-height: 88px;
    }

    .profileRequestInfoBox.profileRequestWide {
        grid-column: 1 / -1;
    }

    .profileRequestInfoBox span {
        display: block;
        color: #667085;
        font-size: 11px;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 6px;
    }

    .profileRequestInfoBox strong {
        display: block;
        color: #101828;
        font-weight: 900;
        overflow-wrap: anywhere;
    }

    .profileRequestMessageBox {
        background: #eef5ff;
        border: 1px solid #cfe0ff;
        border-radius: 18px;
        padding: 16px;
        line-height: 1.6;
        color: #24344f;
        margin-bottom: 16px;
    }

    .profileRequestActionPanel {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
        padding: 16px;
        border: 1px solid #dbe7fb;
        border-radius: 20px;
        background: #fbfdff;
    }

    .profileRequestActionPanel label {
        font-weight: 900;
        color: #101828;
    }

    .profileRequestActionButtons {
        grid-column: 1 / -1;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
        padding-top: 4px;
    }

    .profileRequestActionButtons .btn {
        border-radius: 999px !important;
        font-weight: 950 !important;
        padding: 10px 18px !important;
    }

    .profileRequestEmpty {
        min-height: 260px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 48px 20px;
    }

    .profileRequestEmpty i {
        width: 64px;
        height: 64px;
        border-radius: 22px;
        background: #eaf2ff;
        color: #0647b8;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin-bottom: 18px;
    }

    .profileRequestEmpty h2 {
        font-size: 28px;
        font-weight: 950;
        letter-spacing: -0.03em;
        margin-bottom: 8px;
    }

    @media (max-width: 1200px) {
        .profileRequestStats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .profileRequestFilterGrid,
        .profileRequestInfoGrid,
        .profileRequestActionPanel {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {

        .profileRequestStats,
        .profileRequestFilterGrid {
            grid-template-columns: 1fr;
        }

        .profileRequestCardHeader {
            flex-direction: column;
        }

        .profileRequestActionButtons {
            display: grid;
            grid-template-columns: 1fr;
        }

        .profileRequestActionButtons .btn {
            width: 100%;
        }
    }
</style>

<div class="ivote-dashboard-wrapper">

    <?php if ($notice !== '') { ?>
        <div class="ivote-flash-wrap">
            <div class="alert alert-<?php echo pcr_admin_h($notice_type); ?> alert-dismissible fade show" role="alert">
                <?php echo pcr_admin_h($notice); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php } ?>

    <div class="ivote-stats-grid profileRequestStats">
        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon"><i class="bi bi-inbox-fill"></i></div>
            <h3 class="ivote-stat-title">All Requests</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_all); ?></p>
            <p class="ivote-stat-caption">Total submitted requests</p>
        </div>

        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
            <h3 class="ivote-stat-title">Pending</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_pending); ?></p>
            <p class="ivote-stat-caption">Waiting for admin review</p>
        </div>

        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <h3 class="ivote-stat-title">Approved</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_approved); ?></p>
            <p class="ivote-stat-caption">Approved correction requests</p>
        </div>

        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon red"><i class="bi bi-x-circle-fill"></i></div>
            <h3 class="ivote-stat-title">Rejected</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_rejected); ?></p>
            <p class="ivote-stat-caption">Declined correction requests</p>
        </div>

        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon purple"><i class="bi bi-patch-check-fill"></i></div>
            <h3 class="ivote-stat-title">Resolved</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_resolved); ?></p>
            <p class="ivote-stat-caption">Completed admin actions</p>
        </div>
    </div>

    <section class="ivote-card ivote-dashboard-panel mb-4">
        <form method="get" class="profileRequestFilterGrid">
            <div>
                <label class="form-label fw-bold">Search</label>
                <input type="text" name="q" class="form-control"
                    value="<?php echo pcr_admin_h($search === '' ? null : $search); ?>"
                    placeholder="Search voter ID, name, email, field, or message">
            </div>

            <div>
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select">
                    <?php foreach ($allowed_filters as $filter) { ?>
                        <option value="<?php echo pcr_admin_h($filter); ?>" <?php echo ($status_filter === $filter) ? 'selected' : ''; ?>>
                            <?php echo ($filter === 'All') ? 'All statuses' : pcr_admin_h($filter); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <button type="submit" class="btn btn-ivote-outline">
                <i class="bi bi-funnel me-1"></i>
                Filter
            </button>

            <a href="profile_requests.php" class="btn btn-light fw-bold rounded-4">Reset</a>
        </form>
    </section>

    <section class="ivote-card ivote-dashboard-panel">
        <div class="ivote-card-header">
            <div>
                <h3 class="ivote-section-title">
                    <i class="bi bi-pencil-square text-primary me-1"></i>
                    Profile Change Requests
                </h3>
                <p class="text-muted mb-0">
                    Review voter-submitted correction requests and update their review status.
                </p>
            </div>

            <span class="badge rounded-pill text-bg-light px-3 py-2">
                <?php echo number_format($filtered_count); ?> request(s)
            </span>
        </div>

        <?php if (count($requests) === 0) { ?>
            <div class="profileRequestEmpty">
                <i class="bi bi-inbox-fill"></i>
                <h2>No profile change requests yet</h2>
                <p class="text-muted mb-0">Voter requests will appear here once submitted from the user side.</p>
            </div>
        <?php } else { ?>
            <div class="profileRequestCardList">
                <?php foreach ($requests as $request) { ?>
                    <?php
                    $full_name = trim($request['first_name'] . ' ' . $request['middle_name'] . ' ' . $request['last_name']);

                    if (isset($request['full_name_direct']) && trim($request['full_name_direct']) !== '') {
                        $full_name = trim($request['full_name_direct']);
                    }

                    if ($full_name === '') {
                        $full_name = $request['voter_id'];
                    }

                    $address_parts = array();

                    if ($request['specific_address'] !== '') {
                        $address_parts[] = $request['specific_address'];
                    }

                    if ($request['barangay'] !== '') {
                        $address_parts[] = $request['barangay'];
                    }

                    if ($request['city_municipality'] !== '') {
                        $address_parts[] = $request['city_municipality'];
                    }

                    if ($request['province'] !== '') {
                        $address_parts[] = $request['province'];
                    }

                    if ($request['region'] !== '') {
                        $address_parts[] = $request['region'];
                    }

                    $address = count($address_parts) > 0 ? implode(', ', $address_parts) : 'N/A';
                    ?>
                    <article class="profileRequestCard">
                        <div class="profileRequestCardHeader">
                            <div>
                                <div class="profileRequestVoterId"><?php echo pcr_admin_h($request['voter_id']); ?></div>
                                <div class="profileRequestName"><?php echo pcr_admin_h($full_name); ?></div>
                                <div class="profileRequestSubtext">
                                    Submitted: <?php echo pcr_admin_h(pcr_admin_format_datetime($request['created_at'])); ?>
                                </div>
                            </div>

                            <span
                                class="badge text-bg-<?php echo pcr_admin_badge_class($request['request_status']); ?> profileRequestBadge">
                                <?php echo pcr_admin_h($request['request_status']); ?>
                            </span>
                        </div>

                        <div class="profileRequestCardBody">
                            <div class="profileRequestInfoGrid">
                                <div class="profileRequestInfoBox">
                                    <span>Information to change</span>
                                    <strong><?php echo pcr_admin_h($request['request_field']); ?></strong>
                                </div>

                                <div class="profileRequestInfoBox">
                                    <span>Email</span>
                                    <strong><?php echo pcr_admin_h($request['email']); ?></strong>
                                </div>

                                <div class="profileRequestInfoBox">
                                    <span>Mobile number</span>
                                    <strong><?php echo pcr_admin_h($request['mobile_number']); ?></strong>
                                </div>

                                <div class="profileRequestInfoBox profileRequestWide">
                                    <span>Registered address</span>
                                    <strong><?php echo pcr_admin_h($address); ?></strong>
                                </div>

                                <div class="profileRequestInfoBox">
                                    <span>Reviewed</span>
                                    <strong><?php echo pcr_admin_h(pcr_admin_format_datetime($request['reviewed_at'])); ?></strong>
                                    <div class="profileRequestSubtext"><?php echo pcr_admin_h($request['reviewed_by']); ?></div>
                                </div>

                                <div class="profileRequestInfoBox profileRequestWide">
                                    <span>Request details</span>
                                    <strong><?php echo nl2br(pcr_admin_h($request['request_message'])); ?></strong>
                                </div>
                            </div>

                            <form method="post" class="profileRequestActionPanel">
                                <input type="hidden" name="request_id"
                                    value="<?php echo pcr_admin_h($request['request_id']); ?>">

                                <div>
                                    <label class="form-label">Admin response / user notification message</label>
                                    <textarea name="admin_response" class="form-control" rows="4"
                                        placeholder="Example: Your request has been approved and your profile information has been updated."><?php echo pcr_admin_h($request['admin_response']); ?></textarea>
                                </div>

                                <div class="profileRequestActionButtons threeOnly">
                                    <button type="button" class="btn btn-success pcrConfirmAction" data-action="approve"
                                        data-status="Approved" data-title="Approve Profile Request"
                                        data-message="Approve this request and notify the user?">
                                        <i class="bi bi-check-circle me-1"></i> Approve
                                    </button>

                                    <button type="button" class="btn btn-danger pcrConfirmAction" data-action="reject"
                                        data-status="Rejected" data-title="Reject Profile Request"
                                        data-message="Reject this request and notify the user?">
                                        <i class="bi bi-x-circle me-1"></i> Reject
                                    </button>

                                    <button type="button" class="btn btn-outline-danger pcrConfirmAction" data-action="delete"
                                        data-delete="1" data-title="Delete Profile Request"
                                        data-message="Delete this request permanently? This cannot be undone.">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                </div>
                            </form>
                        </div>
                    </article>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</div>


<style id="profileRequestThreeButtonCleanFix">
    .profileRequestActionButtons.threeOnly {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(150px, 1fr)) !important;
        gap: 12px !important;
    }

    .profileRequestActionButtons.threeOnly .btn {
        min-height: 48px !important;
        border-radius: 999px !important;
        font-weight: 950 !important;
    }

    @media (max-width: 768px) {
        .profileRequestActionButtons.threeOnly {
            grid-template-columns: 1fr !important;
        }
    }
</style>


<style id="profileRequestModernConfirmModalFix">
    .pcrConfirmModern .modal-content {
        border: none !important;
        border-radius: 26px !important;
        overflow: hidden !important;
        box-shadow: 0 30px 90px rgba(16, 24, 40, 0.34) !important;
    }

    .pcrConfirmModernHeader {
        padding: 24px 26px;
        background: linear-gradient(135deg, #0647b8, #0b63e5);
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .pcrConfirmModernIcon {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.18);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .pcrConfirmModernHeader h5 {
        margin: 0;
        font-size: 22px;
        font-weight: 950;
        letter-spacing: -0.03em;
    }

    .pcrConfirmModernBody {
        padding: 24px 26px;
        color: #344054;
        font-size: 16px;
        line-height: 1.6;
    }

    .pcrConfirmModernBody strong {
        color: #101828;
    }

    .pcrConfirmModernFooter {
        padding: 0 26px 24px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .pcrConfirmModernFooter .btn {
        min-height: 46px;
        border-radius: 999px !important;
        font-weight: 950 !important;
        padding-left: 24px;
        padding-right: 24px;
    }

    @media (max-width: 576px) {
        .pcrConfirmModernFooter {
            display: grid;
            grid-template-columns: 1fr;
        }

        .pcrConfirmModernFooter .btn {
            width: 100%;
        }
    }
</style>

<div class="modal fade pcrConfirmModern" id="pcrConfirmActionModal" tabindex="-1"
    aria-labelledby="pcrConfirmActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="pcrConfirmModernHeader">
                <div class="pcrConfirmModernIcon" id="pcrConfirmActionIcon">
                    <i class="bi bi-question-circle"></i>
                </div>
                <div>
                    <h5 id="pcrConfirmActionModalLabel">Confirm Action</h5>
                </div>
            </div>

            <div class="pcrConfirmModernBody">
                <p class="mb-0" id="pcrConfirmActionMessage">Are you sure?</p>
            </div>

            <div class="pcrConfirmModernFooter">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" id="pcrConfirmActionSubmit">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var pendingForm = null;
        var pendingStatus = '';
        var pendingDelete = '';

        function setConfirmButton(action) {
            var button = document.getElementById('pcrConfirmActionSubmit');
            var icon = document.getElementById('pcrConfirmActionIcon');

            button.className = 'btn btn-primary';
            button.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Confirm';
            icon.innerHTML = '<i class="bi bi-question-circle"></i>';

            if (action === 'approve') {
                button.className = 'btn btn-success';
                button.innerHTML = '<i class="bi bi-check-circle me-1"></i> Approve';
                icon.innerHTML = '<i class="bi bi-check-circle"></i>';
            } else if (action === 'reject') {
                button.className = 'btn btn-danger';
                button.innerHTML = '<i class="bi bi-x-circle me-1"></i> Reject';
                icon.innerHTML = '<i class="bi bi-x-circle"></i>';
            } else if (action === 'delete') {
                button.className = 'btn btn-danger';
                button.innerHTML = '<i class="bi bi-trash me-1"></i> Delete';
                icon.innerHTML = '<i class="bi bi-trash"></i>';
            }
        }

        function removeActionInputs(form) {
            var oldInputs = form.querySelectorAll('input[data-pcr-action-input="1"]');

            for (var i = 0; i < oldInputs.length; i++) {
                oldInputs[i].parentNode.removeChild(oldInputs[i]);
            }
        }

        function addHiddenInput(form, name, value) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            input.setAttribute('data-pcr-action-input', '1');
            form.appendChild(input);
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.pcrConfirmAction');

            if (!trigger) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            pendingForm = trigger.closest('form');
            pendingStatus = trigger.getAttribute('data-status') || '';
            pendingDelete = trigger.getAttribute('data-delete') || '';

            if (!pendingForm) {
                return;
            }

            var title = trigger.getAttribute('data-title') || 'Confirm Action';
            var message = trigger.getAttribute('data-message') || 'Are you sure you want to continue?';
            var action = trigger.getAttribute('data-action') || '';

            document.getElementById('pcrConfirmActionModalLabel').textContent = title;
            document.getElementById('pcrConfirmActionMessage').textContent = message;
            setConfirmButton(action);

            var modalElement = document.getElementById('pcrConfirmActionModal');
            var modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
                backdrop: true,
                keyboard: true
            });

            modal.show();
        });

        document.getElementById('pcrConfirmActionSubmit').addEventListener('click', function () {
            if (!pendingForm) {
                return;
            }

            removeActionInputs(pendingForm);

            if (pendingDelete === '1') {
                addHiddenInput(pendingForm, 'delete_request', '1');
            } else if (pendingStatus !== '') {
                addHiddenInput(pendingForm, 'quick_status', pendingStatus);
            }

            pendingForm.submit();
        });
    })();
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>