<?php
/**
 * CivicTrack — System Settings & Municipality Configurations
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$baseUrl = getBaseUrl();
$errors = [];
$settings = [];

try {
    $pdo = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken()) {
            $errors[] = 'Security token invalid. Please try again.';
        } else {
            $allowedKeys = [
                'app_name',
                'municipality_name',
                'city_center_lat',
                'city_center_lng',
                'city_zoom_level',
                'helpline_phone',
                'support_email',
                'sla_alert_hours_before'
            ];

            foreach ($allowedKeys as $key) {
                if (isset($_POST[$key])) {
                    $val = trim($_POST[$key]);
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $val, $val]);
                }
            }

            setFlash('success', 'System configurations updated successfully.');
            header('Location: settings.php');
            exit;
        }
    }

    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

$pageTitle = 'System Settings — CivicTrack Admin';
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
                    <h5 class="fw-bold text-dark mb-0">System Settings</h5>
                    <small class="text-muted">Municipal branding, helplines, and GIS parameters</small>
                </div>
            </div>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small py-2 mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $e): ?>
                            <li><?= e($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-sliders me-2 text-primary"></i> Municipal Configuration</h6>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="settings.php">
                                <?= csrfField() ?>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Portal Title / Application Name</label>
                                    <input type="text" class="form-control" name="app_name" 
                                           value="<?= e($settings['app_name'] ?? 'CivicTrack — Smart City Complaint Management System') ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Governing Municipality Authority Name</label>
                                    <input type="text" class="form-control" name="municipality_name" 
                                           value="<?= e($settings['municipality_name'] ?? 'Pune Smart City Municipal Corporation (PMC)') ?>" required>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Citizen Helpline Phone</label>
                                        <input type="text" class="form-control" name="helpline_phone" 
                                               value="<?= e($settings['helpline_phone'] ?? '1800-22-2026') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Official Support Email</label>
                                        <input type="email" class="form-control" name="support_email" 
                                               value="<?= e($settings['support_email'] ?? 'support@smartcity.gov') ?>" required>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-dark border-top pt-3 mt-4 mb-3">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> Default City GIS & Map Coordinates
                                </h6>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Center Latitude</label>
                                        <input type="text" class="form-control font-monospace" name="city_center_lat" 
                                               value="<?= e($settings['city_center_lat'] ?? '18.5204') ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Center Longitude</label>
                                        <input type="text" class="form-control font-monospace" name="city_center_lng" 
                                               value="<?= e($settings['city_center_lng'] ?? '73.8567') ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Default Zoom Level</label>
                                        <input type="number" class="form-control font-monospace" name="city_zoom_level" 
                                               value="<?= e($settings['city_zoom_level'] ?? '13') ?>" min="5" max="18" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary-civic btn-sm px-4">
                                    <i class="bi bi-save me-1"></i> Save Configuration
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-info-circle text-primary me-2"></i> Environment Info</h6>
                        </div>
                        <div class="card-body p-3 small text-muted">
                            <div class="d-flex justify-content-between mb-2">
                                <span>PHP Version:</span>
                                <strong class="text-dark"><?= PHP_VERSION ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>MySQL Host:</span>
                                <strong class="text-dark"><?= DB_HOST ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Database Name:</span>
                                <strong class="text-dark"><?= DB_NAME ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Timezone:</span>
                                <strong class="text-dark"><?= date_default_timezone_get() ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Server Time:</span>
                                <strong class="text-dark"><?= date('H:i:s d M Y') ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 bg-light">
                        <div class="card-body p-3">
                            <h6 class="fw-bold text-dark small mb-1"><i class="bi bi-database-check text-success me-1"></i> Need to Reset Database?</h6>
                            <p class="small text-muted mb-3">You can re-seed sample demonstration records anytime via the installer.</p>
                            <a href="../setup.php" class="btn btn-outline-secondary btn-sm w-100">
                                Launch Database Setup Installer
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
