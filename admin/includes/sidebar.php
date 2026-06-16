<?php
if (!isset($activePage)) {
    $activePage = '';
}

$current_file = basename($_SERVER['PHP_SELF']);

$pending_profile_requests = 0;

try {
    if (isset($pdo) && $pdo) {
        $check_stmt = $pdo->query("SHOW TABLES LIKE 'profile_change_requests'");
        $profile_table_exists = $check_stmt->fetchColumn();

        if ($profile_table_exists) {
            $count_stmt = $pdo->query("
                SELECT COUNT(*)
                FROM profile_change_requests
                WHERE request_status = 'Pending'
            ");

            $pending_profile_requests = (int) $count_stmt->fetchColumn();
        }
    } elseif (function_exists('db')) {
        $sidebar_pdo = db();

        if ($sidebar_pdo) {
            $check_stmt = $sidebar_pdo->query("SHOW TABLES LIKE 'profile_change_requests'");
            $profile_table_exists = $check_stmt->fetchColumn();

            if ($profile_table_exists) {
                $count_stmt = $sidebar_pdo->query("
                    SELECT COUNT(*)
                    FROM profile_change_requests
                    WHERE request_status = 'Pending'
                ");

                $pending_profile_requests = (int) $count_stmt->fetchColumn();
            }
        }
    }
} catch (Exception $e) {
    $pending_profile_requests = 0;
}

$nav_items = array(
    array(
        'key' => 'dashboard',
        'file' => 'index.php',
        'label' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'href' => '/ivoteph/admin/admin/index.php',
        'badge' => 0
    ),
    array(
        'key' => 'voters',
        'file' => 'voters.php',
        'label' => 'Voter Management',
        'icon' => 'bi-people-fill',
        'href' => '/ivoteph/admin/admin/voters.php',
        'badge' => 0
    ),
    array(
        'key' => 'profile_requests',
        'file' => 'profile_requests.php',
        'label' => 'Profile Requests',
        'icon' => 'bi-pencil-square',
        'href' => '/ivoteph/admin/admin/profile_requests.php',
        'badge' => $pending_profile_requests
    ),
    array(
        'key' => 'candidates',
        'file' => 'candidates.php',
        'label' => 'Candidate Management',
        'icon' => 'bi-person-badge-fill',
        'href' => '/ivoteph/admin/admin/candidates.php',
        'badge' => 0
    ),
    array(
        'key' => 'elections',
        'file' => 'elections.php',
        'label' => 'Elections',
        'icon' => 'bi-calendar-event-fill',
        'href' => '/ivoteph/admin/admin/elections.php',
        'badge' => 0
    ),
    array(
        'key' => 'results',
        'file' => 'results.php',
        'label' => 'Results',
        'icon' => 'bi-graph-up-arrow',
        'href' => '/ivoteph/admin/admin/results.php',
        'badge' => 0
    ),
    array(
        'key' => 'audit_logs',
        'file' => 'audit_logs.php',
        'label' => 'Audit Logs',
        'icon' => 'bi-clock-history',
        'href' => '/ivoteph/admin/admin/audit_logs.php',
        'badge' => 0
    )
);
?>

<style>
    .ivote-sidebar-badge {
        margin-left: auto;
        min-width: 24px;
        height: 24px;
        padding: 0 7px;
        border-radius: 999px;
        background: #d8202a;
        color: #ffffff;
        font-size: 11px;
        font-weight: 950;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        box-shadow: 0 8px 18px rgba(216, 32, 42, 0.25);
    }

    .ivote-sidebar-link.active .ivote-sidebar-badge,
    .ivote-sidebar-link:hover .ivote-sidebar-badge {
        background: #ffffff;
        color: #d8202a;
    }
</style>

<aside class="ivote-sidebar" id="ivoteSidebar">
    <div class="ivote-sidebar-header">
        <div class="ivote-sidebar-logo-wrap">
            <a href="/ivoteph/admin/admin/index.php" class="ivote-sidebar-logo">
                <img src="/ivoteph/admin/assets/img/ivoteph-logo.png" alt="iVotePH" class="ivote-sidebar-logo-img">
            </a>
        </div>

        <button type="button" class="ivote-sidebar-close" id="ivoteSidebarClose" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="ivote-sidebar-nav">
        <?php foreach ($nav_items as $item) { ?>
            <?php
            $is_active = false;

            if ($activePage == $item['key']) {
                $is_active = true;
            }

            if ($current_file == $item['file']) {
                $is_active = true;
            }
            ?>

            <a href="<?php echo e($item['href']); ?>" class="ivote-sidebar-link <?php echo $is_active ? 'active' : ''; ?>">
                <i class="bi <?php echo e($item['icon']); ?>"></i>
                <span><?php echo e($item['label']); ?></span>

                <?php if (isset($item['badge']) && $item['badge'] > 0) { ?>
                    <span class="ivote-sidebar-badge"><?php echo number_format($item['badge']); ?></span>
                <?php } ?>
            </a>
        <?php } ?>
    </nav>
</aside>

<div class="ivote-sidebar-overlay" id="ivoteSidebarOverlay"></div>

<main class="ivote-main">
    <header class="ivote-dashboard-top-card">
        <div class="ivote-dashboard-top-left">
            <button type="button" class="ivote-menu-clean-btn" id="ivoteSidebarOpen" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>

            <a href="/ivoteph/admin/admin/index.php" class="ivote-topbar-logo">
                <img src="/ivoteph/admin/assets/img/ivoteph-logo.png" alt="iVotePH">
            </a>

            <div class="ivote-dashboard-title-block">
                <h1><?php echo e($page_title); ?></h1>
                <p><?php echo e($page_subtitle); ?></p>
            </div>
        </div>

        <div class="ivote-dashboard-top-right">
            <button type="button" class="ivote-logout-btn" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </button>
        </div>
    </header>