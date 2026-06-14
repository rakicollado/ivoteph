<?php
require_once dirname(__FILE__) . '/../auth_check.php';
require_admin();

date_default_timezone_set('Asia/Manila');

$page_title = 'Election Results';
$page_subtitle = 'Monitor submitted ballots, vote totals, turnout, and candidate rankings.';
$activePage = 'results';

$pdo = db();

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function admin_results_table_exists($pdo, $table_name)
{
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);

    if ($table_name == '') {
        return false;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table_name));
        return $stmt->fetchColumn() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}

function admin_results_columns($pdo, $table_name)
{
    $columns = array();
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);

    if ($table_name == '') {
        return $columns;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `" . $table_name . "`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if (isset($row['Field'])) {
                $columns[$row['Field']] = $row;
            }
        }
    } catch (Exception $e) {
        $columns = array();
    }

    return $columns;
}

function admin_results_pick_column($columns, $choices)
{
    for ($i = 0; $i < count($choices); $i++) {
        if (isset($columns[$choices[$i]])) {
            return $choices[$i];
        }
    }

    return '';
}

function admin_results_safe_col($column)
{
    return '`' . str_replace('`', '', $column) . '`';
}

function admin_results_datetime($value)
{
    if ($value === null || $value == '' || $value == '0000-00-00 00:00:00' || $value == '0000-00-00') {
        return '-';
    }

    $time = strtotime($value);

    if (!$time) {
        return e($value);
    }

    return date('M d, Y h:i A', $time);
}

function admin_results_percent($part, $whole)
{
    $part = (int) $part;
    $whole = (int) $whole;

    if ($whole <= 0) {
        return 0;
    }

    return round(($part / $whole) * 100, 1);
}

function admin_results_badge_class($status)
{
    $status = strtolower(trim((string) $status));

    if ($status == 'open') {
        return 'text-bg-success';
    }

    if ($status == 'closed') {
        return 'text-bg-danger';
    }

    return 'text-bg-secondary';
}

function admin_results_scope_label($candidate)
{
    $scope = isset($candidate['election_scope']) ? trim((string) $candidate['election_scope']) : '';

    if ($scope == '' || strtolower($scope) == 'national') {
        return 'National';
    }

    $parts = array();

    if (isset($candidate['city_municipality']) && trim((string) $candidate['city_municipality']) != '') {
        $parts[] = trim((string) $candidate['city_municipality']);
    }

    if (isset($candidate['province']) && trim((string) $candidate['province']) != '') {
        $parts[] = trim((string) $candidate['province']);
    }

    if (isset($candidate['region']) && trim((string) $candidate['region']) != '') {
        $parts[] = trim((string) $candidate['region']);
    }

    if (count($parts) > 0) {
        return 'Local: ' . implode(', ', $parts);
    }

    return 'Local';
}

$election_table_exists = admin_results_table_exists($pdo, 'elections');
$ballots_table_exists = admin_results_table_exists($pdo, 'ballots');
$votes_table_exists = admin_results_table_exists($pdo, 'votes');
$candidates_table_exists = admin_results_table_exists($pdo, 'candidates');
$positions_table_exists = admin_results_table_exists($pdo, 'positions');

$election_columns = $election_table_exists ? admin_results_columns($pdo, 'elections') : array();
$ballot_columns = $ballots_table_exists ? admin_results_columns($pdo, 'ballots') : array();
$vote_columns = $votes_table_exists ? admin_results_columns($pdo, 'votes') : array();
$candidate_columns = $candidates_table_exists ? admin_results_columns($pdo, 'candidates') : array();
$position_columns = $positions_table_exists ? admin_results_columns($pdo, 'positions') : array();

$election_title_col = admin_results_pick_column($election_columns, array('election_name', 'election_title', 'title', 'name'));
$election_status_col = admin_results_pick_column($election_columns, array('election_status', 'status'));
$election_start_col = admin_results_pick_column($election_columns, array('start_datetime', 'start_date', 'starts_at'));
$election_end_col = admin_results_pick_column($election_columns, array('end_datetime', 'end_date', 'ends_at'));
$election_created_col = admin_results_pick_column($election_columns, array('created_at', 'date_created'));

