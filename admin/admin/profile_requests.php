<?php
if (session_id() === '') {
    session_start();
}

date_default_timezone_set('Asia/Manila');

$conn = null;

$connection_files = array(
    __DIR__ . '/db_connect.php',
    __DIR__ . '/../db_connect.php',
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../db_connect.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../includes/db_connect.php',
    __DIR__ . '/../../includes/db_connect.php'
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
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8');

function pcr_h($value) {
    if ($value === null || $value === '') {
        return 'N/A';
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pcr_badge_class($status) {
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

function pcr_table_exists($conn, $table_name) {
    $table_name = mysqli_real_escape_string($conn, $table_name);
    $sql = "SHOW TABLES LIKE '" . $table_name . "'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        mysqli_free_result($result);
        return true;
    }

    if ($result) {
        mysqli_free_result($result);
    }

    return false;
}

function pcr_column_exists($conn, $table_name, $column_name) {
    $table_name = mysqli_real_escape_string($conn, $table_name);
    $column_name = mysqli_real_escape_string($conn, $column_name);

    $sql = "SHOW COLUMNS FROM `" . $table_name . "` LIKE '" . $column_name . "'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        mysqli_free_result($result);
        return true;
    }

    if ($result) {
        mysqli_free_result($result);
    }

    return false;
}

function pcr_select_column($conn, $table_name, $table_alias, $column_name, $alias_name) {
    if (pcr_table_exists($conn, $table_name) && pcr_column_exists($conn, $table_name, $column_name)) {
        return $table_alias . "." . $column_name . " AS " . $alias_name;
    }

    return "'' AS " . $alias_name;
}

function pcr_select_first_column($conn, $table_name, $table_alias, $columns, $alias_name) {
    if (pcr_table_exists($conn, $table_name)) {
        foreach ($columns as $column_name) {
            if (pcr_column_exists($conn, $table_name, $column_name)) {
                return $table_alias . "." . $column_name . " AS " . $alias_name;
            }
        }
    }

    return "'' AS " . $alias_name;
}

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
        INDEX idx_voter_id (voter_id),
        INDEX idx_request_status (request_status),
        INDEX idx_created_at (created_at)
    )
");

$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'])) {
    $request_id = (int) $_POST['request_id'];
    $request_status = isset($_POST['request_status']) ? trim($_POST['request_status']) : 'Pending';
    $admin_response = isset($_POST['admin_response']) ? trim($_POST['admin_response']) : '';

    $allowed_statuses = array('Pending', 'Approved', 'Rejected', 'Resolved');

    if (!in_array($request_status, $allowed_statuses)) {
        $request_status = 'Pending';
    }

    $reviewed_by = 'System Administrator';

    if (isset($_SESSION['admin_name']) && $_SESSION['admin_name'] !== '') {
        $reviewed_by = $_SESSION['admin_name'];
    } elseif (isset($_SESSION['admin_username']) && $_SESSION['admin_username'] !== '') {
        $reviewed_by = $_SESSION['admin_username'];
    }

    $sql_update = "
        UPDATE profile_change_requests
        SET request_status = ?,
            admin_response = ?,
            reviewed_by = ?,
            reviewed_at = NOW(),
            updated_at = NOW()
        WHERE request_id = ?
    ";

    $stmt_update = mysqli_prepare($conn, $sql_update);

    if ($stmt_update) {
        mysqli_stmt_bind_param($stmt_update, 'sssi', $request_status, $admin_response, $reviewed_by, $request_id);

        if (mysqli_stmt_execute($stmt_update)) {
            $notice = 'Request updated successfully.';
        } else {
            $notice = 'Failed to update request.';
        }

        mysqli_stmt_close($stmt_update);
    } else {
        $notice = 'Failed to prepare update request.';
    }
}

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'All';
$allowed_filters = array('All', 'Pending', 'Approved', 'Rejected', 'Resolved');

