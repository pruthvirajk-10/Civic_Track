<?php
/**
 * CivicTrack — Database Setup & Data Management Installer
 * Provides clean production initialization, optional demonstration data seeder,
 * and complaint purge tools.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$status = '';
$details = [];

// Inspect current database state
$dbState = [
    'connected' => false,
    'tables_exist' => false,
    'departments_count' => 0,
    'categories_count' => 0,
    'users_count' => 0,
    'complaints_count' => 0,
    'has_sample_data' => false
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdoTest = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbState['connected'] = true;

    // Check if database exists
    $stmt = $pdoTest->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    if ($stmt->fetch()) {
        $pdoDb = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        // Check tables
        $tables = $pdoDb->query("SHOW TABLES LIKE 'complaints'")->fetchAll();
        if (!empty($tables)) {
            $dbState['tables_exist'] = true;
            $dbState['departments_count'] = (int)$pdoDb->query("SELECT COUNT(*) FROM departments")->fetchColumn();
            $dbState['categories_count'] = (int)$pdoDb->query("SELECT COUNT(*) FROM complaint_categories")->fetchColumn();
            $dbState['users_count'] = (int)$pdoDb->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $dbState['complaints_count'] = (int)$pdoDb->query("SELECT COUNT(*) FROM complaints")->fetchColumn();

            // Check if demo complaints exist
            $demoCheck = $pdoDb->query("SELECT COUNT(*) FROM complaints WHERE reference_number IN ('CT-2026-10001', 'CT-2026-10002')")->fetchColumn();
            if ($demoCheck > 0) {
                $dbState['has_sample_data'] = true;
            }
        }
    }
} catch (Exception $e) {
    // Database connection or table inspection failed
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        if ($action === 'install_clean' || $action === 'install_demo') {
            // 1. Create Database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo->exec("USE `" . DB_NAME . "`;");
            $details[] = "✅ Database <strong>" . DB_NAME . "</strong> initialized.";

            // 2. Execute schema.sql
            $schemaFile = __DIR__ . '/database/schema.sql';
            if (file_exists($schemaFile)) {
                $pdo->exec(file_get_contents($schemaFile));
                $details[] = "✅ Relational database schema tables created.";
            } else {
                throw new Exception("schema.sql not found at " . $schemaFile);
            }

            // 3. Execute master_data.sql
            $masterFile = __DIR__ . '/database/master_data.sql';
            if (file_exists($masterFile)) {
                $pdo->exec(file_get_contents($masterFile));
                $details[] = "✅ Municipal departments, SLA categories, system settings & admin account loaded.";
            }

            // 4. Set secure hash for Administrator
            $adminHash = password_hash('Admin@123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE role = 'admin'");
            $stmt->execute([$adminHash]);

            // 5. If Demo requested, load sample_data.sql
            if ($action === 'install_demo') {
                $sampleFile = __DIR__ . '/database/sample_data.sql';
                if (file_exists($sampleFile)) {
                    $pdo->exec(file_get_contents($sampleFile));
                    $details[] = "✅ Demonstration records loaded (sample officers, test citizens, realistic complaints).";

                    // Hash sample officer and citizen passwords
                    $officerHash = password_hash('Officer@123', PASSWORD_BCRYPT);
                    $citizenHash = password_hash('Citizen@123', PASSWORD_BCRYPT);

                    $pdo->prepare("UPDATE users SET password = ? WHERE role = 'officer'")->execute([$officerHash]);
                    $pdo->prepare("UPDATE users SET password = ? WHERE role = 'citizen'")->execute([$citizenHash]);
                }
            } else {
                $details[] = "✅ <strong>Clean Mode:</strong> Zero sample complaints created. System is 100% clean for genuine citizen input.";
            }

            // 6. Ensure upload directories
            if (!is_dir(COMPLAINT_UPLOAD_DIR)) mkdir(COMPLAINT_UPLOAD_DIR, 0755, true);
            if (!is_dir(RESOLUTION_UPLOAD_DIR)) mkdir(RESOLUTION_UPLOAD_DIR, 0755, true);
            $details[] = "✅ Upload directories verified.";

            $status = 'success';
            $message = ($action === 'install_clean') 
                ? 'Clean Production Database Setup Completed Successfully!' 
                : 'Demonstration Database Setup Completed Successfully!';

        } elseif ($action === 'purge_complaints') {
            $pdo->exec("USE `" . DB_NAME . "`;");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec("TRUNCATE TABLE `complaint_logs`;");
            $pdo->exec("TRUNCATE TABLE `feedback`;");
            $pdo->exec("TRUNCATE TABLE `notifications`;");
            $pdo->exec("TRUNCATE TABLE `sms_logs`;");
            $pdo->exec("TRUNCATE TABLE `complaints`;");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

            $status = 'success';
            $message = 'All complaints, logs, feedback, and notifications have been cleanly purged!';
            $details[] = "✅ Complaints table truncated (0 records).";
            $details[] = "✅ Activity audit logs truncated.";
            $details[] = "✅ Citizen feedback records truncated.";
            $details[] = "✅ Notifications truncated.";
            $details[] = "ℹ️ Master departments, categories, and user accounts remain fully intact.";
        }

    } catch (Exception $e) {
        $status = 'error';
        $message = 'Setup Error: ' . $e->getMessage();
    }
}

$pageTitle = 'CivicTrack — System Setup & Database Management';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card shadow-sm border-0 rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-dark text-white p-4 text-center border-0">
                    <div class="brand-icon mx-auto mb-2" style="width:52px; height:52px; font-size:1.6rem;">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                    <h3 class="fw-bold text-white mb-1">CivicTrack Database Setup & Installer</h3>
                    <p class="text-white-50 small mb-0">Initialize clean production database or toggle demonstration mode</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if ($status === 'success'): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4">
                            <i class="bi bi-check-circle-fill fs-2 me-3"></i>
                            <div>
                                <h5 class="alert-heading fw-bold mb-1"><?= $message ?></h5>
                                <p class="mb-0 small">The database state has been successfully updated.</p>
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded-3 mb-4">
                            <h6 class="fw-bold mb-2 text-dark">Action Log:</h6>
                            <ul class="mb-0 small ps-3">
                                <?php foreach ($details as $item): ?>
                                    <li class="mb-1"><?= $item ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-center gap-3">
                            <a href="login.php" class="btn btn-primary-civic px-4 py-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Login
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary px-4 py-2">
                                <i class="bi bi-house me-1"></i> View Homepage
                            </a>
                        </div>

                    <?php else: ?>

                        <?php if ($status === 'error'): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4">
                                <i class="bi bi-exclamation-triangle-fill fs-2 me-3"></i>
                                <div>
                                    <h5 class="alert-heading fw-bold mb-1">Execution Failed</h5>
                                    <p class="mb-0 small"><?= e($message) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Current Database Status Inspection -->
                        <div class="card border bg-light rounded-4 p-4 mb-4">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="bi bi-activity text-primary me-2"></i> Current Database Inspection Status
                            </h6>
                            <div class="row g-3 text-center">
                                <div class="col-6 col-md-3">
                                    <div class="p-3 bg-white rounded-3 border">
                                        <small class="text-muted d-block">MySQL Status</small>
                                        <strong class="<?= $dbState['connected'] ? 'text-success' : 'text-danger' ?>">
                                            <?= $dbState['connected'] ? 'Connected' : 'Offline' ?>
                                        </strong>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 bg-white rounded-3 border">
                                        <small class="text-muted d-block">Departments</small>
                                        <strong class="text-dark fs-5"><?= $dbState['departments_count'] ?></strong>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 bg-white rounded-3 border">
                                        <small class="text-muted d-block">Users Registered</small>
                                        <strong class="text-dark fs-5"><?= $dbState['users_count'] ?></strong>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="p-3 bg-white rounded-3 border">
                                        <small class="text-muted d-block">Complaints on Record</small>
                                        <strong class="<?= ($dbState['complaints_count'] > 0) ? 'text-primary' : 'text-muted' ?> fs-5">
                                            <?= $dbState['complaints_count'] ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>

                            <?php if ($dbState['has_sample_data']): ?>
                                <div class="alert alert-warning border-warning small mt-3 mb-0 py-2 d-flex align-items-center">
                                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                                    <div>The database currently contains <strong>demonstration sample complaints</strong>. Use "Reset to Clean State" below if you want an empty database for genuine testing.</div>
                                </div>
                            <?php elseif ($dbState['complaints_count'] === 0 && $dbState['tables_exist']): ?>
                                <div class="alert alert-success border-success small mt-3 mb-0 py-2 d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                                    <div>The database is currently in <strong>Clean Production Mode</strong> with 0 complaints. All reported issues will be 100% genuine user data.</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Installation Options -->
                        <div class="row g-4">
                            <!-- Option 1: Clean Production Mode -->
                            <div class="col-md-6">
                                <div class="card h-100 border-primary rounded-4 shadow-sm p-4 text-center">
                                    <div class="rounded-circle bg-primary-subtle text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:50px; height:50px; font-size:1.5rem;">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark">Option 1: Clean System Setup</h5>
                                    <p class="text-muted small mb-3">
                                        Recommended for genuine project evaluation. Sets up all tables, municipal departments, SLA categories, and the Admin account with <strong>zero fake complaints</strong>.
                                    </p>
                                    <form method="POST" action="setup.php" onsubmit="return confirm('Initialize clean database? Existing data in civictrack_db will be reinitialized.');" class="mt-auto">
                                        <input type="hidden" name="action" value="install_clean">
                                        <button type="submit" class="btn btn-primary-civic w-100 py-2 fw-bold">
                                            Initialize Clean Database
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Option 2: Demo Mode -->
                            <div class="col-md-6">
                                <div class="card h-100 border-secondary-subtle rounded-4 shadow-sm p-4 text-center">
                                    <div class="rounded-circle bg-teal-subtle text-teal mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:50px; height:50px; font-size:1.5rem; color:#0d9488; background:#ccfbf1;">
                                        <i class="bi bi-collection-play"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark">Option 2: Load Demonstration Data</h5>
                                    <p class="text-muted small mb-3">
                                        Recommended for mentor presentations when you need pre-populated sample tickets, officer queues, charts, and resolution proofs ready for review.
                                    </p>
                                    <form method="POST" action="setup.php" onsubmit="return confirm('Load sample demonstration records?');" class="mt-auto">
                                        <input type="hidden" name="action" value="install_demo">
                                        <button type="submit" class="btn btn-teal-civic w-100 py-2 fw-bold">
                                            Load Demonstration Data
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Option 3: Reset / Purge Complaints Only -->
                        <?php if ($dbState['tables_exist'] && $dbState['complaints_count'] > 0): ?>
                            <div class="card border-danger-subtle bg-danger-subtle bg-opacity-10 rounded-4 p-4 mt-4 text-center">
                                <h6 class="fw-bold text-danger mb-1"><i class="bi bi-trash3-fill me-1"></i> Reset Complaints to Zero</h6>
                                <p class="small text-muted mb-3 mx-auto" style="max-width:550px;">
                                    Purge all complaints, logs, feedback, and notifications to return the application to an empty state without losing your departments, categories, or user accounts.
                                </p>
                                <form method="POST" action="setup.php" onsubmit="return confirm('Are you sure you want to delete all complaints and reset the counter to zero?');">
                                    <input type="hidden" name="action" value="purge_complaints">
                                    <button type="submit" class="btn btn-outline-danger btn-sm px-4 fw-bold">
                                        Purge All Complaints & Reset to Zero
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Credentials Reference Box -->
                        <div class="card border-0 bg-light rounded-4 p-4 mt-4">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-key-fill text-primary me-1"></i> Verified System Credentials:</h6>
                            <div class="row g-2 small text-dark">
                                <div class="col-md-4">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="badge text-white mb-1" style="background:#7c3aed;">Administrator</span>
                                        <div><strong>Email:</strong> admin@civictrack.gov</div>
                                        <div><strong>Pass:</strong> <code>Admin@123</code></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="badge text-white mb-1" style="background:#0d9488;">Sample Officer</span>
                                        <div><strong>Email:</strong> officer.roads@civictrack.gov</div>
                                        <div><strong>Pass:</strong> <code>Officer@123</code></div>
                                        <small class="text-muted d-block">(In Demo Mode)</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 border rounded bg-white">
                                        <span class="badge bg-primary text-white mb-1">Sample Citizen</span>
                                        <div><strong>Email:</strong> aarav.mehta@example.com</div>
                                        <div><strong>Pass:</strong> <code>Citizen@123</code></div>
                                        <small class="text-muted d-block">(In Demo Mode)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
