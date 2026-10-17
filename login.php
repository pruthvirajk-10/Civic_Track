<?php
/**
 * CivicTrack — Unified Authentication (Login)
 * Supports Citizens, Department Officers, and Administrators
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to respective dashboard
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrlByRole());
    exit;
}

$error = '';
$emailInput = trim($_POST['email'] ?? '');
$redirectTarget = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!verifyCsrfToken()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } elseif (empty($_POST['email']) || empty($_POST['password'])) {
        $error = 'Please enter both your registered email and password.';
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated or suspended. Please contact administrator.';
                } else {
                    // Successful login
                    loginUser($user);

                    // If redirect parameter is present and internal, navigate there
                    if (!empty($redirectTarget) && strpos($redirectTarget, 'http') === false) {
                        header('Location: ' . $redirectTarget);
                    } else {
                        header('Location: ' . getDashboardUrlByRole($user['role']));
                    }
                    exit;
                }
            } else {
                $error = 'Invalid email address or password. Please verify and try again.';
            }
        } catch (Exception $e) {
            $error = 'Database error during authentication: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'CivicTrack — Portal Login';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5 my-auto">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-dark text-white p-4 text-center border-0">
                    <div class="brand-icon mx-auto mb-2" style="width:50px; height:50px; font-size:1.5rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h4 class="fw-bold text-white mb-1">CivicTrack Portal</h4>
                    <p class="text-white-50 small mb-0">Sign in to Citizen, Officer, or Admin Console</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php renderFlash(); ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center small py-2 mb-4">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" novalidate>
                        <?= csrfField() ?>
                        <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold text-dark small">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" 
                                       placeholder="name@example.com" value="<?= e($emailInput) ?>" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label fw-semibold text-dark small mb-0">Password</label>
                                <a href="setup.php" class="small text-muted text-decoration-none">Reset test DB?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0">
                                    <i class="bi bi-key"></i>
                                </span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" 
                                       placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary-civic w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Dashboard
                        </button>
                    </form>

                    <div class="text-center mt-3 pt-2 border-top">
                        <p class="small text-muted mb-0">
                            Don't have an account? <a href="register.php" class="fw-bold text-primary">Register as Citizen</a>
                        </p>
                    </div>

                    <!-- Quick Demo Credentials Filler -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="small fw-bold text-muted text-uppercase mb-2 text-center" style="font-size:0.75rem; letter-spacing:0.05em;">
                            ⚡ 1-Click Demo Login Shortcuts
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <button type="button" class="btn btn-sm btn-outline-purple w-100 text-truncate" style="border-color:#7c3aed; color:#7c3aed; font-size:0.75rem;" 
                                        onclick="fillDemo('admin@civictrack.gov', 'Admin@123')">
                                    <i class="bi bi-shield-fill"></i> Admin
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-sm btn-outline-teal w-100 text-truncate" style="border-color:#0d9488; color:#0d9488; font-size:0.75rem;" 
                                        onclick="fillDemo('officer.roads@civictrack.gov', 'Officer@123')">
                                    <i class="bi bi-person-badge"></i> Officer
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100 text-truncate" style="font-size:0.75rem;" 
                                        onclick="fillDemo('aarav.mehta@example.com', 'Citizen@123')">
                                    <i class="bi bi-person-fill"></i> Citizen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
