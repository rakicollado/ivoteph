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

        .aboutHero {
            padding: 36px;
            margin-bottom: 18px;
            background:
                radial-gradient(circle at top right, rgba(247, 201, 72, 0.25), transparent 30%),
                linear-gradient(135deg, #0646a8 0%, #0b3f91 100%);
            color: #ffffff;
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

            .aboutHero {
                padding: 26px 22px;
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
                            <a href="myballot.php">
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
        <section class="aboutHero userCard">
            <div class="heroEyebrow">
                <i class="fa-solid fa-circle-info"></i>
                About iVotePH
            </div>

            <h1 class="heroTitle">A secure voting system for informed voters.</h1>

            <p class="heroSubtitle">
                iVotePH is designed as a secure, accessible, and transparent online voting platform.
                It connects voter registration, account authentication, candidate browsing, ballot submission,
                election results, and future Power BI reporting into one MySQL-powered system.
            </p>
        </section>

        <section class="aboutGrid">
            <div class="sectionCard userCard">
                <div class="sectionHeader">
                    <h2>What is iVotePH?</h2>
                    <p>
                        iVotePH is a full-stack voting website built for academic demonstration and system development.
                    </p>
                </div>

                <p class="aboutText">
                    The system allows registered voters to access the website using their official Voter ID and password.
                    Voter information is stored in a MySQL database and managed through MySQL Workbench for the Information
                    Management component of the project.
                </p>

                <p class="aboutText">
                    Once logged in, voters can view candidates, review voting information, access their ballot, check results,
                    and inspect their read-only profile. Any corrections to personal information must go through an admin
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
                            <span>MySQL Workbench manages the database schema, records, relationships, and SQL queries.</span>
                        </div>
                    </li>

                    <li>
                        <i class="fa-solid fa-object-group"></i>
                        <div>
                            <strong>Human Computer Interaction</strong>
                            <span>The interface focuses on navigation, readability, accessibility, and voter usability.</span>
                        </div>
                    </li>

                    <li>
                        <i class="fa-solid fa-chart-pie"></i>
                        <div>
                            <strong>Business Intelligence</strong>
                            <span>Power BI will connect to MySQL for aggregate election dashboards and result insights.</span>
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
                    Voters can only access protected pages after logging in with a valid Voter ID and password from MySQL.
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
</body>

</html>