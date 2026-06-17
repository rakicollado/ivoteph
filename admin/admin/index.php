<?php
require_once dirname(__FILE__) . '/../auth_check.php';
require_admin();

$page_title = 'Dashboard';
$page_subtitle = 'Real-time summary from your iVotePH MySQL database.';
$activePage = 'dashboard';

$pdo = db();

function dashboard_count_query($pdo, $sql)
{
    try {
        $stmt = $pdo->query($sql);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function dashboard_rows_query($pdo, $sql)
{
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return array();
    }
}

function dashboard_one_query($pdo, $sql)
{
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function dashboard_table_exists($pdo, $table_name)
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table_name));
        return $stmt->fetchColumn() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

function dashboard_column_exists($pdo, $table_name, $column_name)
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table_name) . "` LIKE " . $pdo->quote($column_name));
        return $stmt->fetchColumn() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

function dashboard_format_datetime($value)
{
    if ($value == '' || $value == null || $value == '0000-00-00 00:00:00') {
        return '-';
    }

    $time = strtotime($value);

    if ($time === false) {
        return '-';
    }

    return date('M d, Y', $time) . '<br><span class="ivote-datetime-time">' . date('h:i A', $time) . '</span>';
}

$total_voter_ids = dashboard_count_query($pdo, "SELECT COUNT(*) FROM registered_voters");
$complete_profiles = dashboard_count_query($pdo, "SELECT COUNT(*) FROM registered_voters WHERE profile_status = 'Complete'");
$registered_accounts = dashboard_count_query($pdo, "SELECT COUNT(*) FROM accounts");
$total_candidates = dashboard_count_query($pdo, "SELECT COUNT(*) FROM candidates");
$total_votes_cast = 0;

