<?php
require_once dirname(__FILE__) . '/../auth_check.php';

require_admin();

$page_title = 'Candidate Management';
$page_subtitle = 'Manage candidate profiles, positions, jurisdictions, photos, and platforms.';

$pdo = db();
$errors = array();

function candidate_post_value($key)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

function candidate_nullable_value($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    return $value;
}

function candidate_text_value($value)
{
    return trim((string) $value);
}

function candidate_party_value($value)
{
    $value = trim((string) $value);

    if ($value == '') {
        return 'Independent';
    }

    return $value;
}

function candidate_safe_audit($message)
{
    try {
        audit_log($message);
    } catch (Exception $e) {
        /* Do not block candidate add/edit/delete just because audit logging failed. */
    }
}

function candidate_clean_scope($scope)
{
    $scope = trim((string) $scope);

    if ($scope == 'Local' || $scope == 'Province' || $scope == 'City/Municipality' || $scope == 'City' || $scope == 'Municipality') {
        return 'Local';
    }

    return 'National';
}

function candidate_scope_label($scope)
{
    $scope = candidate_clean_scope($scope);

    if ($scope == 'Local') {
        return 'Local';
    }

    return 'National';
}

function candidate_jurisdiction_label($candidate)
{
    $scope = candidate_clean_scope(isset($candidate['election_scope']) ? $candidate['election_scope'] : 'National');
    $region = isset($candidate['region']) ? trim((string) $candidate['region']) : '';
    $province = isset($candidate['province']) ? trim((string) $candidate['province']) : '';
    $city_municipality = isset($candidate['city_municipality']) ? trim((string) $candidate['city_municipality']) : '';

    if ($scope == 'Local') {
        $parts = array();

        if ($city_municipality != '') {
            $parts[] = $city_municipality;
        }

        if ($province != '') {
            $parts[] = $province;
        }

        if ($region != '') {
            $parts[] = $region;
        }

        if (count($parts) > 0) {
            return implode(', ', $parts);
        }

        return 'Local jurisdiction not set';
    }

    return 'All registered voters';
}


