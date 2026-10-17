<?php
/**
 * CivicTrack — Citizen Notifications Center
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('citizen');

$userId = getUserId();
$baseUrl = getBaseUrl();
$notifications = [];

try {
    $pdo = getDbConnection();

    // Mark all as read action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
        if (verifyCsrfToken()) {
            $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $updateStmt->execute([$userId]);
            setFlash('success', 'All notifications marked as read.');
            header('Location: notifications.php');
            exit;
        }
    }

    $stmt = $pdo->prepare("
        SELECT n.*, c.reference_number
        FROM notifications n
        LEFT JOIN complaints c ON n.complaint_id = c.complaint_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Notifications — CivicTrack';
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="dashboard-main">
        <header class="dashboard-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-light border d-lg-none" id="sidebarToggleBtn">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h5 class="fw-bold text-dark mb-0">Notifications Center</h5>
                    <small class="text-muted">Real-time alerts on your grievance tickets</small>
                </div>
            </div>

            <?php if (!empty($notifications)): ?>
                <form method="POST" action="notifications.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-check2-all me-1"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <div class="card border-0 shadow-sm rounded-4 bg-white">
                <div class="card-body p-0">
                    <?php if (!empty($notifications)): ?>
                        <div class="list-group list-group-flush rounded-4">
                            <?php foreach ($notifications as $n): ?>
                                <div class="list-group-item p-3 border-bottom <?= ($n['is_read'] == 0) ? 'bg-primary-subtle bg-opacity-10' : '' ?>" id="notif-item-<?= $n['notification_id'] ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="d-flex gap-3">
                                            <div class="rounded-circle p-2 d-flex align-items-center justify-content-center <?= ($n['is_read'] == 0) ? 'bg-primary text-white' : 'bg-light text-muted' ?>" style="width:40px; height:40px; flex-shrink:0;">
                                                <i class="bi bi-bell-fill"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-1">
                                                    <?= e($n['title']) ?>
                                                    <?php if ($n['is_read'] == 0): ?>
                                                        <span class="badge bg-danger rounded-pill ms-2" style="font-size:0.65rem;">New</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="text-secondary small mb-1"><?= e($n['message']) ?></p>
                                                <div class="small text-muted d-flex align-items-center gap-2">
                                                    <i class="bi bi-clock me-1"></i> <?= timeAgo($n['created_at']) ?>
                                                    <?php if (!empty($n['reference_number'])): ?>
                                                        &bull;
                                                        <a href="complaint-details.php?ref=<?= urlencode($n['reference_number']) ?>" class="fw-bold text-primary text-decoration-none">
                                                            View Ticket #<?= e($n['reference_number']) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-bell-slash fs-1 d-block mb-2 text-secondary"></i>
                            <h6 class="fw-bold text-dark">No Notifications</h6>
                            <p class="small mb-0">You're all caught up! New updates on your complaints will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