$active_election = false;
$active_election_id = 0;

if ($election_table_exists) {
    $select_parts = array('election_id');
    $select_parts[] = ($election_title_col != '') ? admin_results_safe_col($election_title_col) . ' AS election_name' : "'Election' AS election_name";
    $select_parts[] = ($election_status_col != '') ? admin_results_safe_col($election_status_col) . ' AS election_status' : "'Draft' AS election_status";
    $select_parts[] = ($election_start_col != '') ? admin_results_safe_col($election_start_col) . ' AS start_datetime' : 'NULL AS start_datetime';
    $select_parts[] = ($election_end_col != '') ? admin_results_safe_col($election_end_col) . ' AS end_datetime' : 'NULL AS end_datetime';

    $order_col = $election_created_col != '' ? $election_created_col : 'election_id';

    try {
        if ($election_status_col != '' && $election_start_col != '' && $election_end_col != '') {
            $sql = "
                SELECT " . implode(', ', $select_parts) . "
                FROM elections
                WHERE LOWER(TRIM(" . admin_results_safe_col($election_status_col) . ")) = 'open'
                  AND " . admin_results_safe_col($election_start_col) . " <= NOW()
                  AND " . admin_results_safe_col($election_end_col) . " >= NOW()
                ORDER BY " . admin_results_safe_col($order_col) . " DESC
                LIMIT 1
            ";

            $stmt = $pdo->query($sql);
            $active_election = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$active_election) {
            $sql = "
                SELECT " . implode(', ', $select_parts) . "
                FROM elections
                ORDER BY " . admin_results_safe_col($order_col) . " DESC
                LIMIT 1
            ";

            $stmt = $pdo->query($sql);
            $active_election = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $active_election = false;
    }
}

if ($active_election && isset($active_election['election_id'])) {
    $active_election_id = (int) $active_election['election_id'];
}

$total_registered_voters = 0;
$total_eligible_voters = 0;
$total_accounts = 0;
$total_ballots = 0;
$total_votes = 0;
$total_candidates = 0;
$total_positions = 0;
$turnout_rate = 0;

try {
    $total_registered_voters = (int) $pdo->query("SELECT COUNT(*) FROM registered_voters")->fetchColumn();
} catch (Exception $e) {
    $total_registered_voters = 0;
}

try {
    $total_eligible_voters = (int) $pdo->query("SELECT COUNT(*) FROM registered_voters WHERE profile_status = 'Complete' AND registration_status = 'Registered'")->fetchColumn();
} catch (Exception $e) {
    try {
        $total_eligible_voters = (int) $pdo->query("SELECT COUNT(*) FROM registered_voters WHERE profile_status = 'Complete'")->fetchColumn();
    } catch (Exception $e2) {
        $total_eligible_voters = 0;
    }
}

try {
    $total_accounts = (int) $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
} catch (Exception $e) {
    $total_accounts = 0;
}

try {
    if ($candidates_table_exists) {
        $total_candidates = (int) $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
    }
} catch (Exception $e) {
    $total_candidates = 0;
}

try {
    if ($positions_table_exists) {
        $total_positions = (int) $pdo->query("SELECT COUNT(*) FROM positions")->fetchColumn();
    }
} catch (Exception $e) {
    $total_positions = 0;
}

$ballot_election_col = admin_results_pick_column($ballot_columns, array('election_id'));
$ballot_submitted_col = admin_results_pick_column($ballot_columns, array('submitted_at', 'created_at', 'voted_at'));
$ballot_voter_col = admin_results_pick_column($ballot_columns, array('voter_id'));
$vote_ballot_col = admin_results_pick_column($vote_columns, array('ballot_id'));
$vote_election_col = admin_results_pick_column($vote_columns, array('election_id'));
$vote_candidate_col = admin_results_pick_column($vote_columns, array('candidate_id'));
$vote_position_col = admin_results_pick_column($vote_columns, array('position_id'));
$vote_time_col = admin_results_pick_column($vote_columns, array('voted_at', 'created_at', 'submitted_at'));

try {
    if ($ballots_table_exists) {
        if ($active_election_id > 0 && $ballot_election_col != '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ballots WHERE " . admin_results_safe_col($ballot_election_col) . " = :election_id");
            $stmt->execute(array(':election_id' => $active_election_id));
            $total_ballots = (int) $stmt->fetchColumn();
        } else {
            $total_ballots = (int) $pdo->query("SELECT COUNT(*) FROM ballots")->fetchColumn();
        }
    }
} catch (Exception $e) {
    $total_ballots = 0;
}

try {
    if ($votes_table_exists) {
        if ($active_election_id > 0 && $vote_ballot_col != '' && $ballots_table_exists && $ballot_election_col != '') {
            $sql = "
                SELECT COUNT(v.vote_id)
                FROM votes v
                INNER JOIN ballots b ON v." . admin_results_safe_col($vote_ballot_col) . " = b.ballot_id
                WHERE b." . admin_results_safe_col($ballot_election_col) . " = :election_id
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(':election_id' => $active_election_id));
            $total_votes = (int) $stmt->fetchColumn();
        } elseif ($active_election_id > 0 && $vote_election_col != '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE " . admin_results_safe_col($vote_election_col) . " = :election_id");
            $stmt->execute(array(':election_id' => $active_election_id));
            $total_votes = (int) $stmt->fetchColumn();
        } else {
            $total_votes = (int) $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
        }
    }
} catch (Exception $e) {
    $total_votes = 0;
}

