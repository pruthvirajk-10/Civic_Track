<?php
/**
 * CivicTrack — 1-Click Database Setup & Installer
 * Initializes MySQL database `civictrack_db`, tables, and demo seed data.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$status = '';
$details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    try {
        // Connect to MySQL server without specific DB first
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // 1. Create Database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $details[] = "✅ Database <strong>" . DB_NAME . "</strong> created / verified.";

        // Switch to DB
        $pdo->exec("USE `" . DB_NAME . "`;");

        // 2. Execute schema.sql
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (file_exists($schemaFile)) {
            $schemaSql = file_get_contents($schemaFile);
            $pdo->exec($schemaSql);
            $details[] = "✅ Database tables created successfully from <code>schema.sql</code>.";
        } else {
            throw new Exception("schema.sql not found at " . $schemaFile);
        }

        // 3. Execute seed.sql
        $seedFile = __DIR__ . '/database/seed.sql';
        if (file_exists($seedFile)) {
            $seedSql = file_get_contents($seedFile);
            $pdo->exec($seedSql);
            $details[] = "✅ Demonstration data loaded successfully from <code>seed.sql</code>.";
        }

        // 4. Update passwords to ensure native PHP bcrypt compatibility
        $adminHash = password_hash('Admin@123', PASSWORD_BCRYPT);
        $officerHash = password_hash('Officer@123', PASSWORD_BCRYPT);
        $citizenHash = password_hash('Citizen@123', PASSWORD_BCRYPT);

        // Update Admin
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE role = 'admin'");
        $stmt->execute([$adminHash]);

        // Update Officers
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE role = 'officer'");
        $stmt->execute([$officerHash]);

        // Update Citizens
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE role = 'citizen'");
        $stmt->execute([$citizenHash]);

        $details[] = "✅ Passwords hashed and synchronized for seamless login verification.";

        // 5. Ensure upload folders exist
        if (!is_dir(COMPLAINT_UPLOAD_DIR)) {
            mkdir(COMPLAINT_UPLOAD_DIR, 0755, true);
        }
        if (!is_dir(RESOLUTION_UPLOAD_DIR)) {
            mkdir(RESOLUTION_UPLOAD_DIR, 0755, true);
        }
        $details[] = "✅ Upload directories verified: <code>uploads/complaints</code> and <code>uploads/resolutions</code>.";

        $status = 'success';
        $message = 'CivicTrack Database Setup Completed Successfully!';

    } catch (Exception $e) {
        $status = 'error';
        $message = 'Setup Error: ' . $e->getMessage();
    }
}

$pageTitle = 'CivicTrack — System Setup & Database Installer';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="brand-icon mx-auto mb-3" style="width:54px; height:54px; font-size:1.6rem;">
                            <i class="bi bi-gear-wide-connected"></i>
                        </div>
                        <h2 class="fw-bold text-dark">CivicTrack Installer</h2>
                        <p class="text-muted">1-Click Database Setup & Sample Data Seeder</p>
                    </div>

                    <?php if ($status === 'success'): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4">
                            <i class="bi bi-check-circle-fill fs-3 me-3"></i>
                            <div>
                                <h5 class="alert-heading fw-bold mb-1"><?= $message ?></h5>
                                <p class="mb-0 small">The database is fully ready for use with sample departments, categories, complaints, and test accounts.</p>
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded-3 mb-4">
                            <h6 class="fw-bold mb-2 text-dark">Installation Log:</h6>
                            <ul class="mb-0 small ps-3">
                                <?php foreach ($details as $item): ?>
                                    <li class="mb-1"><?= $item ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="card border-primary-subtle bg-primary-subtle bg-opacity-10 rounded-3 mb-4">
                            <div class="card-body p-3">
                                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-key-fill me-1"></i> Pre-Configured Test Credentials:</h6>
                                <div class="row g-2 small text-dark">
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded bg-white">
                                            <span class="badge bg-purple text-white mb-1" style="background:#7c3aed;">Administrator</span>
                                            <div><strong>Email:</strong> admin@civictrack.gov</div>
                                            <div><strong>Pass:</strong> <code>Admin@123</code></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded bg-white">
                                            <span class="badge bg-teal text-white mb-1" style="background:#0d9488;">Roads Officer</span>
                                            <div><strong>Email:</strong> officer.roads@civictrack.gov</div>
                                            <div><strong>Pass:</strong> <code>Officer@123</code></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded bg-white">
                                            <span class="badge bg-primary text-white mb-1">Citizen</span>
                                            <div><strong>Email:</strong> aarav.mehta@example.com</div>
                                            <div><strong>Pass:</strong> <code>Citizen@123</code></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-3">
                            <a href="login.php" class="btn btn-primary-civic px-4 py-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Login
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary px-4 py-2">
                                <i class="bi bi-house me-1"></i> View Landing Page
                            </a>
                        </div>

                    <?php else: ?>
                        <?php if ($status === 'error'): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4">
                                <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                                <div>
                                    <h5 class="alert-heading fw-bold mb-1">Installation Failed</h5>
                                    <p class="mb-0 small"><?= e($message) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="border rounded-3 p-3 mb-4 bg-light">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-info-circle me-1"></i> What this installer will do:</h6>
                            <ul class="small text-muted mb-0 ps-3">
                                <li>Check connection to MySQL host: <code><?= DB_HOST ?></code> with user: <code><?= DB_USER ?></code></li>
                                <li>Create database: <code><?= DB_NAME ?></code> if it doesn't already exist</li>
                                <li>Create all tables: <code>departments</code>, <code>users</code>, <code>complaint_categories</code>, <code>complaints</code>, <code>complaint_logs</code>, <code>feedback</code>, <code>notifications</code>, <code>system_settings</code></li>
                                <li>Insert standard municipal departments and default SLA categories</li>
                                <li>Seed realistic demonstration complaints with audit trails and photo references</li>
                                <li>Setup verified bcrypt logins for Admin, Officers, and Citizens</li>
                            </ul>
                        </div>

                        <form method="POST" action="setup.php">
                            <input type="hidden" name="action" value="install">
                            <button type="submit" class="btn btn-primary-civic w-100 py-3 fs-6">
                                <i class="bi bi-play-circle-fill me-2"></i> Click Here to Initialize Database Now
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
