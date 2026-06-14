<?php require_once __DIR__ . '/auth_check.php'; ?>

<?php
/* Use Philippine time for election schedule comparisons. */
date_default_timezone_set('Asia/Manila');

if (!function_exists('ivoteph_h')) {
    function ivoteph_h($value) {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ivoteph_date_display')) {
    function ivoteph_date_display($value) {
        if ($value === null || $value == '' || $value == '0000-00-00') {
            return 'N/A';
        }

        $timestamp = strtotime($value);

        if (!$timestamp) {
            return $value;
        }

        return date('F j, Y', $timestamp);
    }
}

function ivoteph_table_exists($conn, $table_name) {
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);

    if ($table_name == '') {
        return false;
    }

    $database_name = '';
    $database_result = mysqli_query($conn, "SELECT DATABASE()");

    if ($database_result) {
        $database_row = mysqli_fetch_row($database_result);

        if ($database_row && isset($database_row[0])) {
            $database_name = $database_row[0];
        }

        mysqli_free_result($database_result);
    }

    if ($database_name == '') {
        $database_name = 'ivoteph';
    }

    $database_sql = mysqli_real_escape_string($conn, $database_name);
    $table_sql = mysqli_real_escape_string($conn, $table_name);

    $sql = "
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '" . $database_sql . "'
          AND TABLE_NAME = '" . $table_sql . "'
        LIMIT 1
    ";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return false;
    }

    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);

    return ($row && (int) $row[0] > 0);
}

function ivoteph_column_exists($conn, $table_name, $column_name) {
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);
    $column_name = preg_replace('/[^A-Za-z0-9_]/', '', $column_name);

    if ($table_name == '' || $column_name == '') {
        return false;
    }

    $database_name = '';
    $database_result = mysqli_query($conn, "SELECT DATABASE()");

    if ($database_result) {
        $database_row = mysqli_fetch_row($database_result);

        if ($database_row && isset($database_row[0])) {
            $database_name = $database_row[0];
        }

        mysqli_free_result($database_result);
    }

    if ($database_name == '') {
        $database_name = 'ivoteph';
    }

    $database_sql = mysqli_real_escape_string($conn, $database_name);
    $table_sql = mysqli_real_escape_string($conn, $table_name);
    $column_sql = mysqli_real_escape_string($conn, $column_name);

    $sql = "
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '" . $database_sql . "'
          AND TABLE_NAME = '" . $table_sql . "'
          AND COLUMN_NAME = '" . $column_sql . "'
        LIMIT 1
    ";

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return false;
    }

    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);

    return ($row && (int) $row[0] > 0);
}

function ivoteph_fetch_ballot_id($conn, $election_id, $voter_id) {
    if (!ivoteph_table_exists($conn, 'ballots')) {
        return 0;
    }

    if (!ivoteph_column_exists($conn, 'ballots', 'ballot_id')) {
        return 0;
    }

    if (!ivoteph_column_exists($conn, 'ballots', 'election_id') || !ivoteph_column_exists($conn, 'ballots', 'voter_id')) {
        return 0;
    }

    $sql = "
        SELECT ballot_id
        FROM ballots
        WHERE election_id = ?
          AND voter_id = ?
        ORDER BY ballot_id DESC
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'is', $election_id, $voter_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $ballot_id);

    $found_ballot_id = 0;

    if (mysqli_stmt_fetch($stmt)) {
        $found_ballot_id = (int) $ballot_id;
    }

    mysqli_stmt_close($stmt);

    return $found_ballot_id;
}

function ivoteph_create_ballot_record($conn, $election_id, $voter_id, &$ballot_error_message) {
    $ballot_error_message = '';

    if (!ivoteph_column_exists($conn, 'votes', 'ballot_id')) {
        return 0;
    }

    if (!ivoteph_table_exists($conn, 'ballots')) {
        $ballot_error_message = 'The votes table requires ballot_id, but the ballots table is missing.';
        return 0;
    }

    $existing_ballot_id = ivoteph_fetch_ballot_id($conn, $election_id, $voter_id);

    if ($existing_ballot_id > 0) {
        return $existing_ballot_id;
    }

    $columns = array();
    $values = array();

    if (ivoteph_column_exists($conn, 'ballots', 'election_id')) {
        $columns[] = '`election_id`';
        $values[] = (int) $election_id;
    }

    if (ivoteph_column_exists($conn, 'ballots', 'voter_id')) {
        $columns[] = '`voter_id`';
        $values[] = "'" . mysqli_real_escape_string($conn, $voter_id) . "'";
    }

    if (ivoteph_column_exists($conn, 'ballots', 'ballot_status')) {
        $columns[] = '`ballot_status`';
        $values[] = "'Submitted'";
    } elseif (ivoteph_column_exists($conn, 'ballots', 'status')) {
        $columns[] = '`status`';
        $values[] = "'Submitted'";
    }

    if (ivoteph_column_exists($conn, 'ballots', 'submitted_at')) {
        $columns[] = '`submitted_at`';
        $values[] = 'NOW()';
    }

    if (ivoteph_column_exists($conn, 'ballots', 'voted_at')) {
        $columns[] = '`voted_at`';
        $values[] = 'NOW()';
    }

    if (ivoteph_column_exists($conn, 'ballots', 'created_at')) {
        $columns[] = '`created_at`';
        $values[] = 'NOW()';
    }

    if (ivoteph_column_exists($conn, 'ballots', 'updated_at')) {
        $columns[] = '`updated_at`';
        $values[] = 'NOW()';
    }

    if (count($columns) == 0) {
        $ballot_error_message = 'Unable to create ballot record because no usable ballots columns were found.';
        return 0;
    }

    $sql = "INSERT INTO ballots (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";

    if (!mysqli_query($conn, $sql)) {
        $existing_ballot_id = ivoteph_fetch_ballot_id($conn, $election_id, $voter_id);

        if ($existing_ballot_id > 0) {
            return $existing_ballot_id;
        }

        $ballot_error_message = mysqli_error($conn);
        return 0;
    }

    $new_ballot_id = (int) mysqli_insert_id($conn);

    if ($new_ballot_id <= 0) {
        $new_ballot_id = ivoteph_fetch_ballot_id($conn, $election_id, $voter_id);
    }

    if ($new_ballot_id <= 0) {
        $ballot_error_message = 'Ballot record was created, but its ballot_id could not be detected.';
    }

    return $new_ballot_id;
}

function ivoteph_candidate_photo($photo) {
    $photo = trim((string) $photo);

    if ($photo == '') {
        return '';
    }

    if (strpos($photo, 'http://') === 0 || strpos($photo, 'https://') === 0) {
        return $photo;
    }

    if (file_exists(__DIR__ . '/../admin/assets/uploads/candidates/' . $photo)) {
        return '../admin/assets/uploads/candidates/' . rawurlencode($photo);
    }

    if (file_exists(__DIR__ . '/../admin/assets/img/' . $photo)) {
        return '../admin/assets/img/' . rawurlencode($photo);
    }

    return '';
}