if ($total_eligible_voters > 0) {
    $turnout_rate = admin_results_percent($total_ballots, $total_eligible_voters);
}

$position_order_col = admin_results_pick_column($position_columns, array('display_order', 'position_id'));
$position_max_col = admin_results_pick_column($position_columns, array('max_votes'));
$candidate_scope_col = admin_results_pick_column($candidate_columns, array('election_scope'));
$candidate_region_col = admin_results_pick_column($candidate_columns, array('region'));
$candidate_province_col = admin_results_pick_column($candidate_columns, array('province'));
$candidate_city_col = admin_results_pick_column($candidate_columns, array('city_municipality'));

$position_results = array();
$candidate_rows = array();

if ($candidates_table_exists && $positions_table_exists) {
    try {
        $candidate_extra_select = array();
        $candidate_extra_group = array();

        if ($candidate_scope_col != '') {
            $candidate_extra_select[] = 'c.' . admin_results_safe_col($candidate_scope_col) . ' AS election_scope';
            $candidate_extra_group[] = 'c.' . admin_results_safe_col($candidate_scope_col);
        } else {
            $candidate_extra_select[] = "'National' AS election_scope";
        }

        if ($candidate_region_col != '') {
            $candidate_extra_select[] = 'c.' . admin_results_safe_col($candidate_region_col) . ' AS region';
            $candidate_extra_group[] = 'c.' . admin_results_safe_col($candidate_region_col);
        } else {
            $candidate_extra_select[] = "'' AS region";
        }

        if ($candidate_province_col != '') {
            $candidate_extra_select[] = 'c.' . admin_results_safe_col($candidate_province_col) . ' AS province';
            $candidate_extra_group[] = 'c.' . admin_results_safe_col($candidate_province_col);
        } else {
            $candidate_extra_select[] = "'' AS province";
        }

        if ($candidate_city_col != '') {
            $candidate_extra_select[] = 'c.' . admin_results_safe_col($candidate_city_col) . ' AS city_municipality';
            $candidate_extra_group[] = 'c.' . admin_results_safe_col($candidate_city_col);
        } else {
            $candidate_extra_select[] = "'' AS city_municipality";
        }

        $vote_join = "LEFT JOIN votes v ON v." . admin_results_safe_col($vote_candidate_col) . " = c.candidate_id";

        if ($active_election_id > 0 && $vote_ballot_col != '' && $ballots_table_exists && $ballot_election_col != '') {
            $vote_join = "
                LEFT JOIN votes v ON v." . admin_results_safe_col($vote_candidate_col) . " = c.candidate_id
                LEFT JOIN ballots b ON v." . admin_results_safe_col($vote_ballot_col) . " = b.ballot_id
                    AND b." . admin_results_safe_col($ballot_election_col) . " = " . (int) $active_election_id . "
            ";
            $vote_count_expression = "COUNT(b.ballot_id)";
        } elseif ($active_election_id > 0 && $vote_election_col != '') {
            $vote_join = "LEFT JOIN votes v ON v." . admin_results_safe_col($vote_candidate_col) . " = c.candidate_id AND v." . admin_results_safe_col($vote_election_col) . " = " . (int) $active_election_id;
            $vote_count_expression = "COUNT(v.vote_id)";
        } else {
            $vote_count_expression = "COUNT(v.vote_id)";
        }

        $max_vote_select = ($position_max_col != '') ? 'p.' . admin_results_safe_col($position_max_col) . ' AS max_votes' : '1 AS max_votes';
        $order_expression = ($position_order_col != '') ? 'p.' . admin_results_safe_col($position_order_col) : 'p.position_id';

        $group_parts = array(
            'p.position_id',
            'p.position_name',
            'c.candidate_id',
            'c.full_name',
            'c.political_party'
        );

        if ($position_max_col != '') {
            $group_parts[] = 'p.' . admin_results_safe_col($position_max_col);
        }

        for ($i = 0; $i < count($candidate_extra_group); $i++) {
            $group_parts[] = $candidate_extra_group[$i];
        }

        $sql = "
            SELECT
                p.position_id,
                p.position_name,
                " . $max_vote_select . ",
                c.candidate_id,
                c.full_name,
                c.political_party,
                " . implode(', ', $candidate_extra_select) . ",
                " . $vote_count_expression . " AS vote_total
            FROM candidates c
            LEFT JOIN positions p ON c.position_id = p.position_id
            " . $vote_join . "
            GROUP BY " . implode(', ', $group_parts) . "
            ORDER BY " . $order_expression . " ASC, vote_total DESC, c.full_name ASC
        ";

        $stmt = $pdo->query($sql);
        $candidate_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($candidate_rows as $row) {
            $position_id = isset($row['position_id']) ? (int) $row['position_id'] : 0;
            $position_name = isset($row['position_name']) && trim((string) $row['position_name']) != '' ? $row['position_name'] : 'Unassigned Position';

            if (!isset($position_results[$position_id])) {
                $position_results[$position_id] = array(
                    'position_id' => $position_id,
                    'position_name' => $position_name,
                    'max_votes' => isset($row['max_votes']) ? (int) $row['max_votes'] : 1,
                    'total_votes' => 0,
                    'candidates' => array()
                );
            }

            $vote_total = isset($row['vote_total']) ? (int) $row['vote_total'] : 0;
            $position_results[$position_id]['total_votes'] += $vote_total;
            $row['vote_total'] = $vote_total;
            $position_results[$position_id]['candidates'][] = $row;
        }
    } catch (Exception $e) {
        $position_results = array();
        $candidate_rows = array();
    }
}

