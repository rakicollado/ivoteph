<?php require_once __DIR__ . '/auth_check.php'; ?>

<?php

date_default_timezone_set('Asia/Manila');

if (!function_exists('ivoteph_h')) {
    function ivoteph_h($value)
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ivoteph_date_display')) {
    function ivoteph_date_display($value)
    {
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

if (!function_exists('ivoteph_datetime_display')) {
    function ivoteph_datetime_display($value)
    {
        if ($value === null || $value == '' || $value == '0000-00-00 00:00:00') {
            return 'N/A';
        }

        $timestamp = strtotime($value);

        if (!$timestamp) {
            return $value;
        }

        return date('F j, Y g:i A', $timestamp);
    }
}

function ivoteph_current_database($conn)
{
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

    return $database_name;
}

function ivoteph_table_exists($conn, $table_name)
{
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);

    if ($table_name == '') {
        return false;
    }

    $database_sql = mysqli_real_escape_string($conn, ivoteph_current_database($conn));
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

function ivoteph_column_exists($conn, $table_name, $column_name)
{
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);
    $column_name = preg_replace('/[^A-Za-z0-9_]/', '', $column_name);

    if ($table_name == '' || $column_name == '') {
        return false;
    }

    $database_sql = mysqli_real_escape_string($conn, ivoteph_current_database($conn));
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

function ivoteph_fetch_ballot_id($conn, $election_id, $voter_id)
{
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

function ivoteph_create_ballot_record($conn, $election_id, $voter_id, &$ballot_error_message)
{
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

function ivoteph_candidate_photo($photo)
{
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

function ivoteph_initials_from_name($name)
{
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

function ivoteph_fetch_active_election($conn)
{
    if (!ivoteph_table_exists($conn, 'elections')) {
        return false;
    }

    if (
        ivoteph_column_exists($conn, 'elections', 'election_status') &&
        ivoteph_column_exists($conn, 'elections', 'start_datetime') &&
        ivoteph_column_exists($conn, 'elections', 'end_datetime')
    ) {
        $sql_open = "
            SELECT *
            FROM elections
            WHERE LOWER(TRIM(`election_status`)) = 'open'
              AND `start_datetime` <= NOW()
              AND `end_datetime` >= NOW()
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
            WHERE LOWER(TRIM(`election_status`)) = 'open'
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

    if (ivoteph_column_exists($conn, 'elections', 'election_id')) {
        $sql_latest = "SELECT * FROM elections ORDER BY election_id DESC LIMIT 1";
    } else {
        $sql_latest = "SELECT * FROM elections LIMIT 1";
    }

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

function ivoteph_election_value($election, $keys)
{
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

function ivoteph_is_election_open($election)
{
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

function ivoteph_election_title($election)
{
    if (!$election) {
        return 'No election configured';
    }

    $title = ivoteph_election_value($election, array('election_name', 'election_title', 'title', 'name'));

    if ($title != '') {
        return $title;
    }

    return 'Election';
}

function ivoteph_election_status($election)
{
    if (!$election) {
        return 'Not configured';
    }

    $status = ivoteph_election_value($election, array('election_status', 'status'));

    if ($status != '') {
        return ucfirst($status);
    }

    return 'Scheduled';
}

function ivoteph_election_datetime($election, $key)
{
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

function ivoteph_candidate_jurisdiction_label($candidate)
{
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
$voter_city_normalized = preg_replace('/\s+city$/i', '', trim((string) $profile_city_municipality));
$voter_city_normalized_sql = mysqli_real_escape_string($conn, $voter_city_normalized);

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
                    AND LOWER(TRIM(REPLACE(REPLACE(c.city_municipality, ' City', ''), ' city', ''))) = LOWER(TRIM('" . $voter_city_normalized_sql . "'))
                )
                OR (
                    c.election_scope = 'Local'
                    AND LOWER(TRIM(p.position_name)) NOT IN ('governor', 'mayor')
                    AND (
                        (
                            c.city_municipality IS NOT NULL
                            AND TRIM(c.city_municipality) <> ''
                            AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                            AND LOWER(TRIM(REPLACE(REPLACE(c.city_municipality, ' City', ''), ' city', ''))) = LOWER(TRIM('" . $voter_city_normalized_sql . "'))
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
                    AND LOWER(TRIM(REPLACE(REPLACE(c.city_municipality, ' City', ''), ' city', ''))) = LOWER(TRIM('" . $voter_city_normalized_sql . "'))
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

$submitted_ballot_id = 0;
$submitted_ballot_reference = 'Generated after submission';
$submitted_at_raw = '';
$submitted_at_display = 'N/A';
$existing_total_choices = count($existing_votes);
$existing_votes_by_position = array();

if ($already_voted && $active_election_id > 0) {
    $submitted_ballot_id = ivoteph_fetch_ballot_id($conn, $active_election_id, $profile_voter_id);

    if ($submitted_ballot_id > 0) {
        $submitted_ballot_reference = 'BAL-' . str_pad((string) $submitted_ballot_id, 6, '0', STR_PAD_LEFT);

        if (ivoteph_table_exists($conn, 'ballots')) {
            $ballot_time_columns = array();
            $possible_ballot_time_columns = array('submitted_at', 'voted_at', 'created_at', 'updated_at');

            for ($ballot_time_index = 0; $ballot_time_index < count($possible_ballot_time_columns); $ballot_time_index++) {
                $ballot_time_column = $possible_ballot_time_columns[$ballot_time_index];

                if (ivoteph_column_exists($conn, 'ballots', $ballot_time_column)) {
                    $ballot_time_columns[] = '`' . $ballot_time_column . '`';
                }
            }

            if (count($ballot_time_columns) > 0) {
                $sql_ballot_time = "
                    SELECT " . implode(', ', $ballot_time_columns) . "
                    FROM ballots
                    WHERE ballot_id = " . (int) $submitted_ballot_id . "
                    LIMIT 1
                ";

                $result_ballot_time = mysqli_query($conn, $sql_ballot_time);

                if ($result_ballot_time) {
                    $row_ballot_time = mysqli_fetch_assoc($result_ballot_time);

                    if ($row_ballot_time) {
                        for ($ballot_time_index = 0; $ballot_time_index < count($possible_ballot_time_columns); $ballot_time_index++) {
                            $ballot_time_column = $possible_ballot_time_columns[$ballot_time_index];

                            if (isset($row_ballot_time[$ballot_time_column]) && trim((string) $row_ballot_time[$ballot_time_column]) != '') {
                                $submitted_at_raw = $row_ballot_time[$ballot_time_column];
                                break;
                            }
                        }
                    }

                    mysqli_free_result($result_ballot_time);
                }
            }
        }
    }

    if ($submitted_at_raw != '') {
        $submitted_at_display = ivoteph_datetime_display($submitted_at_raw);
    }
}

for ($existing_vote_index = 0; $existing_vote_index < count($existing_votes); $existing_vote_index++) {
    $existing_vote = $existing_votes[$existing_vote_index];
    $existing_position_id = (int) $existing_vote['position_id'];
    $existing_position_name = trim((string) $existing_vote['position_name']);

    if ($existing_position_name == '') {
        $existing_position_name = 'Unassigned';
    }

    if (!isset($existing_votes_by_position[$existing_position_id])) {
        $existing_votes_by_position[$existing_position_id] = array(
            'position_name' => $existing_position_name,
            'candidates' => array()
        );
    }

    $existing_votes_by_position[$existing_position_id]['candidates'][] = trim((string) $existing_vote['full_name']);
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
                                      AND LOWER(TRIM(REPLACE(REPLACE(c.city_municipality, ' City', ''), ' city', ''))) = LOWER(TRIM('" . $voter_city_normalized_sql . "'))
                                  )
                                  OR (
                                      c.election_scope = 'Local'
                                      AND LOWER(TRIM(p.position_name)) NOT IN ('governor', 'mayor')
                                      AND (
                                          (
                                              c.city_municipality IS NOT NULL
                                              AND TRIM(c.city_municipality) <> ''
                                              AND LOWER(TRIM(c.province)) = LOWER(TRIM('" . $voter_province_sql . "'))
                                              AND LOWER(TRIM(REPLACE(REPLACE(c.city_municipality, ' City', ''), ' city', ''))) = LOWER(TRIM('" . $voter_city_normalized_sql . "'))
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
                                      AND LOWER(TRIM(REPLACE(REPLACE(c.city_municipality, ' City', ''), ' city', ''))) = LOWER(TRIM('" . $voter_city_normalized_sql . "'))
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

            if (ivoteph_column_exists($conn, 'votes', 'vote_timestamp')) {
                $vote_time_column = 'vote_timestamp';
            } elseif (ivoteph_column_exists($conn, 'votes', 'voted_at')) {
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
                header('Location: startvoting.php?success=voted');
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

/* iVotePH profile request notification data */
if (!function_exists('ivoteph_profile_request_badge_class')) {
    function ivoteph_profile_request_badge_class($status)
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
}

if (!function_exists('ivoteph_profile_request_table_exists')) {
    function ivoteph_profile_request_table_exists($conn, $table_name)
    {
        $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);

        if ($table_name === '') {
            return false;
        }

        $table_name_sql = mysqli_real_escape_string($conn, $table_name);
        $result = mysqli_query($conn, "SHOW TABLES LIKE '" . $table_name_sql . "'");

        if ($result && mysqli_num_rows($result) > 0) {
            mysqli_free_result($result);
            return true;
        }

        if ($result) {
            mysqli_free_result($result);
        }

        return false;
    }
}

if (!function_exists('ivoteph_profile_request_date')) {
    function ivoteph_profile_request_date($value)
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return 'N/A';
        }

        $time = strtotime($value);

        if (!$time) {
            return 'N/A';
        }

        return date('M d, Y h:i A', $time);
    }
}

$profile_notifications = array();
$profile_notification_count = 0;

if (isset($conn) && $conn && isset($profile_voter_id) && trim((string) $profile_voter_id) !== '' && ivoteph_profile_request_table_exists($conn, 'profile_change_requests')) {
    $stmt_profile_notifications = mysqli_prepare($conn, "
        SELECT
            request_id,
            request_field,
            request_message,
            request_status,
            admin_response,
            created_at,
            reviewed_at
        FROM profile_change_requests
        WHERE voter_id = ?
        ORDER BY request_id DESC
        LIMIT 10
    ");

    if ($stmt_profile_notifications) {
        mysqli_stmt_bind_param($stmt_profile_notifications, 's', $profile_voter_id);
        mysqli_stmt_execute($stmt_profile_notifications);
        mysqli_stmt_bind_result(
            $stmt_profile_notifications,
            $notif_request_id,
            $notif_request_field,
            $notif_request_message,
            $notif_request_status,
            $notif_admin_response,
            $notif_created_at,
            $notif_reviewed_at
        );

        while (mysqli_stmt_fetch($stmt_profile_notifications)) {
            $profile_notifications[] = array(
                'request_id' => $notif_request_id,
                'request_field' => $notif_request_field,
                'request_message' => $notif_request_message,
                'request_status' => $notif_request_status,
                'admin_response' => $notif_admin_response,
                'created_at' => $notif_created_at,
                'reviewed_at' => $notif_reviewed_at
            );

            if ($notif_request_status === 'Approved' || $notif_request_status === 'Rejected' || $notif_request_status === 'Resolved') {
                $profile_notification_count++;
            }
        }

        mysqli_stmt_close($stmt_profile_notifications);
    }
}
/* end iVotePH profile request notification data */

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

        * {
            box-sizing: border-box;
        }

        html,
        body {
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

        .userNavInner {
            width: 100%;
            overflow: hidden;
        }

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

        .userNavList li {
            list-style: none !important;
            flex: 0 0 auto;
        }

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

        .topbarSearch {
            width: 100%;
            min-width: 0;
        }

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

        .userName {
            font-size: 11px;
            font-weight: 900;
            color: var(--userInk);
            line-height: 1.1;
        }

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

        .summaryGrid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .summaryCard {
            padding: 18px;
        }

        .summaryCard span {
            display: block;
            color: var(--userMuted);
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }

        .summaryCard strong {
            display: block;
            color: var(--userBlue);
            font-size: 24px;
            line-height: 1.1;
            font-weight: 950;
        }

        .sectionCard {
            padding: 24px;
            margin-bottom: 18px;
        }

        .sectionHeader {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .sectionHeader h2,
        .sectionHeader h3 {
            margin: 0;
            font-weight: 950;
            color: var(--userInk);
            letter-spacing: -0.03em;
        }

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

        .positionHeader h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 950;
        }

        .positionHeader span {
            font-size: 12px;
            color: var(--userMuted);
            font-weight: 900;
        }

        .candidateVoteGrid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .candidateOption {
            position: relative;
            display: block;
            margin: 0;
            height: 100%;
        }

        .candidateOption input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .candidateVoteCard {
            height: 100%;
            border: 1px solid var(--userLine);
            border-radius: 18px;
            padding: 16px;
            cursor: pointer;
            background: #ffffff;
            transition: 0.2s ease;
        }

        .candidateOption input:checked+.candidateVoteCard {
            border-color: #0b5ed7;
            background: #eaf2ff;
            box-shadow: 0 14px 30px rgba(6, 70, 168, 0.16);
        }

        .candidateVoteCard:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(16, 24, 40, 0.08);
        }

        .candidateVoteTop {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-bottom: 13px;
        }

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

        .candidatePhoto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .candidateVoteName {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 950;
            color: var(--userInk);
        }

        .candidateVoteParty {
            margin: 0;
            color: var(--userMuted);
            font-size: 12px;
            font-weight: 800;
        }

        .candidateVoteScope {
            display: inline-flex;
            margin-top: 6px;
            padding: 5px 9px;
            border-radius: 999px;
            background: var(--userBlueSoft);
            color: var(--userBlue);
            font-size: 11px;
            font-weight: 900;
        }

        .candidateVotePlatform {
            margin: 0;
            color: var(--userMuted);
            font-size: 13px;
            line-height: 1.5;
        }

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

        .voteSubmitBar strong {
            display: block;
            font-weight: 950;
        }

        .voteSubmitBar span {
            display: block;
            font-size: 12px;
            color: var(--userMuted);
        }

        .btnVoteSubmit {
            min-height: 48px;
            border-radius: 999px;
            font-weight: 950;
            padding-left: 24px;
            padding-right: 24px;
        }

        .ballotModalContent {
            border: none;
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(16, 24, 40, 0.22);
        }

        .ballotModalHeader {
            background:
                radial-gradient(circle at top right, rgba(247, 201, 72, 0.28), transparent 34%),
                linear-gradient(135deg, #0646a8 0%, #0b3f91 100%);
            color: #ffffff;
            padding: 24px;
        }

        .ballotModalHeader h5 {
            margin: 0;
            font-size: 22px;
            font-weight: 950;
        }

        .ballotModalHeader p {
            margin: 7px 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: 13px;
        }

        .ballotSummaryLayout {
            display: grid;
            grid-template-columns: 330px minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .ballotSummaryPanel,
        .ballotChoicesPanel {
            border: 1px solid var(--userLine);
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(16, 24, 40, 0.06);
        }

        .ballotSummaryPanel {
            padding: 18px;
        }

        .ballotChoicesPanel {
            overflow: hidden;
        }

        .ballotSummaryTitle,
        .ballotChoicesTitle {
            margin: 0 0 14px;
            color: var(--userInk);
            font-size: 20px;
            line-height: 1.1;
            font-weight: 950;
            letter-spacing: -0.03em;
        }

        .ballotChoicesTitle {
            padding: 18px 18px 0;
            margin-bottom: 10px;
        }

        .ballotSummaryItem {
            padding: 14px 15px;
            border: 1px solid #dce5f2;
            border-radius: 16px;
            background: #f7f9fd;
            margin-bottom: 12px;
        }

        .ballotSummaryItem span {
            display: block;
            margin-bottom: 6px;
            color: var(--userMuted);
            font-size: 10px;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .ballotSummaryItem strong {
            display: block;
            color: var(--userBlue);
            font-size: 14px;
            line-height: 1.35;
            font-weight: 950;
            overflow-wrap: anywhere;
        }

        .ballotPositionList {
            display: grid;
            gap: 12px;
            padding: 0 18px 18px;
        }

        .ballotPositionGroup {
            border: 1px solid #dce5f2;
            border-radius: 18px;
            background: #f8fbff;
            overflow: hidden;
        }

        .ballotPositionGroupHeader {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: #eef5ff;
            border-bottom: 1px solid #dce5f2;
        }

        .ballotPositionGroupHeader strong {
            color: var(--userInk);
            font-size: 14px;
            font-weight: 950;
        }

        .ballotPositionGroupHeader span {
            color: var(--userBlue);
            font-size: 11px;
            font-weight: 950;
        }

        .ballotCandidateList {
            margin: 0;
            padding: 12px 14px 12px 32px;
        }

        .ballotCandidateList li {
            margin-bottom: 6px;
            color: var(--userInk);
            font-size: 14px;
            font-weight: 850;
        }

        .ballotCandidateList li:last-child {
            margin-bottom: 0;
        }

        .ballotSingleCandidate {
            padding: 12px 14px;
            color: var(--userInk);
            font-size: 14px;
            font-weight: 900;
        }

        .positionBlock {
            margin-bottom: 26px;
            border-left: 5px solid #0b5ed7;
        }

        .positionBlock+.positionBlock {
            margin-top: 28px;
        }

        .positionHeader {
            background:
                linear-gradient(90deg, #f1f6ff 0%, #f7f9fd 100%);
        }

        .positionHeader h3 {
            display: inline-flex;
            align-items: center;
            gap: 9px;
        }

        .positionHeader h3:before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--userBlue);
            box-shadow: 0 0 0 5px rgba(11, 94, 215, 0.10);
        }

        .ballotModalActions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 992px) {
            .ballotSummaryLayout {
                grid-template-columns: 1fr;
            }
        }

        .footer {
            padding: 18px 22px;
            text-align: center;
            color: var(--userMuted);
            font-size: 13px;
        }

        #profileModal .modal-dialog {
            max-width: min(1180px, calc(100vw - 24px));
            margin: 12px auto;
        }

        #profileModal .profileModalContent {
            max-height: calc(100vh - 24px);
            display: flex;
            flex-direction: column;
        }

        .profileModalContent {
            border: none;
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(16, 24, 40, 0.22);
        }

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

        .profileModalHeader h5 {
            margin: 0;
            font-size: 22px;
            font-weight: 950;
        }

        .profileModalHeader p {
            margin: 6px 0 0;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.86);
        }

        .profileModalBody {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: 22px;
            background: #ffffff;
        }

        .profileReadOnlyNote {
            margin-bottom: 18px;
            padding: 14px;
            border-radius: 16px;
            background: #eaf2ff;
            border: 1px solid #cfe0ff;
            color: var(--userBlue);
            font-size: 13px;
            font-weight: 800;
            line-height: 1.5;
        }

        .profileSectionTitle {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 18px 0 12px;
            color: var(--userInk);
            font-size: 14px;
            font-weight: 950;
        }

        .profileSectionTitle:first-of-type {
            margin-top: 0;
        }

        .profileSectionTitle i {
            color: var(--userBlue);
        }

        .profileFullGrid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .profileFullGrid.threeCols {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .profileFullItem {
            min-width: 0;
            background: #f7f9fd;
            border: 1px solid #e1e8f3;
            border-radius: 16px;
            padding: 13px;
        }

        .profileFullItem.profileFullWide {
            grid-column: 1 / -1;
        }

        .profileFullItem span {
            display: block;
            font-size: 10.5px;
            font-weight: 900;
            color: var(--userMuted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }

        .profileFullItem strong {
            display: block;
            font-size: 14px;
            font-weight: 900;
            color: var(--userInk);
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .profileModalActions {
            flex: 0 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 14px 22px;
            background: #ffffff;
            border-top: 1px solid #e1e8f3;
            box-shadow: 0 -10px 24px rgba(16, 24, 40, 0.06);
        }

        .profileModalActions .btn {
            min-height: 46px;
            border-radius: 14px;
            font-weight: 900;
            font-size: 13px;
        }

        .requestModalBody {
            padding: 22px;
        }

        .requestNotice {
            background: #eaf2ff;
            border: 1px solid #cfe0ff;
            color: var(--userBlue);
            border-radius: 16px;
            padding: 14px;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .requestModalBody .form-label {
            font-size: 12px;
            font-weight: 900;
            color: var(--userInk);
            margin-bottom: 7px;
        }

        .requestModalBody .form-select,
        .requestModalBody .form-control {
            border-radius: 14px;
            border: 1px solid var(--userLine);
            font-size: 13px;
            box-shadow: none;
        }

        @media (max-width: 1180px) {
            .userTopbarInner {
                grid-template-columns: auto 1fr auto;
                grid-template-rows: auto auto auto;
            }

            .brandLink {
                grid-column: 1;
                grid-row: 1;
            }

            .userChip {
                grid-column: 3;
                grid-row: 1;
            }

            .userNavBar {
                grid-column: 1 / -1;
                grid-row: 2;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }

            .topbarSearch {
                grid-column: 1 / -1;
                grid-row: 3;
            }

            .userNavInner {
                overflow-x: auto;
                scrollbar-width: none;
            }

            .userNavInner::-webkit-scrollbar {
                display: none;
            }

            .userNavList {
                min-width: max-content;
                width: max-content;
            }

            .summaryGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .candidateVoteGrid {
                grid-template-columns: 1fr;
            }

            .profileFullGrid.threeCols {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .userTopbar {
                padding: 10px 12px;
            }

            .brandLogo {
                width: 60px !important;
                max-width: 60px !important;
            }

            .userChip {
                padding: 6px;
            }

            .userMeta,
            .userChip .fa-chevron-down {
                display: none !important;
            }

            .userMain {
                padding: 14px 12px 30px;
            }
    </style>

    <style id="ivotephTopbarFinalCleanFix">
        html,
        body {
            overflow-x: hidden !important;
        }

        body.userPage {
            padding-top: 78px !important;
        }

        .userTopbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 5000 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 10px 22px !important;
            background: rgba(248, 250, 255, 0.97) !important;
            border: 0 !important;
            border-bottom: 1px solid rgba(210, 219, 235, 0.95) !important;
            border-radius: 0 !important;
            box-shadow: 0 10px 24px rgba(16, 24, 40, 0.08) !important;
            backdrop-filter: blur(16px) saturate(1.15) !important;
            -webkit-backdrop-filter: blur(16px) saturate(1.15) !important;
        }

        .userTopbarInner {
            width: 100% !important;
            max-width: 1480px !important;
            min-height: 52px !important;
            margin: 0 auto !important;
            padding: 0 !important;
            display: grid !important;
            grid-template-columns: auto minmax(0, 1fr) auto !important;
            grid-template-areas: "brand nav profile" !important;
            align-items: center !important;
            gap: 14px !important;
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .userTopbar::before,
        .userTopbar::after,
        .userTopbarInner::before,
        .userTopbarInner::after {
            content: none !important;
            display: none !important;
        }

        .brandLink {
            grid-area: brand !important;
            justify-self: start !important;
            align-self: center !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            min-width: 0 !important;
            min-height: 0 !important;
            width: auto !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        img.brandLogo,
        .brandLogo {
            display: block !important;
            width: 80px !important;
            max-width: 80px !important;
            height: auto !important;
            max-height: 42px !important;
            object-fit: contain !important;
            margin: 0 !important;
            filter: none !important;
        }

        .topbarSearch,
        .topbarSearch *,
        .userTopbar .topbarSearch,
        .userTopbar .input-group:has(.searchInput) {
            display: none !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            min-width: 0 !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
        }

        .userNavBar {
            grid-area: nav !important;
            align-self: center !important;
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            -webkit-overflow-scrolling: touch !important;
            scrollbar-width: none !important;
        }

        .userNavBar::-webkit-scrollbar,
        .userNavInner::-webkit-scrollbar {
            display: none !important;
        }

        .userNavInner {
            width: 100% !important;
            max-width: none !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            scrollbar-width: none !important;
        }

        .userNavList {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            flex-wrap: nowrap !important;
            gap: 7px !important;
            width: max-content !important;
            min-width: max-content !important;
            margin: 0 !important;
            padding: 0 !important;
            list-style: none !important;
            overflow: visible !important;
        }

        .userNavList li {
            list-style: none !important;
            flex: 0 0 auto !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .userNavList a {
            height: 40px !important;
            min-height: 40px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            padding: 0 14px !important;
            border-radius: 999px !important;
            background: #f3f6fb !important;
            border: 1px solid transparent !important;
            color: #344054 !important;
            font-size: 12px !important;
            font-weight: 900 !important;
            line-height: 1 !important;
            white-space: nowrap !important;
            text-decoration: none !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .userNavList a i {
            font-size: 12px !important;
            color: currentColor !important;
        }

        .userNavList a:hover {
            background: #e8f1ff !important;
            color: #0646a8 !important;
            transform: none !important;
        }

        .userNavList a.active {
            background: #0b5ed7 !important;
            color: #ffffff !important;
            box-shadow: 0 8px 18px rgba(6, 70, 168, 0.18) !important;
        }

        .userChip {
            grid-area: profile !important;
            justify-self: end !important;
            align-self: center !important;
            height: 44px !important;
            min-height: 44px !important;
            max-width: 290px !important;
            margin: 0 !important;
            padding: 5px 12px 5px 6px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            border-radius: 999px !important;
            background: #ffffff !important;
            border: 1px solid #dce5f2 !important;
            box-shadow: none !important;
            color: #172033 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
        }

        .userAvatarCircle {
            width: 34px !important;
            height: 34px !important;
            min-width: 34px !important;
            font-size: 12px !important;
            background: #0b5ed7 !important;
            color: #ffffff !important;
        }

        .userName {
            font-size: 11px !important;
            line-height: 1.05 !important;
            max-width: 190px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            white-space: nowrap !important;
        }

        .verifiedBadge {
            font-size: 9px !important;
            line-height: 1.05 !important;
        }

        .userMain {
            padding-top: 22px !important;
        }

        .modal {
            z-index: 7000 !important;
        }

        .modal-backdrop {
            z-index: 6500 !important;
        }

        .modal-dialog,
        #profileRequestModal .modal-dialog {
            width: min(560px, calc(100vw - 28px)) !important;
            max-width: min(560px, calc(100vw - 28px)) !important;
            margin: 16px auto !important;
        }

        #profileModal .modal-dialog {
            width: min(980px, calc(100vw - 28px)) !important;
            max-width: min(980px, calc(100vw - 28px)) !important;
            margin: 16px auto !important;
        }

        .modal-content,
        .profileModalContent,
        .ballotModalContent {
            border-radius: 22px !important;
            overflow: hidden !important;
            max-height: calc(100vh - 32px) !important;
        }

        .modal-body,
        .profileModalBody,
        .requestModalBody {
            overflow-y: auto !important;
            max-height: calc(100vh - 190px) !important;
        }

        .profileModalHeader,
        .ballotModalHeader {
            padding: 18px !important;
        }

        .profileModalAvatar {
            width: 58px !important;
            height: 58px !important;
            font-size: 20px !important;
        }

        .requestModalBody,
        .profileModalBody {
            padding: 18px !important;
        }

        .requestModalBody .form-control,
        .requestModalBody .form-select,
        .profileModalContent .form-control,
        .profileModalContent .form-select {
            min-height: 44px !important;
            border-radius: 16px !important;
        }

        .requestModalBody textarea.form-control {
            min-height: 110px !important;
        }

        .profileModalActions {
            padding: 12px 18px !important;
        }

        @media (max-width: 980px) {
            body.userPage {
                padding-top: 116px !important;
            }

            .userTopbar {
                padding: 9px 14px !important;
            }

            .userTopbarInner {
                min-height: 92px !important;
                grid-template-columns: auto 1fr auto !important;
                grid-template-rows: auto auto !important;
                grid-template-areas:
                    "brand spacer profile"
                    "nav nav nav" !important;
                column-gap: 10px !important;
                row-gap: 9px !important;
            }

            .brandLink {
                grid-area: brand !important;
            }

            .userChip {
                grid-area: profile !important;
            }

            .userNavBar {
                grid-area: nav !important;
            }

            img.brandLogo,
            .brandLogo {
                width: 72px !important;
                max-width: 72px !important;
                max-height: 36px !important;
            }

            .userNavList {
                gap: 6px !important;
            }

            .userNavList a {
                height: 38px !important;
                min-height: 38px !important;
                padding: 0 12px !important;
                font-size: 11.5px !important;
            }
        }

        @media (max-width: 640px) {
            body.userPage {
                padding-top: 106px !important;
            }

            .userTopbar {
                padding: 8px 10px !important;
            }

            .userTopbarInner {
                min-height: 88px !important;
                row-gap: 8px !important;
            }

            img.brandLogo,
            .brandLogo {
                width: 62px !important;
                max-width: 62px !important;
                max-height: 32px !important;
            }

            .userChip {
                width: 42px !important;
                height: 42px !important;
                min-height: 42px !important;
                max-width: 42px !important;
                padding: 4px !important;
                border-radius: 999px !important;
            }

            .userAvatarCircle {
                width: 32px !important;
                height: 32px !important;
                min-width: 32px !important;
            }

            .userMeta,
            .userChip .fa-chevron-down {
                display: none !important;
            }

            .userNavList a {
                height: 36px !important;
                min-height: 36px !important;
                padding: 0 10px !important;
                font-size: 11px !important;
                gap: 5px !important;
            }

            .userNavList a i {
                font-size: 11px !important;
            }

            .userMain {
                width: calc(100% - 20px) !important;
                padding-top: 16px !important;
            }

            .modal-dialog,
            #profileModal .modal-dialog,
            #profileRequestModal .modal-dialog {
                width: calc(100vw - 20px) !important;
                max-width: calc(100vw - 20px) !important;
                margin: 10px auto !important;
            }

            .modal-content,
            .profileModalContent,
            .ballotModalContent {
                border-radius: 18px !important;
                max-height: calc(100vh - 20px) !important;
            }

            .modal-body,
            .profileModalBody,
            .requestModalBody {
                max-height: calc(100vh - 170px) !important;
                padding: 14px !important;
            }
        }
    </style>

    <style id="ivotephFinalTopbarFix">
        :root {
            --ivoteFixedTopbarHeight: 78px;
        }

        html,
        body {
            overflow-x: hidden !important;
        }

        body.userPage {
            padding-top: var(--ivoteFixedTopbarHeight, 78px) !important;
        }

        .userTopbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 99999 !important;
            width: 100% !important;
            min-height: 0 !important;
            padding: 9px 18px !important;
            margin: 0 !important;
            background: rgba(248, 251, 255, 0.97) !important;
            border: 0 !important;
            border-bottom: 1px solid #dce5f2 !important;
            box-shadow: 0 8px 22px rgba(16, 24, 40, 0.08) !important;
            border-radius: 0 !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            transform: none !important;
        }

        .userTopbar::before,
        .userTopbar::after,
        .userTopbarInner::before,
        .userTopbarInner::after {
            display: none !important;
            content: none !important;
        }

        .userTopbarInner {
            width: 100% !important;
            max-width: 1480px !important;
            min-height: 50px !important;
            margin: 0 auto !important;
            padding: 0 !important;
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 14px !important;
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            overflow: visible !important;
            transform: none !important;
        }

        .brandLink,
        .brandLogoLink,
        .navbar-brand.brandLogoLink {
            flex: 0 0 auto !important;
            order: 1 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            width: auto !important;
            min-width: 78px !important;
            height: 50px !important;
            min-height: 50px !important;
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .brandLogo,
        img.brandLogo {
            display: block !important;
            width: 74px !important;
            max-width: 74px !important;
            height: auto !important;
            max-height: 38px !important;
            object-fit: contain !important;
            margin: 0 !important;
            padding: 0 !important;
            filter: none !important;
            transform: none !important;
        }

        .topbarSearch,
        .topbarSearch *,
        .searchInput,
        .form-control.searchInput {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 0 !important;
            min-width: 0 !important;
            max-width: 0 !important;
            height: 0 !important;
            min-height: 0 !important;
            max-height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
        }

        .userNavBar {
            flex: 1 1 auto !important;
            order: 2 !important;
            min-width: 0 !important;
            width: auto !important;
            max-width: none !important;
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            scrollbar-width: none !important;
            -webkit-overflow-scrolling: touch !important;
            transform: none !important;
        }

        .userNavBar::-webkit-scrollbar,
        .userNavInner::-webkit-scrollbar {
            display: none !important;
        }

        .userNavInner {
            width: 100% !important;
            min-width: 0 !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            scrollbar-width: none !important;
        }

        .userNavList {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 8px !important;
            width: max-content !important;
            min-width: max-content !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            list-style: none !important;
            overflow: visible !important;
        }

        .userNavList li {
            flex: 0 0 auto !important;
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .userNavList a {
            height: 40px !important;
            min-height: 40px !important;
            padding: 0 14px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 7px !important;
            border-radius: 999px !important;
            background: #f2f5fb !important;
            border: 0 !important;
            color: #30394c !important;
            font-size: 12.5px !important;
            font-weight: 900 !important;
            line-height: 1 !important;
            white-space: nowrap !important;
            text-decoration: none !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .userNavList a i {
            font-size: 12.5px !important;
        }

        .userNavList a:hover {
            background: #e8f1ff !important;
            color: #0646a8 !important;
        }

        .userNavList a.active {
            background: #0b5ed7 !important;
            color: #ffffff !important;
            box-shadow: 0 8px 18px rgba(6, 70, 168, 0.18) !important;
        }

        .userChip {
            flex: 0 0 auto !important;
            order: 3 !important;
            align-self: center !important;
            height: 44px !important;
            min-height: 44px !important;
            max-width: 285px !important;
            padding: 5px 12px 5px 5px !important;
            margin: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 9px !important;
            border-radius: 999px !important;
            background: #ffffff !important;
            border: 1px solid #dce5f2 !important;
            box-shadow: none !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            transform: none !important;
        }

        .userAvatarCircle {
            width: 34px !important;
            height: 34px !important;
            min-width: 34px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 50% !important;
            background: #0b5ed7 !important;
            color: #ffffff !important;
            font-size: 12px !important;
            font-weight: 950 !important;
        }

        .userName {
            font-size: 11.5px !important;
            font-weight: 950 !important;
            line-height: 1.1 !important;
            color: #101828 !important;
        }

        .verifiedBadge {
            font-size: 9.5px !important;
            font-weight: 850 !important;
            line-height: 1.1 !important;
            color: #0b5ed7 !important;
        }

        .menuButton,
        .sidebarOverlay,
        .userSidebar,
        #sidebar,
        .sidebar,
        .sidebarToggle,
        .dashboardSidebarToggle {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        .userMain {
            width: min(1480px, calc(100% - 44px)) !important;
            margin: 0 auto !important;
            padding-top: 22px !important;
        }

        .modal-dialog {
            max-width: min(720px, calc(100vw - 24px)) !important;
            margin: 12px auto !important;
        }

        .modal-dialog.modal-xl {
            max-width: min(980px, calc(100vw - 24px)) !important;
        }

        .modal-content,
        .profileModalContent,
        .ballotModalContent {
            border-radius: 22px !important;
            max-height: calc(100vh - 24px) !important;
            overflow: hidden !important;
        }

        .modal-body,
        .profileModalBody,
        .requestModalBody {
            max-height: calc(100vh - 210px) !important;
            overflow-y: auto !important;
        }

        @media (max-width: 1100px) {
            .userTopbar {
                padding: 8px 12px !important;
            }

            .userTopbarInner {
                gap: 10px !important;
            }

            .brandLink,
            .brandLogoLink,
            .navbar-brand.brandLogoLink {
                min-width: 68px !important;
                height: 48px !important;
                min-height: 48px !important;
            }

            .brandLogo,
            img.brandLogo {
                width: 64px !important;
                max-width: 64px !important;
                max-height: 34px !important;
            }

            .userNavList {
                gap: 6px !important;
            }

            .userNavList a {
                height: 38px !important;
                min-height: 38px !important;
                padding: 0 12px !important;
                font-size: 12px !important;
            }

            .userNavList a i {
                font-size: 12px !important;
            }

            .userChip {
                max-width: 48px !important;
                width: 44px !important;
                height: 44px !important;
                min-height: 44px !important;
                padding: 5px !important;
            }

            .userChip .userMeta,
            .userChip .fa-chevron-down {
                display: none !important;
            }
        }

        @media (max-width: 576px) {
            .userTopbar {
                padding: 7px 8px !important;
            }

            .userTopbarInner {
                min-height: 44px !important;
                gap: 7px !important;
            }

            .brandLink,
            .brandLogoLink,
            .navbar-brand.brandLogoLink {
                min-width: 56px !important;
                height: 44px !important;
                min-height: 44px !important;
            }

            .brandLogo,
            img.brandLogo {
                width: 54px !important;
                max-width: 54px !important;
                max-height: 28px !important;
            }

            .userNavList a {
                height: 34px !important;
                min-height: 34px !important;
                padding: 0 10px !important;
                font-size: 10.5px !important;
                gap: 5px !important;
            }

            .userNavList a i {
                font-size: 10.5px !important;
            }

            .userChip {
                width: 38px !important;
                height: 38px !important;
                min-height: 38px !important;
                padding: 3px !important;
            }

            .userAvatarCircle {
                width: 30px !important;
                height: 30px !important;
                min-width: 30px !important;
                font-size: 11px !important;
            }

            .userMain {
                width: calc(100% - 20px) !important;
                padding-top: 14px !important;
            }

            .modal-dialog,
            .modal-dialog.modal-xl {
                max-width: calc(100vw - 16px) !important;
                margin: 8px auto !important;
            }

            .modal-content,
            .profileModalContent,
            .ballotModalContent {
                border-radius: 18px !important;
            }

            .modal-body,
            .profileModalBody,
            .requestModalBody {
                max-height: calc(100vh - 180px) !important;
                overflow-y: auto !important;
            }
        }
    </style>
    <style id="ivoteModalOverNavbarHardFix">
        body.userPage .userTopbar,
        body.userPage>header.userTopbar,
        html body.userPage>header.userTopbar {
            z-index: 1000 !important;
        }

        body.userPage.modal-open .userTopbar,
        body.userPage.ivoteModalOpen .userTopbar,
        body.userPage.modal-open>header.userTopbar,
        html body.userPage.modal-open>header.userTopbar {
            z-index: 1 !important;
        }

        body.userPage .modal-backdrop,
        body.userPage .modal-backdrop.show {
            z-index: 999998 !important;
        }

        body.userPage .modal {
            z-index: 999999 !important;
        }

        body.userPage .modal-dialog {
            position: relative !important;
            z-index: 1000000 !important;
        }

        body.userPage .modal-content,
        body.userPage .profileModalContent,
        body.userPage .ballotModalContent {
            max-height: calc(100vh - 32px) !important;
            overflow: hidden !important;
            border-radius: 22px !important;
            box-shadow: 0 30px 90px rgba(16, 24, 40, 0.35) !important;
        }

        body.userPage .modal-body,
        body.userPage .profileModalBody,
        body.userPage .requestModalBody {
            max-height: calc(100vh - 220px) !important;
            overflow-y: auto !important;
        }

        @media (max-width: 576px) {
            body.userPage .modal-dialog {
                max-width: calc(100vw - 16px) !important;
                margin: 8px auto !important;
            }

            body.userPage .modal-content,
            body.userPage .profileModalContent,
            body.userPage .ballotModalContent {
                max-height: calc(100vh - 16px) !important;
                border-radius: 18px !important;
            }

            body.userPage .modal-body,
            body.userPage .profileModalBody,
            body.userPage .requestModalBody {
                max-height: calc(100vh - 190px) !important;
            }
        }
    </style>
    <style id="ivoteUserModalHardFix">
        body.userPage.modal-open,
        body.userPage.ivoteModalOpen {
            overflow: hidden !important;
            padding-right: 0 !important;
        }

        body.userPage .modal-backdrop,
        body.userPage .modal-backdrop.show {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
            z-index: -1 !important;
        }

        body.userPage .modal {
            position: fixed !important;
            inset: 0 !important;
            z-index: 2147483000 !important;
            padding: 18px !important;
            background: rgba(15, 23, 42, 0.62) !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            pointer-events: auto !important;
        }

        body.userPage .modal.show {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        body.userPage .modal-dialog {
            position: relative !important;
            z-index: 2147483001 !important;
            width: 100% !important;
            margin: auto !important;
            pointer-events: auto !important;
            transform: none !important;
        }

        body.userPage .modal-content,
        body.userPage .profileModalContent,
        body.userPage .ballotModalContent {
            position: relative !important;
            z-index: 2147483002 !important;
            pointer-events: auto !important;
            background: #ffffff !important;
            opacity: 1 !important;
        }

        body.userPage .modal-content *,
        body.userPage .profileModalContent *,
        body.userPage .ballotModalContent * {
            pointer-events: auto !important;
        }

        body.userPage.modal-open .userTopbar,
        body.userPage.ivoteModalOpen .userTopbar {
            z-index: 1 !important;
        }

        #ivoteNoticeOverlay {
            position: fixed;
            inset: 0;
            z-index: 2147483100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, 0.62);
        }

        #ivoteNoticeOverlay.show {
            display: flex;
        }

        .ivoteNoticeBox {
            width: min(520px, 100%);
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 90px rgba(16, 24, 40, 0.35);
            animation: ivoteNoticePop 0.18s ease-out;
        }

        .ivoteNoticeHeader {
            padding: 22px 24px;
            background: linear-gradient(135deg, #0647b8, #0b63e5);
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ivoteNoticeHeader i {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .ivoteNoticeHeader h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 950;
        }

        .ivoteNoticeBody {
            padding: 24px;
            color: #344054;
            font-size: 16px;
            line-height: 1.6;
        }

        .ivoteNoticeFooter {
            padding: 0 24px 24px;
            display: flex;
            justify-content: flex-end;
        }

        .ivoteNoticeBtn {
            border: 0;
            border-radius: 999px;
            background: #0647b8;
            color: #ffffff;
            font-weight: 950;
            padding: 13px 28px;
            min-width: 120px;
            box-shadow: 0 12px 26px rgba(6, 71, 184, 0.28);
        }

        .ivoteNoticeBtn:hover {
            background: #033587;
        }

        @keyframes ivoteNoticePop {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
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
                        <li><a href="startvoting.php" class="active"><i class="fa-solid fa-check-to-slot"></i>Voting</a>
                        </li>
                        <li><a href="results.php"><i class="fa-solid fa-chart-simple"></i>Results</a></li>
                        <li><a href="help.php"><i class="fa-solid fa-circle-question"></i>Help</a></li>
                    </ul>
                </div>
            </nav>
            <button type="button" class="profileNotifBtn" data-bs-toggle="modal"
                data-bs-target="#profileNotificationModal" title="Profile request notifications">
                <i class="fa-solid fa-bell"></i>
                <?php if (isset($profile_notification_count) && $profile_notification_count > 0) { ?>
                    <span><?php echo number_format($profile_notification_count); ?></span>
                <?php } ?>
            </button>

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
                        Voting opens from
                        <?php echo ivoteph_h(ivoteph_election_datetime($active_election, 'start_date')); ?> to
                        <?php echo ivoteph_h(ivoteph_election_datetime($active_election, 'end_date')); ?>.
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

            <?php if ($already_voted) { ?>
                <div class="alert alert-success rounded-4">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    Your ballot has already been submitted for this election. You cannot vote again, but you can review your
                    official submitted ballot anytime.
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary rounded-pill px-4 py-3 fw-bold" data-bs-toggle="modal"
                        data-bs-target="#submittedBallotModal">
                        <i class="fa-solid fa-file-signature me-2"></i>
                        View Submitted Ballot
                    </button>

                    <a href="results.php" class="btn btn-outline-primary rounded-pill px-4 py-3 fw-bold">
                        <i class="fa-solid fa-chart-simple me-2"></i>
                        View Results
                    </a>
                </div>
            <?php } elseif (!$active_election || $active_election_id <= 0) { ?>
                <div class="alert alert-info rounded-4 mb-0">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    No election schedule is configured yet. Please ask the admin to create an election in the admin panel.
                </div>
            <?php } elseif (!$voting_is_open) { ?>
                <div class="alert alert-warning rounded-4 mb-0">
                    <i class="fa-solid fa-lock me-2"></i>
                    Voting is currently closed. Please follow the official election schedule controlled by the admin.
                </div>
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

                        <div class="positionBlock" data-position-title="<?php echo ivoteph_h($group['position_name']); ?>"
                            data-is-senator="<?php echo $is_senator_position ? '1' : '0'; ?>" <?php echo $is_senator_position ? 'data-max-select="12"' : ''; ?>>
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
                                        <input type="<?php echo $input_type; ?>" name="<?php echo ivoteph_h($input_name); ?>"
                                            value="<?php echo ivoteph_h($candidate['candidate_id']); ?>"
                                            data-position-name="<?php echo ivoteph_h($group['position_name']); ?>"
                                            data-candidate-name="<?php echo ivoteph_h($candidate['full_name']); ?>" <?php echo $is_senator_position ? '' : 'required'; ?>>

                                        <div class="candidateVoteCard">
                                            <div class="candidateVoteTop">
                                                <div class="candidatePhoto">
                                                    <?php if ($candidate_photo != '') { ?>
                                                        <img src="<?php echo ivoteph_h($candidate_photo); ?>"
                                                            alt="<?php echo ivoteph_h($candidate['full_name']); ?>">
                                                    <?php } else { ?>
                                                        <?php echo ivoteph_h($candidate_initials); ?>
                                                    <?php } ?>
                                                </div>

                                                <div>
                                                    <h4 class="candidateVoteName"><?php echo ivoteph_h($candidate['full_name']); ?>
                                                    </h4>
                                                    <p class="candidateVoteParty">
                                                        <?php echo ivoteph_h($candidate['political_party']); ?>
                                                    </p>
                                                    <span
                                                        class="candidateVoteScope"><?php echo ivoteph_h(ivoteph_candidate_jurisdiction_label($candidate)); ?></span>
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

                        <button type="button" class="btn btn-primary btnVoteSubmit" onclick="openBallotConfirmation()">
                            <i class="fa-solid fa-eye me-2"></i>
                            Review Ballot
                        </button>
                    </div>
                </form>

            <?php } ?>
        </section>
    </main>

    <div class="modal fade" id="ballotConfirmModal" tabindex="-1" aria-labelledby="ballotConfirmModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content ballotModalContent">
                <div class="ballotModalHeader">
                    <h5 id="ballotConfirmModalLabel"><i class="fa-solid fa-file-circle-check me-2"></i>Confirm Your
                        Ballot</h5>
                    <p>Please review your ballot summary and selected candidates. Your vote cannot be edited after final
                        submission.</p>
                </div>

                <div class="modal-body p-4">
                    <div class="alert alert-warning rounded-4 mb-3">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        Once you click <strong>Confirm and Submit</strong>, your ballot will be locked.
                    </div>

                    <div class="ballotSummaryLayout">
                        <aside class="ballotSummaryPanel">
                            <h3 class="ballotSummaryTitle">Ballot Summary</h3>

                            <div class="ballotSummaryItem"><span>Voter
                                    ID</span><strong><?php echo ivoteph_h($profile_voter_id); ?></strong></div>
                            <div class="ballotSummaryItem"><span>Status</span><strong>For Review</strong></div>
                            <div class="ballotSummaryItem">
                                <span>Election</span><strong><?php echo ivoteph_h(ivoteph_election_title($active_election)); ?></strong>
                            </div>
                            <div class="ballotSummaryItem"><span>Ballot Reference</span><strong>Generated after
                                    submission</strong></div>
                            <div class="ballotSummaryItem"><span>Submitted At</span><strong>After confirmation</strong>
                            </div>
                            <div class="ballotSummaryItem"><span>Total Choices</span><strong
                                    id="ballotPreviewTotalChoices">0</strong></div>
                            <div class="ballotSummaryItem"><span>Privacy</span><strong>Confidential</strong></div>
                            <div class="ballotSummaryItem"><span>Voting Rule</span><strong>One voter, one
                                    ballot</strong></div>
                        </aside>

                        <section class="ballotChoicesPanel">
                            <h3 class="ballotChoicesTitle">Selected Candidates</h3>
                            <div id="ballotPreviewGroups" class="ballotPositionList"></div>
                        </section>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0 ballotModalActions">
                    <button type="button" class="btn btn-light rounded-pill px-4 py-3 fw-bold"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="voteForm" name="submit_ballot" value="1"
                        class="btn btn-primary rounded-pill px-4 py-3 fw-bold">
                        <i class="fa-solid fa-paper-plane me-2"></i>
                        Confirm and Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="submittedBallotModal" tabindex="-1" aria-labelledby="submittedBallotModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content ballotModalContent">
                <div class="ballotModalHeader">
                    <h5 id="submittedBallotModalLabel"><i class="fa-solid fa-circle-check me-2"></i>Submitted Ballot
                    </h5>
                    <p>This is your official submitted ballot for the active election.</p>
                </div>

                <div class="modal-body p-4">
                    <div class="ballotSummaryLayout">
                        <aside class="ballotSummaryPanel">
                            <h3 class="ballotSummaryTitle">Ballot Summary</h3>

                            <div class="ballotSummaryItem"><span>Voter
                                    ID</span><strong><?php echo ivoteph_h($profile_voter_id); ?></strong></div>
                            <div class="ballotSummaryItem"><span>Status</span><strong>Submitted</strong></div>
                            <div class="ballotSummaryItem">
                                <span>Election</span><strong><?php echo ivoteph_h(ivoteph_election_title($active_election)); ?></strong>
                            </div>
                            <div class="ballotSummaryItem"><span>Ballot
                                    Reference</span><strong><?php echo ivoteph_h($submitted_ballot_reference); ?></strong>
                            </div>
                            <div class="ballotSummaryItem"><span>Submitted
                                    At</span><strong><?php echo ivoteph_h($submitted_at_display); ?></strong></div>
                            <div class="ballotSummaryItem"><span>Total
                                    Choices</span><strong><?php echo ivoteph_h($existing_total_choices); ?></strong>
                            </div>
                            <div class="ballotSummaryItem"><span>Privacy</span><strong>Confidential</strong></div>
                            <div class="ballotSummaryItem"><span>Voting Rule</span><strong>One voter, one
                                    ballot</strong></div>
                        </aside>

                        <section class="ballotChoicesPanel">
                            <h3 class="ballotChoicesTitle">Selected Candidates by Position</h3>
                            <div class="ballotPositionList">
                                <?php foreach ($existing_votes_by_position as $submitted_position) { ?>
                                    <div class="ballotPositionGroup">
                                        <div class="ballotPositionGroupHeader">
                                            <strong><?php echo ivoteph_h($submitted_position['position_name']); ?></strong>
                                            <span><?php echo count($submitted_position['candidates']); ?> choice(s)</span>
                                        </div>

                                        <?php if (count($submitted_position['candidates']) > 1) { ?>
                                            <ol class="ballotCandidateList">
                                                <?php foreach ($submitted_position['candidates'] as $submitted_candidate_name) { ?>
                                                    <li><?php echo ivoteph_h($submitted_candidate_name); ?></li>
                                                <?php } ?>
                                            </ol>
                                        <?php } else { ?>
                                            <div class="ballotSingleCandidate">
                                                <?php echo ivoteph_h($submitted_position['candidates'][0]); ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0 ballotModalActions">
                    <button type="button" class="btn btn-light rounded-pill px-4 py-3 fw-bold"
                        data-bs-dismiss="modal">Close</button>
                    <a href="results.php" class="btn btn-primary rounded-pill px-4 py-3 fw-bold">
                        <i class="fa-solid fa-chart-simple me-2"></i>
                        View Results
                    </a>
                </div>
            </div>
        </div>
    </div>

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
                        <div class="profileFullItem"><span>Voter
                                ID</span><strong><?php echo ivoteph_h($profile_voter_id); ?></strong></div>
                        <div class="profileFullItem"><span>Registration
                                Status</span><strong><?php echo ivoteph_h($profile_registration_status); ?></strong>
                        </div>
                        <div class="profileFullItem"><span>Profile
                                Status</span><strong><?php echo ivoteph_h($profile_status); ?></strong></div>
                        <div class="profileFullItem"><span>Ballot
                                Status</span><strong><?php echo $already_voted ? 'Submitted' : 'Not Submitted'; ?></strong>
                        </div>
                        <div class="profileFullItem"><span>Account Type</span><strong>Voter</strong></div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-user"></i>Personal Information</div>

                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem"><span>First
                                Name</span><strong><?php echo ivoteph_h($profile_first_name); ?></strong></div>
                        <div class="profileFullItem"><span>Middle
                                Name</span><strong><?php echo ivoteph_h($profile_middle_name); ?></strong></div>
                        <div class="profileFullItem"><span>Last
                                Name</span><strong><?php echo ivoteph_h($profile_last_name); ?></strong></div>
                        <div class="profileFullItem"><span>Birth
                                Date</span><strong><?php echo ivoteph_h($profile_birth_date_display); ?></strong></div>
                        <div class="profileFullItem">
                            <span>Sex</span><strong><?php echo ivoteph_h($profile_sex); ?></strong>
                        </div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-address-book"></i>Contact Information</div>

                    <div class="profileFullGrid">
                        <div class="profileFullItem"><span>Email
                                Address</span><strong><?php echo ivoteph_h($profile_email); ?></strong></div>
                        <div class="profileFullItem"><span>Mobile
                                Number</span><strong><?php echo ivoteph_h($profile_mobile_number); ?></strong></div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-location-dot"></i>Registered Address</div>

                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem">
                            <span>Region</span><strong><?php echo ivoteph_h($profile_region); ?></strong>
                        </div>
                        <div class="profileFullItem">
                            <span>Province</span><strong><?php echo ivoteph_h($profile_province); ?></strong>
                        </div>
                        <div class="profileFullItem"><span>City /
                                Municipality</span><strong><?php echo ivoteph_h($profile_city_municipality); ?></strong>
                        </div>
                        <div class="profileFullItem">
                            <span>Barangay</span><strong><?php echo ivoteph_h($profile_barangay); ?></strong>
                        </div>
                        <div class="profileFullItem">
                            <span>Country</span><strong><?php echo ivoteph_h($profile_country); ?></strong>
                        </div>
                        <div class="profileFullItem profileFullWide"><span>Complete
                                Address</span><strong><?php echo ivoteph_h($profile_complete_address); ?></strong></div>
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

    <div class="modal fade" id="profileRequestModal" tabindex="-1" aria-labelledby="profileRequestModalLabel"
        aria-hidden="true">
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

                    <form id="profileChangeRequestForm" method="post" action="submit_profile_request.php"
                        onsubmit="submitProfileChangeRequest(event)">
                        <div class="mb-3">
                            <label for="requestField" class="form-label">Information to change</label>
                            <select class="form-select" id="requestField" name="request_field" required>
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
                            <textarea class="form-control" id="requestMessage" name="request_message" rows="4" required
                                placeholder="Example: My registered last name is misspelled."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-3 rounded-4 fw-bold">
                                <i class="fa-solid fa-paper-plane me-2"></i>Submit Request
                            </button>

                            <button type="button" class="btn btn-light py-3 rounded-4 fw-bold"
                                data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div>© 2026 iVotePH. Secure. Accessible. Transparent.</div>
    </footer>
    <div id="ivoteNoticeOverlay">
        <div class="ivoteNoticeBox">
            <div class="ivoteNoticeHeader">
                <i class="fa-solid fa-circle-exclamation"></i>
                <h3 id="ivoteNoticeTitle">Action Needed</h3>
            </div>

            <div class="ivoteNoticeBody" id="ivoteNoticeMessage">
                Please complete your ballot before submitting.
            </div>

            <div class="ivoteNoticeFooter">
                <button type="button" class="ivoteNoticeBtn" onclick="closeIvoteNotice()">Okay</button>
            </div>
        </div>
    </div>
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

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function openBallotConfirmation() {
            var form = document.getElementById('voteForm');
            var previewGroups = document.getElementById('ballotPreviewGroups');
            var previewTotalChoices = document.getElementById('ballotPreviewTotalChoices');

            if (!form || !previewGroups) {
                return;
            }

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var positionBlocks = form.querySelectorAll('.positionBlock');
            var html = '';
            var totalChoices = 0;

            for (var i = 0; i < positionBlocks.length; i++) {
                var block = positionBlocks[i];
                var positionName = block.getAttribute('data-position-title') || 'Position';
                var isSenator = block.getAttribute('data-is-senator') === '1';
                var checkedInputs = block.querySelectorAll('input:checked');

                if (isSenator) {
                    if (checkedInputs.length < 1) {
                        alert('Please select at least one candidate for Senator. You may select up to 12 senators.');
                        return;
                    }

                    if (checkedInputs.length > 12) {
                        alert('You can select up to 12 senators only.');
                        return;
                    }
                } else if (checkedInputs.length !== 1) {
                    alert('Please select one candidate for ' + positionName + '.');
                    return;
                }

                totalChoices += checkedInputs.length;

                html += '<div class="ballotPositionGroup">';
                html += '<div class="ballotPositionGroupHeader"><strong>' + escapeHtml(positionName) + '</strong><span>' + checkedInputs.length + ' choice(s)</span></div>';

                if (checkedInputs.length > 1) {
                    html += '<ol class="ballotCandidateList">';

                    for (var candidateIndex = 0; candidateIndex < checkedInputs.length; candidateIndex++) {
                        html += '<li>' + escapeHtml(checkedInputs[candidateIndex].getAttribute('data-candidate-name') || 'Selected candidate') + '</li>';
                    }

                    html += '</ol>';
                } else {
                    html += '<div class="ballotSingleCandidate">' + escapeHtml(checkedInputs[0].getAttribute('data-candidate-name') || 'Selected candidate') + '</div>';
                }

                html += '</div>';
            }

            previewGroups.innerHTML = html;

            if (previewTotalChoices) {
                previewTotalChoices.innerHTML = totalChoices;
            }

            var modalElement = document.getElementById('ballotConfirmModal');
            var modal = new bootstrap.Modal(modalElement);
            modal.show();
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

    <script id="ivotephFinalTopbarOffset">
        function ivotephUpdateFixedTopbarOffset() {
            var topbar = document.querySelector('.userTopbar');
            if (!topbar) {
                return;
            }
            document.documentElement.style.setProperty('--ivoteFixedTopbarHeight', topbar.offsetHeight + 'px');
        }

        window.addEventListener('load', ivotephUpdateFixedTopbarOffset);
        window.addEventListener('resize', ivotephUpdateFixedTopbarOffset);
        setTimeout(ivotephUpdateFixedTopbarOffset, 50);
    </script>
    <script>
        document.addEventListener('show.bs.modal', function () {
            document.body.classList.add('ivoteModalOpen');
        });

        document.addEventListener('hidden.bs.modal', function () {
            var openModals = document.querySelectorAll('.modal.show');

            if (openModals.length === 0) {
                document.body.classList.remove('ivoteModalOpen');
            }
        });
    </script>
    <style>
        .ivoteSystemAlertOverlay {
            position: fixed;
            inset: 0;
            z-index: 9999999;
            background: rgba(15, 23, 42, 0.62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .ivoteSystemAlertOverlay.show {
            display: flex;
        }

        .ivoteSystemAlertBox {
            width: min(520px, 100%);
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 30px 90px rgba(16, 24, 40, 0.35);
            overflow: hidden;
            animation: ivoteAlertPop 0.18s ease-out;
        }

        .ivoteSystemAlertHeader {
            background: linear-gradient(135deg, #0647b8, #0b63e5);
            color: #ffffff;
            padding: 22px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ivoteSystemAlertIcon {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .ivoteSystemAlertHeader h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 950;
            letter-spacing: -0.03em;
        }

        .ivoteSystemAlertBody {
            padding: 24px;
            color: #344054;
            font-size: 16px;
            line-height: 1.6;
        }

        .ivoteSystemAlertFooter {
            padding: 0 24px 24px;
            display: flex;
            justify-content: flex-end;
        }

        .ivoteSystemAlertBtn {
            border: 0;
            border-radius: 999px;
            background: #0647b8;
            color: #ffffff;
            font-weight: 950;
            padding: 13px 28px;
            min-width: 120px;
            box-shadow: 0 12px 26px rgba(6, 71, 184, 0.28);
        }

        .ivoteSystemAlertBtn:hover {
            background: #033587;
        }

        @keyframes ivoteAlertPop {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 576px) {
            .ivoteSystemAlertHeader {
                padding: 18px;
            }

            .ivoteSystemAlertBody {
                padding: 20px;
            }

            .ivoteSystemAlertFooter {
                padding: 0 20px 20px;
            }

            .ivoteSystemAlertHeader h3 {
                font-size: 19px;
            }
        }
    </style>

    <div class="ivoteSystemAlertOverlay" id="ivoteSystemAlertOverlay">
        <div class="ivoteSystemAlertBox">
            <div class="ivoteSystemAlertHeader">
                <div class="ivoteSystemAlertIcon">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
                <div>
                    <h3 id="ivoteSystemAlertTitle">Notice</h3>
                </div>
            </div>

            <div class="ivoteSystemAlertBody" id="ivoteSystemAlertMessage">
                Message here.
            </div>

            <div class="ivoteSystemAlertFooter">
                <button type="button" class="ivoteSystemAlertBtn" onclick="closeIvoteSystemAlert()">
                    Okay
                </button>
            </div>
        </div>
    </div>

    <style id="ivoteFinalModalClickFix">
        body.userPage.modal-open,
        body.userPage.ivoteModalOpen {
            overflow: hidden !important;
            padding-right: 0 !important;
        }

        body.userPage .modal-backdrop,
        body.userPage .modal-backdrop.show {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
            pointer-events: none !important;
            z-index: -9999 !important;
        }

        body.userPage .modal {
            position: fixed !important;
            inset: 0 !important;
            z-index: 2147483000 !important;
            padding: 18px !important;
            background: rgba(15, 23, 42, 0.62) !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            pointer-events: auto !important;
            opacity: 1 !important;
        }

        body.userPage .modal:not(.show) {
            display: none !important;
        }

        body.userPage .modal.show {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        body.userPage .modal-dialog {
            position: relative !important;
            z-index: 2147483001 !important;
            width: 100% !important;
            margin: auto !important;
            pointer-events: auto !important;
            transform: none !important;
        }

        body.userPage #ballotConfirmModal .modal-dialog,
        body.userPage #submittedBallotModal .modal-dialog {
            max-width: min(980px, calc(100vw - 24px)) !important;
        }

        body.userPage #profileModal .modal-dialog {
            max-width: min(980px, calc(100vw - 24px)) !important;
        }

        body.userPage #profileRequestModal .modal-dialog {
            max-width: min(560px, calc(100vw - 24px)) !important;
        }

        body.userPage .modal-content,
        body.userPage .profileModalContent,
        body.userPage .ballotModalContent {
            position: relative !important;
            z-index: 2147483002 !important;
            pointer-events: auto !important;
            background: #ffffff !important;
            opacity: 1 !important;
            filter: none !important;
            max-height: calc(100vh - 36px) !important;
            overflow: hidden !important;
            border-radius: 22px !important;
            box-shadow: 0 30px 90px rgba(16, 24, 40, 0.35) !important;
        }

        body.userPage .modal-content *,
        body.userPage .profileModalContent *,
        body.userPage .ballotModalContent * {
            pointer-events: auto !important;
            user-select: auto !important;
        }

        body.userPage .modal-body,
        body.userPage .profileModalBody,
        body.userPage .requestModalBody {
            max-height: calc(100vh - 220px) !important;
            overflow-y: auto !important;
        }

        body.userPage.modal-open .userTopbar,
        body.userPage.ivoteModalOpen .userTopbar {
            z-index: 1 !important;
        }

        #ivoteNoticeOverlay {
            position: fixed !important;
            inset: 0 !important;
            z-index: 2147483100 !important;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(15, 23, 42, 0.62);
        }

        #ivoteNoticeOverlay.show {
            display: flex !important;
        }

        .ivoteNoticeBox {
            width: min(520px, 100%);
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 90px rgba(16, 24, 40, 0.35);
        }

        .ivoteNoticeHeader {
            padding: 22px 24px;
            background: linear-gradient(135deg, #0647b8, #0b63e5);
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .ivoteNoticeHeader i {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .ivoteNoticeHeader h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 950;
        }

        .ivoteNoticeBody {
            padding: 24px;
            color: #344054;
            font-size: 16px;
            line-height: 1.6;
        }

        .ivoteNoticeFooter {
            padding: 0 24px 24px;
            display: flex;
            justify-content: flex-end;
        }

        .ivoteNoticeBtn {
            border: 0;
            border-radius: 999px;
            background: #0647b8;
            color: #ffffff;
            font-weight: 950;
            padding: 13px 28px;
            min-width: 120px;
            box-shadow: 0 12px 26px rgba(6, 71, 184, 0.28);
        }

        .ivoteNoticeBtn:hover {
            background: #033587;
        }

        @media (max-width: 576px) {
            body.userPage .modal {
                padding: 8px !important;
            }

            body.userPage .modal-content,
            body.userPage .profileModalContent,
            body.userPage .ballotModalContent {
                max-height: calc(100vh - 16px) !important;
                border-radius: 18px !important;
            }

            body.userPage .modal-body,
            body.userPage .profileModalBody,
            body.userPage .requestModalBody {
                max-height: calc(100vh - 190px) !important;
            }
        }
    </style>

    <script id="ivoteFinalModalClickFixScript">
        (function () {
            function removeBootstrapBackdrops() {
                var backdrops = document.querySelectorAll('.modal-backdrop');

                for (var i = 0; i < backdrops.length; i++) {
                    backdrops[i].parentNode.removeChild(backdrops[i]);
                }

                document.body.style.paddingRight = '';
            }

            function getModalElement(modalTarget) {
                if (!modalTarget) {
                    return null;
                }

                if (typeof modalTarget === 'string') {
                    return document.getElementById(modalTarget.replace('#', ''));
                }

                return modalTarget;
            }

            function openIvoteModal(modalTarget) {
                var modal = getModalElement(modalTarget);

                if (!modal) {
                    return;
                }

                removeBootstrapBackdrops();

                var openModals = document.querySelectorAll('.modal.show');

                for (var i = 0; i < openModals.length; i++) {
                    if (openModals[i] !== modal) {
                        closeIvoteModal(openModals[i], false);
                    }
                }

                modal.classList.add('show');
                modal.removeAttribute('aria-hidden');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('role', 'dialog');
                modal.style.display = 'flex';

                document.body.classList.add('modal-open');
                document.body.classList.add('ivoteModalOpen');
                document.body.style.overflow = 'hidden';

                setTimeout(function () {
                    var focusable = modal.querySelector('select, input, textarea, button, a[href]');

                    if (focusable) {
                        focusable.focus({ preventScroll: true });
                    }
                }, 30);
            }

            function closeIvoteModal(modalTarget, restoreBody) {
                var modal = getModalElement(modalTarget);

                if (!modal) {
                    return;
                }

                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.style.display = 'none';

                removeBootstrapBackdrops();

                if (restoreBody !== false && document.querySelectorAll('.modal.show').length === 0) {
                    document.body.classList.remove('modal-open');
                    document.body.classList.remove('ivoteModalOpen');
                    document.body.style.overflow = '';
                }
            }

            function ensureNoticeOverlay() {
                var existing = document.getElementById('ivoteNoticeOverlay');

                if (existing) {
                    return existing;
                }

                var overlay = document.createElement('div');
                overlay.id = 'ivoteNoticeOverlay';
                overlay.innerHTML =
                    '<div class="ivoteNoticeBox">' +
                    '<div class="ivoteNoticeHeader">' +
                    '<i class="fa-solid fa-circle-exclamation"></i>' +
                    '<h3 id="ivoteNoticeTitle">Action Needed</h3>' +
                    '</div>' +
                    '<div class="ivoteNoticeBody" id="ivoteNoticeMessage">Please complete the required action.</div>' +
                    '<div class="ivoteNoticeFooter">' +
                    '<button type="button" class="ivoteNoticeBtn" onclick="closeIvoteNotice()">Okay</button>' +
                    '</div>' +
                    '</div>';

                document.body.appendChild(overlay);
                return overlay;
            }

            window.showIvoteNotice = function (message, title) {
                var overlay = ensureNoticeOverlay();
                var titleBox = document.getElementById('ivoteNoticeTitle');
                var messageBox = document.getElementById('ivoteNoticeMessage');

                titleBox.textContent = title || 'Action Needed';
                messageBox.textContent = message || 'Please complete the required action.';

                overlay.classList.add('show');
                document.body.classList.add('ivoteModalOpen');
            };

            window.closeIvoteNotice = function () {
                var overlay = document.getElementById('ivoteNoticeOverlay');

                if (overlay) {
                    overlay.classList.remove('show');
                }

                if (document.querySelectorAll('.modal.show').length === 0) {
                    document.body.classList.remove('modal-open');
                    document.body.classList.remove('ivoteModalOpen');
                    document.body.style.overflow = '';
                }
            };

            window.alert = function (message) {
                showIvoteNotice(message, 'Action Needed');
            };

            window.ivoteOpenModal = openIvoteModal;
            window.ivoteCloseModal = closeIvoteModal;

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function getBallotValidationData() {
                var form = document.getElementById('voteForm');
                var data = {
                    form: form,
                    totalChoices: 0,
                    missingPositions: [],
                    invalidMessage: '',
                    previewHtml: ''
                };

                if (!form) {
                    data.invalidMessage = 'Voting form was not found.';
                    return data;
                }

                var positionBlocks = form.querySelectorAll('.positionBlock');

                for (var i = 0; i < positionBlocks.length; i++) {
                    var block = positionBlocks[i];
                    var positionName = block.getAttribute('data-position-title') || 'Position';
                    var isSenator = block.getAttribute('data-is-senator') === '1';
                    var checkedInputs = block.querySelectorAll('input:checked');

                    if (isSenator) {
                        if (checkedInputs.length < 1) {
                            data.missingPositions.push('Senator');
                            continue;
                        }

                        if (checkedInputs.length > 12) {
                            data.invalidMessage = 'You can select up to 12 senators only.';
                            return data;
                        }
                    } else {
                        if (checkedInputs.length !== 1) {
                            data.missingPositions.push(positionName);
                            continue;
                        }
                    }

                    data.totalChoices += checkedInputs.length;

                    data.previewHtml += '<div class="ballotPositionGroup">';
                    data.previewHtml += '<div class="ballotPositionGroupHeader"><strong>' + escapeHtml(positionName) + '</strong><span>' + checkedInputs.length + ' choice(s)</span></div>';

                    if (checkedInputs.length > 1) {
                        data.previewHtml += '<ol class="ballotCandidateList">';

                        for (var c = 0; c < checkedInputs.length; c++) {
                            data.previewHtml += '<li>' + escapeHtml(checkedInputs[c].getAttribute('data-candidate-name') || 'Selected candidate') + '</li>';
                        }

                        data.previewHtml += '</ol>';
                    } else {
                        data.previewHtml += '<div class="ballotSingleCandidate">' + escapeHtml(checkedInputs[0].getAttribute('data-candidate-name') || 'Selected candidate') + '</div>';
                    }

                    data.previewHtml += '</div>';
                }

                return data;
            }

            window.openBallotConfirmation = function () {
                var previewGroups = document.getElementById('ballotPreviewGroups');
                var previewTotalChoices = document.getElementById('ballotPreviewTotalChoices');
                var data = getBallotValidationData();

                if (!data.form || !previewGroups) {
                    showIvoteNotice('Voting form was not found. Please refresh the page.', 'Form Error');
                    return;
                }

                if (data.invalidMessage !== '') {
                    showIvoteNotice(data.invalidMessage, 'Selection Error');
                    return;
                }

                if (data.totalChoices === 0) {
                    showIvoteNotice('You cannot submit a blank ballot. Please select candidates first.', 'No Votes Selected');
                    return;
                }

                if (data.missingPositions.length > 0) {
                    showIvoteNotice(
                        'Please complete your vote first. Missing position(s): ' + data.missingPositions.join(', ') + '.',
                        'Incomplete Ballot'
                    );
                    return;
                }

                previewGroups.innerHTML = data.previewHtml;

                if (previewTotalChoices) {
                    previewTotalChoices.textContent = data.totalChoices;
                }

                openIvoteModal('ballotConfirmModal');
            };

            window.openProfileRequestModal = function () {
                closeIvoteModal('profileModal', false);

                setTimeout(function () {
                    openIvoteModal('profileRequestModal');
                }, 120);
            };

            window.submitProfileChangeRequest = function (event) {
                event.preventDefault();

                var requestField = document.getElementById('requestField');
                var requestMessage = document.getElementById('requestMessage');

                if (!requestField || !requestMessage || !requestField.value || !requestMessage.value.trim()) {
                    showIvoteNotice('Please select the information to change and enter your correction details.', 'Incomplete Request');
                    return;
                }

                showIvoteNotice('Your profile change request has been prepared.', 'Request Prepared');

                var form = document.getElementById('profileChangeRequestForm');

                if (form) {
                    form.reset();
                }

                closeIvoteModal('profileRequestModal');
            };

            window.logoutUser = function () {
                window.location.href = 'logout.php';
            };

            document.addEventListener('DOMContentLoaded', function () {
                removeBootstrapBackdrops();

                var voteForm = document.getElementById('voteForm');

                if (voteForm) {
                    voteForm.setAttribute('novalidate', 'novalidate');
                }
            });

            document.addEventListener('click', function (event) {
                var modalOpener = event.target.closest('[data-bs-toggle="modal"][data-bs-target]');

                if (modalOpener) {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    openIvoteModal(modalOpener.getAttribute('data-bs-target'));
                    return;
                }

                var modalCloser = event.target.closest('[data-bs-dismiss="modal"]');

                if (modalCloser) {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    var parentModal = modalCloser.closest('.modal');

                    if (parentModal) {
                        closeIvoteModal(parentModal);
                    }

                    return;
                }

                if (event.target.classList && event.target.classList.contains('modal')) {
                    closeIvoteModal(event.target);
                }
            }, true);

            document.addEventListener('change', function (event) {
                var input = event.target;

                if (!input || input.type !== 'checkbox') {
                    return;
                }

                var block = input.closest('[data-max-select]');

                if (!block) {
                    return;
                }

                var maxSelect = parseInt(block.getAttribute('data-max-select'), 10);
                var checkedBoxes = block.querySelectorAll('input[type="checkbox"]:checked');

                if (checkedBoxes.length > maxSelect) {
                    input.checked = false;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showIvoteNotice('You can select up to ' + maxSelect + ' senators only.', 'Selection Limit');
                }
            }, true);

            document.addEventListener('submit', function (event) {
                if (!event.target || event.target.id !== 'voteForm') {
                    return;
                }

                var data = getBallotValidationData();

                if (data.invalidMessage !== '') {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showIvoteNotice(data.invalidMessage, 'Selection Error');
                    return;
                }

                if (data.totalChoices === 0) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showIvoteNotice('You cannot submit a blank ballot. Please select candidates first.', 'No Votes Selected');
                    return;
                }

                if (data.missingPositions.length > 0) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showIvoteNotice(
                        'Please complete your vote first. Missing position(s): ' + data.missingPositions.join(', ') + '.',
                        'Incomplete Ballot'
                    );
                }
            }, true);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    var notice = document.getElementById('ivoteNoticeOverlay');

                    if (notice && notice.classList.contains('show')) {
                        closeIvoteNotice();
                        return;
                    }

                    var openModals = document.querySelectorAll('.modal.show');

                    if (openModals.length > 0) {
                        closeIvoteModal(openModals[openModals.length - 1]);
                    }
                }
            });
        })();
    </script>
    <script>
        window.submitProfileChangeRequest = function (event) {
            event.preventDefault();

            var form = document.getElementById('profileChangeRequestForm');
            var requestField = document.getElementById('requestField');
            var requestMessage = document.getElementById('requestMessage');

            if (!form || !requestField || !requestMessage) {
                alert('Profile request form was not found.');
                return false;
            }

            if (!requestField.value || !requestMessage.value.trim()) {
                alert('Please select the information to change and enter your correction details.');
                return false;
            }

            var submitButton = form.querySelector('button[type="submit"]');
            var originalButtonText = '';

            if (submitButton) {
                originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Submitting...';
            }

            var formData = new FormData();
            formData.append('voter_id', <?php echo json_encode($profile_voter_id); ?>);
            formData.append('request_field', requestField.value);
            formData.append('request_message', requestMessage.value);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'submit_profile_request.php', true);

            xhr.onload = function () {
                var response;

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }

                try {
                    response = JSON.parse(xhr.responseText);
                } catch (error) {
                    alert('Invalid server response. Check submit_profile_request.php.');
                    return;
                }

                if (response.success) {
                    form.reset();

                    var requestModalElement = document.getElementById('profileRequestModal');

                    if (typeof ivoteCloseModal === 'function') {
                        ivoteCloseModal('profileRequestModal');
                    } else if (window.bootstrap && requestModalElement) {
                        var requestModal = bootstrap.Modal.getInstance(requestModalElement);

                        if (requestModal) {
                            requestModal.hide();
                        }
                    }

                    alert(response.message);
                } else {
                    alert(response.message);
                }
            };

            xhr.onerror = function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }

                alert('Connection error. Please try again.');
            };

            xhr.send(formData);

            return false;
        };
    </script>

    <div class="modal fade" id="profileNotificationModal" tabindex="-1" aria-labelledby="profileNotificationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <h5 id="profileNotificationModalLabel">Notifications</h5>
                    <p>Your profile request updates from the admin side.</p>
                </div>

                <div class="requestModalBody">
                    <?php if (!isset($profile_notifications) || count($profile_notifications) === 0) { ?>
                        <div class="requestNotice mb-0">
                            <i class="fa-solid fa-circle-info me-2"></i>
                            You do not have profile request notifications yet.
                        </div>
                    <?php } else { ?>
                        <div class="profileNotifList">
                            <?php foreach ($profile_notifications as $notification) { ?>
                                <div class="profileNotifItem">
                                    <div class="profileNotifTop">
                                        <div>
                                            <strong><?php echo ivoteph_h($notification['request_field']); ?></strong>
                                            <small>
                                                Submitted:
                                                <?php echo ivoteph_h(ivoteph_profile_request_date($notification['created_at'])); ?>
                                            </small>
                                        </div>

                                        <span
                                            class="badge text-bg-<?php echo ivoteph_profile_request_badge_class($notification['request_status']); ?>">
                                            <?php echo ivoteph_h($notification['request_status']); ?>
                                        </span>
                                    </div>

                                    <div class="profileNotifText">
                                        <strong>Your request:</strong><br>
                                        <?php echo nl2br(ivoteph_h($notification['request_message'])); ?>
                                    </div>

                                    <?php if ($notification['admin_response'] !== null && trim((string) $notification['admin_response']) !== '') { ?>
                                        <div class="profileNotifResponse">
                                            <strong>Admin response:</strong><br>
                                            <?php echo nl2br(ivoteph_h($notification['admin_response'])); ?>

                                            <?php if ($notification['reviewed_at'] !== null && trim((string) $notification['reviewed_at']) !== '') { ?>
                                                <small>
                                                    Reviewed:
                                                    <?php echo ivoteph_h(ivoteph_profile_request_date($notification['reviewed_at'])); ?>
                                                </small>
                                            <?php } ?>
                                        </div>
                                    <?php } else { ?>
                                        <div class="profileNotifPending">
                                            <i class="fa-solid fa-clock me-1"></i>
                                            Waiting for admin response.
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>


    <script id="ivoteProfileRequestFinalSubmitFix">
        window.submitProfileChangeRequest = function (event) {
            event.preventDefault();

            var form = document.getElementById('profileChangeRequestForm');
            var requestField = document.getElementById('requestField');
            var requestMessage = document.getElementById('requestMessage');

            if (!form || !requestField || !requestMessage) {
                alert('Profile request form was not found.');
                return false;
            }

            if (!requestField.value || !requestMessage.value.trim()) {
                if (typeof showIvoteNotice === 'function') {
                    showIvoteNotice('Please select the information to change and enter your correction details.', 'Incomplete Request');
                } else {
                    alert('Please select the information to change and enter your correction details.');
                }

                return false;
            }

            var submitButton = form.querySelector('button[type="submit"]');
            var originalButtonText = '';

            if (submitButton) {
                originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Submitting...';
            }

            var formData = new FormData();
            formData.append('voter_id', <?php echo json_encode($profile_voter_id); ?>);
            formData.append('request_field', requestField.value);
            formData.append('request_message', requestMessage.value.trim());

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'submit_profile_request.php', true);

            xhr.onload = function () {
                var response = null;

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }

                try {
                    response = JSON.parse(xhr.responseText);
                } catch (error) {
                    alert('Invalid server response. Please check submit_profile_request.php.');
                    return;
                }

                if (response.success) {
                    form.reset();

                    if (typeof ivoteCloseModal === 'function') {
                        ivoteCloseModal('profileRequestModal');
                    } else if (window.bootstrap) {
                        var requestModalElement = document.getElementById('profileRequestModal');
                        var requestModal = requestModalElement ? bootstrap.Modal.getInstance(requestModalElement) : null;

                        if (requestModal) {
                            requestModal.hide();
                        }
                    }

                    if (typeof showIvoteNotice === 'function') {
                        showIvoteNotice(response.message, 'Request Submitted');
                    } else {
                        alert(response.message);
                    }

                    setTimeout(function () {
                        window.location.reload();
                    }, 900);
                } else {
                    if (typeof showIvoteNotice === 'function') {
                        showIvoteNotice(response.message, 'Request Failed');
                    } else {
                        alert(response.message);
                    }
                }
            };

            xhr.onerror = function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }

                alert('Connection error. Please try again.');
            };

            xhr.send(formData);
            return false;
        };
    </script>

</body>

</html>