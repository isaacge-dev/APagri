<?php
/**
 * sidebar.php — Left navigation panel
 * Requires auth.php to have been included (session already started).
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$archivedCount = 0;
try {
    $archivedCount = (int)$pdo->query('SELECT (SELECT COUNT(*) FROM public.farmer WHERE deleted_at IS NOT NULL) + (SELECT COUNT(*) FROM public.farm WHERE deleted_at IS NOT NULL) + (SELECT COUNT(*) FROM public.crop WHERE deleted_at IS NOT NULL) + (SELECT COUNT(*) FROM public.planting WHERE deleted_at IS NOT NULL) + (SELECT COUNT(*) FROM public.harvest WHERE deleted_at IS NOT NULL)')->fetchColumn();
} catch (PDOException $e) {
    error_log('APAgri archive badge query failed: ' . $e->getMessage());
}

function navLink(string $href, string $icon, string $label, string $current): string {
    $active = ($current === $href) ? ' active' : '';
    return "<li><a href=\"{$href}\" class=\"{$active}\"><i class=\"bi {$icon}\"></i>{$label}</a></li>";
}

$userName  = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'User');
$userRole  = htmlspecialchars($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'Staff');
$initials  = strtoupper(substr($userName, 0, 1));
?>

<!-- ===== SIDEBAR ===== -->
<nav id="sidebar">

    <!-- Brand -->
    <a href="index.php" class="sidebar-brand">
        <!-- Agriculture SVG Logo — stylised wheat sheaf / leaf mark -->
        <div class="brand-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"
                 width="28" height="28" fill="none" aria-hidden="true">
                <!-- Main stem -->
                <line x1="20" y1="34" x2="20" y2="10"
                      stroke="#1b4332" stroke-width="2.4" stroke-linecap="round"/>
                <!-- Left leaves -->
                <path d="M20 24 C14 20 10 14 13 8 C16 14 20 18 20 24Z"
                      fill="#2d6a4f" opacity=".9"/>
                <path d="M20 30 C13 27 9 21 11 15 C14 20 18 25 20 30Z"
                      fill="#40916c" opacity=".75"/>
                <!-- Right leaves -->
                <path d="M20 24 C26 20 30 14 27 8 C24 14 20 18 20 24Z"
                      fill="#40916c" opacity=".9"/>
                <path d="M20 30 C27 27 31 21 29 15 C26 20 22 25 20 30Z"
                      fill="#74c69d" opacity=".75"/>
                <!-- Soil / ground hint -->
                <ellipse cx="20" cy="34.5" rx="5" ry="1.5"
                         fill="#1b4332" opacity=".35"/>
            </svg>
        </div>
        <div>
            <div class="brand-text">APagri</div>
            <div class="brand-sub">Agriculture Info System</div>
        </div>
    </a>

    <!-- Main Navigation -->
    <div class="sidebar-section-label">Main</div>
    <ul class="sidebar-nav">
        <?= navLink('index.php',       'bi-speedometer2',  'Dashboard',       $currentPage) ?>
    </ul>

    <!-- CRUD Modules -->
    <div class="sidebar-section-label">Modules</div>
    <ul class="sidebar-nav">
        <?= navLink('farmers.php',    'bi-people-fill',       'Farmers',          $currentPage) ?>
        <?= navLink('farms.php',      'bi-geo-alt-fill',      'Farms',            $currentPage) ?>
        <?= navLink('crops.php',      'bi-flower2',           'Crops',            $currentPage) ?>
        <?= navLink('plantings.php',  'bi-calendar2-week',    'Planting Records', $currentPage) ?>
        <?= navLink('harvests.php',   'bi-basket3-fill',      'Harvest Records',  $currentPage) ?>
        <li><a href="archive.php" class="<?= $currentPage === 'archive.php' ? 'active' : '' ?>"><i class="bi bi-archive"></i>Archive<?php if ($archivedCount > 0): ?><span class="badge rounded-pill bg-light text-success ms-auto"><?= number_format($archivedCount) ?></span><?php endif; ?></a></li>
        <?= navLink('reports.php',   'bi-bar-chart-line-fill','Reports',          $currentPage) ?>
        <?php if (isAdministrator()): ?>
            <?= navLink('user_accounts.php', 'bi-person-gear', 'User Accounts', $currentPage) ?>
            <?= navLink('activity_records.php', 'bi-journal-text', 'Activity Records', $currentPage) ?>
        <?php endif; ?>
    </ul>

    <!-- Footer: User info + logout -->
    <div class="sidebar-footer">
        <div class="user-pill">
            <div class="user-avatar"><?= $initials ?></div>
            <div>
                <div class="user-name"><?= $userName ?></div>
                <div class="user-role"><?= $userRole ?></div>
            </div>
        </div>
        <a href="change_password.php" class="btn-logout <?= $currentPage === 'change_password.php' ? 'active' : '' ?>">
            <i class="bi bi-key-fill"></i> Change Password
        </a>
        <a href="logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-left"></i> Sign Out
        </a>
    </div>

</nav>

<!-- ===== TOPBAR ===== -->
<header id="topbar">
    <button id="sidebarToggle" onclick="toggleSidebar()" title="Toggle Sidebar">
        <i class="bi bi-list"></i>
    </button>
    <div>
        <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
        <div class="topbar-breadcrumb">APagri &rsaquo; <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="badge text-bg-light border small d-none d-sm-inline">
            <i class="bi bi-clock me-1"></i>
            <span id="liveClock"></span>
        </span>
        <a href="logout.php" class="btn btn-sm btn-outline-secondary d-none d-sm-flex align-items-center gap-1">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</header>

<!-- ===== MAIN CONTENT WRAPPER ===== -->
<main id="mainContent">
<div class="content-area">