$latest_ballots = array();

if ($ballots_table_exists) {
    try {
        $submitted_select = ($ballot_submitted_col != '') ? 'b.' . admin_results_safe_col($ballot_submitted_col) . ' AS submitted_at' : 'NULL AS submitted_at';
        $voter_join_col = ($ballot_voter_col != '') ? 'b.' . admin_results_safe_col($ballot_voter_col) : 'NULL';
        $where_sql = '';

        if ($active_election_id > 0 && $ballot_election_col != '') {
            $where_sql = 'WHERE b.' . admin_results_safe_col($ballot_election_col) . ' = ' . (int) $active_election_id;
        }

        $order_sql = ($ballot_submitted_col != '') ? 'b.' . admin_results_safe_col($ballot_submitted_col) . ' DESC' : 'b.ballot_id DESC';

        $sql = "
            SELECT
                b.ballot_id,
                " . $voter_join_col . " AS voter_id,
                " . $submitted_select . "
            FROM ballots b
            " . $where_sql . "
            ORDER BY " . $order_sql . "
            LIMIT 10
        ";

        $stmt = $pdo->query($sql);
        $latest_ballots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $latest_ballots = array();
    }
}


$position_chart_data = array();

foreach ($position_results as $position_id => $position) {
    $chart_labels = array();
    $chart_votes = array();

    foreach ($position['candidates'] as $candidate) {
        $chart_labels[] = isset($candidate['full_name']) ? $candidate['full_name'] : 'Candidate';
        $chart_votes[] = isset($candidate['vote_total']) ? (int) $candidate['vote_total'] : 0;
    }

    $position_chart_data[] = array(
        'position_id' => (int) $position_id,
        'position_name' => isset($position['position_name']) ? $position['position_name'] : 'Position',
        'total_votes' => isset($position['total_votes']) ? (int) $position['total_votes'] : 0,
        'labels' => $chart_labels,
        'votes' => $chart_votes
    );
}

