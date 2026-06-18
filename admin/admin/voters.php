<?php
require_once dirname(__FILE__) . '/../auth_check.php';
require_admin();

$page_title = 'Voter Management';
$page_subtitle = 'Manage eligible voter IDs, voter information, verification status, and account registration.';

$pdo = db();
$errors = array();

function voter_post_value($key)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

function voter_nullable_value($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    return $value;
}

function voter_detect_profile_status($first_name, $last_name, $birth_date, $sex, $mobile_number)
{
    if ($first_name != '' && $last_name != '' && $birth_date != '' && $sex != '' && $mobile_number != '') {
        return 'Complete';
    }

    return 'Incomplete';
}

function voter_modal_id($voter_id, $prefix)
{
    return $prefix . preg_replace('/[^A-Za-z0-9_]/', '_', $voter_id);
}

function voter_display_name($voter)
{
    $name = trim(
        (isset($voter['first_name']) ? $voter['first_name'] : '') . ' ' .
        (isset($voter['middle_name']) ? $voter['middle_name'] : '') . ' ' .
        (isset($voter['last_name']) ? $voter['last_name'] : '')
    );

    if ($name == '') {
        return 'Incomplete profile';
    }

    return $name;
}

function voter_display_birthdate($birth_date)
{
    if ($birth_date == '' || $birth_date == null || $birth_date == '0000-00-00') {
        return '-';
    }

    return date('M d, Y', strtotime($birth_date));
}

function voter_display_address($voter)
{
    $address = trim(
        (isset($voter['specific_address']) ? $voter['specific_address'] : '') . ' ' .
        (isset($voter['barangay']) ? $voter['barangay'] : '') . ' ' .
        (isset($voter['city']) ? $voter['city'] : '') . ' ' .
        (isset($voter['province']) ? $voter['province'] : '') . ' ' .
        (isset($voter['region']) ? $voter['region'] : '')
    );

    if ($address == '') {
        return 'No address recorded';
    }

    return $address;
}