if (dashboard_table_exists($pdo, 'ballots')) {
    $total_votes_cast = dashboard_count_query($pdo, "
        SELECT COUNT(DISTINCT ballot_id)
        FROM ballots
    ");
} elseif (dashboard_table_exists($pdo, 'votes')) {
    $total_votes_cast = dashboard_count_query($pdo, "
        SELECT COUNT(DISTINCT ballot_id)
        FROM votes
    ");
}

$profile_requests_total = 0;
$profile_requests_pending = 0;

if (dashboard_table_exists($pdo, 'profile_change_requests')) {
    $profile_requests_total = dashboard_count_query($pdo, "
        SELECT COUNT(*)
        FROM profile_change_requests
    ");

    $profile_requests_pending = dashboard_count_query($pdo, "
        SELECT COUNT(*)
        FROM profile_change_requests
        WHERE request_status = 'Pending'
    ");
}

$active_elections = 0;
$latest_election = false;

if (dashboard_table_exists($pdo, 'elections')) {
    $active_elections = dashboard_count_query($pdo, "SELECT COUNT(*) FROM elections WHERE LOWER(election_status) = 'open' OR LOWER(election_status) = 'active'");

    if (dashboard_column_exists($pdo, 'elections', 'created_at')) {
        $latest_election = dashboard_one_query($pdo, "SELECT * FROM elections ORDER BY created_at DESC LIMIT 1");
    } else {
        $latest_election = dashboard_one_query($pdo, "SELECT * FROM elections ORDER BY election_id DESC LIMIT 1");
    }
}

$flashes = consume_flash();

require_once dirname(__FILE__) . '/../includes/header.php';
require_once dirname(__FILE__) . '/../includes/sidebar.php';
?>

<style>
    .ivote-datetime-time {
        font-size: 0.85em;
        font-weight: 400;
        color: #6b7280;
    }

    .ivote-profile-request-badge {
        margin-top: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 26px;
        height: 26px;
        padding: 0 8px;
        border-radius: 999px;
        background: #dc2626;
        color: #ffffff;
        font-size: 12px;
        font-weight: 950;
        box-shadow: 0 8px 18px rgba(220, 38, 38, 0.25);
    }

    .ivote-quick-action:hover .ivote-profile-request-badge {
        background: #ffffff;
        color: #dc2626;
    }
</style>

<div class="ivote-dashboard-wrapper">

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

    <div class="ivote-stats-grid ivote-stats-grid-dashboard-four">
        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon">
                <i class="bi bi-people-fill"></i>
            </div>
            <h3 class="ivote-stat-title">Total Voter IDs</h3>
            <p class="ivote-stat-value"><?php echo number_format($total_voter_ids); ?></p>
            <p class="ivote-stat-caption">Master-list voter records</p>
        </div>

        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon green">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <h3 class="ivote-stat-title">Complete Profiles</h3>
            <p class="ivote-stat-value"><?php echo number_format($complete_profiles); ?></p>
            <p class="ivote-stat-caption">Voters with complete details</p>
        </div>

        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon purple">
                <i class="bi bi-shield-check"></i>
            </div>
            <h3 class="ivote-stat-title">Registered Accounts</h3>
            <p class="ivote-stat-value"><?php echo number_format($registered_accounts); ?></p>
            <p class="ivote-stat-caption">Created voter accounts</p>
        </div>

        <div class="ivote-card ivote-stat-card">
            <div class="ivote-stat-icon yellow">
                <i class="bi bi-check2-square"></i>
            </div>
            <h3 class="ivote-stat-title">Votes Cast</h3>
            <p class="ivote-stat-value"><?php echo number_format($total_votes_cast); ?></p>
            <p class="ivote-stat-caption">Voters who submitted ballots</p>
        </div>
    </div>

    <div class="ivote-dashboard-grid-main">
        <section class="ivote-card ivote-dashboard-panel">
            <div class="ivote-card-header">
                <div>
                    <h3 class="ivote-section-title">
                        <i class="bi bi-calendar-check text-primary me-1"></i>
                        Current Election Status
                    </h3>
                    <p class="text-muted mb-0">
                        Latest election schedule and current voting status.
                    </p>
                </div>

                <a href="elections.php" class="btn btn-ivote-outline">
                    Manage
                </a>
            </div>

            <?php if ($latest_election) { ?>
                <?php
                $election_title = 'Election';

                if (isset($latest_election['election_title']) && $latest_election['election_title'] != '') {
                    $election_title = $latest_election['election_title'];
                } elseif (isset($latest_election['title']) && $latest_election['title'] != '') {
                    $election_title = $latest_election['title'];
                }

                $election_status = isset($latest_election['election_status']) ? $latest_election['election_status'] : (isset($latest_election['status']) ? $latest_election['status'] : 'Scheduled');
                $start_date = isset($latest_election['start_datetime']) ? $latest_election['start_datetime'] : (isset($latest_election['start_date']) ? $latest_election['start_date'] : '');
                $end_date = isset($latest_election['end_datetime']) ? $latest_election['end_datetime'] : (isset($latest_election['end_date']) ? $latest_election['end_date'] : '');
                ?>

                <div class="ivote-election-control-shell">
                    <div class="ivote-election-hero-card">
                        <div>
                            <div class="ivote-election-eyebrow">Official Voting Event</div>
                            <h2><?php echo e($election_title); ?></h2>
                            <p>Voting status is controlled by the admin election schedule.</p>
                        </div>

                        <div class="ivote-election-hero-right">
                            <?php
                            $badge_class = 'text-bg-secondary';
                            $status_lower = strtolower(trim($election_status));
                            if ($status_lower == 'open') { $badge_class = 'text-bg-success'; }
                            elseif ($status_lower == 'scheduled') { $badge_class = 'text-bg-primary'; }
                            elseif ($status_lower == 'closed') { $badge_class = 'text-bg-danger'; }
                            ?>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo e(ucfirst($election_status)); ?>
                            </span>
                        </div>
                    </div>

                    <div class="ivote-election-info-grid">
                        <div class="ivote-election-info-card">
                            <span>Start Date</span>
                            <strong><?php echo dashboard_format_datetime($start_date); ?></strong>
                        </div>

                        <div class="ivote-election-info-card">
                            <span>End Date</span>
                            <strong><?php echo dashboard_format_datetime($end_date); ?></strong>
                        </div>

                        <div class="ivote-election-info-card">
                            <span>Active Elections</span>
                            <strong><?php echo number_format($active_elections); ?></strong>
                        </div>

                        <div class="ivote-election-info-card">
                            <span>Total Candidates</span>
                            <strong><?php echo number_format($total_candidates); ?></strong>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    No election has been created yet.
                </div>
            <?php } ?>
        </section>

        <section class="ivote-card ivote-dashboard-panel">
            <div class="ivote-card-header">
                <div>
                    <h3 class="ivote-section-title">
                        <i class="bi bi-lightning-charge text-primary me-1"></i>
                        Admin Shortcuts
                    </h3>
                    <p class="text-muted mb-0">
                        Quick access to major admin modules.
                    </p>
                </div>
            </div>

            <div class="ivote-quick-grid">
                <a class="ivote-quick-action" href="voters.php">
                    <i class="bi bi-people-fill"></i>
                    Add Voter
                </a>

                <a class="ivote-quick-action" href="profile_requests.php">
                    <i class="bi bi-pencil-square"></i>
                    Profile Requests

                    <?php if ($profile_requests_pending > 0) { ?>
                        <span class="ivote-profile-request-badge">
                            <?php echo number_format($profile_requests_pending); ?>
                        </span>
                    <?php } ?>
                </a>

                <a class="ivote-quick-action" href="candidates.php">
                    <i class="bi bi-person-badge-fill"></i>
                    Add Candidate
                </a>

                <a class="ivote-quick-action" href="elections.php">
                    <i class="bi bi-calendar-event-fill"></i>
                    Elections
                </a>

                <a class="ivote-quick-action" href="results.php">
                    <i class="bi bi-graph-up-arrow"></i>
                    Results
                </a>
            </div>
        </section>
    </div>

</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>