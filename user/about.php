<?php require_once __DIR__ . '/auth_check.php'; ?>

<?php
function ivoteph_h($value)
{
    if ($value === null || $value === '') {
        return 'N/A';
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

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
    <title>About - iVotePH</title>

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

        .userNavList a i {
            font-size: 10.5px;
            color: inherit;
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

        .aboutGrid {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(320px, 0.7fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .sectionCard {
            padding: 26px;
        }

        .sectionHeader {
            margin-bottom: 18px;
        }

        .sectionHeader h2,
        .sectionHeader h3 {
            margin: 0;
            font-weight: 950;
            color: var(--userInk);
            letter-spacing: -0.03em;
        }

        .sectionHeader p {
            margin: 8px 0 0;
            color: var(--userMuted);
            font-size: 14px;
            line-height: 1.6;
        }

        .aboutText {
            color: #475467;
            font-size: 14px;
            line-height: 1.75;
            margin-bottom: 14px;
        }

        .featureGrid {
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
            width: 46px;
            height: 46px;
            border-radius: 15px;
            background: var(--userBlueSoft);
            color: var(--userBlue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 14px;
        }

        .featureCard h3 {
            margin: 0 0 8px;
            font-size: 17px;
            font-weight: 950;
            color: var(--userInk);
        }

        .featureCard p {
            margin: 0;
            color: var(--userMuted);
            font-size: 13px;
            line-height: 1.6;
        }

        .infoList {
            display: grid;
            gap: 12px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .infoList li {
            display: flex;
            gap: 12px;
            padding: 14px;
            border-radius: 16px;
            background: #f7f9fd;
            border: 1px solid #e1e8f3;
        }

        .infoList i {
            color: var(--userBlue);
            margin-top: 3px;
        }

        .infoList strong {
            display: block;
            font-size: 13px;
            font-weight: 950;
            color: var(--userInk);
            margin-bottom: 4px;
        }

        .infoList span {
            display: block;
            color: var(--userMuted);
            font-size: 12.5px;
            line-height: 1.5;
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

        .requestModalBody .form-control:focus,
        .requestModalBody .form-select:focus {
            border-color: #0b5ed7;
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }

        .userPageMotion,
        .userCard,
        .featureCard {
            animation: userFadeUp 0.35s ease both;
        }

        @keyframes userFadeUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }

        @media (max-width: 1400px) {
            .userTopbarInner {
                grid-template-columns: auto minmax(500px, 1fr) minmax(240px, 360px) auto;
                gap: 7px;
            }

            .brandLogo {
                width: 62px !important;
                max-width: 62px !important;
            }

            .userNavList a {
                padding: 8px 7px;
                font-size: 10px;
            }

            .userChip {
                padding-right: 8px;
            }

            .userName {
                font-size: 10.5px;
            }

            .verifiedBadge {
                font-size: 8.5px;
            }
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

            .aboutGrid,
            .featureGrid {
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
                            <a href="about.php" class="active">
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
        <section class="aboutGrid">
            <div class="sectionCard userCard">
                <div class="sectionHeader">
                    <h2>What is iVotePH?</h2>
                    <p>
                        iVotePH is a full-stack voting website built for academic demonstration and system development.
                    </p>
                </div>

                <p class="aboutText">
                    The system allows registered voters to access the website using their official Voter ID and
                    password.
                    Voter information is stored in a MySQL database and managed through MySQL Workbench for the
                    Information
                    Management component of the project.
                </p>

                <p class="aboutText">
                    Once logged in, voters can view candidates, review voting information, cast their ballot through the
                    Voting page, check results,
                    and inspect their read-only profile. Any corrections to personal information must go through an
                    admin
                    request instead of direct user editing.
                </p>

                <p class="aboutText">
                    The project combines interface design, database management, PHP CRUD functions, and future Power BI
                    dashboards for election analytics.
                </p>
            </div>

            <aside class="sectionCard userCard">
                <div class="sectionHeader">
                    <h3>System Coverage</h3>
                    <p>
                        This project connects four course requirements in one system.
                    </p>
                </div>

                <ul class="infoList">
                    <li>
                        <i class="fa-solid fa-laptop-code"></i>
                        <div>
                            <strong>Web Development</strong>
                            <span>PHP handles login, registration, admin management, voting, and CRUD operations.</span>
                        </div>
                    </li>

                    <li>
                        <i class="fa-solid fa-database"></i>
                        <div>
                            <strong>Information Management</strong>
                            <span>MySQL Workbench manages the database schema, records, relationships, and SQL
                                queries.</span>
                        </div>
                    </li>

                    <li>
                        <i class="fa-solid fa-object-group"></i>
                        <div>
                            <strong>Human Computer Interaction</strong>
                            <span>The interface focuses on navigation, readability, accessibility, and voter
                                usability.</span>
                        </div>
                    </li>

                    <li>
                        <i class="fa-solid fa-chart-pie"></i>
                        <div>
                            <strong>Business Intelligence</strong>
                            <span>Power BI will connect to MySQL for aggregate election dashboards and result
                                insights.</span>
                        </div>
                    </li>
                </ul>
            </aside>
        </section>

        <section class="featureGrid">
            <div class="featureCard userCard">
                <div class="featureIcon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3>Secure Access</h3>
                <p>
                    Voters can only access protected pages after logging in with a valid Voter ID and password from
                    MySQL.
                </p>
            </div>

            <div class="featureCard userCard">
                <div class="featureIcon">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <h3>Verified Profiles</h3>
                <p>
                    Incomplete profiles are blocked from dashboard access until registration details are complete.
                </p>
            </div>

            <div class="featureCard userCard">
                <div class="featureIcon">
                    <i class="fa-solid fa-chart-simple"></i>
                </div>
                <h3>Election Analytics</h3>
                <p>
                    Vote counts and result summaries can later be visualized through Power BI dashboards.
                </p>
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

    <div class="modal fade" id="profileRequestModal" tabindex="-1" aria-labelledby="profileRequestModalLabel"
        aria-hidden="true">
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
                                placeholder="Example: My registered last name is misspelled. It should be Dela Cruz."></textarea>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

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