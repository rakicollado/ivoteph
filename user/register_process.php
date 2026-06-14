<?php
if (session_id() == '') {
    session_start();
}

require_once dirname(__FILE__) . '/db_connect.php';

function register_redirect_error($code)
{
    header('Location: register.php?error=' . urlencode($code));
    exit();
}

function register_clean($value)
{
    return trim((string) $value);
}

function register_normalize_text($value)
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/', ' ', $value);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function register_is_blank_date($value)
{
    return ($value == '' || $value == '0000-00-00' || $value == '0000-00-00 00:00:00');
}

function register_make_password_hash($password)
{
    if (function_exists('password_hash')) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    $salt = substr(str_replace('+', '.', base64_encode(md5(uniqid(mt_rand(), true), true))), 0, 22);
    return crypt($password, '$2y$10$' . $salt . '$');
}

function register_official_value_matches($official_value, $submitted_value)
{
    $official_value = register_clean($official_value);
    $submitted_value = register_clean($submitted_value);

    if ($official_value == '') {
        return true;
    }

    return register_normalize_text($official_value) == register_normalize_text($submitted_value);
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: register.php');
    exit();
}

$voter_id = isset($_POST['voter_id']) ? register_clean($_POST['voter_id']) : '';
$first_name = isset($_POST['first_name']) ? register_clean($_POST['first_name']) : '';
$middle_name = isset($_POST['middle_name']) ? register_clean($_POST['middle_name']) : '';
$last_name = isset($_POST['last_name']) ? register_clean($_POST['last_name']) : '';
$birth_date = isset($_POST['birth_date']) ? register_clean($_POST['birth_date']) : '';
$sex = isset($_POST['sex']) ? register_clean($_POST['sex']) : '';
$mobile_number = isset($_POST['mobile_number']) ? register_clean($_POST['mobile_number']) : '';
$email = isset($_POST['email']) ? register_clean($_POST['email']) : '';
$region = isset($_POST['region']) ? register_clean($_POST['region']) : '';
$province = isset($_POST['province']) ? register_clean($_POST['province']) : '';
$city_municipality = isset($_POST['city_municipality']) ? register_clean($_POST['city_municipality']) : '';
$barangay = isset($_POST['barangay']) ? register_clean($_POST['barangay']) : '';
$specific_address = isset($_POST['specific_address']) ? register_clean($_POST['specific_address']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';
$certify = isset($_POST['certify']) ? register_clean($_POST['certify']) : '';

if ($voter_id == '' || $first_name == '' || $last_name == '' || $birth_date == '' || $sex == '' || $mobile_number == '' || $email == '' || $region == '' || $province == '' || $city_municipality == '' || $barangay == '' || $specific_address == '' || $password == '' || $confirm_password == '') {
    register_redirect_error('empty');
}

if ($certify != '1') {
    register_redirect_error('not_certified');
}

if ($password != $confirm_password) {
    register_redirect_error('password_mismatch');
}

if (strlen($password) < 8) {
    register_redirect_error('weak_password');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    register_redirect_error('empty');
}

if ($sex != 'Male' && $sex != 'Female') {
    register_redirect_error('empty');
}

$mobile_number = preg_replace('/[^0-9+]/', '', $mobile_number);

if (strpos($mobile_number, '+63') === 0) {
    $mobile_number = substr($mobile_number, 3);
}

if (strpos($mobile_number, '63') === 0 && strlen($mobile_number) == 12) {
    $mobile_number = substr($mobile_number, 2);
}

if (strpos($mobile_number, '0') === 0 && strlen($mobile_number) == 11) {
    $mobile_number = substr($mobile_number, 1);
}

if ($mobile_number == '') {
    register_redirect_error('empty');
}

mysqli_autocommit($conn, false);

try {
    $sql = "
        SELECT
            voter_id,
            first_name,
            middle_name,
            last_name,
            birth_date,
            sex,
            mobile_number,
            email,
            profile_status,
            registration_status
        FROM registered_voters
        WHERE voter_id = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception('prepare voter failed');
    }

    mysqli_stmt_bind_param($stmt, 's', $voter_id);
    mysqli_stmt_execute($stmt);

    mysqli_stmt_bind_result(
        $stmt,
        $db_voter_id,
        $db_first_name,
        $db_middle_name,
        $db_last_name,
        $db_birth_date,
        $db_sex,
        $db_mobile_number,
        $db_email,
        $db_profile_status,
        $db_registration_status
    );

    if (!mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        register_redirect_error('invalid_voter');
    }

    mysqli_stmt_close($stmt);

    if (!register_official_value_matches($db_first_name, $first_name) || !register_official_value_matches($db_last_name, $last_name)) {
        mysqli_rollback($conn);
        register_redirect_error('identity_mismatch');
    }

    if (!register_is_blank_date($db_birth_date) && $db_birth_date != $birth_date) {
        mysqli_rollback($conn);
        register_redirect_error('identity_mismatch');
    }

    if (!register_official_value_matches($db_sex, $sex)) {
        mysqli_rollback($conn);
        register_redirect_error('identity_mismatch');
    }

    $stmt = mysqli_prepare($conn, "SELECT account_id FROM accounts WHERE voter_id = ? OR username = ? LIMIT 1");

    if (!$stmt) {
        throw new Exception('prepare account duplicate failed');
    }

    mysqli_stmt_bind_param($stmt, 'ss', $voter_id, $voter_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        register_redirect_error('already_registered');
    }

    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "
        SELECT rv.voter_id
        FROM registered_voters rv
        INNER JOIN accounts a ON rv.voter_id = a.voter_id
        WHERE rv.email = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('prepare email duplicate failed');
    }

    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        register_redirect_error('email_exists');
    }

    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "
        SELECT rv.voter_id
        FROM registered_voters rv
        INNER JOIN accounts a ON rv.voter_id = a.voter_id
        WHERE rv.mobile_number = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('prepare mobile duplicate failed');
    }

    mysqli_stmt_bind_param($stmt, 's', $mobile_number);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        register_redirect_error('mobile_exists');
    }

    mysqli_stmt_close($stmt);

    $password_hash = register_make_password_hash($password);

    $stmt = mysqli_prepare($conn, "
        UPDATE registered_voters
        SET
            first_name = ?,
            middle_name = ?,
            last_name = ?,
            birth_date = ?,
            sex = ?,
            mobile_number = ?,
            email = ?,
            profile_status = 'Complete',
            registration_status = 'Registered'
        WHERE voter_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('prepare voter update failed');
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ssssssss',
        $first_name,
        $middle_name,
        $last_name,
        $birth_date,
        $sex,
        $mobile_number,
        $email,
        $voter_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception('execute voter update failed');
    }

    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT address_id FROM voter_addresses WHERE voter_id = ? LIMIT 1");

    if (!$stmt) {
        throw new Exception('prepare address exists failed');
    }

    mysqli_stmt_bind_param($stmt, 's', $voter_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $address_exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    if ($address_exists) {
        $stmt = mysqli_prepare($conn, "
            UPDATE voter_addresses
            SET
                region = ?,
                province = ?,
                city_municipality = ?,
                barangay = ?,
                specific_address = ?,
                country = 'Philippines',
                updated_at = NOW()
            WHERE voter_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception('prepare address update failed');
        }

        mysqli_stmt_bind_param($stmt, 'ssssss', $region, $province, $city_municipality, $barangay, $specific_address, $voter_id);
    } else {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO voter_addresses
                (voter_id, region, province, city_municipality, barangay, specific_address, country, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 'Philippines', NOW(), NULL)
        ");

        if (!$stmt) {
            throw new Exception('prepare address insert failed');
        }

        mysqli_stmt_bind_param($stmt, 'ssssss', $voter_id, $region, $province, $city_municipality, $barangay, $specific_address);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception('execute address save failed');
    }

    mysqli_stmt_close($stmt);

    $username = $voter_id;

    $stmt = mysqli_prepare($conn, "
        INSERT INTO accounts
            (voter_id, username, password_hash, account_status, created_at, is_active)
        VALUES
            (?, ?, ?, 'Active', NOW(), 1)
    ");

    if (!$stmt) {
        throw new Exception('prepare account insert failed');
    }

    mysqli_stmt_bind_param($stmt, 'sss', $voter_id, $username, $password_hash);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception('execute account insert failed');
    }

    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    mysqli_autocommit($conn, true);

    header('Location: login.php?success=registered');
    exit();
} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, true);
    register_redirect_error('server_error');
}
?>
