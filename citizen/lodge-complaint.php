<?php
/**
 * CivicTrack — Lodge a New Civic Complaint
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('citizen');

$userId = getUserId();
$userName = getUserName();
$baseUrl = getBaseUrl();

$errors = [];
$successRef = '';
$preselectedCat = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$categories = [];
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM complaint_categories WHERE is_active = 1 ORDER BY category_name ASC");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $errors[] = 'Database error loading categories: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $errors[] = 'Security token invalid or expired. Please re-submit.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $priority = trim($_POST['priority'] ?? 'Medium');
        $description = trim($_POST['description'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $ward = trim($_POST['ward'] ?? '');
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        // Validation
        if (empty($title)) {
            $errors[] = 'Please provide a clear complaint title.';
        }
        if ($categoryId <= 0) {
            $errors[] = 'Please select a complaint category.';
        }
        if (empty($description)) {
            $errors[] = 'Please describe the civic issue in detail.';
        }
        if (empty($address)) {
            $errors[] = 'Please provide the street address or nearest landmark.';
        }
        if (empty($ward)) {
            $errors[] = 'Please select the municipal ward.';
        }

        // File upload handling
        $uploadedImageUrl = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $uploadedImageUrl = handleFileUpload($_FILES['photo'], 'complaints');
            } catch (Exception $e) {
                $errors[] = 'Photo upload error: ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            try {
                // Fetch department mapped to this category
                $stmt = $pdo->prepare("SELECT dept_id FROM complaint_categories WHERE category_id = ?");
                $stmt->execute([$categoryId]);
                $deptId = (int)$stmt->fetchColumn();

                if ($deptId <= 0) {
                    throw new Exception('Selected category is not mapped to an active municipal department.');
                }

                // Start atomic transaction
                $pdo->beginTransaction();

                // Generate unique reference number & SLA deadline
                $refNumber = generateReferenceNumber($pdo);
                $slaDeadline = calculateSlaDeadline($pdo, $categoryId, $priority);

                // Insert complaint
                $stmt = $pdo->prepare("
                    INSERT INTO complaints (
                        reference_number, citizen_id, category_id, dept_id,
                        title, description, priority, image_url,
                        latitude, longitude, address, ward,
                        status, sla_deadline
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', ?)
                ");
                $stmt->execute([
                    $refNumber,
                    $userId,
                    $categoryId,
                    $deptId,
                    $title,
                    $description,
                    $priority,
                    $uploadedImageUrl,
                    $latitude,
                    $longitude,
                    $address,
                    $ward,
                    $slaDeadline
                ]);

                $complaintId = (int)$pdo->lastInsertId();

                // Log activity
                logComplaintActivity(
                    $pdo,
                    $complaintId,
                    $userId,
                    'Complaint Lodged',
                    null,
                    'Submitted',
                    'Citizen lodged complaint with photographic evidence and ward location.',
                    null
                );

                // Create notification for citizen
                createNotification(
                    $pdo,
                    $userId,
                    $complaintId,
                    'Complaint Submitted',
                    "Your grievance #{$refNumber} has been logged and auto-routed to the department with an SLA target of " . formatDateTime($slaDeadline) . "."
                );

                // Notify department officers if any
                $officerStmt = $pdo->prepare("SELECT user_id FROM users WHERE dept_id = ? AND role = 'officer' AND status = 'active'");
                $officerStmt->execute([$deptId]);
                $deptOfficers = $officerStmt->fetchAll();
                foreach ($deptOfficers as $off) {
                    createNotification(
                        $pdo,
                        (int)$off['user_id'],
                        $complaintId,
                        'New Complaint Assigned',
                        "New grievance #{$refNumber} lodged in {$ward}. Please review evidence."
                    );
                }

                $pdo->commit();
                $successRef = $refNumber;

            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to submit grievance: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Lodge Grievance — CivicTrack';
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
                    <h5 class="fw-bold text-dark mb-0">Lodge Civic Grievance</h5>
                    <small class="text-muted">Report neighborhood municipal infrastructure issues</small>
                </div>
            </div>

            <a href="my-complaints.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to My Grievances
            </a>
        </header>

        <main class="dashboard-content">
            <?php if (!empty($successRef)): ?>
                <!-- Submission Success Screen -->
                <div class="card border-0 shadow-sm rounded-4 text-center p-5 bg-white">
                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 72px; height: 72px; font-size: 2.2rem;">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-2">Complaint Lodged Successfully!</h3>
                    <p class="text-muted mx-auto mb-4" style="max-width: 520px;">
                        Your grievance has been officially registered with the Municipal Corporation and automatically assigned to the concerned department.
                    </p>

                    <div class="p-3 bg-light rounded-4 d-inline-block mx-auto mb-4 border">
                        <small class="text-muted text-uppercase fw-bold d-block" style="letter-spacing:0.05em;">Your Reference Number</small>
                        <span class="fs-3 fw-bold font-monospace text-primary"><?= e($successRef) ?></span>
                    </div>

                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="complaint-details.php?ref=<?= urlencode($successRef) ?>" class="btn btn-primary-civic px-4 py-2">
                            <i class="bi bi-eye-fill me-1"></i> View Live Ticket
                        </a>
                        <a href="my-complaints.php" class="btn btn-outline-secondary px-4 py-2">
                            <i class="bi bi-card-checklist me-1"></i> My Grievances List
                        </a>
                        <a href="lodge-complaint.php" class="btn btn-outline-primary px-4 py-2">
                            <i class="bi bi-plus-circle me-1"></i> Report Another Issue
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger shadow-sm small py-2 mb-4">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please correct the following errors:</div>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm rounded-4 bg-white">
                    <div class="card-header bg-white p-4 border-bottom">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Grievance Submission Form</h5>
                                <p class="text-muted small mb-0">Provide detailed information and photo evidence for faster ground inspection.</p>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bi bi-shield-check me-1"></i> Securely Audited
                            </span>
                        </div>
                    </div>

                    <form method="POST" action="lodge-complaint.php" enctype="multipart/form-data" class="p-4 p-md-5" novalidate>
                        <?= csrfField() ?>

                        <!-- Section 1: Issue Classification -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="bi bi-1-circle-fill text-primary me-2"></i> Issue Classification
                            </h6>

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="title" class="form-label fw-semibold text-dark small">Complaint Title / Summary *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           placeholder="e.g. Deep pothole on main carriageway outside Metro Pillar 42" 
                                           value="<?= e($_POST['title'] ?? '') ?>" required>
                                </div>

                                <div class="col-md-4">
                                    <label for="priority" class="form-label fw-semibold text-dark small">Urgency Level</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="Low" <?= (isset($_POST['priority']) && $_POST['priority'] === 'Low') ? 'selected' : '' ?>>Low (Cosmetic/Routine)</option>
                                        <option value="Medium" <?= (!isset($_POST['priority']) || $_POST['priority'] === 'Medium') ? 'selected' : '' ?>>Medium (Normal Priority)</option>
                                        <option value="High" <?= (isset($_POST['priority']) && $_POST['priority'] === 'High') ? 'selected' : '' ?>>High (Accident Hazard)</option>
                                        <option value="Urgent" <?= (isset($_POST['priority']) && $_POST['priority'] === 'Urgent') ? 'selected' : '' ?>>Urgent (Emergency/Health Risk)</option>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label for="category_id" class="form-label fw-semibold text-dark small">Select Civic Category *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">-- Choose Category (Auto-Routes to Department) --</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['category_id'] ?>" 
                                                <?= ($preselectedCat == $cat['category_id'] || (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id'])) ? 'selected' : '' ?>>
                                                <?= e($cat['category_name']) ?> &mdash; SLA: <?= (int)$cat['sla_hours'] ?> Hours
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">
                                        The complaint will be routed automatically to the designated department based on your chosen category.
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="description" class="form-label fw-semibold text-dark small">Detailed Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" 
                                              placeholder="Provide specifics: approximate dimensions of pothole, duration of issue, exact hazards, etc..." required><?= e($_POST['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Photographic Evidence -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="bi bi-2-circle-fill text-primary me-2"></i> Photographic Evidence
                            </h6>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="photo" class="form-label fw-semibold text-dark small">Upload Photo of the Issue (Max 5MB: JPG, PNG, WEBP)</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text small">
                                        Photographs help municipal field crews locate and assess the required tools and materials accurately.
                                    </div>
                                    <div id="photoPreviewContainer"></div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <h6 class="fw-bold text-dark small mb-1"><i class="bi bi-info-circle text-primary me-1"></i> Best Practices for Photos:</h6>
                                        <ul class="text-muted small mb-0 ps-3">
                                            <li>Take photos during daylight where possible.</li>
                                            <li>Include recognizable landmarks (shop boards, pole numbers).</li>
                                            <li>Ensure the file is under 5MB in size.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Geolocation & Ward -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="bi bi-3-circle-fill text-primary me-2"></i> Location & Ward Details
                            </h6>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="ward" class="form-label fw-semibold text-dark small">Municipal Ward / Zone *</label>
                                    <select class="form-select" id="ward" name="ward" required>
                                        <option value="">Select Ward...</option>
                                        <option value="Ward 01 - City Center" <?= (isset($_POST['ward']) && $_POST['ward'] === 'Ward 01 - City Center') ? 'selected' : '' ?>>Ward 01 - City Center</option>
                                        <option value="Ward 04 - Bandra West" <?= (isset($_POST['ward']) && $_POST['ward'] === 'Ward 04 - Bandra West') ? 'selected' : '' ?>>Ward 04 - Bandra West</option>
                                        <option value="Ward 07 - Powai" <?= (isset($_POST['ward']) && $_POST['ward'] === 'Ward 07 - Powai') ? 'selected' : '' ?>>Ward 07 - Powai</option>
                                        <option value="Ward 09 - South Zone" <?= (isset($_POST['ward']) && $_POST['ward'] === 'Ward 09 - South Zone') ? 'selected' : '' ?>>Ward 09 - South Zone</option>
                                        <option value="Ward 12 - Andheri East" <?= (isset($_POST['ward']) && $_POST['ward'] === 'Ward 12 - Andheri East') ? 'selected' : '' ?>>Ward 12 - Andheri East</option>
                                        <option value="Ward 15 - West Zone" <?= (isset($_POST['ward']) && $_POST['ward'] === 'Ward 15 - West Zone') ? 'selected' : '' ?>>Ward 15 - West Zone</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="address" class="form-label fw-semibold text-dark small">Exact Street Address / Landmark *</label>
                                    <input type="text" class="form-control" id="address" name="address" 
                                           placeholder="e.g. Near St. Peter Church, Hill Road" 
                                           value="<?= e($_POST['address'] ?? '') ?>" required>
                                </div>

                                <!-- Leaflet Interactive Location Map -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-semibold text-dark small mb-0">
                                            <i class="bi bi-map-fill text-primary me-1"></i> Interactive Pinpoint Map (Click to place pin or drag marker)
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="detectGpsBtn">
                                            <i class="bi bi-geo-alt me-1"></i> Detect My GPS Location
                                        </button>
                                    </div>

                                    <div id="complaintMap" class="map-container mb-2"></div>

                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Latitude</span>
                                                <input type="text" class="form-control font-monospace" id="latitude" name="latitude" 
                                                       value="<?= e($_POST['latitude'] ?? '19.076000') ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Longitude</span>
                                                <input type="text" class="form-control font-monospace" id="longitude" name="longitude" 
                                                       value="<?= e($_POST['longitude'] ?? '72.877700') ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3 pt-3 border-top">
                            <a href="dashboard.php" class="btn btn-light px-4 py-2 border">Cancel</a>
                            <button type="submit" class="btn btn-primary-civic btn-lg px-5 py-2 fw-bold shadow-sm">
                                <i class="bi bi-send-fill me-2"></i> Submit Complaint Ticket
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize image preview
    initImagePreview('photo', 'photoPreviewContainer');

    // Initialize Leaflet map
    const defaultLat = parseFloat(document.getElementById('latitude')?.value) || 19.0760;
    const defaultLng = parseFloat(document.getElementById('longitude')?.value) || 72.8777;
    initComplaintMap(defaultLat, defaultLng, 13);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
