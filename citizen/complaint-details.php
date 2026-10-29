<?php
/**
 * CivicTrack — Citizen Complaint Details & Feedback Portal
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

// Can lookup by ID or reference number
$complaintId = (int)($_GET['id'] ?? 0);
$refQuery = trim($_GET['ref'] ?? '');

$complaint = null;
$logs = [];
$feedback = null;
$error = '';

try {
    $pdo = getDbConnection();

    if ($complaintId > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.category_name, cat.icon_class, d.dept_name, d.dept_head_email,
                   cit.name as citizen_name, cit.phone as citizen_phone,
                   o.name as officer_name, o.phone as officer_phone
            FROM complaints c
            JOIN users cit ON c.citizen_id = cit.user_id
            JOIN complaint_categories cat ON c.category_id = cat.category_id
            JOIN departments d ON c.dept_id = d.dept_id
            LEFT JOIN users o ON c.assigned_officer_id = o.user_id
            WHERE c.complaint_id = ? AND c.citizen_id = ?
        ");
        $stmt->execute([$complaintId, $userId]);
        $complaint = $stmt->fetch();
    } elseif (!empty($refQuery)) {
        $stmt = $pdo->prepare("
            SELECT c.*, cat.category_name, cat.icon_class, d.dept_name, d.dept_head_email,
                   cit.name as citizen_name, cit.phone as citizen_phone,
                   o.name as officer_name, o.phone as officer_phone
            FROM complaints c
            JOIN users cit ON c.citizen_id = cit.user_id
            JOIN complaint_categories cat ON c.category_id = cat.category_id
            JOIN departments d ON c.dept_id = d.dept_id
            LEFT JOIN users o ON c.assigned_officer_id = o.user_id
            WHERE c.reference_number = ? AND c.citizen_id = ?
        ");
        $stmt->execute([$refQuery, $userId]);
        $complaint = $stmt->fetch();
    }

    if (!$complaint) {
        // Enforce authorization / Not found
        setFlash('error', 'Grievance record not found or you do not have permission to access it.');
        header('Location: my-complaints.php');
        exit;
    }

    $complaintId = (int)$complaint['complaint_id'];

    // Handle Feedback Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
        if (!verifyCsrfToken()) {
            setFlash('error', 'Security token invalid. Please try again.');
        } elseif (!in_array($complaint['status'], ['Resolved', 'Closed'], true)) {
            setFlash('error', 'Feedback can only be provided for resolved or closed grievances.');
        } else {
            $rating = (int)($_POST['rating'] ?? 5);
            $comments = trim($_POST['comments'] ?? '');

            if ($rating < 1 || $rating > 5) {
                setFlash('error', 'Please provide a star rating between 1 and 5.');
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        INSERT INTO feedback (complaint_id, citizen_id, rating, comments)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comments = VALUES(comments)
                    ");
                    $stmt->execute([$complaintId, $userId, $rating, $comments]);

                    logComplaintActivity(
                        $pdo,
                        $complaintId,
                        $userId,
                        'Feedback Provided',
                        $complaint['status'],
                        $complaint['status'],
                        "Citizen submitted {$rating}-star resolution feedback.",
                        null
                    );

                    $pdo->commit();
                    setFlash('success', 'Thank you for your rating! Your feedback helps us improve municipal civic services.');
                    header("Location: complaint-details.php?id={$complaintId}");
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    setFlash('error', 'Failed to save feedback: ' . $e->getMessage());
                }
            }
        }
    }

    // Handle Reopen Grievance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reopen_complaint') {
        if (!verifyCsrfToken()) {
            setFlash('error', 'Security token invalid. Please try again.');
        } elseif (!in_array($complaint['status'], ['Resolved', 'Closed'], true)) {
            setFlash('error', 'Only resolved or closed grievances can be reopened.');
        } else {
            $reopenReason = trim($_POST['reopen_reason'] ?? '');

            if (empty($reopenReason)) {
                setFlash('error', 'Please provide a clear reason for reopening this grievance.');
            } else {
                try {
                    $pdo->beginTransaction();

                    // Update status to Reopened
                    $stmt = $pdo->prepare("UPDATE complaints SET status = 'Reopened', resolved_at = NULL WHERE complaint_id = ?");
                    $stmt->execute([$complaintId]);

                    logComplaintActivity(
                        $pdo,
                        $complaintId,
                        $userId,
                        'Complaint Reopened',
                        $complaint['status'],
                        'Reopened',
                        'Citizen reopened ticket: ' . $reopenReason,
                        null
                    );

                    // Notify assigned officer or department
                    if (!empty($complaint['assigned_officer_id'])) {
                        createNotification(
                            $pdo,
                            (int)$complaint['assigned_officer_id'],
                            $complaintId,
                            'Ticket Reopened by Citizen',
                            "Citizen marked grievance #{$complaint['reference_number']} as unresolved. Reason: {$reopenReason}"
                        );
                    }

                    // Dispatch SMS notification to citizen's registered mobile number
                    $smsResult = sendCaseSmsUpdate($pdo, $complaintId, 'Complaint Reopened', $reopenReason);
                    $smsNotice = ($smsResult['success'] ?? false) 
                        ? " 📱 SMS alert sent to your registered mobile ({$smsResult['masked_phone']})." 
                        : "";

                    $pdo->commit();
                    setFlash('warning', "Grievance ticket has been reopened and escalated back to the department.{$smsNotice}");
                    header("Location: complaint-details.php?id={$complaintId}");
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    setFlash('error', 'Failed to reopen grievance: ' . $e->getMessage());
                }
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

    // Fetch existing feedback
    $fbStmt = $pdo->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
    $fbStmt->execute([$complaintId]);
    $feedback = $fbStmt->fetch();

    // Fetch SMS dispatch history for this grievance
    $smsLogs = getComplaintSmsLogs($pdo, $complaintId);

} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Grievance #' . e($complaint['reference_number']) . ' — CivicTrack';
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
                    <h5 class="fw-bold text-dark mb-0">Ticket #<?= e($complaint['reference_number']) ?></h5>
                    <small class="text-muted">Lodged on <?= formatDateTime($complaint['created_at']) ?></small>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="my-complaints.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Complaints
                </a>
            </div>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <!-- Status Header Banner -->
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
                                        <i class="bi bi-alarm-fill me-1"></i> Overdue
                                    </span>
                                <?php endif; ?>
                            </div>
                            <h4 class="fw-bold text-dark mb-1"><?= e($complaint['title']) ?></h4>
                            <div class="small text-muted">
                                <i class="bi bi-building me-1 text-primary"></i> <?= e($complaint['dept_name']) ?> &bull;
                                <i class="bi bi-geo-alt-fill me-1 text-danger"></i> <?= e($complaint['ward']) ?>
                            </div>
                        </div>

                        <div class="text-md-end">
                            <small class="text-muted d-block">SLA Resolution Target:</small>
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

                <!-- Workflow Step Indicator -->
                <?php
                $statusOrder = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved'];
                $currIdx = array_search($complaint['status'], $statusOrder);
                if ($currIdx === false) $currIdx = 0;
                ?>
                <div class="card-footer bg-light p-3">
                    <div class="row g-2 text-center">
                        <?php foreach ($statusOrder as $i => $st): ?>
                            <?php
                            $isDone = $i < $currIdx;
                            $isCur = $i === $currIdx;
                            ?>
                            <div class="col">
                                <div class="p-2 rounded border <?= $isCur ? 'bg-primary text-white fw-bold' : ($isDone ? 'bg-success-subtle text-success fw-semibold' : 'bg-white text-muted') ?>">
                                    <div class="small mb-1">
                                        <?php if ($isDone): ?><i class="bi bi-check-circle-fill"></i>
                                        <?php elseif ($isCur): ?><i class="bi bi-arrow-right-circle-fill"></i>
                                        <?php else: ?><i class="bi bi-circle"></i><?php endif; ?>
                                    </div>
                                    <span style="font-size:0.8rem;"><?= $st ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Left Column: Grievance Details & Evidence -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-card-text me-2 text-primary"></i> Grievance Description</h6>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-dark mb-4" style="line-height:1.7;"><?= nl2br(e($complaint['description'])) ?></p>

                            <div class="p-3 bg-light rounded-3 mb-4">
                                <div class="small text-muted mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Location Details:</div>
                                <div class="fw-bold text-dark"><?= e($complaint['address']) ?></div>
                                <?php if (!empty($complaint['ward'])): ?>
                                    <div class="small text-muted mt-1">Ward: <strong><?= e($complaint['ward']) ?></strong></div>
                                <?php endif; ?>
                                <?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
                                    <div class="small text-muted mt-1 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <span>Coordinates: <code><?= e($complaint['latitude']) ?>, <?= e($complaint['longitude']) ?></code></span>
                                        <a href="https://www.google.com/maps?q=<?= urlencode($complaint['latitude'] . ',' . $complaint['longitude']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.78rem;">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Google Maps
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Photographic Evidence Display -->
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Submitted Photo Evidence:</label>
                                    <?php if (!empty($complaint['image_url']) && file_exists(ROOT_PATH . '/' . $complaint['image_url'])): ?>
                                        <div class="border rounded-3 p-1 bg-light">
                                            <img src="<?= $baseUrl . e($complaint['image_url']) ?>" alt="Evidence" class="img-fluid rounded-2 w-100" style="max-height:220px; object-fit:cover;">
                                        </div>
                                    <?php else: ?>
                                        <div class="border rounded-3 p-4 text-center bg-light text-muted small">
                                            <i class="bi bi-image fs-3 d-block mb-1"></i>
                                            No photo was uploaded
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Official Resolution Proof:</label>
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
                                            <img src="<?= $baseUrl . e($proofImg) ?>" alt="Resolution Proof" class="img-fluid rounded-2 w-100" style="max-height:220px; object-fit:cover;">
                                            <div class="text-center small text-success fw-bold py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i> Verified Repair Photo
                                            </div>
                                        </div>
                                    <?php elseif ($complaint['status'] === 'Resolved'): ?>
                                        <div class="border rounded-3 p-4 text-center bg-success-subtle text-success small">
                                            <i class="bi bi-patch-check-fill fs-3 d-block mb-1"></i>
                                            Resolved by Municipal Team
                                        </div>
                                    <?php else: ?>
                                        <div class="border rounded-3 p-4 text-center bg-light text-muted small">
                                            <i class="bi bi-clock-history fs-3 d-block mb-1"></i>
                                            Pending repair completion
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Citizen Feedback / Reopen Section -->
                    <?php if ($complaint['status'] === 'Resolved'): ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                            <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="bi bi-star-fill text-warning me-2"></i> Citizen Resolution Feedback
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reopenModal">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Not Resolved? Reopen
                                </button>
                            </div>

                            <div class="card-body p-4">
                                <?php if ($feedback): ?>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-bold text-dark">Your Rating:</span>
                                        <div>
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                                <i class="bi bi-star-fill <?= ($s <= $feedback['rating']) ? 'text-warning' : 'text-muted opacity-25' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted">(Submitted on <?= formatDateTime($feedback['created_at']) ?>)</small>
                                    </div>
                                    <p class="text-dark bg-light p-3 rounded-3 fst-italic mb-0">
                                        "<?= e($feedback['comments']) ?>"
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted small mb-3">
                                        The department has marked this civic issue as resolved. Please rate the quality of service provided:
                                    </p>

                                    <form method="POST" action="complaint-details.php?id=<?= $complaintId ?>">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="submit_feedback">
                                        <input type="hidden" name="rating" id="ratingInput" value="5">

                                        <div class="mb-3">
                                            <label class="form-label small fw-bold text-dark mb-1">Satisfaction Rating:</label>
                                            <div class="star-rating d-block mb-1" id="starRatingWidget">
                                                <i class="bi bi-star-fill star text-warning" data-value="1"></i>
                                                <i class="bi bi-star-fill star text-warning" data-value="2"></i>
                                                <i class="bi bi-star-fill star text-warning" data-value="3"></i>
                                                <i class="bi bi-star-fill star text-warning" data-value="4"></i>
                                                <i class="bi bi-star-fill star text-warning" data-value="5"></i>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="comments" class="form-label small fw-bold text-dark">Remarks / Comments (Optional):</label>
                                            <textarea class="form-control" id="comments" name="comments" rows="2" 
                                                      placeholder="How satisfied are you with the resolution?"></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-teal-civic btn-sm px-4">
                                            <i class="bi bi-send-check me-1"></i> Submit Citizen Feedback
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Audit Trail Timeline -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-clock-history me-2 text-primary"></i> Status Progression Log
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
                                                    Shift to <strong><?= e($log['new_status']) ?></strong> &bull; <?= e($log['acting_user_name'] ?? 'System') ?>
                                                </div>
                                                <?php if (!empty($log['remarks'])): ?>
                                                    <div class="small text-secondary bg-white p-2 rounded border">
                                                        <?= nl2br(e($log['remarks'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted small">
                                    No status changes recorded yet.
                                </div>
                            <?php endif; ?>
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
                                    <small class="text-muted d-block">Alerts Delivered To:</small>
                                    <span class="fw-bold text-dark font-monospace">
                                        <i class="bi bi-phone me-1 text-primary"></i><?= e(maskPhoneNumber($complaint['citizen_phone'] ?? '')) ?>
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
                                                        <i class="bi bi-check2-all me-1"></i>Delivered to <?= e(maskPhoneNumber($sms['recipient_phone'])) ?>
                                                    </span>
                                                <?php elseif ($sms['status'] === 'Failed'): ?>
                                                    <span class="text-danger fw-semibold" title="<?= e($sms['gateway_response'] ?? '') ?>">
                                                        <i class="bi bi-exclamation-circle-fill me-1"></i><?= e($sms['gateway_response'] ?? 'Gateway Notice') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-secondary fw-semibold">
                                                        <i class="bi bi-arrow-repeat me-1"></i><?= e($sms['status']) ?> &bull; <?= e(maskPhoneNumber($sms['recipient_phone'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="text-muted"><?= date('d M, h:i A', strtotime($sms['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3 text-muted small">
                                    <i class="bi bi-chat-dots fs-3 d-block mb-1 text-secondary opacity-50"></i>
                                    All status updates (Assignment, In Progress, Resolution) are automatically dispatched via SMS to your registered mobile number <strong><?= e(maskPhoneNumber($complaint['citizen_phone'] ?? '')) ?></strong>.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
        </main>
    </div>
</div>

<!-- Reopen Modal -->
<div class="modal fade" id="reopenModal" tabindex="-1" aria-labelledby="reopenModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="reopenModalLabel">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reopen Grievance Ticket
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="complaint-details.php?id=<?= $complaintId ?>">
                <div class="modal-body p-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reopen_complaint">

                    <p class="text-muted small mb-3">
                        If the issue has not been satisfactorily repaired or has reoccurred, please describe what remains unfinished. The ticket will be returned to the department queue.
                    </p>

                    <div class="mb-3">
                        <label for="reopen_reason" class="form-label fw-bold small text-dark">Reason for Reopening *</label>
                        <textarea class="form-control" id="reopen_reason" name="reopen_reason" rows="3" 
                                  placeholder="e.g. Only half the pothole was filled; the drainage is still overflowing..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">
                        <i class="bi bi-send-exclamation me-1"></i> Confirm & Reopen Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initStarRating('starRatingWidget', 'ratingInput');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