function voter_form_fields($voter, $is_edit)
{
    ob_start();
    ?>
    <div class="ivote-form-section">
        <h6>Voter Verification</h6>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Voter ID *</label>
                <input type="text" name="voter_id" class="form-control"
                    value="<?php echo e(isset($voter['voter_id']) ? $voter['voter_id'] : ''); ?>" placeholder="PHV-2025-000"
                    <?php echo $is_edit ? 'readonly' : 'required'; ?>>
            </div>

        </div>
    </div>

    <div class="ivote-form-section">
        <h6>Personal Information</h6>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control"
                    value="<?php echo e(isset($voter['first_name']) ? $voter['first_name'] : ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input type="text" name="middle_name" class="form-control"
                    value="<?php echo e(isset($voter['middle_name']) ? $voter['middle_name'] : ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control"
                    value="<?php echo e(isset($voter['last_name']) ? $voter['last_name'] : ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Birth Date</label>
                <input type="date" name="birth_date" class="form-control"
                    value="<?php echo e(isset($voter['birth_date']) ? $voter['birth_date'] : ''); ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Sex</label>
                <select name="sex" class="form-select">
                    <option value="">Select Sex</option>
                    <option value="Male" <?php echo ((isset($voter['sex']) ? $voter['sex'] : '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ((isset($voter['sex']) ? $voter['sex'] : '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Mobile Number</label>
                <input type="text" name="mobile_number" class="form-control"
                    value="<?php echo e(isset($voter['mobile_number']) ? $voter['mobile_number'] : ''); ?>"
                    placeholder="09171234567">
            </div>

            <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control"
                    value="<?php echo e(isset($voter['email']) ? $voter['email'] : ''); ?>">
            </div>
        </div>
    </div>

    <div class="ivote-form-section">
        <h6>Address Information</h6>

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Region</label>
                <input type="text" name="region" class="form-control"
                    value="<?php echo e(isset($voter['region']) ? $voter['region'] : ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Province</label>
                <input type="text" name="province" class="form-control"
                    value="<?php echo e(isset($voter['province']) ? $voter['province'] : ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">City / Municipality</label>
                <input type="text" name="city_municipality" class="form-control"
                    value="<?php echo e(isset($voter['city']) ? $voter['city'] : ''); ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Barangay</label>
                <input type="text" name="barangay" class="form-control"
                    value="<?php echo e(isset($voter['barangay']) ? $voter['barangay'] : ''); ?>">
            </div>

            <div class="col-md-12">
                <label class="form-label">Specific Address</label>
                <input type="text" name="specific_address" class="form-control"
                    value="<?php echo e(isset($voter['specific_address']) ? $voter['specific_address'] : ''); ?>">
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action == 'create') {
        $voter_id = voter_post_value('voter_id');
        $first_name = voter_post_value('first_name');
        $middle_name = voter_post_value('middle_name');
        $last_name = voter_post_value('last_name');
        $birth_date = voter_post_value('birth_date');
        $sex = voter_post_value('sex');
        $mobile_number = voter_post_value('mobile_number');
        $email = voter_post_value('email');
        $region = voter_post_value('region');
        $province = voter_post_value('province');
        $city = voter_post_value('city_municipality');
        if ($city == '') {
            $city = voter_post_value('city');
        }
        $barangay = voter_post_value('barangay');
        $specific_address = voter_post_value('specific_address');
        $registration_status = voter_post_value('registration_status');

        if ($voter_id == '') {
            $errors[] = 'Voter ID is required.';
        }

        if ($birth_date != '' && !valid_date($birth_date)) {
            $errors[] = 'Birth date must be valid.';
        }

        if ($sex != '' && $sex != 'Male' && $sex != 'Female') {
            $errors[] = 'Sex must be Male or Female.';
        }

        if ($registration_status == '') {
            $registration_status = 'Unregistered';
        }

        if ($registration_status != 'Unregistered' && $registration_status != 'Registered' && $registration_status != 'Blocked') {
            $errors[] = 'Invalid registration status.';
        }

        if (count($errors) == 0) {
            try {
                $check = $pdo->prepare("SELECT COUNT(*) FROM registered_voters WHERE voter_id = :voter_id");
                $check->execute(array(':voter_id' => $voter_id));

                if ((int) $check->fetchColumn() > 0) {
                    $errors[] = 'Voter ID already exists.';
                } else {
                    $profile_status = voter_detect_profile_status($first_name, $last_name, $birth_date, $sex, $mobile_number);

                    $stmt = $pdo->prepare("
                        INSERT INTO registered_voters
                        (voter_id, first_name, middle_name, last_name, birth_date, sex, mobile_number, email, profile_status, registration_status, created_at)
                        VALUES
                        (:voter_id, :first_name, :middle_name, :last_name, :birth_date, :sex, :mobile_number, :email, :profile_status, :registration_status, NOW())
                    ");

                    $stmt->execute(array(
                        ':voter_id' => $voter_id,
                        ':first_name' => voter_nullable_value($first_name),
                        ':middle_name' => voter_nullable_value($middle_name),
                        ':last_name' => voter_nullable_value($last_name),
                        ':birth_date' => voter_nullable_value($birth_date),
                        ':sex' => voter_nullable_value($sex),
                        ':mobile_number' => voter_nullable_value($mobile_number),
                        ':email' => voter_nullable_value($email),
                        ':profile_status' => $profile_status,
                        ':registration_status' => $registration_status
                    ));

                    $has_address = ($region != '' || $province != '' || $city != '' || $barangay != '' || $specific_address != '');

                    if ($has_address) {
                        $addr = $pdo->prepare("
                            INSERT INTO voter_addresses
                            (voter_id, region, province, city_municipality, barangay, specific_address)
                            VALUES
                            (:voter_id, :region, :province, :city, :barangay, :specific_address)
                        ");

                        $addr->execute(array(
                            ':voter_id' => $voter_id,
                            ':region' => $region,
                            ':province' => $province,
                            ':city' => $city,
                            ':barangay' => $barangay,
                            ':specific_address' => $specific_address
                        ));
                    }

                    audit_log('Added voter record: ' . $voter_id);
                    flash('success', 'Voter record added successfully.');
                    header('Location: voters.php');
                    exit;
                }
            } catch (Exception $e) {
                $errors[] = 'Unable to add voter record.';
            }
        }
    }

    if ($action == 'update') {
        $voter_id = voter_post_value('voter_id');
        $first_name = voter_post_value('first_name');
        $middle_name = voter_post_value('middle_name');
        $last_name = voter_post_value('last_name');
        $birth_date = voter_post_value('birth_date');
        $sex = voter_post_value('sex');
        $mobile_number = voter_post_value('mobile_number');
        $email = voter_post_value('email');
        $region = voter_post_value('region');
        $province = voter_post_value('province');
        $city = voter_post_value('city_municipality');
        if ($city == '') {
            $city = voter_post_value('city');
        }
        $barangay = voter_post_value('barangay');
        $specific_address = voter_post_value('specific_address');
        $registration_status = voter_post_value('registration_status');

        if ($voter_id == '') {
            $errors[] = 'Voter ID is missing.';
        }

        if ($birth_date != '' && !valid_date($birth_date)) {
            $errors[] = 'Birth date must be valid.';
        }

        if ($sex != '' && $sex != 'Male' && $sex != 'Female') {
            $errors[] = 'Sex must be Male or Female.';
        }

        if ($registration_status != 'Unregistered' && $registration_status != 'Registered' && $registration_status != 'Blocked') {
            $errors[] = 'Invalid registration status.';
        }

        if (count($errors) == 0) {
            try {
                $profile_status = voter_detect_profile_status($first_name, $last_name, $birth_date, $sex, $mobile_number);

                $stmt = $pdo->prepare("
                    UPDATE registered_voters
                    SET
                        first_name = :first_name,
                        middle_name = :middle_name,
                        last_name = :last_name,
                        birth_date = :birth_date,
                        sex = :sex,
                        mobile_number = :mobile_number,
                        email = :email,
                        profile_status = :profile_status,
                        registration_status = :registration_status
                    WHERE voter_id = :voter_id
                ");

                $stmt->execute(array(
                    ':first_name' => voter_nullable_value($first_name),
                    ':middle_name' => voter_nullable_value($middle_name),
                    ':last_name' => voter_nullable_value($last_name),
                    ':birth_date' => voter_nullable_value($birth_date),
                    ':sex' => voter_nullable_value($sex),
                    ':mobile_number' => voter_nullable_value($mobile_number),
                    ':email' => voter_nullable_value($email),
                    ':profile_status' => $profile_status,
                    ':registration_status' => $registration_status,
                    ':voter_id' => $voter_id
                ));

                $addr_check = $pdo->prepare("SELECT COUNT(*) FROM voter_addresses WHERE voter_id = :voter_id");
                $addr_check->execute(array(':voter_id' => $voter_id));

                if ((int) $addr_check->fetchColumn() > 0) {
                    $addr = $pdo->prepare("
                        UPDATE voter_addresses
                        SET
                            region = :region,
                            province = :province,
                            city_municipality = :city,
                            barangay = :barangay,
                            specific_address = :specific_address
                        WHERE voter_id = :voter_id
                    ");
                } else {
                    $addr = $pdo->prepare("
                        INSERT INTO voter_addresses
                        (voter_id, region, province, city_municipality, barangay, specific_address)
                        VALUES
                        (:voter_id, :region, :province, :city, :barangay, :specific_address)
                    ");
                }

                $addr->execute(array(
                    ':voter_id' => $voter_id,
                    ':region' => $region,
                    ':province' => $province,
                    ':city' => $city,
                    ':barangay' => $barangay,
                    ':specific_address' => $specific_address
                ));

                audit_log('Updated voter record: ' . $voter_id);
                flash('success', 'Voter record updated successfully.');
                header('Location: voters.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Unable to update voter record.';
            }
        }
    }

    if ($action == 'delete') {
        $voter_id = voter_post_value('voter_id');

        if ($voter_id == '') {
            $errors[] = 'Voter ID is missing.';
        }

        if (count($errors) == 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM registered_voters WHERE voter_id = :voter_id");
                $stmt->execute(array(':voter_id' => $voter_id));

                audit_log('Deleted voter record: ' . $voter_id);
                flash('success', 'Voter record deleted successfully.');
                header('Location: voters.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Unable to delete voter record. This voter may already have account, ballot, or vote records.';
            }
        }
    }

    if (count($errors) > 0) {
        foreach ($errors as $error) {
            flash('danger', $error);
        }
    }
}

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 10;

$where = array();
$params = array();

if ($search != '') {
    $where[] = "(rv.voter_id LIKE :search1 OR rv.first_name LIKE :search2 OR rv.middle_name LIKE :search3 OR rv.last_name LIKE :search4 OR rv.email LIKE :search5 OR rv.mobile_number LIKE :search6)";
    $search_like = '%' . $search . '%';
    $params[':search1'] = $search_like;
    $params[':search2'] = $search_like;
    $params[':search3'] = $search_like;
    $params[':search4'] = $search_like;
    $params[':search5'] = $search_like;
    $params[':search6'] = $search_like;
}

if ($status != '') {
    if ($status == 'Complete' || $status == 'Incomplete') {
        $where[] = "rv.profile_status = :status";
        $params[':status'] = $status;
    } else {
        $where[] = "rv.registration_status = :status";
        $params[':status'] = $status;
    }
}

$where_sql = '';

if (count($where) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

$count_sql = "
    SELECT COUNT(*)
    FROM registered_voters rv
    LEFT JOIN voter_addresses va ON rv.voter_id = va.voter_id
    $where_sql
";

$count_stmt = $pdo->prepare($count_sql);

foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$count_stmt->execute();
$total_rows = (int) $count_stmt->fetchColumn();

$pagination = paginate($total_rows, $page, $per_page);
$page = $pagination[0];
$total_pages = $pagination[1];
$offset = $pagination[2];

$sql = "
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
        rv.created_at,
        va.region,
        va.province,
        va.city_municipality AS city,
        va.barangay,
        va.specific_address,
        a.account_id,
        a.username
    FROM registered_voters rv
    LEFT JOIN voter_addresses va ON rv.voter_id = va.voter_id
    LEFT JOIN accounts a ON rv.voter_id = a.voter_id
    $where_sql
    ORDER BY rv.created_at DESC, rv.voter_id ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', (int) $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$stmt->execute();

$voters = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count_total    = 0;
$count_reg      = 0;
$count_unreg    = 0;
$count_blocked  = 0;
$count_complete = 0;
$count_accounts = 0;
try {
    $count_total    = (int) $pdo->query("SELECT COUNT(*) FROM registered_voters")->fetchColumn();
    $count_reg      = (int) $pdo->query("SELECT COUNT(*) FROM registered_voters WHERE registration_status = 'Registered'")->fetchColumn();
    $count_unreg    = (int) $pdo->query("SELECT COUNT(*) FROM registered_voters WHERE registration_status = 'Unregistered'")->fetchColumn();
    $count_blocked  = (int) $pdo->query("SELECT COUNT(*) FROM registered_voters WHERE registration_status = 'Blocked'")->fetchColumn();
    $count_complete = (int) $pdo->query("SELECT COUNT(*) FROM registered_voters WHERE profile_status = 'Complete'")->fetchColumn();
    $count_accounts = (int) $pdo->query("SELECT COUNT(DISTINCT voter_id) FROM accounts")->fetchColumn();
} catch (Exception $e) {}

$flashes = consume_flash();

require_once dirname(__FILE__) . '/../includes/header.php';
require_once dirname(__FILE__) . '/../includes/sidebar.php';
?>

<style>
/* ── Voters page polish ─────────────────────────────────────── */
.ivote-management-page {
    padding: 0;
    max-width: 1500px;
    margin: 0 auto;
}

.ivote-filter-card {
    background: #ffffff;
    border: 1px solid #e4ecf7;
    border-radius: 18px;
    padding: 18px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(6, 71, 184, 0.06);
}

.ivote-filter-form {
    display: grid;
    grid-template-columns: 1fr minmax(160px, 220px) auto auto auto;
    align-items: end;
    gap: 12px;
}

.ivote-filter-form > div {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.ivote-filter-form .form-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #667085;
    margin-bottom: 0;
}

.ivote-filter-form .form-control,
.ivote-filter-form .form-select {
    border-radius: 12px;
    border-color: #d0ddf0;
    font-size: 14px;
}

.ivote-filter-form .btn {
    border-radius: 12px;
    font-weight: 600;
    padding: 8px 18px;
    white-space: nowrap;
    align-self: flex-end;
}

.ivote-data-card {
    background: #ffffff;
    border: 1px solid #e4ecf7;
    border-radius: 18px;
    box-shadow: 0 2px 10px rgba(6, 71, 184, 0.06);
    overflow: hidden;
}

.ivote-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid #edf2fb;
    background: #f8fbff;
}

.ivote-section-title {
    font-size: 17px;
    font-weight: 800;
    letter-spacing: -0.02em;
    margin: 0;
    color: #0d1b3e;
}

.ivote-record-count {
    background: #eef5ff;
    color: #0647b8;
    font-size: 13px;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 999px;
}

.ivote-management-table {
    margin: 0;
    font-size: 14px;
}

.ivote-management-table thead th {
    background: #f4f8ff;
    color: #667085;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e1ebf9;
    padding: 12px 14px;
    white-space: nowrap;
}

.ivote-management-table tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f5fd;
    color: #1a2942;
}

.ivote-management-table tbody tr:last-child td { border-bottom: none; }
.ivote-management-table tbody tr:hover td { background: #f8fbff; }

.btn-ivote-icon {
    width: 34px;
    height: 34px;
    padding: 0;
    border-radius: 10px;
    border: 1px solid #d0ddf0;
    background: #f4f8ff;
    color: #0647b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: all 0.15s;
}
.btn-ivote-icon:hover { background: #0647b8; color: #fff; border-color: #0647b8; }
.btn-ivote-icon.danger { color: #d92d20; border-color: #fecdd3; background: #fff5f5; }
.btn-ivote-icon.danger:hover { background: #d92d20; color: #fff; border-color: #d92d20; }

.ivote-pagination-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 22px;
    border-top: 1px solid #edf2fb;
}
.ivote-pagination-wrap .page-link {
    border-radius: 8px !important;
    margin: 0 2px;
    font-size: 13px;
    font-weight: 600;
    border-color: #d0ddf0;
    color: #0647b8;
}
.ivote-pagination-wrap .page-item.active .page-link {
    background: #0647b8;
    border-color: #0647b8;
}

.ivote-flash-wrap { margin-bottom: 16px; }

.ivote-form-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #edf2fb;
}
.ivote-form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.ivote-form-section h6 {
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #0647b8;
    margin-bottom: 14px;
}

.ivote-profile-view {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.ivote-profile-view > div {
    background: #f7f9fd;
    border: 1px solid #e1e8f3;
    border-radius: 14px;
    padding: 12px 14px;
}
.ivote-profile-view > div.full { grid-column: 1 / -1; }
.ivote-profile-view span {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #667085;
    margin-bottom: 4px;
}
.ivote-profile-view strong { display: block; font-size: 14px; color: #1a2942; font-weight: 700; }

.btn-ivote { background: #0647b8; color: #fff; border-color: #0647b8; border-radius: 12px; font-weight: 700; }
.btn-ivote:hover { background: #053a94; color: #fff; }
.btn-ivote-outline { border: 1.5px solid #0647b8; color: #0647b8; border-radius: 12px; font-weight: 700; background: transparent; }
.btn-ivote-outline:hover { background: #0647b8; color: #fff; }
.ivote-reset-btn { border-radius: 12px; font-weight: 600; }

.ivote-modal .modal-header {
    background: linear-gradient(135deg, #0647b8, #0b63e5);
    color: #fff;
    border-radius: 16px 16px 0 0;
    border-bottom: none;
    padding: 18px 22px;
}
.ivote-modal .modal-title { font-weight: 800; font-size: 17px; }
.ivote-modal .btn-close { filter: brightness(0) invert(1); }
.ivote-modal .modal-content { border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(6,71,184,0.18); }

.ivote-stats-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.ivote-stat-card {
    background: #ffffff;
    border: 1px solid #e4ecf7;
    border-radius: 18px;
    padding: 22px 20px;
    box-shadow: 0 2px 8px rgba(6, 71, 184, 0.06);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.ivote-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #eef5ff;
    color: #0647b8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-bottom: 14px;
    flex-shrink: 0;
}
.ivote-stat-icon.green  { background: #f0fdf4; color: #16a34a; }
.ivote-stat-icon.yellow { background: #fffbeb; color: #b45309; }
.ivote-stat-icon.red    { background: #fff5f5; color: #d92d20; }
.ivote-stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.ivote-stat-icon.teal   { background: #f0fdfa; color: #0d9488; }

.ivote-stat-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #667085;
    margin-bottom: 4px;
}

.ivote-stat-value {
    font-size: 32px;
    font-weight: 800;
    color: #0d1b3e;
    letter-spacing: -0.03em;
    line-height: 1.1;
    margin-bottom: 4px;
    margin-top: 0;
}

.ivote-stat-caption {
    font-size: 12px;
    color: #98a2b3;
    margin: 0;
}

@media (max-width: 1200px) {
    .ivote-stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 768px) {
    .ivote-filter-form > div { min-width: 0; }
    .ivote-profile-view { grid-template-columns: 1fr; }
    .ivote-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

<div class="ivote-management-page">

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

    <div class="ivote-stats-grid">
        <div class="ivote-stat-card">
            <div class="ivote-stat-icon"><i class="bi bi-people-fill"></i></div>
            <h3 class="ivote-stat-title">Total Voters</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_total); ?></p>
            <p class="ivote-stat-caption">All voter records</p>
        </div>
        <div class="ivote-stat-card">
            <div class="ivote-stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <h3 class="ivote-stat-title">Registered</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_reg); ?></p>
            <p class="ivote-stat-caption">Eligible to vote</p>
        </div>
        <div class="ivote-stat-card">
            <div class="ivote-stat-icon yellow"><i class="bi bi-hourglass-split"></i></div>
            <h3 class="ivote-stat-title">Unregistered</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_unreg); ?></p>
            <p class="ivote-stat-caption">Pending registration</p>
        </div>
        <div class="ivote-stat-card">
            <div class="ivote-stat-icon teal"><i class="bi bi-person-check-fill"></i></div>
            <h3 class="ivote-stat-title">Complete Profile</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_complete); ?></p>
            <p class="ivote-stat-caption">Fully filled profiles</p>
        </div>
        <div class="ivote-stat-card">
            <div class="ivote-stat-icon purple"><i class="bi bi-person-badge-fill"></i></div>
            <h3 class="ivote-stat-title">With Account</h3>
            <p class="ivote-stat-value"><?php echo number_format($count_accounts); ?></p>
            <p class="ivote-stat-caption">Has login account</p>
        </div>
    </div>

    <div class="ivote-filter-card">
        <form method="GET" action="voters.php" class="ivote-filter-form">
            <div>
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>"
                    placeholder="Search voter ID, name, email, or mobile number">
            </div>

            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="Registered" <?php echo ($status == 'Registered') ? 'selected' : ''; ?>>Registered
                    </option>
                    <option value="Unregistered" <?php echo ($status == 'Unregistered') ? 'selected' : ''; ?>>Unregistered
                    </option>
                    <option value="Blocked" <?php echo ($status == 'Blocked') ? 'selected' : ''; ?>>Blocked</option>
                    <option value="Complete" <?php echo ($status == 'Complete') ? 'selected' : ''; ?>>Complete Profile
                    </option>
                    <option value="Incomplete" <?php echo ($status == 'Incomplete') ? 'selected' : ''; ?>>Incomplete
                        Profile</option>
                </select>
            </div>

            <button type="submit" class="btn btn-ivote-outline">
                <i class="bi bi-funnel me-1"></i>
                Filter
            </button>

            <a href="voters.php" class="btn btn-light border ivote-reset-btn">
                Reset
            </a>

            <button type="button" class="btn btn-ivote" data-bs-toggle="modal" data-bs-target="#addVoterModal">
                <i class="bi bi-plus-circle me-1"></i>
                Add Voter
            </button>
        </form>
    </div>

    <div class="ivote-card ivote-data-card">
        <div class="ivote-card-header">
            <h3 class="ivote-section-title">
                <i class="bi bi-person-vcard text-primary me-1"></i>
                Voter Records
            </h3>

            <span class="ivote-record-count">
                <?php echo number_format($total_rows); ?> record(s)
            </span>
        </div>

        <div class="table-responsive">
            <table class="table ivote-management-table">
                <thead>
                    <tr>
                        <th>Voter ID</th>
                        <th>Name</th>
                        <th>Birth Date</th>
                        <th>Sex</th>
                        <th>Contact</th>
                        <th>Profile</th>
                        <th>Registration</th>
                        <th>Account</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($voters) > 0) { ?>
                        <?php foreach ($voters as $voter) { ?>
                            <?php
                            $full_name = voter_display_name($voter);
                            $birth_display = voter_display_birthdate($voter['birth_date']);
                            $address_line = voter_display_address($voter);
                            $view_modal = voter_modal_id($voter['voter_id'], 'viewVoter');
                            $edit_modal = voter_modal_id($voter['voter_id'], 'editVoter');
                            $delete_modal = voter_modal_id($voter['voter_id'], 'deleteVoter');
                            ?>

                            <tr>
                                <td>
                                    <strong class="text-primary"><?php echo e($voter['voter_id']); ?></strong>
                                </td>

                                <td>
                                    <div class="fw-bold"><?php echo e($full_name); ?></div>
                                    <small class="text-muted"><?php echo e($address_line); ?></small>
                                </td>

                                <td><?php echo e($birth_display); ?></td>

                                <td><?php echo e($voter['sex'] ? $voter['sex'] : '-'); ?></td>

                                <td>
                                    <div><?php echo e($voter['email'] ? $voter['email'] : '-'); ?></div>
                                    <small
                                        class="text-muted"><?php echo e($voter['mobile_number'] ? $voter['mobile_number'] : 'No mobile'); ?></small>
                                </td>

                                <td>
                                    <span class="badge <?php echo badge_class($voter['profile_status']); ?>">
                                        <?php echo e($voter['profile_status']); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge <?php echo badge_class($voter['registration_status']); ?>">
                                        <?php echo e($voter['registration_status']); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($voter['account_id']) { ?>
                                        <span class="badge text-bg-success">Has Account</span>
                                        <div>
                                            <small class="text-muted"><?php echo e($voter['username']); ?></small>
                                        </div>
                                    <?php } else { ?>
                                        <span class="badge text-bg-secondary">No Account</span>
                                    <?php } ?>
                                </td>

                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-ivote-icon" data-bs-toggle="modal"
                                        data-bs-target="#<?php echo e($view_modal); ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-ivote-icon" data-bs-toggle="modal"
                                        data-bs-target="#<?php echo e($edit_modal); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-ivote-icon danger" data-bs-toggle="modal"
                                        data-bs-target="#<?php echo e($delete_modal); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>

                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                No voters found.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="ivote-pagination-wrap">
            <div class="text-muted small">
                Page <?php echo number_format($page); ?> of <?php echo number_format($total_pages); ?>
            </div>

            <nav>
                <ul class="pagination mb-0">
                    <?php
                    $window = 2;
                    $prev_page = max(1, $page - 1);
                    $next_page = min($total_pages, $page + 1);
                    $base_url = 'voters.php?search=' . urlencode($search) . '&status=' . urlencode($status) . '&page=';
                    ?>
                    <li class="page-item <?php echo ($page == 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $base_url . $prev_page; ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php
                    $shown = array();
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i == 1 || $i == $total_pages || ($i >= $page - $window && $i <= $page + $window)) {
                            $shown[] = $i;
                        }
                    }
                    $prev_shown = null;
                    foreach ($shown as $i) {
                        if ($prev_shown !== null && $i - $prev_shown > 1) {
                            echo '<li class="page-item disabled"><span class="page-link px-2">…</span></li>';
                        }
                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">' .
                             '<a class="page-link" href="' . $base_url . $i . '">' . $i . '</a></li>';
                        $prev_shown = $i;
                    }
                    ?>
                    <li class="page-item <?php echo ($page == $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $base_url . $next_page; ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<?php if (count($voters) > 0) { ?>
    <?php foreach ($voters as $voter) { ?>
        <?php
        $full_name = voter_display_name($voter);
        $birth_display = voter_display_birthdate($voter['birth_date']);
        $address_line = voter_display_address($voter);
        $view_modal = voter_modal_id($voter['voter_id'], 'viewVoter');
        $edit_modal = voter_modal_id($voter['voter_id'], 'editVoter');
        $delete_modal = voter_modal_id($voter['voter_id'], 'deleteVoter');
        ?>
        <div class="modal fade" id="<?php echo e($view_modal); ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content ivote-modal">
                    <div class="modal-header">
                        <h5 class="modal-title">Voter Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="ivote-profile-view">
                            <div>
                                <span>Voter ID</span>
                                <strong><?php echo e($voter['voter_id']); ?></strong>
                            </div>

                            <div>
                                <span>Full Name</span>
                                <strong><?php echo e($full_name); ?></strong>
                            </div>

                            <div>
                                <span>Birth Date</span>
                                <strong><?php echo e($birth_display); ?></strong>
                            </div>

                            <div>
                                <span>Sex</span>
                                <strong><?php echo e($voter['sex'] ? $voter['sex'] : '-'); ?></strong>
                            </div>

                            <div>
                                <span>Email</span>
                                <strong><?php echo e($voter['email'] ? $voter['email'] : '-'); ?></strong>
                            </div>

                            <div>
                                <span>Mobile</span>
                                <strong><?php echo e($voter['mobile_number'] ? $voter['mobile_number'] : '-'); ?></strong>
                            </div>

                            <div class="full">
                                <span>Address</span>
                                <strong><?php echo e($address_line); ?></strong>
                            </div>

                            <div>
                                <span>Profile Status</span>
                                <strong><?php echo e($voter['profile_status']); ?></strong>
                            </div>

                            <div>
                                <span>Registration Status</span>
                                <strong><?php echo e($voter['registration_status']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="<?php echo e($edit_modal); ?>" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content ivote-modal">
                    <form method="POST" action="voters.php">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Voter Record</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update">
                            <?php echo voter_form_fields($voter, true); ?>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-ivote">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="<?php echo e($delete_modal); ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content ivote-modal">
                    <form method="POST" action="voters.php">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Voter Record</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="voter_id" value="<?php echo e($voter['voter_id']); ?>">

                            <p>
                                Are you sure you want to delete voter
                                <strong><?php echo e($voter['voter_id']); ?></strong>?
                            </p>

                            <small class="text-muted">
                                This action cannot be undone. Voters with accounts, ballots, or votes may not be deletable.
                            </small>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Voter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<div class="modal fade" id="addVoterModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content ivote-modal">
            <form method="POST" action="voters.php">
                <div class="modal-header">
                    <h5 class="modal-title">Add Voter Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">

                    <?php
                    $blank_voter = array(
                        'voter_id' => '',
                        'first_name' => '',
                        'middle_name' => '',
                        'last_name' => '',
                        'birth_date' => '',
                        'sex' => '',
                        'mobile_number' => '',
                        'email' => '',
                        'region' => '',
                        'province' => '',
                        'city' => '',
                        'barangay' => '',
                        'specific_address' => '',
                        'registration_status' => 'Unregistered'
                    );

                    echo voter_form_fields($blank_voter, false);
                    ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ivote">Add Voter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once dirname(__FILE__) . '/../includes/footer.php';
?>