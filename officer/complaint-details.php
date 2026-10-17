<?php
/**
 * CivicTrack — Department Officer Complaint Management & Resolution
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('officer');

$userId = getUserId();
$userName = getUserName();
$officerDeptId = getUserDeptId();
$baseUrl = getBaseUrl();

$complaintId = (int)($_GET['id'] ?? 0);
$complaint = null;
$logs = [];
$feedback = null;
$errors = [];

try {
    $pdo = getDbConnection();

    // Query complaint details ensuring it belongs to officer's department
    $stmt = $pdo->prepare("
        SELECT c.*, cat.category_name, cat.icon_class, d.dept_name,
               u.name as citizen_name, u.email as citizen_email, u.phone as citizen_phone,
               o.name as officer_name
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        JOIN departments d ON c.dept_id = d.dept_id
        JOIN users u ON c.citizen_id = u.user_id
        LEFT JOIN users o ON c.assigned_officer_id = o.user_id
        WHERE c.complaint_id = ?
    ");
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        setFlash('error', 'Grievance ticket not found.');
        header('Location: assigned-complaints.php');
        exit;
    }

    // Enforce Department Permission
    if ((int)$complaint['dept_id'] !== (int)$officerDeptId) {
        http_response_code(403);
        die("
        <div style='font-family:sans-serif; max-width:550px; margin:50px auto; padding:25px; border:1px solid #f87171; border-radius:12px; background:#fff5f5; text-align:center;'>
            <h3 style='color:#dc2626;'>403 Department Access Denied</h3>
            <p>This complaint is assigned to <strong>" . e($complaint['dept_name']) . "</strong>. You can only manage tickets assigned to your department.</p>
            <a href='assigned-complaints.php' style='color:#1d4ed8;'>&larr; Return to Assigned Queue</a>
        </div>
        ");
    }

    // Process Status Update Action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
        if (!verifyCsrfToken()) {
            $errors[] = 'Security token invalid or expired. Please try again.';
        } else {
            $newStatus = trim($_POST['new_status'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $actionLabel = trim($_POST['action_label'] ?? 'Status Update');

            $allowedStatuses = ['Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed'];
            if (!in_array($newStatus, $allowedStatuses, true)) {
                $errors[] = 'Invalid status selected.';
            }

            if (empty($remarks)) {
                $errors[] = 'Please provide officer progress remarks or resolution notes.';
            }

            // Handle resolution proof upload
            $proofImagePath = null;
            if (isset($_FILES['proof_photo']) && $_FILES['proof_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $proofImagePath = handleFileUpload($_FILES['proof_photo'], 'resolutions');
                } catch (Exception $e) {
                    $errors[] = 'Proof photo upload error: ' . $e->getMessage();
                }
            }

            // If marking Resolved, proof photo is strongly recommended
            if ($newStatus === 'Resolved' && empty($proofImagePath)) {
                // Check if an earlier proof exists in logs
                $hasPriorProof = false;
                $chkStmt = $pdo->prepare("SELECT COUNT(*) FROM complaint_logs WHERE complaint_id = ? AND proof_image IS NOT NULL");
                $chkStmt->execute([$complaintId]);
                if ($chkStmt->fetchColumn() > 0) {
                    $hasPriorProof = true;
                }
            }

            if (empty($errors)) {
                $prevStatus = $complaint['status'];
                $resolvedAtSql = ($newStatus === 'Resolved') ? "NOW()" : (($prevStatus === 'Resolved') ? "NULL" : "resolved_at");

                // Update complaint
                $updateStmt = $pdo->prepare("
                    UPDATE complaints 
                    SET status = ?, assigned_officer_id = ?, resolved_at = " . (($newStatus === 'Resolved') ? "NOW()" : "resolved_at") . "
                    WHERE complaint_id = ?
                ");
                $updateStmt->execute([$newStatus, $userId, $complaintId]);

                // Insert into complaint_logs
                logComplaintActivity(
                    $pdo,
                    $complaintId,
                    $userId,
                    $actionLabel,
                    $prevStatus,
                    $newStatus,
                    $remarks,
                    $proofImagePath
                );

                // Send notification to Citizen
                createNotification(
                    $pdo,
                    (int)$complaint['citizen_id'],
                    $complaintId,
                    "Ticket Status Updated: {$newStatus}",
                    "Your grievance #{$complaint['reference_number']} status was updated to '{$newStatus}'. Notes: {$remarks}"
                );

                setFlash('success', "Grievance status successfully updated to '{$newStatus}'.");
                header("Location: complaint-details.php?id={$complaintId}");
                exit;
            }
        }
    }

    // Fetch activity logs
    $logStmt = $pdo->prepare("
        SELECT l.*, u.name as acting_user_name, u.role as acting_role
        FROM complaint_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        WHERE l.complaint_id = ?
        ORDER BY l.created_at ASC
    ");
    $logStmt->execute([$complaintId]);
    $logs = $logStmt->fetchAll();

    // Fetch feedback
    $fbStmt = $pdo->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
    $fbStmt->execute([$complaintId]);
    $feedback = $fbStmt->fetch();

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

$pageTitle = 'Manage Ticket #' . e($complaint['reference_number']) . ' — CivicTrack';
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
                    <h5 class="fw-bold text-dark mb-0">Manage Grievance #<?= e($complaint['reference_number']) ?></h5>
                    <small class="text-muted">Assigned to: <?= e($complaint['dept_name']) ?></small>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="assigned-complaints.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Queue
                </a>
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

            <!-- Header Status Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
                <div class="card-body p-4 border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="fs-4 fw-bold font-monospace text-dark"><?= e($complaint['reference_number']) ?></span>
                                <?= getStatusBadge($complaint['status']) ?>
                                <?= getPriorityBadge($complaint['priority']) ?>
                                <?php if (isComplaintOverdue($complaint)): ?>
                                    <span class="badge bg-danger text-white px-2 py-1">
                                        <i class="bi bi-alarm-fill me-1"></i> SLA Breached
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h4 class="fw-bold text-dark mb-1"><?= e($complaint['title']) ?></h4>
                            <div class="small text-muted">
                                <i class="bi bi-person-fill text-primary me-1"></i> Citizen: <strong><?= e($complaint['citizen_name']) ?></strong> (<?= e($complaint['citizen_phone']) ?>) &bull;
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($complaint['ward']) ?>
                            </div>
                        </div>

                        <div class="text-md-end">
                            <small class="text-muted d-block">Committed SLA Deadline:</small>
                            <span class="fw-bold fs-6 <?= isComplaintOverdue($complaint) ? 'text-danger' : 'text-primary' ?>">
                                <i class="bi bi-clock-history me-1"></i> <?= formatDateTime($complaint['sla_deadline']) ?>
                            </span>
                            <?php if ($complaint['status'] === 'Resolved' && $complaint['resolved_at']): ?>
                                <div class="text-success small fw-bold mt-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Resolved: <?= formatDateTime($complaint['resolved_at']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column: Action Form & Status Transition -->
                <div class="col-lg-6">
                    <!-- Action Form Card -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                        <div class="card-header bg-dark text-white p-3 rounded-top-4">
                            <h6 class="fw-bold mb-0"><i class="bi bi-tools me-2 text-info"></i> Update Status & Field Remarks</h6>
                        </div>

                        <div class="card-body p-4">
                            <form method="POST" action="complaint-details.php?id=<?= $complaintId ?>" enctype="multipart/form-data">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update_status">

                                <div class="mb-3">
                                    <label for="new_status" class="form-label fw-bold text-dark small">New Progress Status *</label>
                                    <select class="form-select" id="new_status" name="new_status" required>
                                        <option value="Under Review" <?= ($complaint['status'] === 'Under Review') ? 'selected' : '' ?>>Under Review (Site Verification)</option>
                                        <option value="Assigned" <?= ($complaint['status'] === 'Assigned') ? 'selected' : '' ?>>Assigned (Crew Allocated)</option>
                                        <option value="In Progress" <?= ($complaint['status'] === 'In Progress') ? 'selected' : '' ?>>In Progress (Work in Motion)</option>
                                        <option value="Resolved" <?= ($complaint['status'] === 'Resolved') ? 'selected' : '' ?>>Resolved (Repair Completed)</option>
                                        <option value="Closed" <?= ($complaint['status'] === 'Closed') ? 'selected' : '' ?>>Closed (Administrative Closure)</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="action_label" class="form-label fw-bold text-dark small">Action Headline / Stage</label>
                                    <input type="text" class="form-control" id="action_label" name="action_label" 
                                           placeholder="e.g. Field Inspection Completed / Repair Mix Dispatched" value="Status Update">
                                </div>

                                <div class="mb-3">
                                    <label for="remarks" class="form-label fw-bold text-dark small">Officer Remarks & Progress Notes *</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="4" 
                                              placeholder="Provide detailed ground notes, contractor crew details, or resolution description..." required></textarea>
                                    <div class="form-text small">
                                        These notes are visible to the citizen and recorded in the permanent audit trail.
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="proof_photo" class="form-label fw-bold text-dark small">
                                        Upload After-Repair Resolution Proof Photo (JPG, PNG, WEBP)
                                    </label>
                                    <input type="file" class="form-control" id="proof_photo" name="proof_photo" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text small">
                                        Attach a photograph showing the rectified road, cleared garbage, or fixed streetlight as proof.
                                    </div>
                                    <div id="proofPreviewContainer"></div>
                                </div>

                                <button type="submit" class="btn btn-primary-civic w-100 py-2 fw-bold shadow-sm">
                                    <i class="bi bi-check-circle-fill me-2"></i> Save Status & Notify Citizen
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Citizen Grievance Description Card -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i> Citizen Grievance Summary</h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-dark mb-3" style="line-height:1.7;"><?= nl2br(e($complaint['description'])) ?></p>

                            <div class="p-3 bg-light rounded-3 mb-3">
                                <div class="small text-muted mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Location:</div>
                                <div class="fw-bold text-dark"><?= e($complaint['address']) ?></div>
                                <div class="small text-muted">Ward: <strong><?= e($complaint['ward']) ?></strong></div>
                                <?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
                                    <div class="small text-muted mt-1">
                                        GPS: <code><?= e($complaint['latitude']) ?>, <?= e($complaint['longitude']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Citizen Evidence Photo:</label>
                                    <?php if (!empty($complaint['image_url']) && file_exists(ROOT_PATH . '/' . $complaint['image_url'])): ?>
                                        <div class="border rounded-3 p-1 bg-light">
                                            <img src="<?= $baseUrl . e($complaint['image_url']) ?>" alt="Evidence" class="img-fluid rounded-2 w-100" style="max-height:200px; object-fit:cover;">
                                        </div>
                                    <?php else: ?>
                                        <div class="border rounded-3 p-3 text-center bg-light text-muted small">
                                            No photo provided by citizen
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Latest Resolution Photo:</label>
                                    <?php
                                    $proofImg = null;
                                    foreach ($logs as $l) {
                                        if (!empty($l['proof_image'])) {
                                            $proofImg = $l['proof_image'];
                                        }
                                    }
                                    ?>
                                    <?php if ($proofImg && file_exists(ROOT_PATH . '/' . $proofImg)): ?>
                                        <div class="border border-success rounded-3 p-1 bg-success-subtle bg-opacity-25">
                                            <img src="<?= $baseUrl . e($proofImg) ?>" alt="Resolution" class="img-fluid rounded-2 w-100" style="max-height:200px; object-fit:cover;">
                                        </div>
                                    <?php else: ?>
                                        <div class="border rounded-3 p-3 text-center bg-light text-muted small">
                                            No resolution photo attached yet
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Audit Trail & Citizen Feedback -->
                <div class="col-lg-6">
                    <!-- Feedback Card if Rated -->
                    <?php if ($feedback): ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                            <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-star-fill text-warning me-2"></i> Citizen Resolution Rating</h6>
                                <div>
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="bi bi-star-fill <?= ($s <= $feedback['rating']) ? 'text-warning' : 'text-muted opacity-25' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <p class="text-dark mb-1 fst-italic">"<?= e($feedback['comments']) ?>"</p>
                                <small class="text-muted">Submitted on <?= formatDateTime($feedback['created_at']) ?></small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Activity Log Timeline -->
                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-journal-text me-2 text-primary"></i> Grievance Audit History
                            </h6>
                            <span class="badge bg-light text-dark border"><?= count($logs) ?> Events</span>
                        </div>

                        <div class="card-body p-3">
                            <?php if (!empty($logs)): ?>
                                <div class="timeline-civic">
                                    <?php foreach ($logs as $log): ?>
                                        <div class="timeline-step">
                                            <div class="timeline-dot completed"></div>
                                            <div class="timeline-content">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="fw-bold text-dark small"><?= e($log['action']) ?></span>
                                                    <span class="timeline-time"><?= timeAgo($log['created_at']) ?></span>
                                                </div>
                                                <div class="small text-muted mb-2">
                                                    Shifted to <strong><?= e($log['new_status']) ?></strong> &bull; By <?= e($log['acting_user_name'] ?? 'System') ?> (<?= ucfirst(e($log['acting_role'] ?? '')) ?>)
                                                </div>
                                                <?php if (!empty($log['remarks'])): ?>
                                                    <div class="small text-secondary bg-white p-2 rounded border mb-2">
                                                        <?= nl2br(e($log['remarks'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($log['proof_image']) && file_exists(ROOT_PATH . '/' . $log['proof_image'])): ?>
                                                    <div class="small text-success fw-semibold">
                                                        <i class="bi bi-paperclip me-1"></i> Attached Proof Photo:
                                                        <a href="<?= $baseUrl . e($log['proof_image']) ?>" target="_blank" class="ms-1">View Image</a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted small">
                                    No history recorded yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initImagePreview('proof_photo', 'proofPreviewContainer');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
