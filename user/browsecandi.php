<?php require_once __DIR__ . '/auth_check.php'; ?>

<?php
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

function ivoteph_initials_from_name($name) {
    $name = trim($name);

    if ($name == '') {
        return 'C';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';

    if (isset($parts[0]) && $parts[0] != '') {
        $initials .= strtoupper(substr($parts[0], 0, 1));
    }

    if (count($parts) > 1) {
        $last_part = $parts[count($parts) - 1];

        if ($last_part != '') {
            $initials .= strtoupper(substr($last_part, 0, 1));
        }
    }

    if ($initials == '') {
        $initials = 'C';
    }

    return $initials;
}

function ivoteph_candidate_photo($photo) {
    $photo = trim($photo);

    if ($photo == '') {
        return '';
    }

    if (strpos($photo, 'http://') === 0 || strpos($photo, 'https://') === 0) {
        return $photo;
    }

    if (file_exists(__DIR__ . '/../admin/assets/uploads/candidates/' . $photo)) {
        return '../admin/assets/uploads/candidates/' . rawurlencode($photo);
    }

    if (file_exists(__DIR__ . '/../admin/assets/uploads/' . $photo)) {
        return '../admin/assets/uploads/' . rawurlencode($photo);
    }

    if (file_exists(__DIR__ . '/../admin/assets/img/' . $photo)) {
        return '../admin/assets/img/' . $photo;
    }

    if (file_exists(__DIR__ . '/' . $photo)) {
        return $photo;
    }

    return $photo;
}

/* Logged-in voter profile data */
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
$profile_account_access = 'Active';
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
        a.is_active,
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
        $db_profile_is_active,
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
        $profile_account_access = ($db_profile_is_active == 1) ? 'Active' : 'Inactive';
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

/* Candidate data */
$candidates = array();
$positions = array();

$profile_province_sql = mysqli_real_escape_string($conn, trim((string) $profile_province));
$profile_city_normalized = preg_replace('/\s+(city|municipality)$/i', '', trim((string) $profile_city_municipality));
$profile_city_normalized_sql = mysqli_real_escape_string($conn, $profile_city_normalized);

$candidate_scope_filter_sql = "
    (
        (
            LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%governor%'
            AND TRIM(IFNULL(c.province, '')) <> ''
            AND LOWER(TRIM(IFNULL(c.province, ''))) = LOWER(TRIM('" . $profile_province_sql . "'))
        )
        OR
        (
            LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%mayor%'
            AND TRIM(IFNULL(c.province, '')) <> ''
            AND TRIM(IFNULL(c.city_municipality, '')) <> ''
            AND LOWER(TRIM(IFNULL(c.province, ''))) = LOWER(TRIM('" . $profile_province_sql . "'))
            AND
            (
                LOWER(TRIM(IFNULL(c.city_municipality, ''))) = LOWER(TRIM('" . $profile_city_normalized_sql . "'))
                OR REPLACE(REPLACE(LOWER(TRIM(IFNULL(c.city_municipality, ''))), ' city', ''), ' municipality', '') = LOWER(TRIM('" . $profile_city_normalized_sql . "'))
            )
        )
        OR
        (
            LOWER(TRIM(IFNULL(p.position_name, ''))) NOT LIKE '%governor%'
            AND LOWER(TRIM(IFNULL(p.position_name, ''))) NOT LIKE '%mayor%'
        )
    )";

$sql_candidates = "
    SELECT
        c.candidate_id,
        c.full_name,
        c.political_party,
        c.photo,
        c.platform,
        c.election_scope,
        c.region,
        c.province,
        c.city_municipality,
        p.position_name
    FROM candidates c
    LEFT JOIN positions p ON c.position_id = p.position_id
    WHERE " . $candidate_scope_filter_sql . "
    ORDER BY
        CASE
            WHEN LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%president%' AND LOWER(TRIM(IFNULL(p.position_name, ''))) NOT LIKE '%vice%' THEN 1
            WHEN LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%vice%president%' THEN 2
            WHEN LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%senator%' THEN 3
            WHEN LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%party%' AND LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%list%' THEN 4
            WHEN LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%governor%' THEN 5
            WHEN LOWER(TRIM(IFNULL(p.position_name, ''))) LIKE '%mayor%' THEN 6
            ELSE 99
        END ASC,
        p.position_id ASC,
        c.candidate_id DESC
";

$result_candidates = mysqli_query($conn, $sql_candidates);

if ($result_candidates) {
    while ($row = mysqli_fetch_assoc($result_candidates)) {
        $position_name = $row['position_name'];

        if ($position_name == '') {
            $position_name = 'Unassigned';
        }

        $row['position_name'] = $position_name;
        $candidates[] = $row;

        if (!in_array($position_name, $positions)) {
            $positions[] = $position_name;
        }
    }

    mysqli_free_result($result_candidates);
}

$total_candidates = count($candidates);
$total_positions = count($positions);


/* iVotePH profile request notification data */
if (!function_exists('ivoteph_profile_notif_table_exists')) {
    function ivoteph_profile_notif_table_exists($conn, $table_name)
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

if (!function_exists('ivoteph_profile_notif_column_exists')) {
    function ivoteph_profile_notif_column_exists($conn, $table_name, $column_name)
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
}

if (!function_exists('ivoteph_profile_notif_badge_class')) {
    function ivoteph_profile_notif_badge_class($status)
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

if (!function_exists('ivoteph_profile_notif_date')) {
    function ivoteph_profile_notif_date($value)
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

if (isset($conn) && $conn && isset($profile_voter_id) && trim((string) $profile_voter_id) !== '' && ivoteph_profile_notif_table_exists($conn, 'profile_change_requests')) {
    if (!ivoteph_profile_notif_column_exists($conn, 'profile_change_requests', 'user_seen_at')) {
        mysqli_query($conn, "ALTER TABLE profile_change_requests ADD user_seen_at DATETIME NULL");
    }

    $stmt_profile_notifications = mysqli_prepare($conn, "
        SELECT
            request_id,
            request_field,
            request_message,
            request_status,
            admin_response,
            created_at,
            reviewed_at,
            user_seen_at
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
            $notif_reviewed_at,
            $notif_user_seen_at
        );

        while (mysqli_stmt_fetch($stmt_profile_notifications)) {
            $profile_notifications[] = array(
                'request_id' => $notif_request_id,
                'request_field' => $notif_request_field,
                'request_message' => $notif_request_message,
                'request_status' => $notif_request_status,
                'admin_response' => $notif_admin_response,
                'created_at' => $notif_created_at,
                'reviewed_at' => $notif_reviewed_at,
                'user_seen_at' => $notif_user_seen_at
            );

            if (($notif_request_status === 'Approved' || $notif_request_status === 'Rejected' || $notif_request_status === 'Resolved') && ($notif_user_seen_at === null || trim((string) $notif_user_seen_at) === '' || $notif_user_seen_at === '0000-00-00 00:00:00')) {
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
    <title>Browse Candidates - iVotePH</title>
    <link rel="icon" type="image/png" href="logo.png">

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
            background: #ffffff;
            border: 1px solid var(--userLine);
            text-decoration: none;
        }

        .brandLogo {
            display: block;
            width: 66px !important;
            max-width: 66px !important;
            height: auto !important;
            max-height: 32px !important;
            object-fit: contain !important;
        }

        .userNavBar {
            background: transparent !important;
            padding: 0 !important;
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
            gap: 4px;
            overflow: hidden;
            min-width: 0;
        }

        .userNavList li {
            list-style: none !important;
            min-width: 0;
        }

        .userNavList a {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 8px 8px;
            border-radius: 999px;
            background: #f4f6fb;
            color: #3f4350;
            font-size: 10.5px;
            font-weight: 800;
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
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .summaryCard {
            padding: 20px;
        }

        .summaryCard span {
            display: block;
            color: var(--userMuted);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }

        .summaryCard strong {
            display: block;
            color: var(--userBlue);
            font-size: 28px;
            line-height: 1;
            font-weight: 950;
        }

        .filterCard {
            padding: 18px;
            margin-bottom: 18px;
        }

        .filterHeader {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .filterHeader h2 {
            margin: 0;
            font-weight: 950;
            letter-spacing: -0.03em;
        }

        .positionFilters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filterBtn {
            border: none;
            min-height: 38px;
            padding: 8px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
            color: #3f4350;
            background: #f4f6fb;
            cursor: pointer;
        }

        .filterBtn.active {
            color: #ffffff;
            background: #0b5ed7;
            box-shadow: 0 10px 22px rgba(6, 70, 168, 0.18);
        }

        .candidateGrid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .candidateCard {
            padding: 20px;
            transition: 0.22s ease;
        }

        .candidateCard:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 30px rgba(6, 70, 168, 0.13);
        }

        .candidateTop {
            display: flex;
            gap: 14px;
            align-items: center;
            margin-bottom: 14px;
        }

        .candidatePhoto {
            width: 68px;
            height: 68px;
            min-width: 68px;
            border-radius: 20px;
            overflow: hidden;
            background: var(--userBlueSoft);
            color: var(--userBlue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 950;
        }

        .candidatePhoto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .candidateName {
            margin: 0 0 5px;
            font-size: 18px;
            font-weight: 950;
            color: var(--userInk);
            line-height: 1.15;
        }

        .partyName {
            color: var(--userMuted);
            font-size: 12px;
            font-weight: 800;
        }

        .positionBadge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 13px;
            padding: 7px 11px;
            border-radius: 999px;
            background: var(--userBlueSoft);
            color: var(--userBlue);
            font-size: 11px;
            font-weight: 950;
        }

        .platformText {
            color: var(--userMuted);
            font-size: 13px;
            line-height: 1.65;
            margin-bottom: 0;
        }

        .emptyState {
            padding: 30px;
            text-align: center;
        }

        .emptyState i {
            font-size: 42px;
            color: var(--userBlue);
            margin-bottom: 12px;
        }

        .emptyState h3 {
            font-weight: 950;
            margin-bottom: 8px;
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

            .candidateGrid,
            .summaryGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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
.candidateGrid,
            .summaryGrid,
            .profileFullGrid,
            .profileFullGrid.threeCols,
            .profileModalActions {
                grid-template-columns: 1fr;
            }

            .filterHeader {
                align-items: flex-start;
                flex-direction: column;
            }

            #profileModal .modal-dialog {
                max-width: calc(100vw - 16px);
                margin: 8px auto;
            }

            #profileModal .profileModalContent {
                max-height: calc(100vh - 16px);
                border-radius: 20px;
            }

            .profileModalHeader {
                padding: 20px 16px;
            }

            .profileModalAvatar {
                width: 62px;
                height: 62px;
                font-size: 20px;
            }

            .profileModalBody,
            .requestModalBody {
                padding: 16px;
            }

            .profileModalActions {
                padding: 12px 16px;
            }
        }
    </style>


<style id="ivoteProfileNotificationStyleFix">
    .profileNotifBtn {
        width: 46px !important;
        height: 46px !important;
        min-width: 46px !important;
        border: 1px solid var(--userLine, #dce5f2) !important;
        border-radius: 50% !important;
        background: #ffffff !important;
        color: var(--userBlue, #0646a8) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        position: relative !important;
        box-shadow: 0 10px 22px rgba(16, 24, 40, 0.08) !important;
        cursor: pointer !important;
    }

    .profileNotifBtn:hover {
        background: #eaf2ff !important;
        transform: translateY(-1px) !important;
    }

    .profileNotifBtn span,
    #profileNotificationBadge {
        position: absolute !important;
        top: -4px !important;
        right: -4px !important;
        min-width: 20px !important;
        height: 20px !important;
        padding: 0 6px !important;
        border-radius: 999px !important;
        background: #dc3545 !important;
        color: #ffffff !important;
        border: 2px solid #ffffff !important;
        font-size: 11px !important;
        font-weight: 950 !important;
        line-height: 16px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .profileNotifList {
        display: grid !important;
        gap: 12px !important;
    }

    .profileNotifItem {
        border: 1px solid #dce5f2 !important;
        border-radius: 18px !important;
        background: #ffffff !important;
        padding: 14px !important;
        box-shadow: 0 10px 20px rgba(16, 24, 40, 0.06) !important;
    }

    .profileNotifUnread {
        border-color: #0b5ed7 !important;
        background: #f5f9ff !important;
    }

    .profileNotifTop {
        display: flex !important;
        justify-content: space-between !important;
        gap: 10px !important;
        align-items: flex-start !important;
        margin-bottom: 10px !important;
    }

    .profileNotifTop strong {
        color: #101828 !important;
        font-weight: 950 !important;
    }

    .profileNotifTop small,
    .profileNotifResponse small {
        display: block !important;
        color: #667085 !important;
        font-size: 12px !important;
        margin-top: 3px !important;
    }

    .profileNotifText,
    .profileNotifResponse,
    .profileNotifPending {
        border-radius: 14px !important;
        padding: 12px !important;
        line-height: 1.5 !important;
        color: #344054 !important;
        font-size: 14px !important;
    }

    .profileNotifText {
        background: #f8fafc !important;
        border: 1px solid #edf2f7 !important;
        margin-bottom: 10px !important;
    }

    .profileNotifResponse {
        background: #eef5ff !important;
        border: 1px solid #cfe0ff !important;
    }

    .profileNotifPending {
        background: #fff8e6 !important;
        border: 1px solid #ffe4a3 !important;
        color: #7a4d00 !important;
    }

    body.userPage .userTopbarInner {
        grid-template-columns: auto minmax(540px, 1fr) minmax(240px, 430px) auto auto !important;
    }

    @media (max-width: 1180px) {
        body.userPage .userTopbarInner {
            grid-template-columns: auto 1fr auto auto !important;
        }

        body.userPage .topbarSearch {
            grid-column: 1 / -1 !important;
        }
    }

    @media (max-width: 576px) {
        .profileNotifBtn {
            width: 42px !important;
            height: 42px !important;
            min-width: 42px !important;
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
                        <li>
                            <a href="index.php">
                                <i class="fa-solid fa-landmark"></i>
                                Home
                            </a>
                        </li>

                        <li>
                            <a href="about.php">
                                <i class="fa-solid fa-circle-info"></i>
                                About
                            </a>
                        </li>

                        <li>
                            <a href="browsecandi.php" class="active">
                                <i class="fa-solid fa-users"></i>
                                Candidates
                            </a>
                        </li>

                        <li>
                            <a href="startvoting.php">
                                <i class="fa-solid fa-check-to-slot"></i>
                                Voting
                            </a>
                        </li>
<li>
                            <a href="results.php">
                                <i class="fa-solid fa-chart-simple"></i>
                                Results
                            </a>
                        </li>

                        <li>
                            <a href="help.php">
                                <i class="fa-solid fa-circle-question"></i>
                                Help
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="topbarSearch">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="search" class="form-control searchInput" id="candidateSearch" placeholder="Search candidates, party, or position">
                </div>
            </div>



            <button type="button" class="profileNotifBtn" data-bs-toggle="modal" data-bs-target="#profileNotificationModal" title="Profile request notifications" aria-label="Profile request notifications">
                <i class="fa-solid fa-bell"></i>
                <?php if (isset($profile_notification_count) && $profile_notification_count > 0) { ?>
                    <span id="profileNotificationBadge"><?php echo number_format($profile_notification_count); ?></span>
                <?php } ?>
            </button>

            <button type="button" class="userChip border-0" data-bs-toggle="modal" data-bs-target="#profileModal">
                <span class="userAvatarCircle"><?php echo ivoteph_h($profile_initials); ?></span>
                <span class="userMeta">
                    <span class="userName d-block"><?php echo ivoteph_h($profile_full_name); ?></span>
                    <span class="verifiedBadge">
                        <i class="fa-solid fa-circle-check"></i>
                        Verified Voter
                    </span>
                </span>
                <i class="fa-solid fa-chevron-down text-muted small d-none d-md-inline"></i>
            </button>
        </div>
    </header>

    <main class="userMain">
        <section class="filterCard userCard">
            <div class="filterHeader">
                <div>
                    <h2>Official Candidates</h2>
                    <p class="mb-0 text-muted">Filter candidates by position or use the search bar.</p>
                </div>
            </div>

            <div class="positionFilters">
                <button type="button" class="filterBtn active" data-position="all">All</button>

                <?php foreach ($positions as $position_name) { ?>
                    <button type="button" class="filterBtn" data-position="<?php echo ivoteph_h($position_name); ?>">
                        <?php echo ivoteph_h($position_name); ?>
                    </button>
                <?php } ?>
            </div>
        </section>

        <?php if ($total_candidates < 1) { ?>
            <section class="emptyState userCard">
                <i class="fa-solid fa-users-slash"></i>
                <h3>No candidates found</h3>
                <p class="text-muted mb-0">
                    Add candidates first in the admin side so they can appear here.
                </p>
            </section>
        <?php } else { ?>
            <section class="candidateGrid" id="candidateGrid">
                <?php foreach ($candidates as $candidate) { ?>
                    <?php
                    $candidate_name = $candidate['full_name'];
                    $candidate_party = $candidate['political_party'];
                    $candidate_position = $candidate['position_name'];
                    $candidate_platform = $candidate['platform'];
                    $candidate_photo = ivoteph_candidate_photo($candidate['photo']);
                    $candidate_initials = ivoteph_initials_from_name($candidate_name);
                    ?>

                    <article
                        class="candidateCard userCard"
                        data-position="<?php echo ivoteph_h($candidate_position); ?>"
                        data-search="<?php echo ivoteph_h(strtolower($candidate_name . ' ' . $candidate_party . ' ' . $candidate_position . ' ' . $candidate_platform . ' ' . $candidate['province'] . ' ' . $candidate['city_municipality'])); ?>">

                        <div class="candidateTop">
                            <div class="candidatePhoto">
                                <?php if ($candidate_photo != '') { ?>
                                    <img src="<?php echo ivoteph_h($candidate_photo); ?>" alt="<?php echo ivoteph_h($candidate_name); ?>">
                                <?php } else { ?>
                                    <?php echo ivoteph_h($candidate_initials); ?>
                                <?php } ?>
                            </div>

                            <div>
                                <h3 class="candidateName"><?php echo ivoteph_h($candidate_name); ?></h3>
                                <div class="partyName">
                                    <i class="fa-solid fa-flag me-1"></i>
                                    <?php echo ivoteph_h($candidate_party); ?>
                                </div>
                            </div>
                        </div>

                        <div class="positionBadge">
                            <i class="fa-solid fa-user-tie"></i>
                            <?php echo ivoteph_h($candidate_position); ?>
                        </div>

                        <p class="platformText">
                            <?php echo ivoteph_h($candidate_platform); ?>
                        </p>
                    </article>
                <?php } ?>
            </section>
        <?php } ?>
    </main>

    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar"><?php echo ivoteph_h($profile_initials); ?></div>
                    <h5 id="profileModalLabel"><?php echo ivoteph_h($profile_full_name); ?></h5>
                    <p>
                        <i class="fa-solid fa-circle-check me-1"></i>
                        Verified Registered Voter
                    </p>
                </div>

                <div class="profileModalBody">
                    <div class="profileReadOnlyNote">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        This profile is read-only. Registered voters can review their submitted information anytime,
                        but corrections must be requested through the admin.
                    </div>

                    <div class="profileSectionTitle">
                        <i class="fa-solid fa-id-card"></i>
                        Account Information
                    </div>

                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem">
                            <span>Voter ID</span>
                            <strong><?php echo ivoteph_h($profile_voter_id); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Registration Status</span>
                            <strong><?php echo ivoteph_h($profile_registration_status); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Profile Status</span>
                            <strong><?php echo ivoteph_h($profile_status); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Ballot Status</span>
                            <strong>Not Submitted</strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Account Type</span>
                            <strong>Voter</strong>
                        </div>
                    </div>

                    <div class="profileSectionTitle">
                        <i class="fa-solid fa-user"></i>
                        Personal Information
                    </div>

                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem">
                            <span>First Name</span>
                            <strong><?php echo ivoteph_h($profile_first_name); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Middle Name</span>
                            <strong><?php echo ivoteph_h($profile_middle_name); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Last Name</span>
                            <strong><?php echo ivoteph_h($profile_last_name); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Birth Date</span>
                            <strong><?php echo ivoteph_h($profile_birth_date_display); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Sex</span>
                            <strong><?php echo ivoteph_h($profile_sex); ?></strong>
                        </div>
                    </div>

                    <div class="profileSectionTitle">
                        <i class="fa-solid fa-address-book"></i>
                        Contact Information
                    </div>

                    <div class="profileFullGrid">
                        <div class="profileFullItem">
                            <span>Email Address</span>
                            <strong><?php echo ivoteph_h($profile_email); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Mobile Number</span>
                            <strong><?php echo ivoteph_h($profile_mobile_number); ?></strong>
                        </div>
                    </div>

                    <div class="profileSectionTitle">
                        <i class="fa-solid fa-location-dot"></i>
                        Registered Address
                    </div>

                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem">
                            <span>Region</span>
                            <strong><?php echo ivoteph_h($profile_region); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Province</span>
                            <strong><?php echo ivoteph_h($profile_province); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>City / Municipality</span>
                            <strong><?php echo ivoteph_h($profile_city_municipality); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Barangay</span>
                            <strong><?php echo ivoteph_h($profile_barangay); ?></strong>
                        </div>

                        <div class="profileFullItem">
                            <span>Country</span>
                            <strong><?php echo ivoteph_h($profile_country); ?></strong>
                        </div>

                        <div class="profileFullItem profileFullWide">
                            <span>Complete Address</span>
                            <strong><?php echo ivoteph_h($profile_complete_address); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="profileModalActions">
                    <button type="button" class="btn btn-primary" onclick="openProfileRequestModal()">
                        <i class="fa-solid fa-pen-to-square me-1"></i>
                        Request Change
                    </button>

                    <button type="button" class="btn btn-danger" onclick="logoutUser()">
                        <i class="fa-solid fa-right-from-bracket me-1"></i>
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profileRequestModal" tabindex="-1" aria-labelledby="profileRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                    <h5 id="profileRequestModalLabel">Request Profile Change</h5>
                    <p>
                        Registered voter information cannot be edited directly.
                    </p>
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
                            <textarea class="form-control" id="requestMessage" rows="4" required placeholder="Example: My registered last name is misspelled. It should be Dela Cruz."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-3 rounded-4 fw-bold">
                                <i class="fa-solid fa-paper-plane me-2"></i>
                                Submit Request
                            </button>

                            <button type="button" class="btn btn-light py-3 rounded-4 fw-bold" data-bs-dismiss="modal">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <div class="modal fade" id="profileNotificationModal" tabindex="-1" aria-labelledby="profileNotificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <h5 id="profileNotificationModalLabel">Notifications</h5>
                    <p>Your profile request updates from the admin side.</p>
                </div>

                <div class="requestModalBody profileModalBody">
                    <?php if (!isset($profile_notifications) || count($profile_notifications) === 0) { ?>
                        <div class="requestNotice mb-0">
                            <i class="fa-solid fa-circle-info me-2"></i>
                            You do not have profile request notifications yet.
                        </div>
                    <?php } else { ?>
                        <div class="profileNotifList">
                            <?php foreach ($profile_notifications as $notification) { ?>
                                <div class="profileNotifItem <?php echo (($notification['user_seen_at'] === null || trim((string) $notification['user_seen_at']) === '' || $notification['user_seen_at'] === '0000-00-00 00:00:00') && ($notification['request_status'] === 'Approved' || $notification['request_status'] === 'Rejected' || $notification['request_status'] === 'Resolved')) ? 'profileNotifUnread' : ''; ?>">
                                    <div class="profileNotifTop">
                                        <div>
                                            <strong><?php echo ivoteph_h($notification['request_field']); ?></strong>
                                            <small>
                                                Submitted:
                                                <?php echo ivoteph_h(ivoteph_profile_notif_date($notification['created_at'])); ?>
                                            </small>
                                        </div>

                                        <span class="badge text-bg-<?php echo ivoteph_profile_notif_badge_class($notification['request_status']); ?>">
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
                                                    <?php echo ivoteph_h(ivoteph_profile_notif_date($notification['reviewed_at'])); ?>
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

    <footer class="footer">
        <div>© 2026 iVotePH. Secure. Accessible. Transparent.</div>
    </footer>

    <script>
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

        var activePosition = 'all';
        var searchInput = document.getElementById('candidateSearch');
        var filterButtons = document.querySelectorAll('.filterBtn');
        var candidateCards = document.querySelectorAll('.candidateCard');

        function filterCandidates() {
            var searchTerm = '';

            if (searchInput) {
                searchTerm = searchInput.value.toLowerCase().trim();
            }

            for (var i = 0; i < candidateCards.length; i++) {
                var card = candidateCards[i];
                var cardPosition = card.getAttribute('data-position');
                var cardSearch = card.getAttribute('data-search');

                var matchesPosition = activePosition === 'all' || cardPosition === activePosition;
                var matchesSearch = searchTerm === '' || cardSearch.indexOf(searchTerm) !== -1;

                if (matchesPosition && matchesSearch) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            }
        }

        for (var j = 0; j < filterButtons.length; j++) {
            filterButtons[j].addEventListener('click', function () {
                for (var k = 0; k < filterButtons.length; k++) {
                    filterButtons[k].classList.remove('active');
                }

                this.classList.add('active');
                activePosition = this.getAttribute('data-position');
                filterCandidates();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterCandidates);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>


    <script id="ivoteProfileNotificationSeenFix">
        (function () {
            var notificationButton = document.querySelector('.profileNotifBtn');
            var notificationModal = document.getElementById('profileNotificationModal');
            var currentVoterId = <?php echo json_encode($profile_voter_id); ?>;
            var unseenCount = <?php echo isset($profile_notification_count) ? (int) $profile_notification_count : 0; ?>;
            var hasMarkedSeen = false;

            function hideNotificationBadge() {
                var badge = document.getElementById('profileNotificationBadge');

                if (badge) {
                    badge.parentNode.removeChild(badge);
                }
            }

            function markNotificationsSeen() {
                if (hasMarkedSeen || unseenCount <= 0) {
                    hideNotificationBadge();
                    return;
                }

                hasMarkedSeen = true;
                hideNotificationBadge();

                var formData = new FormData();
                formData.append('action', 'mark_profile_notifications_seen');
                formData.append('voter_id', currentVoterId);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'submit_profile_request.php', true);
                xhr.send(formData);
            }

            if (notificationButton) {
                notificationButton.addEventListener('click', markNotificationsSeen);
            }

            if (notificationModal) {
                notificationModal.addEventListener('shown.bs.modal', markNotificationsSeen);
            }
        })();
    </script>

</body>

</html>