if (!in_array($status_filter, $allowed_filters)) {
    $status_filter = 'All';
}

$where_sql = '';

if ($status_filter !== 'All') {
    $safe_status = mysqli_real_escape_string($conn, $status_filter);
    $where_sql = "WHERE pcr.request_status = '" . $safe_status . "'";
}

$rv_join = '';
$va_join = '';

if (pcr_table_exists($conn, 'registered_voters')) {
    $rv_join = "LEFT JOIN registered_voters rv ON pcr.voter_id = rv.voter_id";
}

if (pcr_table_exists($conn, 'voter_addresses')) {
    $va_join = "LEFT JOIN voter_addresses va ON pcr.voter_id = va.voter_id";
}

$select_first_name = pcr_select_column($conn, 'registered_voters', 'rv', 'first_name', 'first_name');
$select_middle_name = pcr_select_column($conn, 'registered_voters', 'rv', 'middle_name', 'middle_name');
$select_last_name = pcr_select_column($conn, 'registered_voters', 'rv', 'last_name', 'last_name');
$select_email = pcr_select_first_column($conn, 'registered_voters', 'rv', array('email', 'email_address'), 'email');
$select_mobile = pcr_select_first_column($conn, 'registered_voters', 'rv', array('mobile_number', 'contact_number', 'phone_number'), 'mobile_number');

$select_region = pcr_select_column($conn, 'voter_addresses', 'va', 'region', 'region');
$select_province = pcr_select_column($conn, 'voter_addresses', 'va', 'province', 'province');
$select_city = pcr_select_first_column($conn, 'voter_addresses', 'va', array('city_municipality', 'city', 'municipality'), 'city_municipality');
$select_barangay = pcr_select_column($conn, 'voter_addresses', 'va', 'barangay', 'barangay');
$select_specific_address = pcr_select_first_column($conn, 'voter_addresses', 'va', array('specific_address', 'street_address', 'address'), 'specific_address');

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
$result_requests = mysqli_query($conn, $sql_requests);

if ($result_requests) {
    while ($row = mysqli_fetch_assoc($result_requests)) {
        $requests[] = $row;
    }

    mysqli_free_result($result_requests);
}

$count_all = 0;
$count_pending = 0;
$count_approved = 0;
$count_rejected = 0;
$count_resolved = 0;

$result_counts = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN request_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN request_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN request_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN request_status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count
    FROM profile_change_requests
");

