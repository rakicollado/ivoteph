<?php
require_once dirname(__FILE__) . '/../auth_check.php';
require_admin();

date_default_timezone_set('Asia/Manila');

$page_title = 'Audit Logs';
$page_subtitle = 'Track admin activities, security actions, and important system changes.';

$pdo = db();

function audit_get_value($key)
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : '';
}

function audit_display_datetime($value)
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

function audit_column_exists($pdo, $table_name, $column_name)
{
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);
    $column_name = preg_replace('/[^A-Za-z0-9_]/', '', $column_name);

    if ($table_name == '' || $column_name == '') {
        return false;
    }

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table_name . '`');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            if (isset($column['Field']) && strtolower($column['Field']) == strtolower($column_name)) {
                return true;
            }
        }
    } catch (Exception $e) {
        return false;
    }

    return false;
}

function audit_table_exists($pdo, $table_name)
{
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);

    if ($table_name == '') {
        return false;
    }

    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ');
        $stmt->bindValue(':table_name', $table_name);
        $stmt->execute();

        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
    } catch (Exception $e) {
        /* Fall through to direct table probe below. */
    }

    try {
        $pdo->query('SELECT 1 FROM `' . $table_name . '` LIMIT 1');
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function audit_first_existing_column($pdo, $table_name, $columns)
{
    for ($i = 0; $i < count($columns); $i++) {
        if (audit_column_exists($pdo, $table_name, $columns[$i])) {
            return $columns[$i];
        }
    }

    return '';
}

function audit_backtick($column_name)
{
    $column_name = preg_replace('/[^A-Za-z0-9_]/', '', $column_name);
    return '`' . $column_name . '`';
}

function audit_action_badge($action)
{
    $action = strtolower((string) $action);

    if (strpos($action, 'login') !== false || strpos($action, 'logged') !== false) {
        return 'text-bg-primary';
    }

    if (strpos($action, 'add') !== false || strpos($action, 'create') !== false || strpos($action, 'open') !== false) {
        return 'text-bg-success';
    }

    if (strpos($action, 'update') !== false || strpos($action, 'edit') !== false || strpos($action, 'change') !== false || strpos($action, 'schedule') !== false) {
        return 'text-bg-warning';
    }

    if (strpos($action, 'delete') !== false || strpos($action, 'close') !== false || strpos($action, 'logout') !== false) {
        return 'text-bg-danger';
    }

    return 'text-bg-secondary';
}

function audit_action_icon($action)
{
    $action = strtolower((string) $action);

    if (strpos($action, 'login') !== false || strpos($action, 'logged') !== false) {
        return 'bi-shield-check';
    }

    if (strpos($action, 'voter') !== false) {
        return 'bi-person-check';
    }

    if (strpos($action, 'candidate') !== false) {
        return 'bi-person-badge';
    }

    if (strpos($action, 'election') !== false || strpos($action, 'schedule') !== false || strpos($action, 'voting') !== false) {
        return 'bi-calendar-check';
    }

    if (strpos($action, 'delete') !== false) {
        return 'bi-trash';
    }

    if (strpos($action, 'close') !== false || strpos($action, 'logout') !== false) {
        return 'bi-lock';
    }

    return 'bi-clock-history';
}

$search = audit_get_value('search');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 12;

$audit_logs = array();
$total_rows = 0;
$total_today = 0;
$total_logins = 0;
$total_system_changes = 0;
$table_ready = audit_table_exists($pdo, 'audit_logs');

$log_id_column = '';
$admin_name_column = '';
$action_column = '';
$timestamp_column = '';

$where = array();
$params = array();
$where_sql = '';
$order_sql = 'ORDER BY 1 DESC';

if ($table_ready) {
    $log_id_column = audit_first_existing_column($pdo, 'audit_logs', array('log_id', 'audit_log_id', 'id'));
    $admin_name_column = audit_first_existing_column($pdo, 'audit_logs', array('admin_name', 'admin_username', 'username', 'admin'));
    $action_column = audit_first_existing_column($pdo, 'audit_logs', array('action', 'activity', 'description', 'message'));
    $timestamp_column = audit_first_existing_column($pdo, 'audit_logs', array('timestamp', 'created_at', 'log_time', 'created_on', 'date_created'));

    if ($search != '') {
        $search_parts = array();

        if ($admin_name_column != '') {
            $search_parts[] = audit_backtick($admin_name_column) . ' LIKE :search';
        }

        if ($action_column != '') {
            $search_parts[] = audit_backtick($action_column) . ' LIKE :search';
        }

        if (count($search_parts) > 0) {
            $where[] = '(' . implode(' OR ', $search_parts) . ')';
            $params[':search'] = '%' . $search . '%';
        }
    }

    if (count($where) > 0) {
        $where_sql = 'WHERE ' . implode(' AND ', $where);
    }

    if ($timestamp_column != '' && $log_id_column != '') {
        $order_sql = 'ORDER BY ' . audit_backtick($timestamp_column) . ' DESC, ' . audit_backtick($log_id_column) . ' DESC';
    } elseif ($timestamp_column != '') {
        $order_sql = 'ORDER BY ' . audit_backtick($timestamp_column) . ' DESC';
    } elseif ($log_id_column != '') {
        $order_sql = 'ORDER BY ' . audit_backtick($log_id_column) . ' DESC';
    }

    try {
        $count_sql = 'SELECT COUNT(*) FROM audit_logs ' . $where_sql;
        $count_stmt = $pdo->prepare($count_sql);

        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }

        $count_stmt->execute();
        $total_rows = (int) $count_stmt->fetchColumn();
    } catch (Exception $e) {
        $total_rows = 0;
    }

    if ($timestamp_column != '') {
        try {
            $total_today = (int) $pdo->query('
                SELECT COUNT(*)
                FROM audit_logs
                WHERE DATE(' . audit_backtick($timestamp_column) . ') = CURDATE()
            ')->fetchColumn();
        } catch (Exception $e) {
            $total_today = 0;
        }
    }

    if ($action_column != '') {
        try {
            $total_logins = (int) $pdo->query('
                SELECT COUNT(*)
                FROM audit_logs
                WHERE ' . audit_backtick($action_column) . " LIKE '%login%'
                   OR " . audit_backtick($action_column) . " LIKE '%logged%'
                   OR " . audit_backtick($action_column) . " LIKE '%logout%'
            ")->fetchColumn();
        } catch (Exception $e) {
            $total_logins = 0;
        }

        try {
            $total_system_changes = (int) $pdo->query('
                SELECT COUNT(*)
                FROM audit_logs
                WHERE ' . audit_backtick($action_column) . " LIKE '%add%'
                   OR " . audit_backtick($action_column) . " LIKE '%create%'
                   OR " . audit_backtick($action_column) . " LIKE '%update%'
                   OR " . audit_backtick($action_column) . " LIKE '%edit%'
                   OR " . audit_backtick($action_column) . " LIKE '%delete%'
                   OR " . audit_backtick($action_column) . " LIKE '%open%'
                   OR " . audit_backtick($action_column) . " LIKE '%close%'
                   OR " . audit_backtick($action_column) . " LIKE '%schedule%'
            ")->fetchColumn();
        } catch (Exception $e) {
            $total_system_changes = 0;
        }
    }
}

$pagination = paginate($total_rows, $page, $per_page);
$page = $pagination[0];
$total_pages = $pagination[1];
$offset = $pagination[2];

if ($table_ready) {
    $select_parts = array();

    if ($log_id_column != '') {
        $select_parts[] = audit_backtick($log_id_column) . ' AS log_id';
    } else {
        $select_parts[] = '0 AS log_id';
    }

    if ($admin_name_column != '') {
        $select_parts[] = audit_backtick($admin_name_column) . ' AS admin_name';
    } else {
        $select_parts[] = "'System Admin' AS admin_name";
    }

    if ($action_column != '') {
        $select_parts[] = audit_backtick($action_column) . ' AS action';
    } else {
        $select_parts[] = "'Admin activity' AS action";
    }

    if ($timestamp_column != '') {
        $select_parts[] = audit_backtick($timestamp_column) . ' AS log_timestamp';
    } else {
        $select_parts[] = 'NULL AS log_timestamp';
    }

    try {
        $sql = '
            SELECT
                ' . implode(",\n                ", $select_parts) . '
            FROM audit_logs
            ' . $where_sql . '
            ' . $order_sql . '
            LIMIT :limit OFFSET :offset
        ';

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', (int) $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();

        $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $audit_logs = array();
    }
}

$flashes = consume_flash();

require_once dirname(__FILE__) . '/../includes/header.php';
require_once dirname(__FILE__) . '/../includes/sidebar.php';
?>

<div class="ivote-management-page ivote-audit-page">

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

    <div class="ivote-results-stat-grid">
        <div class="ivote-card ivote-result-stat">
            <span>Total Logs</span>
            <strong><?php echo number_format($total_rows); ?></strong>
            <small>Recorded admin activities</small>
        </div>

        <div class="ivote-card ivote-result-stat">
            <span>Today</span>
            <strong><?php echo number_format($total_today); ?></strong>
            <small>Logs recorded today</small>
        </div>

        <div class="ivote-card ivote-result-stat">
            <span>Login Records</span>
            <strong><?php echo number_format($total_logins); ?></strong>
            <small>Admin login and logout activities</small>
        </div>

        <div class="ivote-card ivote-result-stat">
            <span>System Changes</span>
            <strong><?php echo number_format($total_system_changes); ?></strong>
            <small>Voter, candidate, and election actions</small>
        </div>
    </div>

    <div class="ivote-filter-card">
        <form method="GET" action="audit_logs.php" class="ivote-audit-filter-form">
            <div>
                <label class="form-label">Search Logs</label>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="<?php echo e($search); ?>"
                    placeholder="Search admin name or action"
                >
            </div>

            <button type="submit" class="btn btn-ivote-outline">
                <i class="bi bi-search me-1"></i>
                Search
            </button>

            <a href="audit_logs.php" class="btn btn-light border">
                Reset
            </a>
        </form>
    </div>

    <div class="ivote-card ivote-data-card">
        <div class="ivote-card-header">
            <h3 class="ivote-section-title">
                <i class="bi bi-clock-history text-primary me-1"></i>
                Admin Activity History
            </h3>

            <span class="ivote-record-count">
                <?php echo number_format($total_rows); ?> record(s)
            </span>
        </div>

        <?php if (!$table_ready) { ?>
            <div class="alert alert-warning rounded-4 mb-0">
                The audit_logs table was not found in the database.
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table ivote-management-table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Type</th>
                            <th>Date and Time</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (count($audit_logs) > 0) { ?>
                            <?php foreach ($audit_logs as $log) { ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary">
                                            #<?php echo e($log['log_id']); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <div class="fw-bold">
                                            <?php echo e($log['admin_name']); ?>
                                        </div>
                                        <small class="text-muted">System administrator</small>
                                    </td>

                                    <td>
                                        <div class="ivote-audit-action">
                                            <i class="bi <?php echo e(audit_action_icon($log['action'])); ?>"></i>
                                            <span><?php echo e($log['action']); ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo audit_action_badge($log['action']); ?>">
                                            Activity
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo e(audit_display_datetime($log['log_timestamp'])); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    No audit logs found.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="ivote-pagination-wrap">
                <div class="text-muted small">
                    Page <?php echo number_format($page); ?> of <?php echo number_format($total_pages); ?>
                </div>

                <nav>
                    <ul class="pagination mb-0">
                        <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="audit_logs.php?search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </nav>
            </div>
        <?php } ?>
    </div>

</div>

<?php
require_once dirname(__FILE__) . '/../includes/footer.php';
?>
