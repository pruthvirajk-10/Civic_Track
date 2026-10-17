<?php
/**
 * CivicTrack — Role-Aware Dashboard Sidebar
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$baseUrl = getBaseUrl();
$role = getUserRole();
$userId = getUserId();
$userName = getUserName();
$currentPage = basename($_SERVER['PHP_SELF']);

// Unread notifications count
$unreadCount = 0;
try {
    $pdo = getDbConnection();
    if ($userId) {
        $unreadCount = getUnreadNotificationCount($pdo, $userId);
    }
} catch (Exception $e) {
    // Graceful fallback
}
?>
<aside class="dashboard-sidebar">
    <div class="sidebar-header">
        <div class="brand-icon">
            <i class="bi bi-buildings-fill"></i>
        </div>
        <div>
            <div class="sidebar-brand-name">Civic<span class="text-info">Track</span></div>
            <span class="sidebar-role-badge role-<?= htmlspecialchars($role) ?>">
                <?= ucfirst(htmlspecialchars($role)) ?> Portal
            </span>
        </div>
    </div>

    <div class="sidebar-menu">
        <?php if ($role === 'citizen'): ?>
            <div class="menu-section-title">Citizen Grievance</div>
            <a href="<?= $baseUrl ?>citizen/dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>My Dashboard</span>
            </a>
            <a href="<?= $baseUrl ?>citizen/lodge-complaint.php" class="sidebar-link <?= ($currentPage === 'lodge-complaint.php') ? 'active' : '' ?>">
                <i class="bi bi-plus-circle-fill text-warning"></i>
                <span>Lodge Complaint</span>
            </a>
            <a href="<?= $baseUrl ?>citizen/my-complaints.php" class="sidebar-link <?= ($currentPage === 'my-complaints.php' || $currentPage === 'complaint-details.php') ? 'active' : '' ?>">
                <i class="bi bi-card-checklist"></i>
                <span>My Complaints</span>
            </a>
            <a href="<?= $baseUrl ?>citizen/notifications.php" class="sidebar-link <?= ($currentPage === 'notifications.php') ? 'active' : '' ?>">
                <i class="bi bi-bell"></i>
                <span>Notifications</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $baseUrl ?>citizen/profile.php" class="sidebar-link <?= ($currentPage === 'profile.php') ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i>
                <span>My Profile</span>
            </a>

        <?php elseif ($role === 'officer'): ?>
            <div class="menu-section-title">Officer Queue</div>
            <a href="<?= $baseUrl ?>officer/dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Desk Dashboard</span>
            </a>
            <a href="<?= $baseUrl ?>officer/assigned-complaints.php" class="sidebar-link <?= ($currentPage === 'assigned-complaints.php' || $currentPage === 'complaint-details.php') ? 'active' : '' ?>">
                <i class="bi bi-inbox-fill"></i>
                <span>Assigned Queue</span>
            </a>
            <a href="<?= $baseUrl ?>officer/profile.php" class="sidebar-link <?= ($currentPage === 'profile.php') ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i>
                <span>Officer Profile</span>
            </a>

        <?php elseif ($role === 'admin'): ?>
            <div class="menu-section-title">Municipal Administration</div>
            <a href="<?= $baseUrl ?>admin/dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-pie-chart-fill"></i>
                <span>City Analytics</span>
            </a>
            <a href="<?= $baseUrl ?>admin/complaints.php" class="sidebar-link <?= ($currentPage === 'complaints.php' || $currentPage === 'complaint-details.php') ? 'active' : '' ?>">
                <i class="bi bi-folder-fill"></i>
                <span>All Complaints</span>
            </a>
            <a href="<?= $baseUrl ?>admin/departments.php" class="sidebar-link <?= ($currentPage === 'departments.php') ? 'active' : '' ?>">
                <i class="bi bi-building-gear"></i>
                <span>Departments</span>
            </a>
            <a href="<?= $baseUrl ?>admin/categories.php" class="sidebar-link <?= ($currentPage === 'categories.php') ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i>
                <span>SLA & Categories</span>
            </a>
            <a href="<?= $baseUrl ?>admin/users.php" class="sidebar-link <?= ($currentPage === 'users.php') ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>User Accounts</span>
            </a>
            <a href="<?= $baseUrl ?>admin/reports.php" class="sidebar-link <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <span>Reports & Exports</span>
            </a>
            <a href="<?= $baseUrl ?>admin/settings.php" class="sidebar-link <?= ($currentPage === 'settings.php') ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i>
                <span>System Settings</span>
            </a>
        <?php endif; ?>

        <div class="menu-section-title mt-3">Quick Navigation</div>
        <a href="<?= $baseUrl ?>track.php" class="sidebar-link" target="_blank">
            <i class="bi bi-box-arrow-up-right text-info"></i>
            <span>Public Tracker</span>
        </a>
        <a href="<?= $baseUrl ?>index.php" class="sidebar-link" target="_blank">
            <i class="bi bi-house"></i>
            <span>Public Portal</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between">
            <div class="overflow-hidden me-2">
                <div class="fw-bold text-white text-truncate small"><?= e($userName) ?></div>
                <div class="text-muted text-truncate" style="font-size:0.75rem;"><?= ucfirst(e($role)) ?></div>
            </div>
            <a href="<?= $baseUrl ?>logout.php" class="btn btn-sm btn-outline-danger" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>
