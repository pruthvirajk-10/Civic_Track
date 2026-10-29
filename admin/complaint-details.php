<?php
/**
 * CivicTrack — Administrator Complaint Details & Executive Override
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$userId = getUserId();
$baseUrl = getBaseUrl();
$complaintId = (int)($_GET['id'] ?? 0);

$complaint = null;
$departments = [];
$officers = [];
$logs = [];
$feedback = null;
$errors = [];

try {
    $pdo = getDbConnection();

    // Fetch complaint
    $stmt = $pdo->prepare("
        SELECT c.*, cat.category_name, cat.icon_class, d.dept_name,
               u.name as citizen_name, u.email as citizen_email, u.phone as citizen_phone,
               o.name as officer_name, o.email as officer_email
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
        header('Location: complaints.php');
        exit;
    }

    // Departments for reassignment
    $departments = $pdo->query("SELECT dept_id, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetchAll();

    // Department officers in the assigned department
    $offStmt = $pdo->prepare("SELECT user_id, name FROM users WHERE dept_id = ? AND role = 'officer' AND status = 'active' ORDER BY name ASC");
    $offStmt->execute([$complaint['dept_id']]);
    $officers = $offStmt->fetchAll();

    // Process Admin Update / Reassignment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_override') {
        if (!verifyCsrfToken()) {
            $errors[] = 'Security token invalid. Please try again.';
        } else {
            $newDeptId = (int)($_POST['dept_id'] ?? $complaint['dept_id']);
            $newOfficerId = !empty($_POST['officer_id']) ? (int)$_POST['officer_id'] : null;
            $newStatus = trim($_POST['status'] ?? $complaint['status']);
            $newPriority = trim($_POST['priority'] ?? $complaint['priority']);
            $adminRemarks = trim($_POST['admin_remarks'] ?? '');

            if (empty($adminRemarks)) {
                $errors[] = 'Please provide an administrative note explaining this adjustment.';
            }

            if (empty($errors)) {
                $prevStatus = $complaint['status'];
                $deptChanged = ($newDeptId !== (int)$complaint['dept_id']);

                $updateStmt = $pdo->prepare("
                    UPDATE complaints 
                    SET dept_id = ?, assigned_officer_id = ?, status = ?, priority = ?,
                        resolved_at = " . (($newStatus === 'Resolved') ? "NOW()" : (($prevStatus === 'Resolved') ? "NULL" : "resolved_at")) . "
                    WHERE complaint_id = ?
                ");
                $updateStmt->execute([$newDeptId, $newOfficerId, $newStatus, $newPriority, $complaintId]);

                $actionTitle = $deptChanged ? 'Department Reassignment' : 'Administrative Override';

                // Log entry
                logComplaintActivity(
                    $pdo,
                    $complaintId,
                    $userId,
                    $actionTitle,
                    $prevStatus,
                    $newStatus,
                    'Admin Directive: ' . $adminRemarks,
                    null
                );

                // Notify citizen
                createNotification(
                    $pdo,
                    (int)$complaint['citizen_id'],
                    $complaintId,
                    'Administrative Update on Grievance',
                    "Municipal administration updated ticket #{$complaint['reference_number']}. Status: {$newStatus}."
                );

                // If assigned to a specific officer, notify them
                if ($newOfficerId) {
                    createNotification(
                        $pdo,
                        $newOfficerId,
                        $complaintId,
                        'Ticket Assigned by Admin',
                        "Administrator assigned ticket #{$complaint['reference_number']} to your queue."
                    );
                }

                // Dispatch SMS notification to Citizen's registered mobile number
                $smsResult = sendCaseSmsUpdate($pdo, $complaintId, 'Administrative Override', $adminRemarks);
                $smsNotice = ($smsResult['success'] ?? false) 
                    ? " 📱 SMS alert dispatched to citizen's registered mobile ({$smsResult['masked_phone']})." 
                    : "";

                setFlash('success', "Complaint parameters updated successfully by Municipal Administration.{$smsNotice}");
                header("Location: complaint-details.php?id={$complaintId}");
                exit;
            }
        }
    }

    // Activity logs
    $logStmt = $pdo->prepare("
        SELECT l.*, u.name as acting_user_name, u.role as acting_role
        FROM complaint_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        WHERE l.complaint_id = ?
        ORDER BY l.created_at ASC
    ");
    $logStmt->execute([$complaintId]);
    $logs = $logStmt->fetchAll();

    // SMS notification history dispatched to citizen's registered mobile
    $smsLogs = getComplaintSmsLogs($pdo, $complaintId);

    // Feedback
    $fbStmt = $pdo->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
    $fbStmt->execute([$complaintId]);
    $feedback = $fbStmt->fetch();

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

$pageTitle = 'Admin Manage #' . e($complaint['reference_number']) . ' — CivicTrack';
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
                    <h5 class="fw-bold text-dark mb-0">Executive Grievance Control</h5>
                    <small class="text-muted">Ticket: <code><?= e($complaint['reference_number']) ?></code></small>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="complaints.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Complaints
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

            <!-- Ticket Banner -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
                <div class="card-body p-4 border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="fs-4 fw-bold font-monospace text-dark"><?= e($complaint['reference_number']) ?></span>
                                <?= getStatusBadge($complaint['status']) ?>
                                <?= getPriorityBadge($complaint['priority']) ?>
                                <?php if (isComplaintOverdue($complaint)): ?>
                                    <span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-alarm-fill me-1"></i> Overdue</span>
                                <?php endif; ?>
                            </div>
                            <h4 class="fw-bold text-dark mb-1"><?= e($complaint['title']) ?></h4>
                            <div class="small text-muted">
                                Citizen: <strong><?= e($complaint['citizen_name']) ?></strong> (<?= e($complaint['citizen_email']) ?> &bull; <?= e($complaint['citizen_phone']) ?>) &bull;
                                Ward: <strong><?= e($complaint['ward']) ?></strong>
                            </div>
                        </div>

                        <div class="text-md-end">
                            <small class="text-muted d-block">SLA Target:</small>
                            <span class="fw-bold fs-6 <?= isComplaintOverdue($complaint) ? 'text-danger' : 'text-primary' ?>">
                                <?= formatDateTime($complaint['sla_deadline']) ?>
                            </span>
                            <?php if ($complaint['status'] === 'Resolved' && $complaint['resolved_at']): ?>
                                <div class="text-success small fw-bold mt-1">
                                    Resolved on <?= formatDateTime($complaint['resolved_at']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Administrative Control Override Panel -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                        <div class="card-header bg-dark text-white p-3 rounded-top-4">
                            <h6 class="fw-bold mb-0"><i class="bi bi-sliders me-2 text-info"></i> Municipal Override & Reassignment</h6>
                        </div>

                        <div class="card-body p-4">
                            <form method="POST" action="complaint-details.php?id=<?= $complaintId ?>">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="admin_override">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="dept_id" class="form-label small fw-bold text-dark">Assigned Department</label>
                                        <select class="form-select form-select-sm" id="dept_id" name="dept_id" required>
                                            <?php foreach ($departments as $d): ?>
                                                <option value="<?= $d['dept_id'] ?>" <?= ($complaint['dept_id'] == $d['dept_id']) ? 'selected' : '' ?>>
                                                    <?= e($d['dept_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="officer_id" class="form-label small fw-bold text-dark">Assign Specific Officer</label>
                                        <select class="form-select form-select-sm" id="officer_id" name="officer_id">
                                            <option value="">-- Unassigned (Department Queue) --</option>
                                            <?php foreach ($officers as $off): ?>
                                                <option value="<?= $off['user_id'] ?>" <?= ($complaint['assigned_officer_id'] == $off['user_id']) ? 'selected' : '' ?>>
                                                    <?= e($off['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="status" class="form-label small fw-bold text-dark">Lifecycle Status</label>
                                        <select class="form-select form-select-sm" id="status" name="status" required>
                                            <option value="Submitted" <?= ($complaint['status'] === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
                                            <option value="Under Review" <?= ($complaint['status'] === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
                                            <option value="Assigned" <?= ($complaint['status'] === 'Assigned') ? 'selected' : '' ?>>Assigned</option>
                                            <option value="In Progress" <?= ($complaint['status'] === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                                            <option value="Resolved" <?= ($complaint['status'] === 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                                            <option value="Closed" <?= ($complaint['status'] === 'Closed') ? 'selected' : '' ?>>Closed</option>
                                            <option value="Reopened" <?= ($complaint['status'] === 'Reopened') ? 'selected' : '' ?>>Reopened</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="priority" class="form-label small fw-bold text-dark">Escalation Priority</label>
                                        <select class="form-select form-select-sm" id="priority" name="priority" required>
                                            <option value="Low" <?= ($complaint['priority'] === 'Low') ? 'selected' : '' ?>>Low</option>
                                            <option value="Medium" <?= ($complaint['priority'] === 'Medium') ? 'selected' : '' ?>>Medium</option>
                                            <option value="High" <?= ($complaint['priority'] === 'High') ? 'selected' : '' ?>>High</option>
                                            <option value="Urgent" <?= ($complaint['priority'] === 'Urgent') ? 'selected' : '' ?>>Urgent</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label for="admin_remarks" class="form-label small fw-bold text-dark">Administrative Instruction / Reason *</label>
                                        <textarea class="form-control form-control-sm" id="admin_remarks" name="admin_remarks" rows="3" 
                                                  placeholder="State directive (e.g. Reassigned to Roads after water line test; escalated to emergency priority)" required></textarea>
                                    </div>
                                </div>

                                <div class="p-2 mt-3 rounded-2 bg-light border d-flex align-items-center justify-content-between small">
                                    <span class="text-muted">
                                        <i class="bi bi-phone-vibrate text-primary me-1"></i> Citizen SMS Alert:
                                    </span>
                                    <span class="fw-semibold text-dark font-monospace">
                                        <?= e($complaint['citizen_phone'] ?? 'No phone') ?> (Auto-Notified)
                                    </span>
                                </div>

                                <button type="submit" class="btn btn-primary-civic btn-sm px-4 mt-3">
                                    <i class="bi bi-check2-circle me-1"></i> Apply Directive
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Details Card -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i> Report Details</h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-dark mb-3"><?= nl2br(e($complaint['description'])) ?></p>
                            <div class="p-3 bg-light rounded-3 mb-3 small">
                                <div><strong>Address:</strong> <?= e($complaint['address']) ?></div>
                                <?php if (!empty($complaint['ward'])): ?>
                                    <div><strong>Ward:</strong> <?= e($complaint['ward']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($complaint['latitude'])): ?>
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-1">
                                        <span><strong>GPS:</strong> <code><?= e($complaint['latitude']) ?>, <?= e($complaint['longitude']) ?></code></span>
                                        <a href="https://www.google.com/maps?q=<?= urlencode($complaint['latitude'] . ',' . $complaint['longitude']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.78rem;">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Google Maps
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Citizen Photo:</label>
                                    <?php if (!empty($complaint['image_url']) && file_exists(ROOT_PATH . '/' . $complaint['image_url'])): ?>
                                        <img src="<?= $baseUrl . e($complaint['image_url']) ?>" alt="Evidence" class="img-fluid rounded border w-100" style="max-height:180px; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="p-3 bg-light border text-center text-muted small">No photo</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Resolution Photo:</label>
                                    <?php
                                    $proofImg = null;
                                    foreach ($logs as $l) {
                                        if (!empty($l['proof_image'])) $proofImg = $l['proof_image'];
                                    }
                                    ?>
                                    <?php if ($proofImg && file_exists(ROOT_PATH . '/' . $proofImg)): ?>
                                        <img src="<?= $baseUrl . e($proofImg) ?>" alt="Resolution" class="img-fluid rounded border border-success w-100" style="max-height:180px; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="p-3 bg-light border text-center text-muted small">No proof uploaded</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Audit Trail & Feedback -->
                <div class="col-lg-6">
                    <?php if ($feedback): ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                            <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-star-fill text-warning me-2"></i> Citizen Rating</h6>
                                <div>
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="bi bi-star-fill <?= ($s <= $feedback['rating']) ? 'text-warning' : 'text-muted opacity-25' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <p class="text-dark fst-italic mb-1">"<?= e($feedback['comments']) ?>"</p>
                                <small class="text-muted">Recorded on <?= formatDateTime($feedback['created_at']) ?></small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-journal-text me-2 text-primary"></i> Full Audit Trail</h6>
                            <span class="badge bg-light text-dark border"><?= count($logs) ?> Events</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="timeline-civic">
                                <?php foreach ($logs as $l): ?>
                                    <div class="timeline-step">
                                        <div class="timeline-dot completed"></div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold text-dark small"><?= e($l['action']) ?></span>
                                                <span class="timeline-time"><?= timeAgo($l['created_at']) ?></span>
                                            </div>
                                            <div class="small text-muted mb-1">
                                                Status: <strong><?= e($l['new_status']) ?></strong> &bull; By <?= e($l['acting_user_name'] ?? 'System') ?> (<?= ucfirst(e($l['acting_role'] ?? '')) ?>)
                                            </div>
                                            <?php if (!empty($l['remarks'])): ?>
                                                <div class="small text-secondary bg-white p-2 rounded border mb-1">
                                                    <?= nl2br(e($l['remarks'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Registered Mobile SMS Updates Card -->
                    <div class="card border-0 shadow-sm rounded-4 bg-white mt-4">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-phone-vibrate-fill me-2 text-success"></i> Registered Mobile SMS Alerts
                            </h6>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                <i class="bi bi-shield-check me-1"></i> SMS Active
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3 mb-3 border">
                                <div>
                                    <small class="text-muted d-block">Citizen Registered Mobile:</small>
                                    <span class="fw-bold text-dark font-monospace">
                                        <i class="bi bi-phone me-1 text-primary"></i><?= e($complaint['citizen_phone'] ?? 'N/A') ?>
                                    </span>
                                </div>
                                <span class="badge bg-dark"><?= count($smsLogs) ?> Sent</span>
                            </div>

                            <?php if (!empty($smsLogs)): ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($smsLogs as $sms): ?>
                                        <div class="p-3 rounded-3 border bg-light bg-opacity-50">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                                    <?= e($sms['event_type']) ?>
                                                </span>
                                                <span class="text-muted" style="font-size:0.75rem;">
                                                    <i class="bi bi-clock me-1"></i><?= timeAgo($sms['created_at']) ?>
                                                </span>
                                            </div>
                                            <div class="p-2 bg-white rounded-2 border my-2 text-dark font-monospace" style="font-size:0.83rem; line-height:1.5;">
                                                <i class="bi bi-chat-quote-fill text-muted me-1"></i><?= e($sms['message']) ?>
                                            </div>
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-1" style="font-size:0.75rem;">
                                                <?php if ($sms['status'] === 'Delivered'): ?>
                                                    <span class="text-success fw-semibold">
                                                        <i class="bi bi-check2-all me-1"></i>Delivered to <?= e($sms['recipient_phone']) ?>
                                                    </span>
                                                <?php elseif ($sms['status'] === 'Failed'): ?>
                                                    <span class="text-danger fw-semibold" title="<?= e($sms['gateway_response'] ?? '') ?>">
                                                        <i class="bi bi-exclamation-circle-fill me-1"></i><?= e($sms['gateway_response'] ?? 'Gateway Notice') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-secondary fw-semibold">
                                                        <i class="bi bi-arrow-repeat me-1"></i><?= e($sms['status']) ?> &bull; <?= e($sms['recipient_phone']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="text-muted"><?= date('d M, h:i A', strtotime($sms['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3 text-muted small">
                                    <i class="bi bi-chat-left-dots fs-3 d-block mb-1 opacity-50"></i>
                                    No SMS dispatches recorded yet for this grievance.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
