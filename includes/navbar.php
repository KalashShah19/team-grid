<?php
/**
 * Navbar Include
 * Navigation bar with notification bell, user menu
 * Clean extensionless URLs
 */
if (!$isLoggedIn) return;

$userName = $currentUser['first_name'];
$userInitial = strtoupper(substr($userName, 0, 1));
$userRole = $currentUser['role'] ?? 'player';
$userFullName = getUserDisplayName($currentUser['id']);
?>

<nav class="navbar" id="mainNavbar">
    <div class="navbar-left">
        <a href="<?php echo BASE_URL; ?>/polls" class="tm-logo">
            <div class="tm-logo-badge">
                <div class="tm-logo-shield"></div>
                <span class="tm-logo-text">TG</span>
            </div>
            <span class="tm-logo-title">Team<span>Grid</span></span>
        </a>
    </div>

    <div class="navbar-center">
        <div class="nav-links">
            <a href="<?php echo BASE_URL; ?>/polls" class="nav-link <?php echo $currentPage === 'polls' || $currentPage === 'poll-detail' ? 'active' : ''; ?>">
                <span class="nav-icon">📊</span> Polls
            </a>
            <a href="<?php echo BASE_URL; ?>/matches" class="nav-link <?php echo $currentPage === 'matches' || $currentPage === 'match-detail' ? 'active' : ''; ?>">
                <span class="nav-icon">⚽</span> Matches
            </a>
            <?php if ($isAdminUser): ?>
                <a href="<?php echo BASE_URL; ?>/admin" class="nav-link <?php echo strpos($currentPage, 'admin') !== false || $currentPage === 'index' && strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? 'active' : ''; ?>">
                    <span class="nav-icon">⚙️</span> Admin
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/payments" class="nav-link <?php echo $currentPage === 'payments' ? 'active' : ''; ?>">
                    <span class="nav-icon">💰</span> Payments
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/reports" class="nav-link <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                    <span class="nav-icon">📈</span> Reports
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/users" class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                    <span class="nav-icon">👥</span> Users
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/analysis" class="nav-link <?php echo $currentPage === 'analysis' ? 'active' : ''; ?>">
                    <span class="nav-icon">🔍</span> Analysis
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/logs" class="nav-link <?php echo $currentPage === 'logs' ? 'active' : ''; ?>">
                    <span class="nav-icon">📋</span> Logs
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="navbar-right">
        <!-- Notification Bell -->
        <button class="notif-bell" id="notifBell" aria-label="Notifications">
            🔔
            <span class="notif-badge <?php echo $unreadCount === 0 ? 'hidden' : ''; ?>" id="notifBadge">
                <?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?>
            </span>
        </button>

        <!-- User Avatar -->
        <div class="user-avatar" id="userAvatar" title="<?php echo htmlspecialchars($userFullName); ?>">
            <?php echo $userInitial; ?>
        </div>

        <!-- User Dropdown -->
        <div class="user-menu" id="userMenu">
            <div class="user-menu-header">
                <div class="user-menu-name"><?php echo htmlspecialchars($userFullName); ?></div>
                <div class="user-menu-role"><?php echo $userRole; ?></div>
            </div>
            <?php if ($isAdminUser): ?>
                <a href="<?php echo BASE_URL; ?>/admin" class="user-menu-item">⚙️ Admin Panel</a>
            <?php endif; ?>
            <div class="user-menu-divider"></div>
            <a href="<?php echo BASE_URL; ?>/api/auth.php?action=logout" class="user-menu-item" style="color:var(--accent-red);">🚪 Logout</a>
        </div>
    </div>
</nav>

<!-- Notification Panel (slide from top) -->
<div class="notif-panel" id="notifPanel">
    <div class="notif-header">
        <h3>Notifications</h3>
        <button class="notif-mark-all" id="markAllRead" title="Mark all as read">✓ Mark all read</button>
    </div>
    <div class="notif-list" id="notifList">
        <div class="notif-empty">
            <div class="notif-empty-icon">🔕</div>
            <p>No notifications yet</p>
        </div>
    </div>
</div>

<!-- Mobile Bottom Nav -->
<div class="mobile-nav">
    <div class="mobile-nav-links">
        <a href="<?php echo BASE_URL; ?>/polls" class="mobile-nav-link <?php echo $currentPage === 'polls' || $currentPage === 'poll-detail' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span>Polls</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/matches" class="mobile-nav-link <?php echo $currentPage === 'matches' || $currentPage === 'match-detail' ? 'active' : ''; ?>">
            <span class="nav-icon">⚽</span>
            <span>Matches</span>
        </a>
        <?php if ($isAdminUser): ?>
            <a href="<?php echo BASE_URL; ?>/admin" class="mobile-nav-link <?php echo strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? 'active' : ''; ?>">
                <span class="nav-icon">⚙️</span>
                <span>Admin</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/payments" class="mobile-nav-link <?php echo $currentPage === 'payments' ? 'active' : ''; ?>">
                <span class="nav-icon">💰</span>
                <span>Pay</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
