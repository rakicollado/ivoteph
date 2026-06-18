<?php require_once __DIR__ . '/auth_check.php'; ?>

<?php
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

$profile_voter_id = isset($auth_voter_id) ? $auth_voter_id : (isset($_SESSION['voter_id']) ? $_SESSION['voter_id'] : '');
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
$profile_request_has_user_seen_at = false;

if (isset($conn) && $conn && isset($profile_voter_id) && trim((string) $profile_voter_id) !== '' && ivoteph_profile_request_table_exists($conn, 'profile_change_requests')) {
    $seen_column_result = mysqli_query($conn, "SHOW COLUMNS FROM profile_change_requests LIKE 'user_seen_at'");

    if ($seen_column_result && mysqli_num_rows($seen_column_result) > 0) {
        $profile_request_has_user_seen_at = true;
        mysqli_free_result($seen_column_result);
    } else {
        if ($seen_column_result) {
            mysqli_free_result($seen_column_result);
        }

        mysqli_query($conn, "ALTER TABLE profile_change_requests ADD COLUMN user_seen_at DATETIME NULL");

        $seen_column_result = mysqli_query($conn, "SHOW COLUMNS FROM profile_change_requests LIKE 'user_seen_at'");

        if ($seen_column_result && mysqli_num_rows($seen_column_result) > 0) {
            $profile_request_has_user_seen_at = true;
            mysqli_free_result($seen_column_result);
        } else if ($seen_column_result) {
            mysqli_free_result($seen_column_result);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_profile_notifications_read'])) {
        if ($profile_request_has_user_seen_at) {
            $stmt_mark_read = mysqli_prepare($conn, "
                UPDATE profile_change_requests
                SET user_seen_at = NOW()
                WHERE voter_id = ?
                AND request_status IN ('Approved', 'Rejected', 'Resolved')
                AND (user_seen_at IS NULL OR user_seen_at = '0000-00-00 00:00:00')
            ");

            if ($stmt_mark_read) {
                mysqli_stmt_bind_param($stmt_mark_read, 's', $profile_voter_id);
                mysqli_stmt_execute($stmt_mark_read);
                mysqli_stmt_close($stmt_mark_read);
            }
        }

        header('Location: about.php');
        exit;
    }

    if ($profile_request_has_user_seen_at) {
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
    } else {
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
    }

    if ($stmt_profile_notifications) {
        mysqli_stmt_bind_param($stmt_profile_notifications, 's', $profile_voter_id);
        mysqli_stmt_execute($stmt_profile_notifications);

        if ($profile_request_has_user_seen_at) {
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
        } else {
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
        }

        while (mysqli_stmt_fetch($stmt_profile_notifications)) {
            if (!$profile_request_has_user_seen_at) {
                $notif_user_seen_at = null;
            }

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

            if (
                ($notif_request_status === 'Approved' || $notif_request_status === 'Rejected' || $notif_request_status === 'Resolved')
                && (
                    !$profile_request_has_user_seen_at
                    || $notif_user_seen_at === null
                    || $notif_user_seen_at === ''
                    || $notif_user_seen_at === '0000-00-00 00:00:00'
                )
            ) {
                $profile_notification_count++;
            }
        }

        mysqli_stmt_close($stmt_profile_notifications);
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About - iVotePH</title>
    <link rel="icon" type="image/png" href="logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style id="aboutUnifiedLayoutFix">
        body.userPage.aboutPage {
            padding-top: 86px !important;
        }

        body.aboutPage .userTopbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 5000 !important;
            padding: 10px 22px !important;
            background: rgba(248, 251, 255, 0.97) !important;
            border-bottom: 1px solid #dce5f2 !important;
            box-shadow: 0 8px 22px rgba(16, 24, 40, 0.08) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
        }

        body.aboutPage .userTopbarInner {
            width: 100% !important;
            max-width: 1480px !important;
            min-height: 54px !important;
            margin: 0 auto !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center !important;
            gap: 14px !important;
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
        }

        body.aboutPage .brandLink {
            flex: 0 0 auto !important;
            min-width: 78px !important;
            padding: 0 !important;
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
        }

        body.aboutPage .brandLogo,
        body.aboutPage img.brandLogo {
            width: 74px !important;
            max-width: 74px !important;
            max-height: 38px !important;
            object-fit: contain !important;
        }

        body.aboutPage .userNavBar {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            scrollbar-width: none !important;
        }

        body.aboutPage .userNavBar::-webkit-scrollbar,
        body.aboutPage .userNavInner::-webkit-scrollbar {
            display: none !important;
        }

        body.aboutPage .userNavInner {
            overflow-x: auto !important;
            overflow-y: hidden !important;
        }

        body.aboutPage .userNavList {
            width: max-content !important;
            min-width: max-content !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        body.aboutPage .userNavList a {
            height: 40px !important;
            min-height: 40px !important;
            padding: 0 14px !important;
            border-radius: 999px !important;
            font-size: 12px !important;
            font-weight: 900 !important;
            box-shadow: none !important;
        }

        body.aboutPage .profileNotifBtn {
            flex: 0 0 auto !important;
            position: relative !important;
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            border-radius: 999px !important;
            border: 1px solid #dce5f2 !important;
            background: #ffffff !important;
            color: #0647b8 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
        }

        body.aboutPage .profileNotifBtn span {
            position: absolute !important;
            top: -6px !important;
            right: -6px !important;
            min-width: 20px !important;
            height: 20px !important;
            padding: 0 6px !important;
            border-radius: 999px !important;
            background: #d8202a !important;
            color: #ffffff !important;
            border: 2px solid #ffffff !important;
            font-size: 11px !important;
            font-weight: 950 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        body.aboutPage .userChip {
            flex: 0 0 auto !important;
            height: 44px !important;
            min-height: 44px !important;
            max-width: 285px !important;
            padding: 5px 12px 5px 5px !important;
            gap: 9px !important;
            border-radius: 999px !important;
            background: #ffffff !important;
            border: 1px solid #dce5f2 !important;
            box-shadow: none !important;
            overflow: hidden !important;
        }

        body.aboutPage .userAvatarCircle {
            width: 34px !important;
            height: 34px !important;
            min-width: 34px !important;
            background: #0b5ed7 !important;
            color: #ffffff !important;
        }

        body.aboutPage .userMain {
            width: min(1480px, calc(100% - 44px)) !important;
            margin: 0 auto !important;
            padding: 18px 0 34px !important;
        }

        .aboutUnifiedCard {
            display: grid;
            grid-template-rows: auto auto auto;
            gap: 18px;
            padding: clamp(26px, 3vw, 38px);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid #d7e3f4;
            box-shadow: 0 18px 38px rgba(16, 24, 40, 0.10);
        }

        .aboutHeroRow {
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            gap: 20px;
            align-items: stretch;
            padding-bottom: 18px;
            border-bottom: 1px solid #dce5f2;
        }

        .aboutHeroTitlePanel,
        .aboutHeroLeadPanel {
            min-height: 178px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .aboutHeroLeadPanel {
            padding: 24px;
            border-radius: 22px;
            background: #f8fbff;
            border: 1px solid #d7e3f4;
        }

        .aboutEyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #0647b8;
            font-size: 12px;
            font-weight: 950;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .aboutTitle {
            margin: 0;
            color: #101828;
            font-size: clamp(46px, 5vw, 78px);
            line-height: 0.95;
            font-weight: 950;
            letter-spacing: -0.07em;
        }

        .aboutLead {
            margin: 0;
            color: #344054;
            font-size: clamp(18px, 1.5vw, 23px);
            line-height: 1.58;
            font-weight: 700;
        }

        .aboutTextGrid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            align-items: stretch;
        }

        .aboutTextCard {
            min-height: 208px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 10px;
            padding: 20px;
            border-radius: 20px;
            background: #ffffff;
            border: 1px solid #d7e3f4;
        }

        .aboutTextCard h3 {
            margin: 0;
            color: #101828;
            font-size: 17px;
            font-weight: 950;
            letter-spacing: -0.02em;
        }

        .aboutTextCard p {
            margin: 0;
            color: #475467;
            font-size: 13.5px;
            line-height: 1.72;
        }

        .aboutFeatureGrid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            align-items: stretch;
        }

        .aboutFeatureCard {
            min-height: 138px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding: 18px;
            border-radius: 20px;
            background: #f8fbff;
            border: 1px solid #d7e3f4;
        }

        .aboutFeatureIcon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: #eaf2ff;
            color: #0647b8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            margin-bottom: 12px;
        }

        .aboutFeatureCard h3 {
            margin: 0 0 8px;
            color: #101828;
            font-size: 15px;
            font-weight: 950;
        }

        .aboutFeatureCard p {
            margin: 0;
            color: #667085;
            font-size: 12px;
            line-height: 1.55;
        }

        .profileModalContent {
            border: 0;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(16, 24, 40, 0.22);
        }

        .profileModalHeader {
            background: linear-gradient(135deg, #0646a8 0%, #0b3f91 100%);
            color: #ffffff;
            padding: 22px;
            text-align: center;
        }

        .profileModalAvatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #ffffff;
            color: #0647b8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 950;
            margin-bottom: 10px;
        }

        .profileModalHeader h5 {
            margin: 0;
            font-weight: 950;
        }

        .profileModalHeader p {
            margin: 6px 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: 13px;
        }

        .profileModalBody,
        .requestModalBody {
            padding: 22px;
            max-height: calc(100vh - 220px);
            overflow-y: auto;
            background: #ffffff;
        }

        .profileReadOnlyNote,
        .requestNotice {
            padding: 14px;
            border-radius: 16px;
            background: #eaf2ff;
            border: 1px solid #cfe0ff;
            color: #0647b8;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .profileSectionTitle {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 18px 0 12px;
            color: #101828;
            font-size: 14px;
            font-weight: 950;
        }

        .profileSectionTitle i {
            color: #0647b8;
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

        .profileFullWide {
            grid-column: 1 / -1;
        }

        .profileFullItem span {
            display: block;
            font-size: 10.5px;
            font-weight: 900;
            color: #667085;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }

        .profileFullItem strong {
            display: block;
            color: #101828;
            font-size: 14px;
            font-weight: 900;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .profileModalActions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 14px 22px;
            background: #ffffff;
            border-top: 1px solid #e1e8f3;
        }

        .profileNotifList {
            display: grid;
            gap: 12px;
        }

        .profileNotifItem {
            padding: 14px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid #dce5f2;
        }

        .profileNotifTop {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }

        .profileNotifTop strong,
        .profileNotifTop small {
            display: block;
        }

        .profileNotifText,
        .profileNotifResponse,
        .profileNotifPending {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #f7f9fd;
            color: #344054;
            font-size: 13px;
            line-height: 1.5;
        }

        .footer {
            padding: 18px 22px;
            text-align: center;
            color: #667085;
            font-size: 13px;
        }

        @media (max-width: 1100px) {
            body.userPage.aboutPage {
                padding-top: 118px !important;
            }

            body.aboutPage .userTopbarInner {
                display: grid !important;
                grid-template-columns: auto 1fr auto auto !important;
                grid-template-rows: auto auto !important;
                gap: 8px !important;
            }

            body.aboutPage .brandLink {
                grid-column: 1 !important;
                grid-row: 1 !important;
            }

            body.aboutPage .profileNotifBtn {
                grid-column: 3 !important;
                grid-row: 1 !important;
            }

            body.aboutPage .userChip {
                grid-column: 4 !important;
                grid-row: 1 !important;
                width: 44px !important;
                max-width: 44px !important;
                padding: 5px !important;
            }

            body.aboutPage .userMeta,
            body.aboutPage .userChip .fa-chevron-down {
                display: none !important;
            }

            body.aboutPage .userNavBar {
                grid-column: 1 / -1 !important;
                grid-row: 2 !important;
            }

            .aboutHeroRow,
            .aboutTextGrid {
                grid-template-columns: 1fr;
            }

            .aboutTextColumn:first-child {
                border-right: 0;
                border-bottom: 1px solid #dce5f2;
                padding-right: 0;
                padding-bottom: 18px;
            }

            .aboutTextColumn:last-child {
                padding-left: 0;
                padding-top: 18px;
            }

            .aboutFeatureGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            body.userPage.aboutPage {
                padding-top: 108px !important;
            }

            body.aboutPage .userTopbar {
                padding: 8px 10px !important;
            }

            body.aboutPage .brandLogo,
            body.aboutPage img.brandLogo {
                width: 58px !important;
            }

            body.aboutPage .profileNotifBtn,
            body.aboutPage .userChip {
                width: 38px !important;
                height: 38px !important;
                min-width: 38px !important;
            }

            body.aboutPage .userAvatarCircle {
                width: 30px !important;
                height: 30px !important;
                min-width: 30px !important;
            }

            body.aboutPage .userMain {
                width: calc(100% - 20px) !important;
                padding-top: 12px !important;
            }

            .aboutUnifiedCard {
                padding: 22px;
                border-radius: 22px;
                min-height: auto;
            }

            .aboutTitle {
                font-size: 42px;
            }

            .aboutLead {
                font-size: 16px;
            }

            .aboutFeatureGrid,
            .profileFullGrid,
            .profileFullGrid.threeCols,
            .profileModalActions {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1180px) {
            .aboutTextGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 760px) {

            .aboutHeroRow,
            .aboutTextGrid,
            .aboutFeatureGrid {
                grid-template-columns: 1fr !important;
            }

            .aboutHeroTitlePanel,
            .aboutHeroLeadPanel,
            .aboutTextCard,
            .aboutFeatureCard {
                min-height: auto !important;
            }
        }
    </style>

    <style id="ivoteAboutUniformFinalFix">
        body.aboutPage .aboutUnifiedCard {
            padding: 32px 34px !important;
            display: block !important;
            border-radius: 26px !important;
        }

        body.aboutPage .aboutHeroRow {
            display: block !important;
            padding-bottom: 18px !important;
            margin-bottom: 18px !important;
            border-bottom: 1px solid #dce5f2 !important;
        }

        body.aboutPage .aboutHeroTitlePanel,
        body.aboutPage .aboutHeroLeadPanel {
            min-height: 0 !important;
            height: auto !important;
            display: block !important;
        }

        body.aboutPage .aboutHeroLeadPanel {
            width: 100% !important;
            margin-top: 16px !important;
            padding: 18px 20px !important;
            border-radius: 20px !important;
            background: #f8fbff !important;
            border: 1px solid #d7e3f4 !important;
        }

        body.aboutPage .aboutTitle {
            margin: 0 !important;
            max-width: 100% !important;
            font-size: clamp(48px, 5vw, 70px) !important;
            line-height: 0.98 !important;
            letter-spacing: -0.065em !important;
        }

        body.aboutPage .aboutLead {
            max-width: none !important;
            margin: 0 !important;
            color: #344054 !important;
            font-size: clamp(17px, 1.45vw, 21px) !important;
            line-height: 1.58 !important;
            font-weight: 750 !important;
        }

        body.aboutPage .aboutTextGrid {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 14px !important;
            margin-bottom: 18px !important;
            align-items: stretch !important;
        }

        body.aboutPage .aboutTextCard {
            min-height: 150px !important;
            height: 100% !important;
            padding: 18px 20px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
            gap: 9px !important;
            background: #ffffff !important;
            border: 1px solid #d7e3f4 !important;
            border-radius: 20px !important;
        }

        body.aboutPage .aboutTextCard h3 {
            margin: 0 !important;
            font-size: 16px !important;
            line-height: 1.25 !important;
        }

        body.aboutPage .aboutTextCard p {
            margin: 0 !important;
            font-size: 13.5px !important;
            line-height: 1.65 !important;
        }

        body.aboutPage .aboutFeatureGrid {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 14px !important;
            align-items: stretch !important;
            margin-top: 0 !important;
        }

        body.aboutPage .aboutFeatureCard {
            min-height: 128px !important;
            height: 100% !important;
            padding: 17px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
            background: #f8fbff !important;
            border: 1px solid #d7e3f4 !important;
            border-radius: 20px !important;
        }

        body.aboutPage .aboutFeatureIcon {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px !important;
            margin-bottom: 11px !important;
        }

        body.aboutPage .aboutFeatureCard h3 {
            margin: 0 0 7px !important;
            font-size: 14.5px !important;
            line-height: 1.2 !important;
        }

        body.aboutPage .aboutFeatureCard p {
            margin: 0 !important;
            font-size: 12px !important;
            line-height: 1.48 !important;
        }

        @media (max-width: 980px) {
            body.aboutPage .aboutUnifiedCard {
                padding: 26px !important;
            }

            body.aboutPage .aboutTextGrid,
            body.aboutPage .aboutFeatureGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 640px) {
            body.aboutPage .aboutUnifiedCard {
                padding: 22px !important;
            }

            body.aboutPage .aboutTitle {
                font-size: 42px !important;
            }

            body.aboutPage .aboutLead {
                font-size: 15.5px !important;
            }

            body.aboutPage .aboutTextGrid,
            body.aboutPage .aboutFeatureGrid {
                grid-template-columns: 1fr !important;
            }

            body.aboutPage .aboutTextCard,
            body.aboutPage .aboutFeatureCard {
                min-height: auto !important;
            }
        }
    </style>


    <style id="aboutCleanFinalNoSideTextFix">
        body.aboutPage .aboutCleanCard {
            width: 100% !important;
            min-height: auto !important;
            padding: 34px !important;
            border-radius: 26px !important;
            background: rgba(255, 255, 255, 0.98) !important;
            border: 1px solid #d7e3f4 !important;
            box-shadow: 0 18px 38px rgba(16, 24, 40, 0.10) !important;
        }

        body.aboutPage .aboutCleanHeader {
            display: block !important;
            width: 100% !important;
            margin: 0 0 18px !important;
            padding: 0 0 16px !important;
            border-bottom: 1px solid #dce5f2 !important;
        }

        body.aboutPage .aboutCleanEyebrow {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin: 0 0 12px !important;
            color: #0647b8 !important;
            font-size: 12px !important;
            font-weight: 950 !important;
            letter-spacing: 0.08em !important;
            text-transform: uppercase !important;
        }

        body.aboutPage .aboutCleanHeader h1 {
            margin: 0 !important;
            color: #101828 !important;
            font-size: clamp(48px, 5vw, 74px) !important;
            line-height: 0.96 !important;
            font-weight: 950 !important;
            letter-spacing: -0.07em !important;
            text-align: left !important;
        }

        body.aboutPage .aboutCleanIntro {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 14px !important;
            width: 100% !important;
            margin: 0 0 18px !important;
        }

        body.aboutPage .aboutCleanIntro p {
            min-height: 124px !important;
            margin: 0 !important;
            padding: 18px 20px !important;
            border-radius: 18px !important;
            background: #ffffff !important;
            border: 1px solid #d7e3f4 !important;
            color: #475467 !important;
            font-size: 14px !important;
            line-height: 1.65 !important;
            font-weight: 500 !important;
            text-align: left !important;
        }

        body.aboutPage .aboutCleanFeatureGrid {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 14px !important;
            width: 100% !important;
            margin: 0 !important;
        }

        body.aboutPage .aboutCleanFeatureCard {
            min-height: 126px !important;
            padding: 17px !important;
            border-radius: 18px !important;
            background: #f8fbff !important;
            border: 1px solid #d7e3f4 !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
        }

        body.aboutPage .aboutCleanIcon {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px !important;
            border-radius: 14px !important;
            background: #eaf2ff !important;
            color: #0647b8 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 17px !important;
            margin: 0 0 11px !important;
        }

        body.aboutPage .aboutCleanFeatureCard h3 {
            margin: 0 0 7px !important;
            color: #101828 !important;
            font-size: 15px !important;
            line-height: 1.2 !important;
            font-weight: 950 !important;
        }

        body.aboutPage .aboutCleanFeatureCard p {
            margin: 0 !important;
            color: #475467 !important;
            font-size: 12px !important;
            line-height: 1.5 !important;
        }

        @media (max-width: 980px) {
            body.aboutPage .aboutCleanCard {
                padding: 26px !important;
            }

            body.aboutPage .aboutCleanIntro,
            body.aboutPage .aboutCleanFeatureGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 640px) {
            body.aboutPage .aboutCleanCard {
                padding: 22px !important;
            }

            body.aboutPage .aboutCleanHeader h1 {
                font-size: 42px !important;
            }

            body.aboutPage .aboutCleanIntro,
            body.aboutPage .aboutCleanFeatureGrid {
                grid-template-columns: 1fr !important;
            }

            body.aboutPage .aboutCleanIntro p,
            body.aboutPage .aboutCleanFeatureCard {
                min-height: auto !important;
            }
        }
    </style>
</head>

<body class="userPage aboutPage">
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
                        <li><a href="index.php"><i class="fa-solid fa-landmark"></i> Home</a></li>
                        <li><a href="about.php" class="active"><i class="fa-solid fa-circle-info"></i> About</a></li>
                        <li><a href="browsecandi.php"><i class="fa-solid fa-users"></i> Candidates</a></li>
                        <li><a href="startvoting.php"><i class="fa-solid fa-check-to-slot"></i> Voting</a></li>
                        <li><a href="results.php"><i class="fa-solid fa-chart-simple"></i> Results</a></li>
                        <li><a href="help.php"><i class="fa-solid fa-circle-question"></i> Help</a></li>
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
                    <span class="verifiedBadge"><i class="fa-solid fa-circle-check"></i> Verified Voter</span>
                </span>
                <i class="fa-solid fa-chevron-down text-muted small d-none d-md-inline"></i>
            </button>
        </div>
    </header>

    <main class="userMain userPageMotion">
        <section class="aboutCleanCard">
            <div class="aboutCleanHeader">
                <div class="aboutCleanEyebrow">
                    <i class="fa-solid fa-shield-halved"></i>
                    Secure Online Voting Platform
                </div>
                <h1>About iVotePH</h1>
            </div>

            <div class="aboutCleanIntro">
                <p>
                    iVotePH is a secure and accessible online voting website designed to help registered Filipino voters
                    review candidates, cast their ballot, monitor election updates, and manage profile correction
                    requests in one organized digital space.
                </p>
                <p>
                    The website provides a guided voting experience from login to final ballot submission. Verified
                    voters can browse candidates by position, read candidate information, select their preferred
                    candidates, and review a ballot summary before confirming their final vote.
                </p>
                <p>
                    iVotePH also protects official voter records. Users can view their registered profile details,
                    contact information, and address, but they cannot directly edit official information from the user
                    side. If a correction is needed, the voter can send a profile change request to the administrator
                    and check the request status through the notification button.
                </p>
                <p>
                    Once a ballot is submitted, the vote is locked and can no longer be changed. This helps reduce
                    accidental changes, prevents repeated voting, and supports a clearer, more reliable election process
                    for voters and administrators.
                </p>
            </div>

            <div class="aboutCleanFeatureGrid">
                <div class="aboutCleanFeatureCard">
                    <div class="aboutCleanIcon"><i class="fa-solid fa-user-check"></i></div>
                    <h3>Verified Access</h3>
                    <p>Only registered and verified voters can access protected voting pages.</p>
                </div>

                <div class="aboutCleanFeatureCard">
                    <div class="aboutCleanIcon"><i class="fa-solid fa-users"></i></div>
                    <h3>Candidate Review</h3>
                    <p>Users can review candidate details before making their final choices.</p>
                </div>

                <div class="aboutCleanFeatureCard">
                    <div class="aboutCleanIcon"><i class="fa-solid fa-list-check"></i></div>
                    <h3>Ballot Review</h3>
                    <p>The ballot summary helps voters verify selections before submission.</p>
                </div>

                <div class="aboutCleanFeatureCard">
                    <div class="aboutCleanIcon"><i class="fa-solid fa-bell"></i></div>
                    <h3>Request Updates</h3>
                    <p>Profile request updates appear through the notification bell.</p>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar"><?php echo ivoteph_h($profile_initials); ?></div>
                    <h5 id="profileModalLabel"><?php echo ivoteph_h($profile_full_name); ?></h5>
                    <p><i class="fa-solid fa-circle-check me-1"></i> Verified Registered Voter</p>
                </div>

                <div class="profileModalBody">
                    <div class="profileReadOnlyNote">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        This profile is read-only. Registered voters can review their submitted information anytime, but
                        corrections must be requested through the admin.
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-id-card"></i> Account Information</div>
                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem"><span>Voter
                                ID</span><strong><?php echo ivoteph_h($profile_voter_id); ?></strong></div>
                        <div class="profileFullItem"><span>Registration
                                Status</span><strong><?php echo ivoteph_h($profile_registration_status); ?></strong>
                        </div>
                        <div class="profileFullItem"><span>Profile
                                Status</span><strong><?php echo ivoteph_h($profile_status); ?></strong></div>
                        <div class="profileFullItem"><span>Ballot Status</span><strong>Not Submitted</strong></div>
                        <div class="profileFullItem"><span>Account Type</span><strong>Voter</strong></div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-user"></i> Personal Information</div>
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
                            <span>Sex</span><strong><?php echo ivoteph_h($profile_sex); ?></strong></div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-address-book"></i> Contact Information</div>
                    <div class="profileFullGrid">
                        <div class="profileFullItem"><span>Email
                                Address</span><strong><?php echo ivoteph_h($profile_email); ?></strong></div>
                        <div class="profileFullItem"><span>Mobile
                                Number</span><strong><?php echo ivoteph_h($profile_mobile_number); ?></strong></div>
                    </div>

                    <div class="profileSectionTitle"><i class="fa-solid fa-location-dot"></i> Registered Address</div>
                    <div class="profileFullGrid threeCols">
                        <div class="profileFullItem">
                            <span>Region</span><strong><?php echo ivoteph_h($profile_region); ?></strong></div>
                        <div class="profileFullItem">
                            <span>Province</span><strong><?php echo ivoteph_h($profile_province); ?></strong></div>
                        <div class="profileFullItem"><span>City /
                                Municipality</span><strong><?php echo ivoteph_h($profile_city_municipality); ?></strong>
                        </div>
                        <div class="profileFullItem">
                            <span>Barangay</span><strong><?php echo ivoteph_h($profile_barangay); ?></strong></div>
                        <div class="profileFullItem">
                            <span>Country</span><strong><?php echo ivoteph_h($profile_country); ?></strong></div>
                        <div class="profileFullItem profileFullWide"><span>Complete
                                Address</span><strong><?php echo ivoteph_h($profile_complete_address); ?></strong></div>
                    </div>
                </div>

                <div class="profileModalActions">
                    <button type="button" class="btn btn-primary fw-bold rounded-4" onclick="openProfileRequestModal()">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Request Change
                    </button>
                    <button type="button" class="btn btn-danger fw-bold rounded-4" onclick="logoutUser()">
                        <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
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
                            <label for="requestField" class="form-label fw-bold">Information to change</label>
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
                            <label for="requestMessage" class="form-label fw-bold">Reason / Correct Information</label>
                            <textarea class="form-control" id="requestMessage" name="request_message" rows="4" required
                                placeholder="Example: My registered last name is misspelled. It should be Dela Cruz."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-3 rounded-4 fw-bold">
                                <i class="fa-solid fa-paper-plane me-2"></i> Submit Request
                            </button>
                            <button type="button" class="btn btn-light py-3 rounded-4 fw-bold"
                                data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profileNotificationModal" tabindex="-1" aria-labelledby="profileNotificationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar"><i class="fa-solid fa-bell"></i></div>
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
                                            <small>Submitted:
                                                <?php echo ivoteph_h(ivoteph_profile_request_date($notification['created_at'])); ?></small>
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
                                                <small class="d-block mt-2">Reviewed:
                                                    <?php echo ivoteph_h(ivoteph_profile_request_date($notification['reviewed_at'])); ?></small>
                                            <?php } ?>
                                        </div>
                                    <?php } else { ?>
                                        <div class="profileNotifPending"><i class="fa-solid fa-clock me-1"></i> Waiting for admin
                                            response.</div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ivoteNoticeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content profileModalContent">
                <div class="profileModalHeader">
                    <div class="profileModalAvatar"><i class="fa-solid fa-circle-info"></i></div>
                    <h5 id="ivoteNoticeTitle">Notice</h5>
                    <p id="ivoteNoticeMessage">Action completed.</p>
                </div>
                <div class="p-3 bg-white d-grid">
                    <button type="button" class="btn btn-primary rounded-4 fw-bold"
                        data-bs-dismiss="modal">Okay</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div>© 2026 iVotePH. Secure. Accessible. Transparent.</div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function showIvoteNotice(message, title) {
            var titleElement = document.getElementById('ivoteNoticeTitle');
            var messageElement = document.getElementById('ivoteNoticeMessage');
            var modalElement = document.getElementById('ivoteNoticeModal');

            if (!modalElement || !window.bootstrap) {
                return;
            }

            if (titleElement) {
                titleElement.textContent = title || 'Notice';
            }

            if (messageElement) {
                messageElement.textContent = message || 'Action completed.';
            }

            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }

        function openProfileRequestModal() {
            var profileModalElement = document.getElementById('profileModal');
            var requestModalElement = document.getElementById('profileRequestModal');

            var profileModal = profileModalElement ? bootstrap.Modal.getInstance(profileModalElement) : null;

            if (profileModal) {
                profileModal.hide();
            }

            setTimeout(function () {
                bootstrap.Modal.getOrCreateInstance(requestModalElement).show();
            }, 250);
        }

        function submitProfileChangeRequest(event) {
            event.preventDefault();

            var form = document.getElementById('profileChangeRequestForm');
            var requestField = document.getElementById('requestField');
            var requestMessage = document.getElementById('requestMessage');

            if (!form || !requestField || !requestMessage) {
                showIvoteNotice('Profile request form was not found.', 'Request Error');
                return false;
            }

            if (!requestField.value || !requestMessage.value.trim()) {
                showIvoteNotice('Please select the information to change and enter your correction details.', 'Incomplete Request');
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
                    showIvoteNotice('Invalid server response. Please check submit_profile_request.php.', 'Request Failed');
                    return;
                }

                if (response.success) {
                    form.reset();

                    var requestModalElement = document.getElementById('profileRequestModal');
                    var requestModal = requestModalElement ? bootstrap.Modal.getInstance(requestModalElement) : null;

                    if (requestModal) {
                        requestModal.hide();
                    }

                    showIvoteNotice(response.message, 'Request Submitted');

                    setTimeout(function () {
                        window.location.reload();
                    }, 900);
                } else {
                    showIvoteNotice(response.message, 'Request Failed');
                }
            };

            xhr.onerror = function () {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }

                showIvoteNotice('Connection error. Please try again.', 'Request Failed');
            };

            xhr.send(formData);
            return false;
        }

        function logoutUser() {
            window.location.href = 'logout.php';
        }
    </script>
</body>

</html>