if ($result_counts) {
    $row_counts = mysqli_fetch_assoc($result_counts);

    $count_all = (int) $row_counts['total_count'];
    $count_pending = (int) $row_counts['pending_count'];
    $count_approved = (int) $row_counts['approved_count'];
    $count_rejected = (int) $row_counts['rejected_count'];
    $count_resolved = (int) $row_counts['resolved_count'];

    mysqli_free_result($result_counts);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile Change Requests - iVotePH Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --blue: #0647b8;
            --blueDark: #033587;
            --ink: #101828;
            --muted: #667085;
            --line: #dfe7f3;
            --page: #f3f6fb;
            --soft: #eef5ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: radial-gradient(circle at top left, rgba(6, 71, 184, 0.12), transparent 32%), linear-gradient(180deg, #f7f9ff, var(--page));
            color: var(--ink);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        a {
            text-decoration: none;
        }

        .adminPage {
            width: min(1500px, calc(100% - 40px));
            margin: 0 auto;
            padding: 28px 0 48px;
        }

        .topCard,
        .statCard,
        .filterCard,
        .requestCard,
        .emptyState {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 10px 28px rgba(16, 24, 40, 0.07);
        }

        .topCard {
            padding: 22px 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .topTitle {
            margin: 0;
            font-size: clamp(24px, 3vw, 34px);
            font-weight: 950;
            letter-spacing: -0.04em;
        }

        .topSubtitle {
            margin: 4px 0 0;
            color: var(--muted);
        }

        .btnBlue {
            background: var(--blue);
            border-color: var(--blue);
            color: #ffffff;
            border-radius: 14px;
            font-weight: 900;
            padding: 11px 16px;
        }

        .btnBlue:hover {
            background: var(--blueDark);
            border-color: var(--blueDark);
            color: #ffffff;
        }

        .statGrid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .statCard {
            padding: 20px;
            min-height: 128px;
        }

        .statLabel {
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .statValue {
            margin-top: 8px;
            color: var(--blue);
            font-size: 34px;
            line-height: 1;
            font-weight: 950;
        }

        .filterCard {
            padding: 16px;
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filterBtn {
            border-radius: 999px;
            padding: 9px 14px;
            font-weight: 900;
            background: #f2f5fb;
            color: #344054;
        }

        .filterBtn.active,
        .filterBtn:hover {
            background: var(--blue);
            color: #ffffff;
        }

        .requestList {
            display: grid;
            gap: 16px;
        }

        .requestCard {
            padding: 20px;
        }

        .requestHeader {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            border-bottom: 1px solid #edf2f8;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }

        .requestTitle {
            margin: 0;
            font-size: 20px;
            font-weight: 950;
        }

        .requestMeta {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

        .infoGrid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .infoBox {
            background: #f7f9fd;
            border: 1px solid #e1e8f3;
            border-radius: 16px;
            padding: 13px;
        }

        .infoBox span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }

        .infoBox strong {
            display: block;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .messageBox {
            padding: 14px;
            background: var(--soft);
            border: 1px solid #cfe0ff;
            border-radius: 16px;
            color: #24344f;
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .adminForm {
            display: grid;
            grid-template-columns: 180px 1fr auto;
            gap: 10px;
            align-items: end;
        }

        .adminForm select,
        .adminForm textarea {
            border-radius: 14px;
            border: 1px solid var(--line);
        }

        .emptyState {
            text-align: center;
            padding: 60px 20px;
        }

        .emptyState i {
            font-size: 44px;
            color: var(--blue);
            margin-bottom: 12px;
        }

        @media (max-width: 1100px) {
            .statGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .infoGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .adminPage {
                width: calc(100% - 24px);
            }

            .topCard,
            .requestHeader {
                flex-direction: column;
            }

            .statGrid,
            .infoGrid,
            .adminForm {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <main class="adminPage">
        <section class="topCard">
            <div>
                <h1 class="topTitle">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>
                    Profile Change Requests
                </h1>
                <p class="topSubtitle">
                    Review voter-submitted correction requests from the user side.
                </p>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="index.php" class="btn btn-light fw-bold rounded-4">
                    <i class="fa-solid fa-gauge me-1"></i>
                    Dashboard
                </a>

                <a href="results.php" class="btn btnBlue">
                    <i class="fa-solid fa-chart-simple me-1"></i>
                    Results
                </a>
            </div>
        </section>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-info rounded-4 fw-bold">
                <?php echo pcr_h($notice); ?>
            </div>
        <?php endif; ?>

        <section class="statGrid">
            <div class="statCard">
                <div class="statLabel">All Requests</div>
                <div class="statValue"><?php echo pcr_h($count_all); ?></div>
            </div>

            <div class="statCard">
                <div class="statLabel">Pending</div>
                <div class="statValue"><?php echo pcr_h($count_pending); ?></div>
            </div>

            <div class="statCard">
                <div class="statLabel">Approved</div>
                <div class="statValue"><?php echo pcr_h($count_approved); ?></div>
            </div>

            <div class="statCard">
                <div class="statLabel">Rejected</div>
                <div class="statValue"><?php echo pcr_h($count_rejected); ?></div>
            </div>

            <div class="statCard">
                <div class="statLabel">Resolved</div>
                <div class="statValue"><?php echo pcr_h($count_resolved); ?></div>
            </div>
        </section>

        <section class="filterCard">
            <?php
            $filters = array('All', 'Pending', 'Approved', 'Rejected', 'Resolved');

            foreach ($filters as $filter):
                $active = ($status_filter === $filter) ? 'active' : '';
            ?>
                <a class="filterBtn <?php echo $active; ?>" href="profile_requests.php?status=<?php echo urlencode($filter); ?>">
                    <?php echo pcr_h($filter); ?>
                </a>
            <?php endforeach; ?>
        </section>

        <?php if (count($requests) === 0): ?>
            <section class="emptyState">
                <i class="fa-solid fa-inbox"></i>
                <h2>No profile change requests yet</h2>
                <p class="text-muted mb-0">
                    Voter requests will appear here once submitted from the user side.
                </p>
            </section>
        <?php else: ?>
            <section class="requestList">
                <?php foreach ($requests as $request): ?>
                    <?php
                    $full_name = trim($request['first_name'] . ' ' . $request['middle_name'] . ' ' . $request['last_name']);

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
                    <article class="requestCard">
                        <div class="requestHeader">
                            <div>
                                <h2 class="requestTitle"><?php echo pcr_h($full_name); ?></h2>

                                <div class="requestMeta">
                                    Voter ID: <?php echo pcr_h($request['voter_id']); ?> · Submitted:
                                    <?php echo pcr_h(date('M d, Y h:i A', strtotime($request['created_at']))); ?>
                                </div>
                            </div>

                            <span class="badge bg-<?php echo pcr_badge_class($request['request_status']); ?> rounded-pill px-3 py-2">
                                <?php echo pcr_h($request['request_status']); ?>
                            </span>
                        </div>

                        <div class="infoGrid">
                            <div class="infoBox">
                                <span>Information to Change</span>
                                <strong><?php echo pcr_h($request['request_field']); ?></strong>
                            </div>

                            <div class="infoBox">
                                <span>Email</span>
                                <strong><?php echo pcr_h($request['email']); ?></strong>
                            </div>

                            <div class="infoBox">
                                <span>Mobile Number</span>
                                <strong><?php echo pcr_h($request['mobile_number']); ?></strong>
                            </div>

                            <div class="infoBox" style="grid-column: 1 / -1;">
                                <span>Registered Address</span>
                                <strong><?php echo pcr_h($address); ?></strong>
                            </div>
                        </div>

                        <div class="messageBox">
                            <strong>Request Details:</strong><br>
                            <?php echo nl2br(pcr_h($request['request_message'])); ?>
                        </div>

                        <?php if ($request['admin_response'] !== null && $request['admin_response'] !== ''): ?>
                            <div class="messageBox" style="background:#f7f9fd;border-color:#e1e8f3;">
                                <strong>Admin Response:</strong><br>
                                <?php echo nl2br(pcr_h($request['admin_response'])); ?>

                                <div class="small text-muted mt-2">
                                    Reviewed by <?php echo pcr_h($request['reviewed_by']); ?>

                                    <?php if ($request['reviewed_at']): ?>
                                        · <?php echo pcr_h(date('M d, Y h:i A', strtotime($request['reviewed_at']))); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="adminForm">
                            <input type="hidden" name="request_id" value="<?php echo pcr_h($request['request_id']); ?>">

                            <div>
                                <label class="form-label fw-bold small">Status</label>

                                <select name="request_status" class="form-select">
                                    <?php foreach (array('Pending', 'Approved', 'Rejected', 'Resolved') as $status): ?>
                                        <option value="<?php echo pcr_h($status); ?>" <?php echo ($request['request_status'] === $status) ? 'selected' : ''; ?>>
                                            <?php echo pcr_h($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="form-label fw-bold small">Admin response</label>

                                <textarea name="admin_response" class="form-control" rows="2" placeholder="Example: Your request has been reviewed."><?php echo pcr_h($request['admin_response']); ?></textarea>
                            </div>

                            <button type="submit" class="btn btnBlue">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                Save
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>