$flashes = array();
if (function_exists('consume_flash')) {
    $flashes = consume_flash();
}

require_once dirname(__FILE__) . '/../includes/header.php';
require_once dirname(__FILE__) . '/../includes/sidebar.php';
?>

<style>
    .ivote-results-chart-section {
        padding: 22px;
    }

    .ivote-results-chart-section > .ivote-card-header {
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--ivote-border-soft);
    }

    .ivote-position-chart-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        align-items: stretch;
    }

    .ivote-position-chart-card {
        padding: 0;
        overflow: hidden;
        border-radius: 22px;
        background: #ffffff;
        border: 1px solid var(--ivote-border);
        box-shadow: var(--ivote-shadow-soft);
    }

    .ivote-position-chart-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        min-height: auto;
        padding: 18px 20px 12px;
        margin: 0;
        border-bottom: 1px solid var(--ivote-border-soft);
    }

    .ivote-position-chart-title span {
        display: block;
        font-size: 11px;
        font-weight: 950;
        color: var(--ivote-blue);
        text-transform: uppercase;
        letter-spacing: 0.07em;
        margin-bottom: 5px;
    }

    .ivote-position-chart-title h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 950;
        color: var(--ivote-text);
        line-height: 1.2;
    }

    .ivote-position-chart-count {
        flex: 0 0 auto;
        background: var(--ivote-blue-light);
        color: var(--ivote-blue);
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 950;
        white-space: nowrap;
    }

    .ivote-position-chart-body {
        height: 310px;
        min-height: 310px;
        padding: 16px 18px 20px;
        position: relative;
    }

    @media (max-width: 1200px) {
        .ivote-position-chart-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .ivote-results-chart-section {
            padding: 16px;
        }

        .ivote-position-chart-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 16px;
        }

        .ivote-position-chart-body {
            height: 280px;
            min-height: 280px;
            padding: 12px;
        }
    }
</style>