function ivoteph_initials_from_name($name) {
    $name = trim((string) $name);

    if ($name == '') {
        return 'C';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';

    if (isset($parts[0]) && $parts[0] != '') {
        $initials .= substr($parts[0], 0, 1);
    }

    if (count($parts) > 1 && $parts[count($parts) - 1] != '') {
        $initials .= substr($parts[count($parts) - 1], 0, 1);
    }

    if ($initials == '') {
        $initials = 'C';
    }

    return strtoupper($initials);
}

function ivoteph_fetch_active_election($conn) {
    if (!ivoteph_table_exists($conn, 'elections')) {
        return false;
    }

    /*
        Current iVotePH election table columns:
        election_id, election_name, start_datetime, end_datetime, election_status, created_at

        This query intentionally uses those exact names first, because the older
        startvoting.php versions checked old names like status/start_date/end_date.
    */
    if (
        ivoteph_column_exists($conn, 'elections', 'election_status') &&
        ivoteph_column_exists($conn, 'elections', 'start_datetime') &&
        ivoteph_column_exists($conn, 'elections', 'end_datetime')
    ) {
        $sql_open = "
            SELECT *
            FROM elections
            WHERE LOWER(TRIM(election_status)) = 'open'
              AND start_datetime <= NOW()
              AND end_datetime >= NOW()
            ORDER BY election_id DESC
            LIMIT 1
        ";

        $result_open = mysqli_query($conn, $sql_open);

        if ($result_open) {
            $row_open = mysqli_fetch_assoc($result_open);
            mysqli_free_result($result_open);

            if ($row_open) {
                return $row_open;
            }
        }

        $sql_any_open = "
            SELECT *
            FROM elections
            WHERE LOWER(TRIM(election_status)) = 'open'
            ORDER BY election_id DESC
            LIMIT 1
        ";

        $result_any_open = mysqli_query($conn, $sql_any_open);

        if ($result_any_open) {
            $row_any_open = mysqli_fetch_assoc($result_any_open);
            mysqli_free_result($result_any_open);

            if ($row_any_open) {
                return $row_any_open;
            }
        }
    }

    $status_column = '';

    if (ivoteph_column_exists($conn, 'elections', 'election_status')) {
        $status_column = 'election_status';
    } elseif (ivoteph_column_exists($conn, 'elections', 'status')) {
        $status_column = 'status';
    }

    if ($status_column != '') {
        $sql_open = "
            SELECT *
            FROM elections
            WHERE LOWER(TRIM(`" . $status_column . "`)) IN ('open', 'active')
            ORDER BY election_id DESC
            LIMIT 1
        ";

        $result_open = mysqli_query($conn, $sql_open);

        if ($result_open) {
            $row_open = mysqli_fetch_assoc($result_open);
            mysqli_free_result($result_open);

            if ($row_open) {
                return $row_open;
            }
        }
    }

    $sql_latest = "SELECT * FROM elections ORDER BY election_id DESC LIMIT 1";
    $result_latest = mysqli_query($conn, $sql_latest);

    if ($result_latest) {
        $row_latest = mysqli_fetch_assoc($result_latest);
        mysqli_free_result($result_latest);

        if ($row_latest) {
            return $row_latest;
        }
    }

    return false;
}

function ivoteph_election_value($election, $keys) {
    if (!$election) {
        return '';
    }

    foreach ($keys as $key) {
        if (isset($election[$key]) && trim((string) $election[$key]) != '') {
            return trim((string) $election[$key]);
        }
    }

    return '';
}

function ivoteph_is_election_open($election) {
    if (!$election) {
        return false;
    }

    $status = strtolower(ivoteph_election_value($election, array('election_status', 'status')));

    if ($status != 'open' && $status != 'active') {
        return false;
    }

    $now = time();
    $start_value = ivoteph_election_value($election, array('start_datetime', 'start_date', 'start_time'));
    $end_value = ivoteph_election_value($election, array('end_datetime', 'end_date', 'end_time'));

    if ($start_value != '' && $start_value != '0000-00-00 00:00:00') {
        $start_time = strtotime($start_value);

        if ($start_time && $now < $start_time) {
            return false;
        }
    }

    if ($end_value != '' && $end_value != '0000-00-00 00:00:00') {
        $end_time = strtotime($end_value);

        if ($end_time && $now > $end_time) {
            return false;
        }
    }

    return true;
}

function ivoteph_election_title($election) {
    if (!$election) {
        return 'No election configured';
    }

    $title = ivoteph_election_value($election, array('election_name', 'election_title', 'title', 'name'));

    if ($title != '') {
        return $title;
    }

    return 'Election';
}

function ivoteph_election_status($election) {
    if (!$election) {
        return 'Not configured';
    }

    $status = ivoteph_election_value($election, array('election_status', 'status'));

    if ($status != '') {
        return ucfirst($status);
    }

    return 'Scheduled';
}

function ivoteph_election_datetime($election, $key) {
    if (!$election) {
        return 'N/A';
    }

    $value = '';

    if ($key == 'start_date') {
        $value = ivoteph_election_value($election, array('start_datetime', 'start_date', 'start_time'));
    } elseif ($key == 'end_date') {
        $value = ivoteph_election_value($election, array('end_datetime', 'end_date', 'end_time'));
    } elseif (isset($election[$key])) {
        $value = trim((string) $election[$key]);
    }

    if ($value == '' || $value == '0000-00-00 00:00:00') {
        return 'N/A';
    }

    $time = strtotime($value);

    if (!$time) {
        return $value;
    }

    return date('F j, Y g:i A', $time);
}

/* Logged-in voter profile */
$profile_voter_id = isset($auth_voter_id) ? $auth_voter_id : $_SESSION['voter_id'];
$profile_first_name = isset($auth_first_name) ? $auth_first_name : '';
$profile_middle_name = '';
$profile_last_name = isset($auth_last_name) ? $auth_last_name : '';
$profile_birth_date = isset($auth_birth_date) ? $auth_birth_date : '';
$profile_sex = '';
$profile_mobile_number = '';
$profile_email = isset($auth_email) ? $auth_email : '';
$profile_status = 'Complete';
$profile_registration_status = isset($auth_registration_status) ? $auth_registration_status : 'Registered';
$profile_account_status = 'Active';
$profile_region = '';
$profile_province = '';
$profile_city_municipality = '';
$profile_barangay = '';
$profile_specific_address = '';
$profile_country = 'Philippines';

$sql_profile = "
    SELECT
        rv.voter_id,
        rv.first_name,
        rv.middle_name,
        rv.last_name,
        rv.birth_date,
        rv.sex,
        rv.mobile_number,
        rv.email,
        rv.profile_status,
        rv.registration_status,
        a.account_status,
        va.region,
        va.province,
        va.city_municipality,
        va.barangay,
        va.specific_address,
        va.country
    FROM registered_voters rv
    LEFT JOIN accounts a ON rv.voter_id = a.voter_id
    LEFT JOIN voter_addresses va ON rv.voter_id = va.voter_id
    WHERE rv.voter_id = ?
    LIMIT 1
";

$stmt_profile = mysqli_prepare($conn, $sql_profile);

if ($stmt_profile) {
    mysqli_stmt_bind_param($stmt_profile, 's', $profile_voter_id);
    mysqli_stmt_execute($stmt_profile);

    mysqli_stmt_bind_result(
        $stmt_profile,
        $db_profile_voter_id,
        $db_profile_first_name,
        $db_profile_middle_name,
        $db_profile_last_name,
        $db_profile_birth_date,
        $db_profile_sex,
        $db_profile_mobile_number,
        $db_profile_email,
        $db_profile_status,
        $db_profile_registration_status,
        $db_profile_account_status,
        $db_profile_region,
        $db_profile_province,
        $db_profile_city_municipality,
        $db_profile_barangay,
        $db_profile_specific_address,
        $db_profile_country
    );

    if (mysqli_stmt_fetch($stmt_profile)) {
        $profile_voter_id = $db_profile_voter_id;
        $profile_first_name = $db_profile_first_name;
        $profile_middle_name = $db_profile_middle_name;
        $profile_last_name = $db_profile_last_name;
        $profile_birth_date = $db_profile_birth_date;
        $profile_sex = $db_profile_sex;
        $profile_mobile_number = $db_profile_mobile_number;
        $profile_email = $db_profile_email;
        $profile_status = $db_profile_status;
        $profile_registration_status = $db_profile_registration_status;
        $profile_account_status = $db_profile_account_status;
        $profile_region = $db_profile_region;
        $profile_province = $db_profile_province;
        $profile_city_municipality = $db_profile_city_municipality;
        $profile_barangay = $db_profile_barangay;
        $profile_specific_address = $db_profile_specific_address;
        $profile_country = $db_profile_country;
    }

    mysqli_stmt_close($stmt_profile);
}

$profile_full_name = trim($profile_first_name . ' ' . $profile_middle_name . ' ' . $profile_last_name);

if ($profile_full_name == '') {
    $profile_full_name = $profile_voter_id;
}

$profile_initials = strtoupper(substr($profile_first_name, 0, 1) . substr($profile_last_name, 0, 1));

if ($profile_initials == '') {
    $profile_initials = 'V';
}

$profile_birth_date_display = ivoteph_date_display($profile_birth_date);

$address_parts = array();

if ($profile_specific_address != '') {
    $address_parts[] = $profile_specific_address;
}

if ($profile_barangay != '') {
    $address_parts[] = $profile_barangay;
}

if ($profile_city_municipality != '') {
    $address_parts[] = $profile_city_municipality;
}

if ($profile_province != '') {
    $address_parts[] = $profile_province;
}

if ($profile_region != '') {
    $address_parts[] = $profile_region;
}

$profile_complete_address = '';

if (count($address_parts) > 0) {
    $profile_complete_address = implode(', ', $address_parts);
}


function ivoteph_candidate_jurisdiction_label($candidate) {
    $scope = '';

    if (isset($candidate['election_scope'])) {
        $scope = trim((string) $candidate['election_scope']);
    }

    if ($scope == '' || $scope == 'National') {
        return 'National candidate';
    }

    $region = isset($candidate['candidate_region']) ? trim((string) $candidate['candidate_region']) : '';
    $province = isset($candidate['candidate_province']) ? trim((string) $candidate['candidate_province']) : '';
    $city = isset($candidate['candidate_city_municipality']) ? trim((string) $candidate['candidate_city_municipality']) : '';

    $parts = array();

    if ($city != '') {
        $parts[] = $city;
    }

    if ($province != '') {
        $parts[] = $province;
    }

    if ($region != '') {
        $parts[] = $region;
    }

    if (count($parts) > 0) {
        return 'Local: ' . implode(', ', $parts);
    }

    return 'Local candidate';
}

/* Election and candidate data */
$errors = array();
$success_message = '';
$active_election = ivoteph_fetch_active_election($conn);
$voting_is_open = ivoteph_is_election_open($active_election);
$active_election_id = 0;

if ($active_election && isset($active_election['election_id'])) {
    $active_election_id = (int) $active_election['election_id'];
}

$candidate_groups = array();
$total_candidates = 0;
$total_positions = 0;
$candidate_scope_enabled = false;
$candidate_region_enabled = false;
$voter_province_sql = mysqli_real_escape_string($conn, trim((string) $profile_province));
$voter_city_sql = mysqli_real_escape_string($conn, trim((string) $profile_city_municipality));

if (ivoteph_table_exists($conn, 'candidates')) {
    $candidate_scope_enabled = (
        ivoteph_column_exists($conn, 'candidates', 'election_scope') &&
        ivoteph_column_exists($conn, 'candidates', 'province') &&
        ivoteph_column_exists($conn, 'candidates', 'city_municipality')
    );

    $candidate_region_enabled = ivoteph_column_exists($conn, 'candidates', 'region');
}

if (ivoteph_table_exists($conn, 'candidates') && ivoteph_table_exists($conn, 'positions')) {
    $order_sql = "p.position_id ASC, p.position_name ASC, c.full_name ASC";

    if (ivoteph_column_exists($conn, 'positions', 'display_order')) {
        $order_sql = "p.display_order ASC, p.position_id ASC, p.position_name ASC, c.full_name ASC";
    }

    $scope_select_sql = "
            'National' AS election_scope,
            NULL AS candidate_region,
            NULL AS candidate_province,
            NULL AS candidate_city_municipality,";

    $scope_where_sql = '';

    if ($candidate_scope_enabled) {
        $scope_select_sql = "
            c.election_scope,
            " . ($candidate_region_enabled ? "c.region" : "NULL") . " AS candidate_region,
            c.province AS candidate_province,
            c.city_municipality AS candidate_city_municipality,";

        $scope_where_sql = "
        WHERE
            (
                c.election_scope IS NULL
                OR c.election_scope = ''
                OR c.election_scope = 'National'
                OR (
                    c.election_scope = 'Local'
                    AND LOWER(TRIM(p.position_name)) = 'governor'
                    AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                )
                OR (
                    c.election_scope = 'Local'
                    AND LOWER(TRIM(p.position_name)) = 'mayor'
                    AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                    AND LOWER(TRIM(c.city_municipality)) = LOWER(TRIM('" . $voter_city_sql . "'))
                )
                OR (
                    c.election_scope = 'Local'
                    AND LOWER(TRIM(p.position_name)) NOT IN ('governor', 'mayor')
                    AND (
                        (
                            c.city_municipality IS NOT NULL
                            AND TRIM(c.city_municipality) <> ''
                            AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                            AND LOWER(TRIM(c.city_municipality)) = LOWER(TRIM('" . $voter_city_sql . "'))
                        )
                        OR
                        (
                            (c.city_municipality IS NULL OR TRIM(c.city_municipality) = '')
                            AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                        )
                    )
                )
                OR (
                    c.election_scope = 'Province'
                    AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                )
                OR (
                    (c.election_scope = 'City/Municipality' OR c.election_scope = 'City' OR c.election_scope = 'Municipality')
                    AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                    AND LOWER(TRIM(c.city_municipality)) = LOWER(TRIM('" . $voter_city_sql . "'))
                )
            )";
    }

    $sql_candidates = "
        SELECT
            c.candidate_id,
            c.full_name,
            c.political_party,
            c.photo,
            c.platform,
            c.position_id," . $scope_select_sql . "
            p.position_name
        FROM candidates c
        LEFT JOIN positions p ON c.position_id = p.position_id
        " . $scope_where_sql . "
        ORDER BY " . $order_sql;

    $result_candidates = mysqli_query($conn, $sql_candidates);

    if ($result_candidates) {
        while ($row = mysqli_fetch_assoc($result_candidates)) {
            $position_id = (int) $row['position_id'];

            if ($position_id <= 0) {
                $position_id = 0;
            }

            $position_name = $row['position_name'];

            if ($position_name == '') {
                $position_name = 'Unassigned';
            }

            if (!isset($row['election_scope']) || trim((string) $row['election_scope']) == '') {
                $row['election_scope'] = 'National';
            }

            if (!isset($row['candidate_region'])) {
                $row['candidate_region'] = '';
            }

            if (!isset($row['candidate_province'])) {
                $row['candidate_province'] = '';
            }

            if (!isset($row['candidate_city_municipality'])) {
                $row['candidate_city_municipality'] = '';
            }

            if (!isset($candidate_groups[$position_id])) {
                $candidate_groups[$position_id] = array(
                    'position_id' => $position_id,
                    'position_name' => $position_name,
                    'candidates' => array()
                );
            }

            $candidate_groups[$position_id]['candidates'][] = $row;
            $total_candidates++;
        }

        mysqli_free_result($result_candidates);
    }
}

$total_positions = count($candidate_groups);
$already_voted = false;
$existing_votes = array();

if ($active_election_id > 0 && ivoteph_table_exists($conn, 'votes')) {
    $sql_existing = "
        SELECT
            v.position_id,
            v.candidate_id,
            c.full_name,
            p.position_name
        FROM votes v
        LEFT JOIN candidates c ON v.candidate_id = c.candidate_id
        LEFT JOIN positions p ON v.position_id = p.position_id
        WHERE v.election_id = ?
          AND v.voter_id = ?
        ORDER BY p.position_id ASC
    ";

    $stmt_existing = mysqli_prepare($conn, $sql_existing);

    if ($stmt_existing) {
        mysqli_stmt_bind_param($stmt_existing, 'is', $active_election_id, $profile_voter_id);
        mysqli_stmt_execute($stmt_existing);
        mysqli_stmt_bind_result($stmt_existing, $ex_position_id, $ex_candidate_id, $ex_full_name, $ex_position_name);

        while (mysqli_stmt_fetch($stmt_existing)) {
            $existing_votes[] = array(
                'position_id' => $ex_position_id,
                'candidate_id' => $ex_candidate_id,
                'full_name' => $ex_full_name,
                'position_name' => $ex_position_name
            );
        }

        mysqli_stmt_close($stmt_existing);
    }

    if (count($existing_votes) > 0) {
        $already_voted = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ballot'])) {
    if (!ivoteph_table_exists($conn, 'votes')) {
        $errors[] = 'The votes table is missing. Please import or create the votes table first.';
    } elseif (!$active_election || $active_election_id <= 0) {
        $errors[] = 'No election is configured yet. Please ask the admin to create an election schedule.';
    } elseif (!$voting_is_open) {
        $errors[] = 'Voting is not open yet. Please follow the official election schedule.';
    } elseif ($already_voted) {
        $errors[] = 'You have already submitted your ballot for this election.';
    } elseif ($total_positions < 1 || $total_candidates < 1) {
        $errors[] = 'No candidates are available for voting yet.';
    } elseif (!isset($_POST['candidate']) || !is_array($_POST['candidate'])) {
        $errors[] = 'Please select one candidate for every position.';
    } else {
        $selections = $_POST['candidate'];
        $validated_votes = array();

        foreach ($candidate_groups as $position_id => $group) {
            $position_name_clean = strtolower(trim((string) $group['position_name']));
            $is_senator_position = ($position_name_clean == 'senator' || $position_name_clean == 'senators');
            $selected_candidate_ids = array();

            if (!isset($selections[$position_id])) {
                if ($is_senator_position) {
                    $errors[] = 'Please select at least one candidate for Senator. You may select up to 12 senators.';
                } else {
                    $errors[] = 'Please select one candidate for ' . $group['position_name'] . '.';
                }
            } else {
                if (is_array($selections[$position_id])) {
                    for ($selection_index = 0; $selection_index < count($selections[$position_id]); $selection_index++) {
                        $candidate_id_from_post = (int) $selections[$position_id][$selection_index];

                        if ($candidate_id_from_post > 0 && !in_array($candidate_id_from_post, $selected_candidate_ids)) {
                            $selected_candidate_ids[] = $candidate_id_from_post;
                        }
                    }
                } else {
                    $candidate_id_from_post = (int) $selections[$position_id];

                    if ($candidate_id_from_post > 0) {
                        $selected_candidate_ids[] = $candidate_id_from_post;
                    }
                }

                if ($is_senator_position) {
                    if (count($selected_candidate_ids) < 1) {
                        $errors[] = 'Please select at least one candidate for Senator. You may select up to 12 senators.';
                    } elseif (count($selected_candidate_ids) > 12) {
                        $errors[] = 'You can select up to 12 senators only.';
                    }
                } else {
                    if (count($selected_candidate_ids) < 1) {
                        $errors[] = 'Please select one candidate for ' . $group['position_name'] . '.';
                    } elseif (count($selected_candidate_ids) > 1) {
                        $errors[] = 'Please select only one candidate for ' . $group['position_name'] . '.';
                    }
                }

                if (count($errors) == 0) {
                    for ($selected_index = 0; $selected_index < count($selected_candidate_ids); $selected_index++) {
                        $candidate_id = (int) $selected_candidate_ids[$selected_index];
                        $candidate_id_sql = (int) $candidate_id;
                        $position_id_sql = (int) $position_id;

                        $validate_scope_sql = '';

                        if ($candidate_scope_enabled) {
                            $validate_scope_sql = "
                              AND
                              (
                                  c.election_scope IS NULL
                                  OR c.election_scope = ''
                                  OR c.election_scope = 'National'
                                  OR (
                                      c.election_scope = 'Local'
                                      AND LOWER(TRIM(p.position_name)) = 'governor'
                                      AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                                  )
                                  OR (
                                      c.election_scope = 'Local'
                                      AND LOWER(TRIM(p.position_name)) = 'mayor'
                                      AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                                      AND LOWER(TRIM(c.city_municipality)) = LOWER(TRIM('" . $voter_city_sql . "'))
                                  )
                                  OR (
                                      c.election_scope = 'Local'
                                      AND LOWER(TRIM(p.position_name)) NOT IN ('governor', 'mayor')
                                      AND (
                                          (
                                              c.city_municipality IS NOT NULL
                                              AND TRIM(c.city_municipality) <> ''
                                              AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                                              AND LOWER(TRIM(c.city_municipality)) = LOWER(TRIM('" . $voter_city_sql . "'))
                                          )
                                          OR
                                          (
                                              (c.city_municipality IS NULL OR TRIM(c.city_municipality) = '')
                                              AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                                          )
                                      )
                                  )
                                  OR (
                                      c.election_scope = 'Province'
                                      AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                                  )
                                  OR (
                                      (c.election_scope = 'City/Municipality' OR c.election_scope = 'City' OR c.election_scope = 'Municipality')
                                      AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                                      AND LOWER(TRIM(c.city_municipality)) = LOWER(TRIM('" . $voter_city_sql . "'))
                                  )
                              )";
                        }

                        $sql_validate = "
                            SELECT c.candidate_id, c.position_id
                            FROM candidates c
                            LEFT JOIN positions p ON c.position_id = p.position_id
                            WHERE c.candidate_id = " . $candidate_id_sql . "
                              AND c.position_id = " . $position_id_sql . "
                              " . $validate_scope_sql . "
                            LIMIT 1
                        ";

                        $result_validate = mysqli_query($conn, $sql_validate);

                        if ($result_validate) {
                            if (mysqli_num_rows($result_validate) == 1) {
                                $validated_votes[] = array(
                                    'position_id' => $position_id,
                                    'candidate_id' => $candidate_id
                                );
                            } else {
                                $errors[] = 'Invalid candidate selected for ' . $group['position_name'] . '.';
                            }

                            mysqli_free_result($result_validate);
                        } else {
                            $errors[] = 'Unable to validate selected candidates.';
                        }
                    }
                }
            }
        }

        if (count($errors) == 0 && count($validated_votes) > 0) {
            mysqli_query($conn, "START TRANSACTION");
            $saved = true;
            $vote_error_message = '';

            $vote_time_column = '';

            if (ivoteph_column_exists($conn, 'votes', 'voted_at')) {
                $vote_time_column = 'voted_at';
            } elseif (ivoteph_column_exists($conn, 'votes', 'created_at')) {
                $vote_time_column = 'created_at';
            } elseif (ivoteph_column_exists($conn, 'votes', 'submitted_at')) {
                $vote_time_column = 'submitted_at';
            }

            $vote_has_ballot_id = ivoteph_column_exists($conn, 'votes', 'ballot_id');
            $ballot_id_for_vote = 0;

            if ($vote_has_ballot_id) {
                $ballot_error_message = '';
                $ballot_id_for_vote = ivoteph_create_ballot_record($conn, $active_election_id, $profile_voter_id, $ballot_error_message);

                if ($ballot_id_for_vote <= 0) {
                    $saved = false;
                    $vote_error_message = $ballot_error_message;
                }
            }

            for ($i = 0; $saved && $i < count($validated_votes); $i++) {
                $position_id = (int) $validated_votes[$i]['position_id'];
                $candidate_id = (int) $validated_votes[$i]['candidate_id'];

                if ($vote_has_ballot_id) {
                    if ($vote_time_column != '') {
                        $sql_insert = "
                            INSERT INTO votes
                                (ballot_id, election_id, voter_id, candidate_id, position_id, `" . $vote_time_column . "`)
                            VALUES
                                (?, ?, ?, ?, ?, NOW())
                        ";
                    } else {
                        $sql_insert = "
                            INSERT INTO votes
                                (ballot_id, election_id, voter_id, candidate_id, position_id)
                            VALUES
                                (?, ?, ?, ?, ?)
                        ";
                    }
                } else {
                    if ($vote_time_column != '') {
                        $sql_insert = "
                            INSERT INTO votes
                                (election_id, voter_id, candidate_id, position_id, `" . $vote_time_column . "`)
                            VALUES
                                (?, ?, ?, ?, NOW())
                        ";
                    } else {
                        $sql_insert = "
                            INSERT INTO votes
                                (election_id, voter_id, candidate_id, position_id)
                            VALUES
                                (?, ?, ?, ?)
                        ";
                    }
                }

                $stmt_insert = mysqli_prepare($conn, $sql_insert);

                if (!$stmt_insert) {
                    $saved = false;
                    $vote_error_message = mysqli_error($conn);
                    break;
                }

                if ($vote_has_ballot_id) {
                    mysqli_stmt_bind_param($stmt_insert, 'iisii', $ballot_id_for_vote, $active_election_id, $profile_voter_id, $candidate_id, $position_id);
                } else {
                    mysqli_stmt_bind_param($stmt_insert, 'isii', $active_election_id, $profile_voter_id, $candidate_id, $position_id);
                }

                if (!mysqli_stmt_execute($stmt_insert)) {
                    $saved = false;
                    $vote_error_message = mysqli_stmt_error($stmt_insert);
                    mysqli_stmt_close($stmt_insert);
                    break;
                }

                mysqli_stmt_close($stmt_insert);
            }

            if ($saved) {
                mysqli_query($conn, "COMMIT");
                header('Location: myballot.php?success=voted');
                exit();
            } else {
                mysqli_query($conn, "ROLLBACK");

                if ($vote_error_message != '') {
                    $errors[] = 'Unable to submit your ballot. Database message: ' . $vote_error_message;
                } else {
                    $errors[] = 'Unable to submit your ballot. You may have already voted or the vote records could not be saved.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Start Voting - iVotePH</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        :root {
            --userBlue: #0646a8;
            --userBlueDark: #0b3f91;
            --userBlueSoft: #eaf2ff;
            --userInk: #172033;
            --userMuted: #667085;
            --userLine: #dce5f2;
            --userShadow: 0 14px 34px rgba(11, 36, 71, 0.10);
        }

        * { box-sizing: border-box; }

        html, body {
            width: 100%;
            min-height: 100%;
            margin: 0;
            overflow-x: hidden;
        }

        body.userPage {
            color: var(--userInk);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            background:
                linear-gradient(180deg, rgba(244, 248, 255, 0.94), rgba(247, 249, 252, 0.98)),
                url("flag-bg.png") center top / cover fixed no-repeat;
        }

        .videoBg {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.10;
            z-index: -2;
            pointer-events: none;
        }

        .appBackdrop {
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background:
                radial-gradient(circle at top right, rgba(216, 32, 42, 0.08), transparent 30%),
                radial-gradient(circle at top left, rgba(6, 70, 168, 0.14), transparent 32%);
        }

        .userTopbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 12px 18px;
            background: rgba(255, 255, 255, 0.94);
            border-bottom: 1px solid rgba(210, 219, 235, 0.95);
            box-shadow: 0 10px 28px rgba(16, 24, 40, 0.08);
            backdrop-filter: blur(18px);
        }

        .userTopbarInner {
            width: 100%;
            max-width: 1480px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: auto minmax(540px, 1fr) minmax(260px, 430px) auto;
            align-items: center;
            gap: 8px;
        }

        .brandLink {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 5px 8px;
            border-radius: 14px;
            background: transparent;
            border: none;
            text-decoration: none;
        }

        .brandLogo {
            display: block;
            width: 66px !important;
            max-width: 66px !important;
            height: auto !important;
            max-height: 34px !important;
            object-fit: contain !important;
        }

        .userNavBar {
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
        }

        .userNavInner { width: 100%; overflow: hidden; }

        .userNavList {
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center;
            justify-content: flex-start;
            gap: 6px;
            overflow: hidden;
            min-width: 0;
            width: 100%;
        }

        .userNavList li { list-style: none !important; flex: 0 0 auto; }

        .userNavList a {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 8px 11px;
            border-radius: 999px;
            background: #f4f6fb;
            color: #3f4350;
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(16, 24, 40, 0.04);
            transition: 0.2s ease;
        }

        .userNavList a:hover {
            background: #e9f1ff;
            color: var(--userBlue);
            transform: translateY(-1px);
        }

        .userNavList a.active {
            background: #0b5ed7;
            color: #ffffff;
            box-shadow: 0 10px 22px rgba(6, 70, 168, 0.22);
        }

        .topbarSearch { width: 100%; min-width: 0; }

        .topbarSearch .input-group {
            height: 42px;
            border-radius: 999px;
            overflow: hidden;
            background: #f3f4fb;
        }

        .topbarSearch .input-group-text {
            border: none;
            background: #f3f4fb;
            padding-left: 15px;
            padding-right: 7px;
            color: var(--userMuted);
        }

        .searchInput {
            height: 42px !important;
            border: none !important;
            background: #f3f4fb !important;
            box-shadow: none !important;
            font-size: 12px;
            padding-left: 4px;
        }

        .userChip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 6px 10px 6px 6px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid var(--userLine);
            color: var(--userInk);
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(16, 24, 40, 0.08);
            cursor: pointer;
            white-space: nowrap;
        }

        .userAvatarCircle {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border-radius: 50%;
            background: #0b5ed7;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 900;
        }

        .userName { font-size: 11px; font-weight: 900; color: var(--userInk); line-height: 1.1; }

        .verifiedBadge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #0b5ed7;
            font-size: 9px;
            font-weight: 800;
            line-height: 1.1;
        }

        .userMain {
            width: 100%;
            max-width: 1480px;
            margin: 0 auto;
            padding: 24px 22px 40px;
        }

        .userCard {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--userLine);
            border-radius: 24px;
            box-shadow: var(--userShadow);
        }

        .voteHero {
            margin-bottom: 18px;
            padding: 34px 36px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            color: #ffffff;
            background:
                radial-gradient(circle at top right, rgba(247, 201, 72, 0.25), transparent 30%),
                linear-gradient(135deg, #0646a8 0%, #0b3f91 100%);
            overflow: hidden;
        }

        .heroEyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .heroTitle {
            font-size: clamp(2.1rem, 4vw, 4rem);
            line-height: 1;
            letter-spacing: -0.05em;
            font-weight: 950;
            margin-bottom: 14px;
        }

        .heroSubtitle {
            max-width: 820px;
            color: rgba(255, 255, 255, 0.88);
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 0;
        }

        .voteStatusPill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #ffffff;
            color: var(--userBlue);
            font-size: 12px;
            font-weight: 950;
            white-space: nowrap;
        }

        .summaryGrid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .summaryCard { padding: 18px; }
        .summaryCard span {
            display: block;
            color: var(--userMuted);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }
        .summaryCard strong { display: block; color: var(--userBlue); font-size: 24px; line-height: 1.1; font-weight: 950; }

        .sectionCard { padding: 24px; margin-bottom: 18px; }

        .sectionHeader {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .sectionHeader h2,
        .sectionHeader h3 { margin: 0; font-weight: 950; color: var(--userInk); letter-spacing: -0.03em; }

        .positionBlock {
            border: 1px solid var(--userLine);
            border-radius: 22px;
            background: #ffffff;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .positionHeader {
            padding: 18px;
            background: #f7f9fd;
            border-bottom: 1px solid var(--userLine);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .positionHeader h3 { margin: 0; font-size: 20px; font-weight: 950; }
        .positionHeader span { font-size: 12px; color: var(--userMuted); font-weight: 900; }

        .candidateVoteGrid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .candidateOption { position: relative; display: block; margin: 0; height: 100%; }
        .candidateOption input { position: absolute; opacity: 0; pointer-events: none; }

        .candidateVoteCard {
            height: 100%;
            border: 1px solid var(--userLine);
            border-radius: 18px;
            padding: 16px;
            cursor: pointer;
            background: #ffffff;
            transition: 0.2s ease;
        }

        .candidateOption input:checked + .candidateVoteCard {
            border-color: #0b5ed7;
            background: #eaf2ff;
            box-shadow: 0 14px 30px rgba(6, 70, 168, 0.16);
        }

        .candidateVoteCard:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(16, 24, 40, 0.08); }

        .candidateVoteTop { display: flex; align-items: center; gap: 13px; margin-bottom: 13px; }

        .candidatePhoto {
            width: 56px;
            height: 56px;
            min-width: 56px;
            border-radius: 17px;
            background: var(--userBlueSoft);
            color: var(--userBlue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            font-weight: 950;
            overflow: hidden;
        }

        .candidatePhoto img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .candidateVoteName { margin: 0 0 4px; font-size: 16px; font-weight: 950; color: var(--userInk); }
        .candidateVoteParty { margin: 0; color: var(--userMuted); font-size: 12px; font-weight: 800; }
        .candidateVoteScope { display: inline-flex; margin-top: 6px; padding: 5px 9px; border-radius: 999px; background: var(--userBlueSoft); color: var(--userBlue); font-size: 11px; font-weight: 900; }
        .candidateVotePlatform { margin: 0; color: var(--userMuted); font-size: 13px; line-height: 1.5; }

        .voteSubmitBar {
            position: sticky;
            bottom: 0;
            z-index: 20;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border: 1px solid var(--userLine);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 -8px 24px rgba(16, 24, 40, 0.08);
            backdrop-filter: blur(12px);
        }

        .voteSubmitBar strong { display: block; font-weight: 950; }
        .voteSubmitBar span { display: block; font-size: 12px; color: var(--userMuted); }
        .btnVoteSubmit { min-height: 48px; border-radius: 999px; font-weight: 950; padding-left: 24px; padding-right: 24px; }

        .footer { padding: 18px 22px; text-align: center; color: var(--userMuted); font-size: 13px; }

        #profileModal .modal-dialog { max-width: min(1180px, calc(100vw - 24px)); margin: 12px auto; }
        #profileModal .profileModalContent { max-height: calc(100vh - 24px); display: flex; flex-direction: column; }
        .profileModalContent { border: none; border-radius: 26px; overflow: hidden; box-shadow: 0 24px 70px rgba(16, 24, 40, 0.22); }
        .profileModalHeader {
            flex: 0 0 auto;
            background:
                radial-gradient(circle at top right, rgba(247, 201, 72, 0.28), transparent 34%),
                linear-gradient(135deg, #0646a8 0%, #0b3f91 100%);
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .profileModalAvatar {
            width: 74px;
            height: 74px;
            border-radius: 50%;
            background: #ffffff;
            color: var(--userBlue);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 950;
            box-shadow: 0 12px 24px rgba(16, 24, 40, 0.16);
            margin-bottom: 10px;
        }
        .profileModalHeader h5 { margin: 0; font-size: 22px; font-weight: 950; }
        .profileModalHeader p { margin: 6px 0 0; font-size: 13px; color: rgba(255, 255, 255, 0.86); }
        .profileModalBody { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 22px; background: #ffffff; }
        .profileReadOnlyNote { margin-bottom: 18px; padding: 14px; border-radius: 16px; background: #eaf2ff; border: 1px solid #cfe0ff; color: var(--userBlue); font-size: 13px; font-weight: 800; line-height: 1.5; }
        .profileSectionTitle { display: flex; align-items: center; gap: 8px; margin: 18px 0 12px; color: var(--userInk); font-size: 14px; font-weight: 950; }
        .profileSectionTitle:first-of-type { margin-top: 0; }
        .profileSectionTitle i { color: var(--userBlue); }
        .profileFullGrid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .profileFullGrid.threeCols { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .profileFullItem { min-width: 0; background: #f7f9fd; border: 1px solid #e1e8f3; border-radius: 16px; padding: 13px; }
        .profileFullItem.profileFullWide { grid-column: 1 / -1; }
        .profileFullItem span { display: block; font-size: 10.5px; font-weight: 900; color: var(--userMuted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px; }
        .profileFullItem strong { display: block; font-size: 14px; font-weight: 900; color: var(--userInk); line-height: 1.35; overflow-wrap: anywhere; }
        .profileModalActions { flex: 0 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 14px 22px; background: #ffffff; border-top: 1px solid #e1e8f3; box-shadow: 0 -10px 24px rgba(16, 24, 40, 0.06); }
        .profileModalActions .btn { min-height: 46px; border-radius: 14px; font-weight: 900; font-size: 13px; }
        .requestModalBody { padding: 22px; }
        .requestNotice { background: #eaf2ff; border: 1px solid #cfe0ff; color: var(--userBlue); border-radius: 16px; padding: 14px; font-size: 13px; font-weight: 800; line-height: 1.5; margin-bottom: 16px; }
        .requestModalBody .form-label { font-size: 12px; font-weight: 900; color: var(--userInk); margin-bottom: 7px; }
        .requestModalBody .form-select, .requestModalBody .form-control { border-radius: 14px; border: 1px solid var(--userLine); font-size: 13px; box-shadow: none; }

        @media (max-width: 1180px) {
            .userTopbarInner { grid-template-columns: auto 1fr auto; grid-template-rows: auto auto auto; }
            .brandLink { grid-column: 1; grid-row: 1; }
            .userChip { grid-column: 3; grid-row: 1; }
            .userNavBar { grid-column: 1 / -1; grid-row: 2; overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
            .topbarSearch { grid-column: 1 / -1; grid-row: 3; }
            .userNavInner { overflow-x: auto; scrollbar-width: none; }
            .userNavInner::-webkit-scrollbar { display: none; }
            .userNavList { min-width: max-content; width: max-content; }
            .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .candidateVoteGrid { grid-template-columns: 1fr; }
            .profileFullGrid.threeCols { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 768px) {
            .userTopbar { padding: 10px 12px; }
            .brandLogo { width: 60px !important; max-width: 60px !important; }
            .userChip { padding: 6px; }
            .userMeta, .userChip .fa-chevron-down { display: none !important; }
            .userMain { padding: 14px 12px 30px; }
            .voteHero { padding: 26px 22px; flex-direction: column; }
            .summaryGrid { grid-template-columns: 1fr; }
            .voteSubmitBar { position: static; flex-direction: column; align-items: stretch; }
            .profileFullGrid, .profileFullGrid.threeCols, .profileModalActions { grid-template-columns: 1fr; }
            #profileModal .modal-dialog { max-width: calc(100vw - 16px); margin: 8px auto; }
            #profileModal .profileModalContent { max-height: calc(100vh - 16px); border-radius: 20px; }
            .profileModalHeader { padding: 20px 16px; }
            .profileModalAvatar { width: 62px; height: 62px; font-size: 20px; }
            .profileModalBody, .requestModalBody { padding: 16px; }
            .profileModalActions { padding: 12px 16px; }
        }
    </style>
</head>

<body class="userPage">
    <video class="videoBg" autoplay muted loop playsinline>
        <source src="flag.mp4" type="video/mp4">
    </video>

    <div class="appBackdrop"></div>

    <header class="userTopbar">
        <div class="userTopbarInner">
            <a href="index.php" class="brandLink" aria-label="iVotePH Home">
                <img src="FINALS 2.png" class="brandLogo" alt="iVotePH">
            </a>

            <nav class="userNavBar" aria-label="User navigation">
                <div class="userNavInner">
                    <ul class="userNavList">
                        <li><a href="index.php"><i class="fa-solid fa-landmark"></i>Home</a></li>
                        <li><a href="about.php"><i class="fa-solid fa-circle-info"></i>About</a></li>
                        <li><a href="browsecandi.php"><i class="fa-solid fa-users"></i>Candidates</a></li>
                        <li><a href="startvoting.php" class="active"><i class="fa-solid fa-check-to-slot"></i>Voting</a></li>
                        <li><a href="myballot.php"><i class="fa-solid fa-file-signature"></i>My Ballot</a></li>
                        <li><a href="results.php"><i class="fa-solid fa-chart-simple"></i>Results</a></li>
                        <li><a href="help.php"><i class="fa-solid fa-circle-question"></i>Help</a></li>
                    </ul>
                </div>
            </nav>

            <div class="topbarSearch">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="search" class="form-control searchInput" placeholder="Search candidates, voting info, or results">
                </div>
            </div>

            <button type="button" class="userChip border-0" data-bs-toggle="modal" data-bs-target="#profileModal">
                <span class="userAvatarCircle"><?php echo ivoteph_h($profile_initials); ?></span>
                <span class="userMeta">
                    <span class="userName d-block"><?php echo ivoteph_h($profile_full_name); ?></span>
                    <span class="verifiedBadge"><i class="fa-solid fa-circle-check"></i>Verified Voter</span>
                </span>
                <i class="fa-solid fa-chevron-down text-muted small d-none d-md-inline"></i>
            </button>
        </div>
    </header>

    <main class="userMain userPageMotion">
        <section class="voteHero userCard">
            <div>
                <div class="heroEyebrow">
                    <i class="fa-solid fa-check-to-slot"></i>
                    Voting Center
                </div>

                <h1 class="heroTitle">Start Voting</h1>

                <p class="heroSubtitle">
                    Select one candidate per position. Your ballot will be saved in the MySQL vote records and locked after submission.
                </p>
            </div>

            <span class="voteStatusPill">
                <?php if ($already_voted) { ?>
                    <i class="fa-solid fa-circle-check"></i> Submitted
                <?php } elseif ($voting_is_open) { ?>
                    <i class="fa-solid fa-unlock"></i> Voting Open
                <?php } else { ?>
                    <i class="fa-solid fa-lock"></i> Voting Closed
                <?php } ?>
            </span>
        </section>

        <section class="summaryGrid">
            <div class="summaryCard userCard">
                <span>Election</span>
                <strong><?php echo ivoteph_h(ivoteph_election_title($active_election)); ?></strong>
            </div>

            <div class="summaryCard userCard">
                <span>Status</span>
                <strong><?php echo ivoteph_h(ivoteph_election_status($active_election)); ?></strong>
            </div>

            <div class="summaryCard userCard">
                <span>Positions</span>
                <strong><?php echo ivoteph_h($total_positions); ?></strong>
            </div>

            <div class="summaryCard userCard">
                <span>Candidates</span>
                <strong><?php echo ivoteph_h($total_candidates); ?></strong>
            </div>
        </section>

        <section class="sectionCard userCard">
            <div class="sectionHeader">
                <div>
                    <h2>Official Ballot</h2>
                    <p class="text-muted mb-0">
                        Voting opens from <?php echo ivoteph_h(ivoteph_election_datetime($active_election, 'start_date')); ?> to <?php echo ivoteph_h(ivoteph_election_datetime($active_election, 'end_date')); ?>.
                    </p>
                </div>
            </div>

            <?php if (isset($_GET['error']) && $_GET['error'] == 'already_voted') { ?>
                <div class="alert alert-warning rounded-4">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    You have already submitted your ballot.
                </div>
            <?php } ?>

            <?php if (count($errors) > 0) { ?>
                <?php foreach ($errors as $error) { ?>
                    <div class="alert alert-danger rounded-4">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <?php echo ivoteph_h($error); ?>
                    </div>
                <?php } ?>
            <?php } ?>

            <?php if (!$active_election || $active_election_id <= 0) { ?>
                <div class="alert alert-info rounded-4 mb-0">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    No election schedule is configured yet. Please ask the admin to create an election in the admin panel.
                </div>
            <?php } elseif (!$voting_is_open) { ?>
                <div class="alert alert-warning rounded-4 mb-0">
                    <i class="fa-solid fa-lock me-2"></i>
                    Voting is currently closed. Please follow the official election schedule controlled by the admin.
                </div>
            <?php } elseif ($already_voted) { ?>
                <div class="alert alert-success rounded-4">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    Your ballot has already been submitted for this election.
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>Selected Candidate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existing_votes as $vote) { ?>
                                <tr>
                                    <td><?php echo ivoteph_h($vote['position_name']); ?></td>
                                    <td class="fw-bold"><?php echo ivoteph_h($vote['full_name']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <a href="myballot.php" class="btn btn-primary rounded-pill px-4 py-3 fw-bold">
                    <i class="fa-solid fa-file-signature me-2"></i>
                    View My Ballot
                </a>
            <?php } elseif ($total_positions < 1 || $total_candidates < 1) { ?>
                <div class="alert alert-info rounded-4 mb-0">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    No candidates are available yet. Please ask the admin to add candidates first.
                </div>
            <?php } else { ?>
                <form method="POST" action="startvoting.php" id="voteForm">
                    <?php foreach ($candidate_groups as $position_id => $group) { ?>
                        <?php
                        $position_name_clean = strtolower(trim((string) $group['position_name']));
                        $is_senator_position = ($position_name_clean == 'senator' || $position_name_clean == 'senators');
                        $input_type = $is_senator_position ? 'checkbox' : 'radio';
                        $input_name = $is_senator_position ? 'candidate[' . $position_id . '][]' : 'candidate[' . $position_id . ']';
                        $instruction_text = $is_senator_position ? 'Select up to 12 candidates' : 'Select one candidate';
                        ?>

                        <div class="positionBlock" <?php echo $is_senator_position ? 'data-max-select="12" data-position-name="Senator"' : ''; ?>>
                            <div class="positionHeader">
                                <div>
                                    <h3><?php echo ivoteph_h($group['position_name']); ?></h3>
                                    <span><?php echo ivoteph_h($instruction_text); ?></span>
                                </div>

                                <span><?php echo count($group['candidates']); ?> candidate(s)</span>
                            </div>

                            <div class="candidateVoteGrid">
                                <?php foreach ($group['candidates'] as $candidate) { ?>
                                    <?php
                                    $candidate_photo = ivoteph_candidate_photo($candidate['photo']);
                                    $candidate_initials = ivoteph_initials_from_name($candidate['full_name']);
                                    ?>

                                    <label class="candidateOption">
                                        <input type="<?php echo $input_type; ?>" name="<?php echo ivoteph_h($input_name); ?>" value="<?php echo ivoteph_h($candidate['candidate_id']); ?>" <?php echo $is_senator_position ? '' : 'required'; ?>>

                                        <div class="candidateVoteCard">
                                            <div class="candidateVoteTop">
                                                <div class="candidatePhoto">
                                                    <?php if ($candidate_photo != '') { ?>
                                                        <img src="<?php echo ivoteph_h($candidate_photo); ?>" alt="<?php echo ivoteph_h($candidate['full_name']); ?>">
                                                    <?php } else { ?>
                                                        <?php echo ivoteph_h($candidate_initials); ?>
                                                    <?php } ?>
                                                </div>

                                                <div>
                                                    <h4 class="candidateVoteName"><?php echo ivoteph_h($candidate['full_name']); ?></h4>
                                                    <p class="candidateVoteParty"><?php echo ivoteph_h($candidate['political_party']); ?></p>
                                                    <span class="candidateVoteScope"><?php echo ivoteph_h(ivoteph_candidate_jurisdiction_label($candidate)); ?></span>
                                                </div>
                                            </div>

                                            <p class="candidateVotePlatform">
                                                <?php echo ivoteph_h($candidate['platform']); ?>
                                            </p>
                                        </div>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="voteSubmitBar">
                        <div>
                            <strong>Submit Final Ballot</strong>
                            <span>Review your choices carefully. You cannot edit your ballot after submission.</span>
                        </div>

                        <button type="submit" name="submit_ballot" value="1" class="btn btn-primary btnVoteSubmit" onclick="return confirm('Submit your final ballot? You cannot change your vote after this.');">
                            <i class="fa-solid fa-paper-plane me-2"></i>
                            Submit Ballot
                        </button>
                    </div>
                </form>
            <?php } ?>
        </section>
    </main>

    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar"><?php echo ivoteph_h($profile_initials); ?></div>
                    <h5 id="profileModalLabel"><?php echo ivoteph_h($profile_full_name); ?></h5>
                    <p><i class="fa-solid fa-circle-check me-1"></i>Verified Registered Voter</p>
                </div>

                <div class="profileModalBody">
                    <div class="profileReadOnlyNote">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        This profile is read-only. Registered voters can review their submitted information anytime,
                        but corrections must be requested through the admin.
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-id-card"></i>Account Information</div>

                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem"><span>Voter ID</span><strong><?php echo ivoteph_h($profile_voter_id); ?></strong></div>
                        <div class="profileFullItem"><span>Registration Status</span><strong><?php echo ivoteph_h($profile_registration_status); ?></strong></div>
                        <div class="profileFullItem"><span>Profile Status</span><strong><?php echo ivoteph_h($profile_status); ?></strong></div>
                        <div class="profileFullItem"><span>Ballot Status</span><strong><?php echo $already_voted ? 'Submitted' : 'Not Submitted'; ?></strong></div>
                        <div class="profileFullItem"><span>Account Type</span><strong>Voter</strong></div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-user"></i>Personal Information</div>

                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem"><span>First Name</span><strong><?php echo ivoteph_h($profile_first_name); ?></strong></div>
                        <div class="profileFullItem"><span>Middle Name</span><strong><?php echo ivoteph_h($profile_middle_name); ?></strong></div>
                        <div class="profileFullItem"><span>Last Name</span><strong><?php echo ivoteph_h($profile_last_name); ?></strong></div>
                        <div class="profileFullItem"><span>Birth Date</span><strong><?php echo ivoteph_h($profile_birth_date_display); ?></strong></div>
                        <div class="profileFullItem"><span>Sex</span><strong><?php echo ivoteph_h($profile_sex); ?></strong></div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-address-book"></i>Contact Information</div>

                    <div class="profileFullGrid">
                        <div class="profileFullItem"><span>Email Address</span><strong><?php echo ivoteph_h($profile_email); ?></strong></div>
                        <div class="profileFullItem"><span>Mobile Number</span><strong><?php echo ivoteph_h($profile_mobile_number); ?></strong></div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-location-dot"></i>Registered Address</div>

                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem"><span>Region</span><strong><?php echo ivoteph_h($profile_region); ?></strong></div>
                        <div class="profileFullItem"><span>Province</span><strong><?php echo ivoteph_h($profile_province); ?></strong></div>
                        <div class="profileFullItem"><span>City / Municipality</span><strong><?php echo ivoteph_h($profile_city_municipality); ?></strong></div>
                        <div class="profileFullItem"><span>Barangay</span><strong><?php echo ivoteph_h($profile_barangay); ?></strong></div>
                        <div class="profileFullItem"><span>Country</span><strong><?php echo ivoteph_h($profile_country); ?></strong></div>
                        <div class="profileFullItem profileFullWide"><span>Complete Address</span><strong><?php echo ivoteph_h($profile_complete_address); ?></strong></div>
                    </div>
                </div>

                <div class="profileModalActions">
                    <button type="button" class="btn btn-primary" onclick="openProfileRequestModal()">
                        <i class="fa-solid fa-pen-to-square me-1"></i>Request Change
                    </button>

                    <button type="button" class="btn btn-danger" onclick="logoutUser()">
                        <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profileRequestModal" tabindex="-1" aria-labelledby="profileRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar"><i class="fa-solid fa-pen-to-square"></i></div>
                    <h5 id="profileRequestModalLabel">Request Profile Change</h5>
                    <p>Registered voter information cannot be edited directly.</p>
                </div>

                <div class="requestModalBody">
                    <div class="requestNotice">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        Submit a request to the admin if your registered name or personal details need correction.
                    </div>

                    <form id="profileChangeRequestForm" onsubmit="submitProfileChangeRequest(event)">
                        <div class="mb-3">
                            <label for="requestField" class="form-label">Information to change</label>
                            <select class="form-select" id="requestField" required>
                                <option value="">Select information</option>
                                <option value="Full Name">Full Name</option>
                                <option value="Email Address">Email Address</option>
                                <option value="Mobile Number">Mobile Number</option>
                                <option value="Birth Date">Birth Date</option>
                                <option value="Sex">Sex</option>
                                <option value="Registered Address">Registered Address</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="requestMessage" class="form-label">Reason / Correct Information</label>
                            <textarea class="form-control" id="requestMessage" rows="4" required placeholder="Example: My registered last name is misspelled."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-3 rounded-4 fw-bold">
                                <i class="fa-solid fa-paper-plane me-2"></i>Submit Request
                            </button>

                            <button type="button" class="btn btn-light py-3 rounded-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div>© 2026 iVotePH. Secure. Accessible. Transparent.</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        var senatorBlocks = document.querySelectorAll('[data-max-select]');

        for (var senatorBlockIndex = 0; senatorBlockIndex < senatorBlocks.length; senatorBlockIndex++) {
            senatorBlocks[senatorBlockIndex].addEventListener('change', function (event) {
                var maxSelect = parseInt(this.getAttribute('data-max-select'), 10);
                var checkedBoxes = this.querySelectorAll('input[type="checkbox"]:checked');

                if (checkedBoxes.length > maxSelect) {
                    event.target.checked = false;
                    alert('You can select up to ' + maxSelect + ' senators only.');
                }
            });
        }

        function openProfileRequestModal() {
            var profileModalElement = document.getElementById('profileModal');
            var requestModalElement = document.getElementById('profileRequestModal');

            var profileModal = bootstrap.Modal.getInstance(profileModalElement);
            var requestModal = new bootstrap.Modal(requestModalElement);

            if (profileModal) {
                profileModal.hide();
            }

            setTimeout(function () {
                requestModal.show();
            }, 250);
        }

        function submitProfileChangeRequest(event) {
            event.preventDefault();

            var requestField = document.getElementById('requestField').value;
            var requestMessage = document.getElementById('requestMessage').value;

            if (!requestField || !requestMessage.trim()) {
                alert('Please complete the profile change request form.');
                return;
            }

            alert('Your profile change request has been prepared. Later, this will be sent to the admin side once connected to the database.');

            document.getElementById('profileChangeRequestForm').reset();

            var requestModalElement = document.getElementById('profileRequestModal');
            var requestModal = bootstrap.Modal.getInstance(requestModalElement);

            if (requestModal) {
                requestModal.hide();
            }
        }

        function logoutUser() {
            window.location.href = 'logout.php';
        }
    </script>
</body>

</html>
