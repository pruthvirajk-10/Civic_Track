<?php
/**
 * CivicTrack — Citizen Registration
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrlByRole());
    exit;
}

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'ward' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $errors[] = 'Session security token expired. Please try again.';
    } else {
        $formData['name'] = trim($_POST['name'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $formData['address'] = trim($_POST['address'] ?? '');
        $formData['ward'] = trim($_POST['ward'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($formData['name'])) {
            $errors[] = 'Full name is required.';
        }
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (empty($formData['phone'])) {
            $errors[] = 'Contact phone number is required.';
        }
        if (empty($formData['ward'])) {
            $errors[] = 'Please specify your residence ward or zone.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            try {
                $pdo = getDbConnection();

                // Check duplicate email
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$formData['email']]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'An account with this email address already exists. Please log in.';
                } else {
                    // Hash password securely
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                    // Insert citizen
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, phone, password, role, status, address, ward)
                        VALUES (?, ?, ?, ?, 'citizen', 'active', ?, ?)
                    ");
                    $stmt->execute([
                        $formData['name'],
                        $formData['email'],
                        $formData['phone'],
                        $hashedPassword,
                        $formData['address'],
                        $formData['ward']
                    ]);

                    $newUserId = (int)$pdo->lastInsertId();

                    // Log in the user immediately
                    loginUser([
                        'user_id' => $newUserId,
                        'name' => $formData['name'],
                        'email' => $formData['email'],
                        'role' => 'citizen',
                        'phone' => $formData['phone'],
                        'dept_id' => null
                    ]);

                    // Send welcome notification
                    createNotification($pdo, $newUserId, null, 'Welcome to CivicTrack', 'Your citizen account has been successfully created. You can now lodge and track civic complaints in real time.');

                    setFlash('success', 'Welcome to CivicTrack, ' . $formData['name'] . '! Your account is active.');
                    header('Location: ' . getBaseUrl() . 'citizen/dashboard.php');
                    exit;
                }
            } catch (Exception $e) {
                $errors[] = 'Registration error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'CivicTrack — Citizen Registration';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5 my-auto">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-7">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-dark text-white p-4 text-center border-0">
                    <div class="brand-icon mx-auto mb-2" style="width:50px; height:50px; font-size:1.5rem;">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <h4 class="fw-bold text-white mb-1">Citizen Registration</h4>
                    <p class="text-white-50 small mb-0">Join CivicTrack to report & track neighborhood civic issues</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger small py-2 mb-4">
                            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please resolve the following errors:</div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php" novalidate>
                        <?= csrfField() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-semibold text-dark small">Full Legal Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="e.g. Aarav Mehta" value="<?= e($formData['name']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold text-dark small">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="name@example.com" value="<?= e($formData['email']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold text-dark small">Phone / Mobile *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       placeholder="+91 98765 43210" value="<?= e($formData['phone']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="ward" class="form-label fw-semibold text-dark small">Ward / Municipal Zone *</label>
                                <select class="form-select" id="ward" name="ward" required>
                                    <option value="">Select Ward / Area...</option>
                                    <option value="Ward 01 - City Center" <?= ($formData['ward'] === 'Ward 01 - City Center') ? 'selected' : '' ?>>Ward 01 - City Center</option>
                                    <option value="Ward 04 - Bandra West" <?= ($formData['ward'] === 'Ward 04 - Bandra West') ? 'selected' : '' ?>>Ward 04 - Bandra West</option>
                                    <option value="Ward 07 - Powai" <?= ($formData['ward'] === 'Ward 07 - Powai') ? 'selected' : '' ?>>Ward 07 - Powai</option>
                                    <option value="Ward 09 - South Zone" <?= ($formData['ward'] === 'Ward 09 - South Zone') ? 'selected' : '' ?>>Ward 09 - South Zone</option>
                                    <option value="Ward 12 - Andheri East" <?= ($formData['ward'] === 'Ward 12 - Andheri East') ? 'selected' : '' ?>>Ward 12 - Andheri East</option>
                                    <option value="Ward 15 - West Zone" <?= ($formData['ward'] === 'Ward 15 - West Zone') ? 'selected' : '' ?>>Ward 15 - West Zone</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label fw-semibold text-dark small">Residential Address</label>
                                <input type="text" class="form-control" id="address" name="address" 
                                       placeholder="Building / Flat No, Street, Landmark" value="<?= e($formData['address']) ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label fw-semibold text-dark small">Create Password *</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="At least 6 characters" required>
                            </div>

                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label fw-semibold text-dark small">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Re-enter password" required>
                            </div>
                        </div>

                        <div class="form-check my-3">
                            <input class="form-check-input" type="checkbox" id="termsCheck" required checked>
                            <label class="form-check-label small text-muted" for="termsCheck">
                                I confirm that information submitted for municipal civic complaints will be accurate and truthful.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-teal-civic w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-person-check-fill me-1"></i> Complete Registration & Log In
                        </button>
                    </form>

                    <div class="text-center mt-3 pt-2 border-top">
                        <p class="small text-muted mb-0">
                            Already registered? <a href="login.php" class="fw-bold text-primary">Sign in to your account</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
