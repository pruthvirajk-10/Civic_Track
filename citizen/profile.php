<?php
/**
 * CivicTrack — Citizen Profile Management
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('citizen');

$userId = getUserId();
$baseUrl = getBaseUrl();
$user = null;
$errors = [];

try {
    $pdo = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken()) {
            $errors[] = 'Security token expired. Please try again.';
        } else {
            $action = $_POST['action'] ?? '';

            if ($action === 'update_profile') {
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $ward = trim($_POST['ward'] ?? '');
                $address = trim($_POST['address'] ?? '');

                if (empty($name) || empty($phone)) {
                    $errors[] = 'Full name and phone number are required.';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, ward = ?, address = ? WHERE user_id = ?");
                    $stmt->execute([$name, $phone, $ward, $address, $userId]);
                    $_SESSION['user_name'] = $name;
                    setFlash('success', 'Profile information updated successfully.');
                    header('Location: profile.php');
                    exit;
                }
            } elseif ($action === 'change_password') {
                $currentPass = $_POST['current_password'] ?? '';
                $newPass = $_POST['new_password'] ?? '';
                $confirmPass = $_POST['confirm_password'] ?? '';

                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $dbPass = $stmt->fetchColumn();

                if (!password_verify($currentPass, $dbPass)) {
                    $errors[] = 'Current password is incorrect.';
                } elseif (strlen($newPass) < 6) {
                    $errors[] = 'New password must be at least 6 characters.';
                } elseif ($newPass !== $confirmPass) {
                    $errors[] = 'New password and confirmation do not match.';
                } else {
                    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$newHash, $userId]);
                    setFlash('success', 'Password updated successfully.');
                    header('Location: profile.php');
                    exit;
                }
            }
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

$pageTitle = 'Citizen Profile — CivicTrack';
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
                    <h5 class="fw-bold text-dark mb-0">My Citizen Profile</h5>
                    <small class="text-muted">Manage your personal details and account security</small>
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
                <!-- Personal Information -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-person-fill text-primary me-2"></i> Personal Details</h6>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="profile.php">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_profile">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Email Address (Immutable)</label>
                                    <input type="text" class="form-control bg-light" value="<?= e($user['email']) ?>" readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="name" class="form-label small fw-bold text-dark">Full Legal Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= e($user['name']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label small fw-bold text-dark">Contact Phone *</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?= e($user['phone']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="ward" class="form-label small fw-bold text-dark">Default Residence Ward</label>
                                    <input type="text" class="form-control" id="ward" name="ward" value="<?= e($user['ward']) ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label small fw-bold text-dark">Residential Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?= e($user['address']) ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary-civic btn-sm px-4">
                                    <i class="bi bi-save me-1"></i> Save Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Update -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-shield-lock-fill text-danger me-2"></i> Security & Password</h6>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="profile.php">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-3">
                                    <label for="current_password" class="form-label small fw-bold text-dark">Current Password *</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label small fw-bold text-dark">New Password *</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label small fw-bold text-dark">Confirm New Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>

                                <button type="submit" class="btn btn-outline-danger btn-sm px-4">
                                    <i class="bi bi-key-fill me-1"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
