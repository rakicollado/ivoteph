<?php require_once __DIR__ . '/auth_check.php'; ?>

<?php
function ivoteph_h($value) {
    if ($value === null || $value === '') {
        return 'N/A';
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

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

date_default_timezone_set('Asia/Manila');

function ivoteph_datetime_display($value) {
    if ($value === null || $value == '' || $value == '0000-00-00 00:00:00') {
        return 'N/A';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return $value;
    }

    return date('F j, Y g:i A', $timestamp);
}

function ivoteph_table_exists($conn, $table_name) {
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);

    if ($table_name == '') {
        return false;
    }

    $sql = "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table_name) . "'";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return false;
    }

    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);

    return $row ? true : false;
}

function ivoteph_column_exists($conn, $table_name, $column_name) {
    $table_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);
    $column_name = preg_replace('/[^A-Za-z0-9_]/', '', $column_name);

    if ($table_name == '' || $column_name == '') {
        return false;
    }

    $sql = "SHOW COLUMNS FROM `" . $table_name . "` LIKE '" . mysqli_real_escape_string($conn, $column_name) . "'";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return false;
    }

    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);

    return $row ? true : false;
}

function ivoteph_position_icon($position_name) {
    $position_name = strtolower((string) $position_name);

    if (strpos($position_name, 'president') !== false) {
        return 'fa-landmark';
    }

    if (strpos($position_name, 'senator') !== false) {
        return 'fa-users';
    }

    if (strpos($position_name, 'governor') !== false) {
        return 'fa-building-flag';
    }

    if (strpos($position_name, 'mayor') !== false) {
        return 'fa-city';
    }

    if (strpos($position_name, 'party') !== false) {
        return 'fa-people-group';
    }

    return 'fa-check-to-slot';
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

$ballot_id = 0;
$ballot_election_id = 0;
$ballot_election_name = 'No submitted ballot yet';
$ballot_status = 'Not Submitted';
$ballot_status_class = 'pending';
$ballot_submitted_at = 'N/A';
$ballot_reference = 'N/A';
$ballot_votes = array();
$ballot_error = '';
$total_ballot_votes = 0;
$latest_ballot = false;

if (ivoteph_table_exists($conn, 'ballots')) {
    $ballot_status_column = '';
    $ballot_time_column = '';
    $ballot_order_column = 'ballot_id';

    if (ivoteph_column_exists($conn, 'ballots', 'ballot_status')) {
        $ballot_status_column = 'ballot_status';
    } elseif (ivoteph_column_exists($conn, 'ballots', 'status')) {
        $ballot_status_column = 'status';
    }

    if (ivoteph_column_exists($conn, 'ballots', 'submitted_at')) {
        $ballot_time_column = 'submitted_at';
        $ballot_order_column = 'submitted_at';
    } elseif (ivoteph_column_exists($conn, 'ballots', 'voted_at')) {
        $ballot_time_column = 'voted_at';
        $ballot_order_column = 'voted_at';
    } elseif (ivoteph_column_exists($conn, 'ballots', 'created_at')) {
        $ballot_time_column = 'created_at';
        $ballot_order_column = 'created_at';
    }

    if (ivoteph_column_exists($conn, 'ballots', 'ballot_id') && ivoteph_column_exists($conn, 'ballots', 'voter_id')) {
        $select_status = ($ballot_status_column != '') ? "b.`" . $ballot_status_column . "` AS detected_ballot_status" : "'Submitted' AS detected_ballot_status";
        $select_time = ($ballot_time_column != '') ? "b.`" . $ballot_time_column . "` AS detected_submitted_at" : "NULL AS detected_submitted_at";
        $join_elections = '';
        $select_election_name = "'' AS detected_election_name";

        if (ivoteph_table_exists($conn, 'elections') && ivoteph_column_exists($conn, 'ballots', 'election_id')) {
            $join_elections = " LEFT JOIN elections e ON b.election_id = e.election_id ";

            if (ivoteph_column_exists($conn, 'elections', 'election_name')) {
                $select_election_name = "e.election_name AS detected_election_name";
            } elseif (ivoteph_column_exists($conn, 'elections', 'election_title')) {
                $select_election_name = "e.election_title AS detected_election_name";
            } elseif (ivoteph_column_exists($conn, 'elections', 'title')) {
                $select_election_name = "e.title AS detected_election_name";
            }
        }

        $sql_ballot = "
            SELECT
                b.ballot_id,
                " . (ivoteph_column_exists($conn, 'ballots', 'election_id') ? "b.election_id" : "0") . " AS detected_election_id,
                " . $select_status . ",
                " . $select_time . ",
                " . $select_election_name . "
            FROM ballots b
            " . $join_elections . "
            WHERE b.voter_id = ?
            ORDER BY b.`" . $ballot_order_column . "` DESC, b.ballot_id DESC
            LIMIT 1
        ";

        $stmt_ballot = mysqli_prepare($conn, $sql_ballot);

        if ($stmt_ballot) {
            mysqli_stmt_bind_param($stmt_ballot, 's', $profile_voter_id);
            mysqli_stmt_execute($stmt_ballot);
            mysqli_stmt_bind_result($stmt_ballot, $db_ballot_id, $db_election_id, $db_ballot_status, $db_submitted_at, $db_election_name);

            if (mysqli_stmt_fetch($stmt_ballot)) {
                $ballot_id = (int) $db_ballot_id;
                $ballot_election_id = (int) $db_election_id;
                $ballot_status = ($db_ballot_status != '') ? $db_ballot_status : 'Submitted';
                $ballot_submitted_at = ivoteph_datetime_display($db_submitted_at);
                $ballot_election_name = ($db_election_name != '') ? $db_election_name : 'Election #' . $ballot_election_id;
                $ballot_reference = 'BAL-' . str_pad((string) $ballot_id, 6, '0', STR_PAD_LEFT);
                $latest_ballot = true;
            }

            mysqli_stmt_close($stmt_ballot);
        } else {
            $ballot_error = mysqli_error($conn);
        }
    }
}

if ($ballot_id > 0 && ivoteph_table_exists($conn, 'votes')) {
    $vote_time_select = 'NULL AS detected_vote_time';

    if (ivoteph_column_exists($conn, 'votes', 'voted_at')) {
        $vote_time_select = 'v.voted_at AS detected_vote_time';
    } elseif (ivoteph_column_exists($conn, 'votes', 'created_at')) {
        $vote_time_select = 'v.created_at AS detected_vote_time';
    } elseif (ivoteph_column_exists($conn, 'votes', 'submitted_at')) {
        $vote_time_select = 'v.submitted_at AS detected_vote_time';
    }

    $order_sql = 'p.position_id ASC, c.full_name ASC';

    if (ivoteph_table_exists($conn, 'positions') && ivoteph_column_exists($conn, 'positions', 'display_order')) {
        $order_sql = 'p.display_order ASC, p.position_id ASC, c.full_name ASC';
    }

    $where_sql = 'v.ballot_id = ?';
    $bind_type = 'i';
    $bind_value_int = $ballot_id;

    $sql_votes = "
        SELECT
            v.vote_id,
            v.position_id,
            v.candidate_id,
            " . $vote_time_select . ",
            c.full_name,
            c.political_party,
            c.platform,
            p.position_name
        FROM votes v
        LEFT JOIN candidates c ON v.candidate_id = c.candidate_id
        LEFT JOIN positions p ON v.position_id = p.position_id
        WHERE " . $where_sql . "
        ORDER BY " . $order_sql . "
    ";

    $stmt_votes = mysqli_prepare($conn, $sql_votes);

    if ($stmt_votes) {
        mysqli_stmt_bind_param($stmt_votes, $bind_type, $bind_value_int);
        mysqli_stmt_execute($stmt_votes);
        mysqli_stmt_bind_result(
            $stmt_votes,
            $db_vote_id,
            $db_position_id,
            $db_candidate_id,
            $db_vote_time,
            $db_candidate_name,
            $db_party,
            $db_platform,
            $db_position_name
        );

        while (mysqli_stmt_fetch($stmt_votes)) {
            $ballot_votes[] = array(
                'vote_id' => $db_vote_id,
                'position_id' => $db_position_id,
                'candidate_id' => $db_candidate_id,
                'vote_time' => $db_vote_time,
                'candidate_name' => $db_candidate_name,
                'party' => $db_party,
                'platform' => $db_platform,
                'position_name' => $db_position_name
            );
        }

        mysqli_stmt_close($stmt_votes);
    } else {
        $ballot_error = mysqli_error($conn);
    }
}

$total_ballot_votes = count($ballot_votes);

if ($total_ballot_votes > 0) {
    $ballot_status = 'Submitted';
    $ballot_status_class = 'submitted';
} else {
    $ballot_status_class = 'pending';
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Ballot - iVotePH</title>

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

        .ballotHero {
            margin-bottom: 18px;
            padding: 34px 36px;
            min-height: 250px;
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

        .ballotHeroEyebrow {
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

        .ballotHero h1 {
            font-size: clamp(2.1rem, 4vw, 4rem);
            line-height: 1;
            letter-spacing: -0.05em;
            font-weight: 950;
            margin-bottom: 14px;
        }

        .ballotHero p {
            max-width: 720px;
            color: rgba(255, 255, 255, 0.88);
            font-size: 15px;
            line-height: 1.65;
            margin-bottom: 0;
        }

        .ballotStatusPill {
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

        .ballotStatusPill.submitted {
            background: #dcfce7;
            color: #15803d;
        }

        .ballotStatusPill.pending {
            background: #fff7d6;
            color: #a16207;
        }

        .ballotItemStatus.submitted {
            background: #dcfce7;
            color: #15803d;
        }

        .ballotItemStatus.pending {
            background: #fff7d6;
            color: #a16207;
        }

        .ballotGrid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(320px, 0.75fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .sectionCard {
            padding: 24px;
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

        .ballotPlaceholder {
            display: grid;
            gap: 12px;
        }

        .ballotItem {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border: 1px solid var(--userLine);
            border-radius: 18px;
            background: #ffffff;
        }

        .ballotItemIcon,
        .featureIcon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: var(--userBlueSoft);
            color: var(--userBlue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .ballotItem h4 {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: 950;
            color: var(--userInk);
        }

        .ballotItem p {
            margin: 0;
            color: var(--userMuted);
            font-size: 13px;
        }

        .ballotItemStatus {
            padding: 7px 10px;
            border-radius: 999px;
            background: #fff7e6;
            color: #9a6700;
            font-size: 11px;
            font-weight: 950;
        }

        .ballotSummary {
            display: grid;
            gap: 12px;
        }

        .summaryItem {
            border-radius: 18px;
            background: #f7f9fd;
            border: 1px solid #e1e8f3;
            padding: 16px;
        }

        .summaryItem span {
            display: block;
            font-size: 11px;
            font-weight: 900;
            color: var(--userMuted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }

        .summaryItem strong {
            display: block;
            color: var(--userBlue);
            font-weight: 950;
        }

        .infoGrid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .featureCard {
            padding: 20px;
            border: 1px solid var(--userLine);
            border-radius: 18px;
            background: #ffffff;
        }

        .featureIcon {
            margin-bottom: 14px;
        }

        .featureCard h4 {
            font-size: 18px;
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

            .ballotGrid,
            .infoGrid {
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

            .ballotHero {
                padding: 26px 22px;
                flex-direction: column;
            }

            .ballotItem {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .ballotItemStatus {
                grid-column: 1 / -1;
                width: fit-content;
            }

            .profileFullGrid,
            .profileFullGrid.threeCols,
            .profileModalActions {
                grid-template-columns: 1fr;
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
                            <a href="browsecandi.php">
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
                            <a href="myballot.php" class="active">
                                <i class="fa-solid fa-file-signature"></i>
                                My Ballot
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
                    <input type="search" class="form-control searchInput" placeholder="Search candidates, voting info, or results">
                </div>
            </div>

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

    <main class="userMain userPageMotion">
        <section class="ballotHero userCard">
            <div>
                <div class="ballotHeroEyebrow">
                    <i class="fa-solid fa-file-signature"></i>
                    Ballot Review Center
                </div>

                <h1>My Ballot</h1>

                <p>
                    Review your submitted ballot record from the MySQL ballots and votes tables.
                    Your private choices are shown only inside your voter account.
                </p>
            </div>

            <span class="ballotStatusPill <?php echo ivoteph_h($ballot_status_class); ?>">
                <?php if ($total_ballot_votes > 0) { ?>
                    <i class="fa-solid fa-circle-check"></i>
                <?php } else { ?>
                    <i class="fa-solid fa-clock"></i>
                <?php } ?>
                <?php echo ivoteph_h($ballot_status); ?>
            </span>
        </section>

        <section class="ballotGrid">
            <div class="sectionCard userCard">
                <div class="sectionHeader">
                    <div>
                        <h2>Current Ballot Selections</h2>
                        <p class="text-muted mb-0">
                            These are the candidates saved under your submitted ballot.
                        </p>
                    </div>
                </div>

                <?php if (isset($_GET['success']) && $_GET['success'] == 'voted') { ?>
                    <div class="alert alert-success rounded-4 p-4">
                        <i class="fa-solid fa-circle-check me-2"></i>
                        Your ballot was submitted successfully.
                    </div>
                <?php } ?>

                <?php if ($ballot_error != '') { ?>
                    <div class="alert alert-danger rounded-4 p-4">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        Unable to load ballot details: <?php echo ivoteph_h($ballot_error); ?>
                    </div>
                <?php } ?>

                <?php if ($total_ballot_votes > 0) { ?>
                    <div class="alert alert-success rounded-4 p-4">
                        <i class="fa-solid fa-lock me-2"></i>
                        Your ballot has been submitted and locked. You cannot edit your choices after submission.
                    </div>

                    <div class="ballotPlaceholder">
                        <?php foreach ($ballot_votes as $vote) { ?>
                            <?php
                            $display_position = $vote['position_name'];
                            if ($display_position == '') {
                                $display_position = 'Position #' . $vote['position_id'];
                            }

                            $display_candidate = $vote['candidate_name'];
                            if ($display_candidate == '') {
                                $display_candidate = 'Candidate #' . $vote['candidate_id'];
                            }
                            ?>

                            <div class="ballotItem">
                                <div class="ballotItemIcon">
                                    <i class="fa-solid <?php echo ivoteph_h(ivoteph_position_icon($display_position)); ?>"></i>
                                </div>

                                <div>
                                    <h4><?php echo ivoteph_h($display_position); ?></h4>
                                    <p>
                                        <strong><?php echo ivoteph_h($display_candidate); ?></strong>
                                        <?php if ($vote['party'] != '') { ?>
                                            <br><span><?php echo ivoteph_h($vote['party']); ?></span>
                                        <?php } ?>
                                    </p>
                                </div>

                                <span class="ballotItemStatus submitted">Submitted</span>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="alert alert-primary rounded-4 p-4">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        You have not submitted a ballot yet. Go to the Voting page to select candidates and submit your official ballot.
                    </div>

                    <div class="ballotPlaceholder">
                        <div class="ballotItem">
                            <div class="ballotItemIcon">
                                <i class="fa-solid fa-check-to-slot"></i>
                            </div>

                            <div>
                                <h4>No submitted ballot found</h4>
                                <p>Your selected candidates will appear here after successful voting.</p>
                            </div>

                            <span class="ballotItemStatus pending">Pending</span>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <aside class="sectionCard userCard">
                <div class="sectionHeader">
                    <h3>Ballot Summary</h3>
                </div>

                <div class="ballotSummary">
                    <div class="summaryItem">
                        <span>Voter ID</span>
                        <strong><?php echo ivoteph_h($profile_voter_id); ?></strong>
                    </div>

                    <div class="summaryItem">
                        <span>Status</span>
                        <strong><?php echo ivoteph_h($ballot_status); ?></strong>
                    </div>

                    <div class="summaryItem">
                        <span>Election</span>
                        <strong><?php echo ivoteph_h($ballot_election_name); ?></strong>
                    </div>

                    <div class="summaryItem">
                        <span>Ballot Reference</span>
                        <strong><?php echo ivoteph_h($ballot_reference); ?></strong>
                    </div>

                    <div class="summaryItem">
                        <span>Submitted At</span>
                        <strong><?php echo ivoteph_h($ballot_submitted_at); ?></strong>
                    </div>

                    <div class="summaryItem">
                        <span>Total Choices</span>
                        <strong><?php echo ivoteph_h($total_ballot_votes); ?></strong>
                    </div>

                    <div class="summaryItem">
                        <span>Privacy</span>
                        <strong>Confidential</strong>
                    </div>

                    <div class="summaryItem">
                        <span>Voting Rule</span>
                        <strong>One voter, one ballot</strong>
                    </div>

                    <?php if ($total_ballot_votes > 0) { ?>
                        <a href="results.php" class="btn btn-primary py-3 fw-bold rounded-4">
                            <i class="fa-solid fa-chart-simple me-2"></i>
                            View Results
                        </a>
                    <?php } else { ?>
                        <a href="startvoting.php" class="btn btn-primary py-3 fw-bold rounded-4">
                            <i class="fa-solid fa-check-to-slot me-2"></i>
                            Go to Voting
                        </a>
                    <?php } ?>
                </div>
            </aside>
        </section>

        <section class="sectionCard userCard">
            <div class="sectionHeader">
                <h2>Before Submitting</h2>
            </div>

            <div class="infoGrid">
                <div class="featureCard">
                    <div class="featureIcon">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <h4>Review</h4>
                    <p class="text-muted">
                        Check all selected candidates before final submission.
                    </p>
                </div>

                <div class="featureCard">
                    <div class="featureIcon">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h4>Lock</h4>
                    <p class="text-muted">
                        Submission locks your ballot to prevent double voting.
                    </p>
                </div>

                <div class="featureCard">
                    <div class="featureIcon">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <h4>Receipt</h4>
                    <p class="text-muted">
                        A reference receipt can confirm successful submission without revealing choices.
                    </p>
                </div>
            </div>
        </section>
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
                            <strong><?php echo ivoteph_h($ballot_status); ?></strong>
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

    <footer class="footer">
        <div>© 2026 iVotePH. Secure. Accessible. Transparent.</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

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
    </script>
</body>

</html>