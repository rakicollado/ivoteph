<?php
if (!isset($activePage)) {
    $activePage = '';
}

$current_file = basename($_SERVER['PHP_SELF']);

$nav_items = array(
    array(
        'key' => 'dashboard',
        'file' => 'index.php',
        'label' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'href' => '/ivoteph/admin/admin/index.php'
    ),
    array(
        'key' => 'voters',
        'file' => 'voters.php',
        'label' => 'Voter Management',
        'icon' => 'bi-people-fill',
        'href' => '/ivoteph/admin/admin/voters.php'
    ),
    array(
        'key' => 'candidates',
        'file' => 'candidates.php',
        'label' => 'Candidate Management',
        'icon' => 'bi-person-badge-fill',
        'href' => '/ivoteph/admin/admin/candidates.php'
    ),
    array(
        'key' => 'elections',
        'file' => 'elections.php',
        'label' => 'Elections',
        'icon' => 'bi-calendar-event-fill',
        'href' => '/ivoteph/admin/admin/elections.php'
    ),
    array(
        'key' => 'results',
        'file' => 'results.php',
        'label' => 'Results',
        'icon' => 'bi-graph-up-arrow',
        'href' => '/ivoteph/admin/admin/results.php'
    ),
    array(
        'key' => 'audit_logs',
        'file' => 'audit_logs.php',
        'label' => 'Audit Logs',
        'icon' => 'bi-clock-history',
        'href' => '/ivoteph/admin/admin/audit_logs.php'
    )
);
?>

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