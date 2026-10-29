<?php
/**
 * CivicTrack — Forgot Password (OTP via Registered Mobile)
 * Flow: Step 1 → Enter Email → Step 2 → Enter OTP → Step 3 → New Password
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (function_exists('isLoggedIn') && isLoggedIn()) {
    require_once __DIR__ . '/includes/auth.php';
    header('Location: ' . getDashboardUrlByRole());
    exit;
}

$step    = (int)($_SESSION['fp_step'] ?? 1);   // 1=email, 2=otp, 3=new password
$error   = '';
$success = '';

// ─────────────────────────────────────────────
// POST Handler
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken()) {
        $error = 'Security token expired. Please refresh and try again.';
    } else {

        $action = trim($_POST['action'] ?? '');
        $pdo    = getDbConnection();

        // ── STEP 1: Verify email & send OTP ──────────────────────────────
        if ($action === 'send_otp') {
            $email = strtolower(trim($_POST['email'] ?? ''));

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $stmt = $pdo->prepare("SELECT user_id, name, phone, status FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'No account found with that email address.';
                } elseif ($user['status'] !== 'active') {
                    $error = 'Your account is not active. Please contact the administrator.';
                } elseif (empty($user['phone'])) {
                    $error = 'No mobile number is registered on this account. Please contact admin.';
                } else {
                    // Rate-limit: max 3 OTPs per hour per user
                    $rateCheck = $pdo->prepare(
                        "SELECT COUNT(*) FROM password_reset_otp 
                         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
                    );
                    $rateCheck->execute([$user['user_id']]);
                    if ((int)$rateCheck->fetchColumn() >= 3) {
                        $error = 'Too many OTP requests. Please wait 1 hour before trying again.';
                    } else {
                        // Invalidate old OTPs for this user
                        $pdo->prepare("UPDATE password_reset_otp SET is_used = 1 WHERE user_id = ? AND is_used = 0")
                            ->execute([$user['user_id']]);

                        // Generate 6-digit OTP
                        $otpCode   = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                        $ins = $pdo->prepare(
                            "INSERT INTO password_reset_otp (user_id, phone, otp_code, expires_at)
                             VALUES (?, ?, ?, ?)"
                        );
                        $ins->execute([$user['user_id'], $user['phone'], $otpCode, $expiresAt]);

                        // Send OTP via Fast2SMS
                        $smsText = "Your CivicTrack password reset OTP is: {$otpCode}. Valid for 10 minutes. Do not share this with anyone.";
                        $smsResult = dispatchFast2Sms($user['phone'], $smsText);

                        if ($smsResult['status'] === 'Delivered') {
                            // Mask phone for display: 9876543210 → 98****3210
                            $phone = $user['phone'];
                            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
                            $maskedPhone = substr($cleanPhone, 0, 2) . '****' . substr($cleanPhone, -4);

                            $_SESSION['fp_step']    = 2;
                            $_SESSION['fp_user_id'] = $user['user_id'];
                            $_SESSION['fp_masked']  = $maskedPhone;
                            $_SESSION['fp_otp_id']  = (int)$pdo->lastInsertId();

                            header('Location: forgot-password.php');
                            exit;
                        } else {
                            // SMS failed — remove the OTP record we just inserted
                            $pdo->prepare("DELETE FROM password_reset_otp WHERE otp_id = ?")
                                ->execute([(int)$pdo->lastInsertId()]);
                            $error = 'Failed to send OTP SMS: ' . $smsResult['response'] . '. Please try again.';
                        }
                    }
                }
            }
        }

        // ── STEP 2: Verify OTP ───────────────────────────────────────────
        elseif ($action === 'verify_otp') {
            $enteredOtp = trim($_POST['otp'] ?? '');
            $userId     = (int)($_SESSION['fp_user_id'] ?? 0);

            if (empty($enteredOtp) || !preg_match('/^\d{6}$/', $enteredOtp)) {
                $error = 'Please enter the 6-digit OTP sent to your mobile.';
            } elseif (!$userId) {
                $error = 'Session expired. Please start over.';
                unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_masked'], $_SESSION['fp_otp_id']);
                $step = 1;
            } else {
                $stmt = $pdo->prepare(
                    "SELECT otp_id FROM password_reset_otp
                     WHERE user_id = ? AND otp_code = ? AND is_used = 0 AND expires_at > NOW()
                     ORDER BY created_at DESC LIMIT 1"
                );
                $stmt->execute([$userId, $enteredOtp]);
                $otpRow = $stmt->fetch();

                if (!$otpRow) {
                    $error = 'Invalid or expired OTP. Please check and try again, or request a new OTP.';
                } else {
                    $_SESSION['fp_step']           = 3;
                    $_SESSION['fp_verified_otp_id'] = (int)$otpRow['otp_id'];
                    header('Location: forgot-password.php');
                    exit;
                }
            }
        }

        // ── STEP 3: Reset Password ───────────────────────────────────────
        elseif ($action === 'reset_password') {
            $newPass    = $_POST['new_password']    ?? '';
            $confirmPass= $_POST['confirm_password'] ?? '';
            $userId     = (int)($_SESSION['fp_user_id']         ?? 0);
            $verOtpId   = (int)($_SESSION['fp_verified_otp_id'] ?? 0);

            if (empty($newPass) || strlen($newPass) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } elseif ($newPass !== $confirmPass) {
                $error = 'Passwords do not match. Please re-enter.';
            } elseif (!$userId || !$verOtpId) {
                $error = 'Session expired. Please start over.';
                unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_masked'],
                      $_SESSION['fp_otp_id'], $_SESSION['fp_verified_otp_id']);
                $step = 1;
            } else {
                // Verify OTP record still exists and is unused
                $chk = $pdo->prepare("SELECT otp_id FROM password_reset_otp WHERE otp_id = ? AND user_id = ? AND is_used = 0");
                $chk->execute([$verOtpId, $userId]);
                if (!$chk->fetch()) {
                    $error = 'OTP already used or session expired. Please start over.';
                    unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_masked'],
                          $_SESSION['fp_otp_id'], $_SESSION['fp_verified_otp_id']);
                    $step = 1;
                } else {
                    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$hashed, $userId]);
                    // Mark OTP as used
                    $pdo->prepare("UPDATE password_reset_otp SET is_used = 1 WHERE user_id = ? AND is_used = 0")->execute([$userId]);

                    // Clear session state
                    unset($_SESSION['fp_step'], $_SESSION['fp_user_id'], $_SESSION['fp_masked'],
                          $_SESSION['fp_otp_id'], $_SESSION['fp_verified_otp_id']);

                    setFlash('success', 'Password reset successfully! You can now log in with your new password.');
                    header('Location: login.php');
                    exit;
                }
            }
        }
    }
}

// Sync $step after POST
$step = (int)($_SESSION['fp_step'] ?? 1);
$maskedPhone = $_SESSION['fp_masked'] ?? '';

$pageTitle = 'CivicTrack — Forgot Password';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5 my-auto">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white">

                <!-- Card Header -->
                <div class="card-header bg-dark text-white p-4 text-center border-0">
                    <div class="brand-icon mx-auto mb-2" style="width:50px; height:50px; font-size:1.5rem;">
                        <i class="bi bi-phone-lock-fill"></i>
                    </div>
                    <h4 class="fw-bold text-white mb-1">Forgot Password</h4>
                    <p class="text-white-50 small mb-0">
                        <?php if ($step === 1): ?>
                            Enter your registered email to receive OTP
                        <?php elseif ($step === 2): ?>
                            Enter the OTP sent to your mobile
                        <?php else: ?>
                            Set your new password
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Step Progress Bar -->
                <div class="px-4 pt-3 pb-0 bg-light border-bottom">
                    <div class="d-flex align-items-center justify-content-center gap-2 pb-3">
                        <?php foreach ([1 => ['bi-envelope-fill','Email'], 2 => ['bi-phone-fill','OTP'], 3 => ['bi-key-fill','Reset']] as $s => [$icon, $label]): ?>
                            <div class="d-flex flex-column align-items-center">
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                                     style="width:34px;height:34px;font-size:0.85rem;
                                            background:<?= $step > $s ? '#198754' : ($step === $s ? '#0d6efd' : '#dee2e6') ?>;
                                            color:<?= $step >= $s ? '#fff' : '#6c757d' ?>;">
                                    <i class="bi <?= $step > $s ? 'bi-check-lg' : $icon ?>"></i>
                                </div>
                                <span class="mt-1" style="font-size:0.65rem;color:<?= $step === $s ? '#0d6efd' : '#6c757d' ?>;font-weight:<?= $step === $s ? '700' : '400' ?>;">
                                    <?= $label ?>
                                </span>
                            </div>
                            <?php if ($s < 3): ?>
                                <div style="flex:1;height:2px;background:<?= $step > $s ? '#198754' : '#dee2e6' ?>;max-width:60px;"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center small py-2 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php
                    // ════════════════════════════════════════════════
                    // STEP 1 — Enter Email
                    // ════════════════════════════════════════════════
                    if ($step === 1): ?>
                        <form method="POST" action="forgot-password.php" novalidate>
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="send_otp">

                            <p class="text-muted small mb-4">
                                Enter the email address linked to your CivicTrack account.
                                We'll send a 6-digit OTP to your registered mobile number.
                            </p>

                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold text-dark small">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control border-start-0"
                                           id="email" name="email"
                                           placeholder="name@example.com"
                                           value="<?= e($_POST['email'] ?? '') ?>"
                                           required autofocus>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                                <i class="bi bi-send-fill me-1"></i> Send OTP to Mobile
                            </button>
                        </form>

                    <?php
                    // ════════════════════════════════════════════════
                    // STEP 2 — Enter OTP
                    // ════════════════════════════════════════════════
                    elseif ($step === 2): ?>
                        <form method="POST" action="forgot-password.php" novalidate>
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="verify_otp">

                            <div class="text-center mb-4">
                                <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3"
                                     style="width:60px;height:60px;">
                                    <i class="bi bi-phone-fill text-success fs-3"></i>
                                </div>
                                <p class="text-muted small mb-0">
                                    OTP sent to <strong>+91 <?= e($maskedPhone) ?></strong><br>
                                    <span class="text-danger small">Valid for 10 minutes only.</span>
                                </p>
                            </div>

                            <div class="mb-4">
                                <label for="otp" class="form-label fw-semibold text-dark small">Enter 6-Digit OTP</label>
                                <input type="text" class="form-control form-control-lg text-center fw-bold fs-4 letter-spacing-wide"
                                       id="otp" name="otp"
                                       placeholder="— — — — — —"
                                       maxlength="6"
                                       inputmode="numeric"
                                       pattern="\d{6}"
                                       autocomplete="one-time-code"
                                       style="letter-spacing: 0.5em;"
                                       required autofocus>
                                <div class="form-text text-center mt-2">
                                    Check your SMS inbox for the OTP.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold shadow-sm mb-3">
                                <i class="bi bi-shield-check me-1"></i> Verify OTP
                            </button>

                            <!-- Resend OTP -->
                            <div class="text-center">
                                <span class="text-muted small">Didn't receive OTP? </span>
                                <a href="forgot-password.php?resend=1" class="small fw-bold text-primary text-decoration-none"
                                   onclick="return confirm('Resend OTP to <?= e($maskedPhone) ?>?')">
                                    Resend OTP
                                </a>
                            </div>
                        </form>

                    <?php
                    // ════════════════════════════════════════════════
                    // STEP 3 — Set New Password
                    // ════════════════════════════════════════════════
                    elseif ($step === 3): ?>
                        <form method="POST" action="forgot-password.php" novalidate id="resetForm">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reset_password">

                            <div class="text-center mb-4">
                                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2"
                                     style="width:60px;height:60px;">
                                    <i class="bi bi-key-fill text-primary fs-3"></i>
                                </div>
                                <p class="text-muted small mb-0">OTP verified ✅ — Set your new password below.</p>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-semibold text-dark small">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" class="form-control border-start-0"
                                           id="new_password" name="new_password"
                                           placeholder="Min. 8 characters"
                                           minlength="8" required>
                                    <button type="button" class="btn btn-outline-secondary border-start-0"
                                            onclick="togglePwd('new_password','eyeNew')">
                                        <i class="bi bi-eye" id="eyeNew"></i>
                                    </button>
                                </div>
                                <!-- Password strength bar -->
                                <div class="mt-2">
                                    <div class="progress" style="height:4px;">
                                        <div class="progress-bar" id="strengthBar" style="width:0%; transition:width 0.3s;"></div>
                                    </div>
                                    <small id="strengthLabel" class="text-muted" style="font-size:0.7rem;"></small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold text-dark small">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                    <input type="password" class="form-control border-start-0"
                                           id="confirm_password" name="confirm_password"
                                           placeholder="Re-enter new password"
                                           minlength="8" required>
                                    <button type="button" class="btn btn-outline-secondary border-start-0"
                                            onclick="togglePwd('confirm_password','eyeConfirm')">
                                        <i class="bi bi-eye" id="eyeConfirm"></i>
                                    </button>
                                </div>
                                <div id="matchMsg" class="form-text mt-1"></div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm" id="resetBtn">
                                <i class="bi bi-check2-circle me-1"></i> Reset Password
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Back to Login -->
                    <div class="text-center mt-4 pt-3 border-top">
                        <a href="login.php" class="small text-muted text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i> Back to Login
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePwd(fieldId, iconId) {
    const field = document.getElementById(fieldId);
    const icon  = document.getElementById(iconId);
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Password strength checker
const newPwdField = document.getElementById('new_password');
const confirmField = document.getElementById('confirm_password');
if (newPwdField) {
    newPwdField.addEventListener('input', function() {
        const val = this.value;
        let strength = 0;
        if (val.length >= 8)             strength++;
        if (/[A-Z]/.test(val))           strength++;
        if (/[0-9]/.test(val))           strength++;
        if (/[^A-Za-z0-9]/.test(val))   strength++;

        const bar   = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');
        const colors = ['#dc3545','#fd7e14','#ffc107','#198754'];
        const labels = ['Weak','Fair','Good','Strong'];

        bar.style.width   = (strength * 25) + '%';
        bar.style.background = colors[strength - 1] || '#dee2e6';
        label.textContent = strength > 0 ? labels[strength - 1] : '';
        label.style.color = colors[strength - 1] || '#6c757d';

        checkMatch();
    });
}

if (confirmField) {
    confirmField.addEventListener('input', checkMatch);
}

function checkMatch() {
    const matchMsg = document.getElementById('matchMsg');
    if (!matchMsg || !newPwdField || !confirmField) return;
    if (confirmField.value.length === 0) { matchMsg.textContent = ''; return; }
    if (newPwdField.value === confirmField.value) {
        matchMsg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Passwords match</span>';
    } else {
        matchMsg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Passwords do not match</span>';
    }
}

// OTP auto-format (numbers only)
const otpField = document.getElementById('otp');
if (otpField) {
    otpField.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
    });
}
</script>

<?php
// Handle resend OTP (clears session step back to 1, keeps user_id)
if (isset($_GET['resend']) && $_GET['resend'] == '1' && isset($_SESSION['fp_user_id'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_otp_id']);
    header('Location: forgot-password.php');
    exit;
}

require_once __DIR__ . '/includes/footer.php';
?>