<div class="ivote-management-page ivote-results-page">

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

    <div class="ivote-results-hero">
        <div>
            <div class="ivote-results-eyebrow">Official Admin Results</div>
            <h2><?php echo $active_election ? e($active_election['election_name']) : 'Election Results'; ?></h2>
            <p>
                Results are computed directly from submitted ballots and vote records in the MySQL database.
            </p>
        </div>

        <div class="ivote-results-hero-meta">
            <?php if ($active_election) { ?>
                <span class="badge <?php echo e(admin_results_badge_class($active_election['election_status'])); ?>">
                    <?php echo e(ucfirst($active_election['election_status'])); ?>
                </span>
                <small>
                    <?php echo admin_results_datetime($active_election['start_datetime']); ?>
                    -
                    <?php echo admin_results_datetime($active_election['end_datetime']); ?>
                </small>
            <?php } else { ?>
                <span class="badge text-bg-secondary">No election found</span>
            <?php } ?>
        </div>
    </div>

    <div class="ivote-results-stat-grid">
        <div class="ivote-card ivote-result-stat">
            <span>Submitted Ballots</span>
            <strong><?php echo number_format($total_ballots); ?></strong>
            <small>One ballot per voter per election</small>
        </div>

        <div class="ivote-card ivote-result-stat">
            <span>Total Vote Records</span>
            <strong><?php echo number_format($total_votes); ?></strong>
            <small>Includes multi-select senator votes</small>
        </div>

        <div class="ivote-card ivote-result-stat">
            <span>Turnout Rate</span>
            <strong><?php echo e($turnout_rate); ?>%</strong>
            <small><?php echo number_format($total_eligible_voters); ?> eligible voters</small>
        </div>

        <div class="ivote-card ivote-result-stat">
            <span>Candidates</span>
            <strong><?php echo number_format($total_candidates); ?></strong>
            <small><?php echo number_format($total_positions); ?> positions</small>
        </div>
    </div>


    <section class="ivote-card ivote-data-card ivote-results-chart-section">
        <div class="ivote-card-header">
            <div>
                <h3 class="ivote-section-title">
                    <i class="bi bi-bar-chart-fill text-primary me-1"></i>
                    Position-Based Vote Results
                </h3>
                <p class="text-muted mb-0">Each position is shown in its own chart so candidate rankings are easier to compare and present.</p>
            </div>
        </div>

        <?php if (count($position_chart_data) > 0) { ?>
            <div class="ivote-position-chart-grid">
                <?php foreach ($position_chart_data as $chart_position) { ?>
                    <section class="ivote-position-chart-card">
                        <div class="ivote-position-chart-header">
                            <div class="ivote-position-chart-title">
                                <span>Position result</span>
                                <h3><?php echo e($chart_position['position_name']); ?></h3>
                            </div>

                            <div class="ivote-position-chart-count">
                                <?php echo number_format((int) $chart_position['total_votes']); ?> vote(s)
                            </div>
                        </div>

                        <div class="ivote-position-chart-body">
                            <canvas id="positionChart<?php echo (int) $chart_position['position_id']; ?>"></canvas>
                        </div>
                    </section>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle me-1"></i>
                No vote records are available for visualization yet.
            </div>
        <?php } ?>
    </section>

    <div class="ivote-results-bottom-grid">
        <section class="ivote-card ivote-data-card">
            <div class="ivote-card-header">
                <div>
                    <h3 class="ivote-section-title">
                        <i class="bi bi-graph-up-arrow text-primary me-1"></i>
                        Results by Position
                    </h3>
                    <p class="text-muted mb-0">Candidate rankings based on submitted votes.</p>
                </div>
            </div>

            <?php if (count($position_results) > 0) { ?>
                <div class="ivote-results-position-grid">
                    <?php foreach ($position_results as $position) { ?>
                        <?php
                            $position_total = (int) $position['total_votes'];
                            $leading_votes = 0;

                            if (count($position['candidates']) > 0) {
                                $leading_votes = (int) $position['candidates'][0]['vote_total'];
                            }
                        ?>

                        <div class="ivote-position-result-card">
                            <div class="ivote-position-result-header">
                                <div>
                                    <span>Position</span>
                                    <h3><?php echo e($position['position_name']); ?></h3>
                                </div>
                                <strong><?php echo number_format($position_total); ?> votes</strong>
                            </div>

                            <div class="ivote-position-candidate-list">
                                <?php foreach ($position['candidates'] as $candidate) { ?>
                                    <?php
                                        $candidate_votes = (int) $candidate['vote_total'];
                                        $candidate_percent = admin_results_percent($candidate_votes, $position_total);
                                        $is_leading = ($position_total > 0 && $candidate_votes == $leading_votes);
                                    ?>

                                    <div class="ivote-position-candidate-row">
                                        <div class="ivote-position-candidate-main">
                                            <div>
                                                <strong><?php echo e($candidate['full_name']); ?></strong>
                                                <small>
                                                    <?php echo e($candidate['political_party'] != '' ? $candidate['political_party'] : 'Independent'); ?>
                                                    | <?php echo e(admin_results_scope_label($candidate)); ?>
                                                </small>
                                            </div>

                                            <div class="ivote-position-candidate-votes">
                                                <?php if ($is_leading) { ?>
                                                    <span class="badge text-bg-success">Leading</span>
                                                <?php } ?>
                                                <strong><?php echo number_format($candidate_votes); ?></strong>
                                                <small><?php echo e($candidate_percent); ?>%</small>
                                            </div>
                                        </div>

                                        <div class="ivote-result-progress">
                                            <div style="width: <?php echo e($candidate_percent); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    No result records are available yet.
                </div>
            <?php } ?>
        </section>

        <section class="ivote-card ivote-data-card">
            <div class="ivote-card-header">
                <div>
                    <h3 class="ivote-section-title">
                        <i class="bi bi-clock-history text-primary me-1"></i>
                        Latest Submitted Ballots
                    </h3>
                    <p class="text-muted mb-0">Recent voter IDs that submitted a ballot. Names are hidden for privacy.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table ivote-clean-table">
                    <thead>
                        <tr>
                            <th>Ballot ID</th>
                            <th>Voter ID</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($latest_ballots) > 0) { ?>
                            <?php foreach ($latest_ballots as $ballot) { ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?php echo e($ballot['ballot_id']); ?></td>
                                    <td class="fw-bold"><?php echo e($ballot['voter_id']); ?></td>
                                    <td><?php echo admin_results_datetime($ballot['submitted_at']); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    No submitted ballots yet.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        var positionCharts = <?php echo json_encode($position_chart_data); ?>;

        var rootStyles = window.getComputedStyle(document.documentElement);
        var blue = rootStyles.getPropertyValue('--ivote-blue').trim() || '#0647b8';
        var blueDark = rootStyles.getPropertyValue('--ivote-blue-dark').trim() || '#033587';
        var green = rootStyles.getPropertyValue('--ivote-green').trim() || '#16a34a';
        var purple = rootStyles.getPropertyValue('--ivote-purple').trim() || '#7c3aed';
        var yellow = rootStyles.getPropertyValue('--ivote-yellow').trim() || '#facc15';
        var muted = rootStyles.getPropertyValue('--ivote-muted').trim() || '#667085';
        var gridColor = 'rgba(102, 112, 133, 0.18)';
        var palette = [blue, green, purple, yellow, blueDark, '#0ea5e9', '#f97316', '#ef4444', '#14b8a6', '#6366f1', '#84cc16', '#a855f7'];

        function makeColors(count) {
            var colors = [];

            for (var i = 0; i < count; i++) {
                colors.push(palette[i % palette.length]);
            }

            return colors;
        }

        function emptyMessage(canvasId, message) {
            var canvas = document.getElementById(canvasId);

            if (!canvas) {
                return;
            }

            var parent = canvas.parentNode;
            var empty = document.createElement('div');
            empty.className = 'alert alert-info mb-0';
            empty.innerHTML = '<i class="bi bi-info-circle me-1"></i>' + message;
            parent.innerHTML = '';
            parent.appendChild(empty);
        }

        function createPositionChart(chartData) {
            var canvasId = 'positionChart' + chartData.position_id;
            var canvas = document.getElementById(canvasId);

            if (!canvas) {
                return;
            }

            if (!chartData.labels || chartData.labels.length < 1) {
                emptyMessage(canvasId, 'No candidates are available for this position.');
                return;
            }

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Votes',
                        data: chartData.votes,
                        backgroundColor: makeColors(chartData.labels.length),
                        borderWidth: 0,
                        borderRadius: 10
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return 'Votes: ' + context.parsed.x;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: gridColor },
                            ticks: {
                                color: muted,
                                precision: 0,
                                stepSize: 1
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                color: muted,
                                font: { weight: 'bold' }
                            }
                        }
                    }
                }
            });
        }

        for (var i = 0; i < positionCharts.length; i++) {
            createPositionChart(positionCharts[i]);
        }
    })();
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