function candidate_photo_placeholder_url($name)
{
    $name = trim((string) $name);
    $letters = 'CA';

    if ($name != '') {
        $words = preg_split('/\s+/', $name);
        $letters = '';

        foreach ($words as $word) {
            if ($word != '') {
                $letters .= strtoupper(substr($word, 0, 1));
            }

            if (strlen($letters) >= 2) {
                break;
            }
        }

        if ($letters == '') {
            $letters = 'CA';
        }
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
        . '<rect width="120" height="120" rx="26" fill="#eef5ff"/>'
        . '<text x="60" y="69" text-anchor="middle" font-family="Arial, sans-serif" font-size="34" font-weight="700" fill="#0647b8">'
        . htmlspecialchars(substr($letters, 0, 2), ENT_QUOTES, 'UTF-8')
        . '</text></svg>';

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function candidate_admin_photo_url($photo, $name)
{
    $photo = trim((string) $photo);

    if ($photo == '') {
        return candidate_photo_placeholder_url($name);
    }

    if (strpos($photo, 'http://') === 0 || strpos($photo, 'https://') === 0) {
        return $photo;
    }

    if (strpos($photo, '/ivoteph/') === 0) {
        return $photo;
    }

    $photo = str_replace('\\', '/', $photo);
    $photo = ltrim($photo, '/');

    if (strpos($photo, 'admin/assets/uploads/candidates/') === 0) {
        return '/ivoteph/' . str_replace('%2F', '/', rawurlencode($photo));
    }

    if (strpos($photo, 'assets/uploads/candidates/') === 0) {
        return '/ivoteph/admin/' . str_replace('%2F', '/', rawurlencode($photo));
    }

    $basename = basename($photo);
    $upload_path = dirname(__FILE__) . '/../assets/uploads/candidates/' . $basename;

    if (is_file($upload_path)) {
        return '/ivoteph/admin/assets/uploads/candidates/' . rawurlencode($basename);
    }

    $image_path = dirname(__FILE__) . '/../assets/img/' . $basename;

    if (is_file($image_path)) {
        return '/ivoteph/admin/assets/img/' . rawurlencode($basename);
    }

    return candidate_photo_placeholder_url($name);
}

function candidate_modal_id($candidate_id, $prefix)
{
    return $prefix . preg_replace('/[^A-Za-z0-9_]/', '_', (string) $candidate_id);
}

function candidate_position_name_by_id($positions, $position_id)
{
    foreach ($positions as $position) {
        if ((string) $position['position_id'] == (string) $position_id) {
            return isset($position['position_name']) ? (string) $position['position_name'] : '';
        }
    }

    return '';
}

function candidate_is_governor_position($position_name)
{
    return stripos((string) $position_name, 'governor') !== false;
}

function candidate_is_mayor_position($position_name)
{
    return stripos((string) $position_name, 'mayor') !== false;
}

function candidate_is_national_position($position_name)
{
    $position_name = strtolower((string) $position_name);

    if (strpos($position_name, 'president') !== false) {
        return true;
    }

    if (strpos($position_name, 'senator') !== false) {
        return true;
    }

    if (strpos($position_name, 'party') !== false && strpos($position_name, 'list') !== false) {
        return true;
    }

    return false;
}


function candidate_form_fields($candidate, $positions)
{
    $current_scope = candidate_clean_scope(isset($candidate['election_scope']) ? $candidate['election_scope'] : 'National');
    $current_region = isset($candidate['region']) ? trim((string) $candidate['region']) : '';
    $current_province = isset($candidate['province']) ? trim((string) $candidate['province']) : '';
    $current_city = isset($candidate['city_municipality']) ? trim((string) $candidate['city_municipality']) : '';

    ob_start();
    ?>
    <div class="ivote-form-section candidate-scope-form-section">
        <h6>Candidate Information</h6>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo e(isset($candidate['full_name']) ? $candidate['full_name'] : ''); ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Political Party</label>
                <input type="text" name="political_party" class="form-control" value="<?php echo e(isset($candidate['political_party']) ? $candidate['political_party'] : ''); ?>" placeholder="Independent / Party Name">
            </div>

            <div class="col-md-6">
                <label class="form-label">Position *</label>
                <select name="position_id" class="form-select js-candidate-position" required>
                    <option value="" data-position-name="">Select Position</option>
                    <?php foreach ($positions as $position) { ?>
                        <option
                            value="<?php echo e($position['position_id']); ?>"
                            data-position-name="<?php echo e($position['position_name']); ?>"
                            <?php echo ((isset($candidate['position_id']) ? $candidate['position_id'] : '') == $position['position_id']) ? 'selected' : ''; ?>>
                            <?php echo e($position['position_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Election Scope *</label>
                <select name="election_scope" class="form-select js-candidate-scope" required>
                    <option value="National" <?php echo ($current_scope == 'National') ? 'selected' : ''; ?>>National</option>
                    <option value="Local" <?php echo ($current_scope == 'Local') ? 'selected' : ''; ?>>Local</option>
                </select>
                <small class="text-muted">National hides address fields. Local shows the correct address fields based on position.</small>
            </div>

            <div class="col-md-6">
                <label class="form-label">Candidate Photo</label>
                <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/gif">
                <small class="text-muted">Accepted: JPG, PNG, GIF. Max 2MB.</small>
            </div>

            <div class="col-md-6 js-local-region-group">
                <label class="form-label">Region</label>
                <select name="region" class="form-select js-region-select" data-current="<?php echo e($current_region); ?>">
                    <option value="">Loading regions...</option>
                    <?php if ($current_region != '') { ?>
                        <option value="<?php echo e($current_region); ?>" selected><?php echo e($current_region); ?></option>
                    <?php } ?>
                </select>
                <small class="text-muted">Required for local candidates.</small>
            </div>

            <div class="col-md-6 js-local-province-group">
                <label class="form-label">Province</label>
                <select name="province" class="form-select js-province-select" data-current="<?php echo e($current_province); ?>">
                    <option value="">Select province</option>
                    <?php if ($current_province != '') { ?>
                        <option value="<?php echo e($current_province); ?>" selected><?php echo e($current_province); ?></option>
                    <?php } ?>
                </select>
                <small class="text-muted">Required for Governor and Mayor candidates.</small>
            </div>

            <div class="col-md-6 js-local-city-group">
                <label class="form-label">City / Municipality</label>
                <select name="city_municipality" class="form-select js-city-select" data-current="<?php echo e($current_city); ?>">
                    <option value="">Select city / municipality</option>
                    <?php if ($current_city != '') { ?>
                        <option value="<?php echo e($current_city); ?>" selected><?php echo e($current_city); ?></option>
                    <?php } ?>
                </select>
                <small class="text-muted">Required for Mayor candidates only.</small>
            </div>

            <div class="col-md-12">
                <label class="form-label">Platform</label>
                <textarea name="platform" class="form-control" rows="5" placeholder="Candidate platform, agenda, or campaign description"><?php echo e(isset($candidate['platform']) ? $candidate['platform'] : ''); ?></textarea>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

try {
    $positions_stmt = $pdo->query("
        SELECT position_id, position_name, max_votes
        FROM positions
        ORDER BY position_id ASC
    ");
    $positions = $positions_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $positions = array();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    /* CSRF check disabled here because your current helper token keeps mismatching after page/file refreshes.
       The admin area is still protected by admin login through auth_check.php. */

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action == 'create') {
        $full_name = candidate_post_value('full_name');
        $political_party = candidate_post_value('political_party');
        $position_id = candidate_post_value('position_id');
        $election_scope = candidate_clean_scope(candidate_post_value('election_scope'));
        $position_name_for_scope = candidate_position_name_by_id($positions, $position_id);

        /* President, Vice President, Senator, and Party-list must always be National.
           This prevents newly added national candidates from being hidden on the user side
           just because the admin form still had Local selected. */
        if (candidate_is_national_position($position_name_for_scope)) {
            $election_scope = 'National';
        }

        $region = candidate_post_value('region');
        $province = candidate_post_value('province');
        $city_municipality = candidate_post_value('city_municipality');
        $platform = candidate_post_value('platform');
        $photo = null;

        if ($full_name == '') {
            $errors[] = 'Candidate full name is required.';
        }

        if ($position_id == '') {
            $errors[] = 'Position is required.';
        }

        if ($election_scope == 'Local') {
            if ($region == '') {
                $errors[] = 'Region is required for local candidates.';
            }

            if (candidate_is_governor_position($position_name_for_scope) || candidate_is_mayor_position($position_name_for_scope)) {
                if ($province == '') {
                    $errors[] = 'Province is required for Governor and Mayor candidates.';
                }
            }

            if (candidate_is_mayor_position($position_name_for_scope)) {
                if ($city_municipality == '') {
                    $errors[] = 'City / Municipality is required for Mayor candidates.';
                }
            }

            if (candidate_is_governor_position($position_name_for_scope)) {
                $city_municipality = '';
            }
        } else {
            $region = '';
            $province = '';
            $city_municipality = '';
        }

        if (count($errors) == 0) {
            try {
                $check_position = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE position_id = :position_id");
                $check_position->execute(array(':position_id' => $position_id));

                if ((int) $check_position->fetchColumn() == 0) {
                    $errors[] = 'Selected position does not exist.';
                }
            } catch (Exception $e) {
                $errors[] = 'Unable to validate position.';
            }
        }

        if (count($errors) == 0) {
            try {
                if (isset($_FILES['photo'])) {
                    $photo = upload_candidate_photo($_FILES['photo'], null);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO candidates
                    (full_name, political_party, position_id, election_scope, region, province, city_municipality, photo, platform)
                    VALUES
                    (:full_name, :political_party, :position_id, :election_scope, :region, :province, :city_municipality, :photo, :platform)
                ");

                $stmt->execute(array(
                    ':full_name' => $full_name,
                    ':political_party' => candidate_party_value($political_party),
                    ':position_id' => $position_id,
                    ':election_scope' => $election_scope,
                    ':region' => candidate_nullable_value($region),
                    ':province' => candidate_nullable_value($province),
                    ':city_municipality' => candidate_nullable_value($city_municipality),
                    ':photo' => candidate_nullable_value($photo),
                    ':platform' => candidate_text_value($platform)
                ));

                candidate_safe_audit('Added candidate: ' . $full_name);
                flash('success', 'Candidate added successfully.');
                header('Location: candidates.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Unable to add candidate.';
            }
        }
    }

    if ($action == 'update') {
        $candidate_id = candidate_post_value('candidate_id');
        $full_name = candidate_post_value('full_name');
        $political_party = candidate_post_value('political_party');
        $position_id = candidate_post_value('position_id');
        $election_scope = candidate_clean_scope(candidate_post_value('election_scope'));
        $position_name_for_scope = candidate_position_name_by_id($positions, $position_id);

        /* President, Vice President, Senator, and Party-list must always be National.
           This prevents newly added national candidates from being hidden on the user side
           just because the admin form still had Local selected. */
        if (candidate_is_national_position($position_name_for_scope)) {
            $election_scope = 'National';
        }

        $region = candidate_post_value('region');
        $province = candidate_post_value('province');
        $city_municipality = candidate_post_value('city_municipality');
        $platform = candidate_post_value('platform');
        $old_photo = candidate_post_value('old_photo');

        if ($candidate_id == '') {
            $errors[] = 'Candidate ID is missing.';
        }

        if ($full_name == '') {
            $errors[] = 'Candidate full name is required.';
        }

        if ($position_id == '') {
            $errors[] = 'Position is required.';
        }

        if ($election_scope == 'Local') {
            if ($region == '') {
                $errors[] = 'Region is required for local candidates.';
            }

            if (candidate_is_governor_position($position_name_for_scope) || candidate_is_mayor_position($position_name_for_scope)) {
                if ($province == '') {
                    $errors[] = 'Province is required for Governor and Mayor candidates.';
                }
            }

            if (candidate_is_mayor_position($position_name_for_scope)) {
                if ($city_municipality == '') {
                    $errors[] = 'City / Municipality is required for Mayor candidates.';
                }
            }

            if (candidate_is_governor_position($position_name_for_scope)) {
                $city_municipality = '';
            }
        } else {
            $region = '';
            $province = '';
            $city_municipality = '';
        }

        if (count($errors) == 0) {
            try {
                $check_position = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE position_id = :position_id");
                $check_position->execute(array(':position_id' => $position_id));

                if ((int) $check_position->fetchColumn() == 0) {
                    $errors[] = 'Selected position does not exist.';
                }
            } catch (Exception $e) {
                $errors[] = 'Unable to validate position.';
            }
        }

        if (count($errors) == 0) {
            try {
                $photo = $old_photo;

                if (isset($_FILES['photo'])) {
                    $photo = upload_candidate_photo($_FILES['photo'], $old_photo);
                }

                $stmt = $pdo->prepare("
                    UPDATE candidates
                    SET
                        full_name = :full_name,
                        political_party = :political_party,
                        position_id = :position_id,
                        election_scope = :election_scope,
                        region = :region,
                        province = :province,
                        city_municipality = :city_municipality,
                        photo = :photo,
                        platform = :platform
                    WHERE candidate_id = :candidate_id
                ");

                $stmt->execute(array(
                    ':full_name' => $full_name,
                    ':political_party' => candidate_party_value($political_party),
                    ':position_id' => $position_id,
                    ':election_scope' => $election_scope,
                    ':region' => candidate_nullable_value($region),
                    ':province' => candidate_nullable_value($province),
                    ':city_municipality' => candidate_nullable_value($city_municipality),
                    ':photo' => candidate_nullable_value($photo),
                    ':platform' => candidate_text_value($platform),
                    ':candidate_id' => $candidate_id
                ));

                candidate_safe_audit('Updated candidate: ' . $full_name);
                flash('success', 'Candidate updated successfully.');
                header('Location: candidates.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Unable to update candidate.';
            }
        }
    }

    if ($action == 'delete') {
        $candidate_id = candidate_post_value('candidate_id');
        $photo = candidate_post_value('photo');

        if ($candidate_id == '') {
            $errors[] = 'Candidate ID is missing.';
        }

        if (count($errors) == 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM candidates WHERE candidate_id = :candidate_id");
                $stmt->execute(array(':candidate_id' => $candidate_id));

                if ($photo != '') {
                    delete_candidate_photo($photo);
                }

                candidate_safe_audit('Deleted candidate ID: ' . $candidate_id);
                flash('success', 'Candidate deleted successfully.');
                header('Location: candidates.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Unable to delete candidate. This candidate may already have vote records.';
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
$position_filter = isset($_GET['position_id']) ? trim((string) $_GET['position_id']) : '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 10;

$where = array();
$params = array();

if ($search != '') {
    $where[] = "(c.full_name LIKE :search1 OR c.political_party LIKE :search2 OR c.platform LIKE :search3 OR p.position_name LIKE :search4 OR c.election_scope LIKE :search5 OR c.region LIKE :search6 OR c.province LIKE :search7 OR c.city_municipality LIKE :search8)";
    $search_like = '%' . $search . '%';
    $params[':search1'] = $search_like;
    $params[':search2'] = $search_like;
    $params[':search3'] = $search_like;
    $params[':search4'] = $search_like;
    $params[':search5'] = $search_like;
    $params[':search6'] = $search_like;
    $params[':search7'] = $search_like;
    $params[':search8'] = $search_like;
}

if ($position_filter != '') {
    $where[] = "c.position_id = :position_id";
    $params[':position_id'] = $position_filter;
}

$where_sql = '';

if (count($where) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

$count_sql = "
    SELECT COUNT(*)
    FROM candidates c
    LEFT JOIN positions p ON c.position_id = p.position_id
    $where_sql
";

$count_stmt = $pdo->prepare($count_sql);

foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}

$count_stmt->execute();
$total_rows = (int) $count_stmt->fetchColumn();

$pagination = paginate($total_rows, $page, $per_page);
$page = $pagination[0];
$total_pages = $pagination[1];
$offset = $pagination[2];

$sql = "
    SELECT
        c.candidate_id,
        c.full_name,
        c.political_party,
        c.position_id,
        c.election_scope,
        c.region,
        c.province,
        c.city_municipality,
        c.photo,
        c.platform,
        p.position_name
    FROM candidates c
    LEFT JOIN positions p ON c.position_id = p.position_id
    $where_sql
    ORDER BY p.position_id ASC, c.candidate_id ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', (int) $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
$stmt->execute();

$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_candidates = 0;
$total_positions = 0;

try {
    $total_candidates = (int) $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
    $total_positions = (int) $pdo->query("SELECT COUNT(*) FROM positions")->fetchColumn();
} catch (Exception $e) {
}

$flashes = consume_flash();

require_once dirname(__FILE__) . '/../includes/header.php';
require_once dirname(__FILE__) . '/../includes/sidebar.php';
?>

<style>
/* ── Candidates page polish ─────────────────────────────────── */
.ivote-management-page {
    padding: 0;
    max-width: 100%;
    margin: 0;
}

/* Filter bar */
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
    grid-template-columns: 1fr minmax(160px, 200px) auto auto auto;
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

/* Data card */
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

/* Table */
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
    padding: 12px 16px;
    white-space: nowrap;
}

.ivote-management-table tbody td {
    padding: 14px 16px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f5fd;
    color: #1a2942;
}

.ivote-management-table tbody tr:last-child td {
    border-bottom: none;
}

.ivote-management-table tbody tr:hover td {
    background: #f8fbff;
}

/* Candidate cell with photo */
.ivote-candidate-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ivote-candidate-cell img {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #d8e6fa;
}

/* Platform preview */
.ivote-platform-preview {
    color: #667085;
    font-size: 13px;
    max-width: 260px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Action buttons */
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

.btn-ivote-icon:hover {
    background: #0647b8;
    color: #fff;
    border-color: #0647b8;
}

.btn-ivote-icon.danger {
    color: #d92d20;
    border-color: #fecdd3;
    background: #fff5f5;
}

.btn-ivote-icon.danger:hover {
    background: #d92d20;
    color: #fff;
    border-color: #d92d20;
}

/* Pagination */
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

/* Flash wrap */
.ivote-flash-wrap { margin-bottom: 16px; }

/* Form sections inside modals */
.ivote-form-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #edf2fb;
}

.ivote-form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.ivote-form-section h6 {
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #0647b8;
    margin-bottom: 14px;
}

/* Profile view in modals */
.ivote-candidate-profile {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}

.ivote-candidate-photo-large img {
    width: 110px;
    height: 110px;
    border-radius: 20px;
    object-fit: cover;
    border: 2px solid #d8e6fa;
}

.ivote-profile-view {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    flex: 1;
}

.ivote-profile-view > div {
    background: #f7f9fd;
    border: 1px solid #e1e8f3;
    border-radius: 14px;
    padding: 12px 14px;
}

.ivote-profile-view > div.full {
    grid-column: 1 / -1;
}

.ivote-profile-view span {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #667085;
    margin-bottom: 4px;
}

.ivote-profile-view strong {
    display: block;
    font-size: 14px;
    color: #1a2942;
    font-weight: 700;
}

/* btn-ivote */
.btn-ivote {
    background: #0647b8;
    color: #fff;
    border-color: #0647b8;
    border-radius: 12px;
    font-weight: 700;
}
.btn-ivote:hover { background: #053a94; color: #fff; }

.btn-ivote-outline {
    border: 1.5px solid #0647b8;
    color: #0647b8;
    border-radius: 12px;
    font-weight: 700;
    background: transparent;
}
.btn-ivote-outline:hover { background: #0647b8; color: #fff; }

.ivote-reset-btn {
    border-radius: 12px;
    font-weight: 600;
}

/* Modal */
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

@media (max-width: 768px) {
    .ivote-management-page { padding: 0; }
    .ivote-filter-form > div { min-width: 100%; flex: 1 1 100%; }
    .ivote-candidate-profile { flex-direction: column; }
    .ivote-profile-view { grid-template-columns: 1fr; }
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

    <div class="ivote-filter-card">
        <form method="GET" action="candidates.php" class="ivote-filter-form">
            <div>
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="<?php echo e($search); ?>" placeholder="Search candidate, party, position, or platform">
            </div>

            <div>
                <label class="form-label">Position</label>
                <select name="position_id" class="form-select">
                    <option value="">All positions</option>
                    <?php foreach ($positions as $position) { ?>
                        <option value="<?php echo e($position['position_id']); ?>" <?php echo ($position_filter == $position['position_id']) ? 'selected' : ''; ?>>
                            <?php echo e($position['position_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <button type="submit" class="btn btn-ivote-outline">
                <i class="bi bi-funnel me-1"></i>
                Filter
            </button>

            <a href="candidates.php" class="btn btn-light border ivote-reset-btn">
                Reset
            </a>

            <button type="button" class="btn btn-ivote" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                <i class="bi bi-plus-circle me-1"></i>
                Add Candidate
            </button>
        </form>
    </div>

    <div class="ivote-card ivote-data-card">
        <div class="ivote-card-header">
            <h3 class="ivote-section-title">
                <i class="bi bi-person-badge text-primary me-1"></i>
                Candidate Records
            </h3>

            <span class="ivote-record-count">
                <?php echo number_format($total_rows); ?> record(s)
            </span>
        </div>

        <div class="table-responsive">
            <table class="table ivote-management-table">
                <thead>
                    <tr>
                        <th>Candidate</th>
                        <th>Political Party</th>
                        <th>Position</th>
                        <th>Jurisdiction</th>
                        <th>Platform</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($candidates) > 0) { ?>
                        <?php foreach ($candidates as $candidate) { ?>
                            <?php
                                $view_modal = candidate_modal_id($candidate['candidate_id'], 'viewCandidate');
                                $edit_modal = candidate_modal_id($candidate['candidate_id'], 'editCandidate');
                                $delete_modal = candidate_modal_id($candidate['candidate_id'], 'deleteCandidate');

                                $photo_url = candidate_admin_photo_url($candidate['photo'], $candidate['full_name']);
                            ?>

                            <tr>
                                <td>
                                    <div class="ivote-candidate-cell">
                                        <img src="<?php echo e($photo_url); ?>" alt="Candidate Photo" onerror="this.onerror=null; this.src='<?php echo e(candidate_photo_placeholder_url($candidate['full_name'])); ?>';">
                                        <div>
                                            <div class="fw-bold"><?php echo e($candidate['full_name']); ?></div>
                                            <small class="text-muted">ID: <?php echo e($candidate['candidate_id']); ?></small>
                                        </div>
                                    </div>
                                </td>

                                <td><?php echo e($candidate['political_party'] ? $candidate['political_party'] : 'Independent'); ?></td>

                                <td>
                                    <span class="badge text-bg-secondary">
                                        <?php echo e($candidate['position_name'] ? $candidate['position_name'] : 'No Position'); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="fw-bold"><?php echo e(candidate_scope_label($candidate['election_scope'])); ?></div>
                                    <small class="text-muted"><?php echo e(candidate_jurisdiction_label($candidate)); ?></small>
                                </td>

                                <td>
                                    <span class="ivote-platform-preview">
                                        <?php
                                            $platform = trim((string) $candidate['platform']);
                                            if ($platform == '') {
                                                echo 'No platform provided.';
                                            } else {
                                                echo e(strlen($platform) > 80 ? substr($platform, 0, 80) . '...' : $platform);
                                            }
                                        ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-ivote-icon" data-bs-toggle="modal" data-bs-target="#<?php echo e($view_modal); ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-ivote-icon" data-bs-toggle="modal" data-bs-target="#<?php echo e($edit_modal); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-ivote-icon danger" data-bs-toggle="modal" data-bs-target="#<?php echo e($delete_modal); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>

                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                No candidates found.
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
                    $base_url = 'candidates.php?search=' . urlencode($search) . '&position_id=' . urlencode($position_filter) . '&page=';
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

<?php if (count($candidates) > 0) { ?>
<?php foreach ($candidates as $candidate) { ?>
<?php
    $view_modal = candidate_modal_id($candidate['candidate_id'], 'viewCandidate');
    $edit_modal = candidate_modal_id($candidate['candidate_id'], 'editCandidate');
    $delete_modal = candidate_modal_id($candidate['candidate_id'], 'deleteCandidate');
    $photo_url = candidate_admin_photo_url($candidate['photo'], $candidate['full_name']);
?>
                            <div class="modal fade" id="<?php echo e($view_modal); ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content ivote-modal">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Candidate Profile</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="ivote-candidate-profile">
                                                <div class="ivote-candidate-photo-large">
                                                    <img src="<?php echo e($photo_url); ?>" alt="Candidate Photo" onerror="this.onerror=null; this.src='<?php echo e(candidate_photo_placeholder_url($candidate['full_name'])); ?>';">
                                                </div>

                                                <div class="ivote-profile-view">
                                                    <div>
                                                        <span>Full Name</span>
                                                        <strong><?php echo e($candidate['full_name']); ?></strong>
                                                    </div>

                                                    <div>
                                                        <span>Political Party</span>
                                                        <strong><?php echo e($candidate['political_party'] ? $candidate['political_party'] : 'Independent'); ?></strong>
                                                    </div>

                                                    <div>
                                                        <span>Position</span>
                                                        <strong><?php echo e($candidate['position_name'] ? $candidate['position_name'] : 'No Position'); ?></strong>
                                                    </div>

                                                    <div>
                                                        <span>Election Scope</span>
                                                        <strong><?php echo e(candidate_scope_label($candidate['election_scope'])); ?></strong>
                                                    </div>

                                                    <div class="full">
                                                        <span>Jurisdiction</span>
                                                        <strong><?php echo e(candidate_jurisdiction_label($candidate)); ?></strong>
                                                    </div>

                                                    <div class="full">
                                                        <span>Platform</span>
                                                        <strong><?php echo nl2br(e($candidate['platform'] ? $candidate['platform'] : 'No platform provided.')); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="<?php echo e($edit_modal); ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content ivote-modal">
                                        <form method="POST" action="candidates.php" enctype="multipart/form-data">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Candidate</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="candidate_id" value="<?php echo e($candidate['candidate_id']); ?>">
                                                <input type="hidden" name="old_photo" value="<?php echo e($candidate['photo']); ?>">

                                                <?php echo candidate_form_fields($candidate, $positions); ?>
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
                                        <form method="POST" action="candidates.php">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Candidate</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="candidate_id" value="<?php echo e($candidate['candidate_id']); ?>">
                                                <input type="hidden" name="photo" value="<?php echo e($candidate['photo']); ?>">

                                                <p>
                                                    Are you sure you want to delete
                                                    <strong><?php echo e($candidate['full_name']); ?></strong>?
                                                </p>

                                                <small class="text-muted">
                                                    This action cannot be undone. Candidates with vote records may not be deletable.
                                                </small>
                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Delete Candidate</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
<?php } ?>
<?php } ?>

<div class="modal fade" id="addCandidateModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content ivote-modal">
            <form method="POST" action="candidates.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add Candidate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">

                    <?php
                        $blank_candidate = array(
                            'full_name' => '',
                            'political_party' => '',
                            'position_id' => '',
                            'election_scope' => 'National',
                            'region' => '',
                            'province' => '',
                            'city_municipality' => '',
                            'photo' => '',
                            'platform' => ''
                        );

                        echo candidate_form_fields($blank_candidate, $positions);
                    ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-ivote">Add Candidate</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
(function () {
    var locationData = {
        regions: [],
        provinces: [],
        cities: []
    };

    var loaded = false;

    function normalizeName(value) {
        return String(value || '').trim().toLowerCase();
    }

    function sortByName(items) {
        return items.sort(function (a, b) {
            return String(a.name || '').localeCompare(String(b.name || ''));
        });
    }

    function codeRegionPrefix(code) {
        return String(code || '').substring(0, 2);
    }

    function codeProvincePrefix(code) {
        return String(code || '').substring(0, 5);
    }

    function getSelectedOption(select) {
        if (!select || select.selectedIndex < 0) {
            return null;
        }

        return select.options[select.selectedIndex];
    }

    function clearSelect(select, placeholder) {
        if (!select) {
            return;
        }

        select.innerHTML = '';
        var option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    }

    function appendLocationOption(select, item, selectedValue) {
        var option = document.createElement('option');
        option.value = item.name;
        option.textContent = item.name;
        option.setAttribute('data-code', item.code || '');

        if (normalizeName(item.name) == normalizeName(selectedValue)) {
            option.selected = true;
        }

        select.appendChild(option);
    }

    function fillRegions(form) {
        var regionSelect = form.querySelector('.js-region-select');

        if (!regionSelect) {
            return;
        }

        var selectedValue = regionSelect.getAttribute('data-current') || regionSelect.value || '';
        clearSelect(regionSelect, 'Select region');

        sortByName(locationData.regions.slice()).forEach(function (region) {
            appendLocationOption(regionSelect, region, selectedValue);
        });

        fillProvinces(form);
    }

    function fillProvinces(form) {
        var regionSelect = form.querySelector('.js-region-select');
        var provinceSelect = form.querySelector('.js-province-select');

        if (!regionSelect || !provinceSelect) {
            return;
        }

        var selectedValue = provinceSelect.getAttribute('data-current') || provinceSelect.value || '';
        var selectedRegionOption = getSelectedOption(regionSelect);
        var regionCode = selectedRegionOption ? selectedRegionOption.getAttribute('data-code') : '';
        var regionPrefix = codeRegionPrefix(regionCode);

        clearSelect(provinceSelect, 'Select province');

        if (regionPrefix == '13') {
            appendLocationOption(provinceSelect, { name: 'Metro Manila', code: '13000' }, selectedValue);
        } else if (regionPrefix != '') {
            sortByName(locationData.provinces.slice()).forEach(function (province) {
                if (codeRegionPrefix(province.code) == regionPrefix) {
                    appendLocationOption(provinceSelect, province, selectedValue);
                }
            });
        }

        fillCities(form);
    }

    function fillCities(form) {
        var regionSelect = form.querySelector('.js-region-select');
        var provinceSelect = form.querySelector('.js-province-select');
        var citySelect = form.querySelector('.js-city-select');

        if (!regionSelect || !provinceSelect || !citySelect) {
            return;
        }

        var selectedValue = citySelect.getAttribute('data-current') || citySelect.value || '';
        var selectedRegionOption = getSelectedOption(regionSelect);
        var selectedProvinceOption = getSelectedOption(provinceSelect);
        var regionCode = selectedRegionOption ? selectedRegionOption.getAttribute('data-code') : '';
        var provinceCode = selectedProvinceOption ? selectedProvinceOption.getAttribute('data-code') : '';
        var regionPrefix = codeRegionPrefix(regionCode);
        var provincePrefix = codeProvincePrefix(provinceCode);

        clearSelect(citySelect, 'Select city / municipality');

        if (provincePrefix == '') {
            return;
        }

        sortByName(locationData.cities.slice()).forEach(function (city) {
            var cityRegionPrefix = codeRegionPrefix(city.code);
            var cityProvincePrefix = codeProvincePrefix(city.code);

            if (regionPrefix == '13') {
                if (cityRegionPrefix == '13') {
                    appendLocationOption(citySelect, city, selectedValue);
                }
            } else if (cityProvincePrefix == provincePrefix) {
                appendLocationOption(citySelect, city, selectedValue);
            }
        });
    }

    function positionLooksGovernor(positionName) {
        return normalizeName(positionName).indexOf('governor') !== -1;
    }

    function positionLooksMayor(positionName) {
        return normalizeName(positionName).indexOf('mayor') !== -1;
    }

    function positionLooksNational(positionName) {
        var name = normalizeName(positionName);
        return name.indexOf('president') !== -1 || name.indexOf('senator') !== -1 || (name.indexOf('party') !== -1 && name.indexOf('list') !== -1);
    }

    function setGroupVisible(group, visible) {
        if (!group) {
            return;
        }

        group.style.display = visible ? '' : 'none';
    }

    function setFieldState(field, enabled, required) {
        if (!field) {
            return;
        }

        field.disabled = !enabled;
        field.required = !!required;

        if (!enabled) {
            field.value = '';
        }
    }

    function syncCandidateForm(form, autoScope) {
        var positionSelect = form.querySelector('.js-candidate-position');
        var scopeSelect = form.querySelector('.js-candidate-scope');
        var regionSelect = form.querySelector('.js-region-select');
        var provinceSelect = form.querySelector('.js-province-select');
        var citySelect = form.querySelector('.js-city-select');
        var regionGroup = form.querySelector('.js-local-region-group');
        var provinceGroup = form.querySelector('.js-local-province-group');
        var cityGroup = form.querySelector('.js-local-city-group');

        var selectedPositionOption = getSelectedOption(positionSelect);
        var positionName = selectedPositionOption ? (selectedPositionOption.getAttribute('data-position-name') || selectedPositionOption.textContent || '') : '';
        var isGovernor = positionLooksGovernor(positionName);
        var isMayor = positionLooksMayor(positionName);

        var isNationalPosition = positionLooksNational(positionName);

        if (scopeSelect) {
            if (isNationalPosition) {
                scopeSelect.value = 'National';
                scopeSelect.disabled = true;
            } else {
                scopeSelect.disabled = false;

                if (autoScope && positionName != '') {
                    if (isGovernor || isMayor) {
                        scopeSelect.value = 'Local';
                    }
                }
            }
        }

        var isLocal = scopeSelect && scopeSelect.value == 'Local';
        var showCity = isLocal && isMayor;

        setGroupVisible(regionGroup, isLocal);
        setGroupVisible(provinceGroup, isLocal);
        setGroupVisible(cityGroup, showCity);

        setFieldState(regionSelect, isLocal, isLocal);
        setFieldState(provinceSelect, isLocal, isLocal && (isGovernor || isMayor || positionName != ''));
        setFieldState(citySelect, showCity, showCity);
    }

    function initCandidateForm(form) {
        var positionSelect = form.querySelector('.js-candidate-position');
        var scopeSelect = form.querySelector('.js-candidate-scope');
        var regionSelect = form.querySelector('.js-region-select');
        var provinceSelect = form.querySelector('.js-province-select');

        if (loaded) {
            fillRegions(form);
        }

        syncCandidateForm(form, false);

        if (positionSelect) {
            positionSelect.addEventListener('change', function () {
                syncCandidateForm(form, true);
            });
        }

        if (scopeSelect) {
            scopeSelect.addEventListener('change', function () {
                syncCandidateForm(form, false);
            });
        }

        if (regionSelect) {
            regionSelect.addEventListener('change', function () {
                var provinceSelect = form.querySelector('.js-province-select');
                var citySelect = form.querySelector('.js-city-select');

                if (provinceSelect) {
                    provinceSelect.setAttribute('data-current', '');
                }

                if (citySelect) {
                    citySelect.setAttribute('data-current', '');
                }

                fillProvinces(form);
                syncCandidateForm(form, false);
            });
        }

        if (provinceSelect) {
            provinceSelect.addEventListener('change', function () {
                var citySelect = form.querySelector('.js-city-select');

                if (citySelect) {
                    citySelect.setAttribute('data-current', '');
                }

                fillCities(form);
                syncCandidateForm(form, false);
            });
        }
    }

    function initAllForms() {
        var forms = document.querySelectorAll('form[action="candidates.php"]');

        Array.prototype.forEach.call(forms, function (form) {
            if (form.querySelector('.candidate-scope-form-section')) {
                initCandidateForm(form);
            }
        });
    }

    function loadLocations() {
        return Promise.all([
            fetch('https://psgc.cloud/api/regions').then(function (response) { return response.json(); }),
            fetch('https://psgc.cloud/api/provinces').then(function (response) { return response.json(); }),
            fetch('https://psgc.cloud/api/cities').then(function (response) { return response.json(); }),
            fetch('https://psgc.cloud/api/municipalities').then(function (response) { return response.json(); })
        ]).then(function (responses) {
            locationData.regions = responses[0] || [];
            locationData.provinces = responses[1] || [];
            locationData.cities = (responses[2] || []).concat(responses[3] || []);
            loaded = true;

            var forms = document.querySelectorAll('form[action="candidates.php"]');
            Array.prototype.forEach.call(forms, function (form) {
                if (form.querySelector('.candidate-scope-form-section')) {
                    fillRegions(form);
                    syncCandidateForm(form, false);
                }
            });
        }).catch(function () {
            var selects = document.querySelectorAll('.js-region-select');
            Array.prototype.forEach.call(selects, function (select) {
                if (select.options.length < 2) {
                    clearSelect(select, 'Unable to load regions. Check internet connection.');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAllForms();
        loadLocations();
    });
})();
</script>

<?php
require_once dirname(__FILE__) . '/../includes/footer.php';
?>