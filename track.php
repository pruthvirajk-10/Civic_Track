<?php
/**
 * CivicTrack — Public Complaint Tracker
 * Allows citizens and officials to track complaint status via Reference Number
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$refInput = trim($_GET['ref'] ?? '');
$complaint = null;
$logs = [];
$feedback = null;
$error = '';

if (!empty($refInput)) {
    try {
        $pdo = getDbConnection();

        // Fetch complaint details with category and department joins
        $stmt = $pdo->prepare("
            SELECT c.*, cat.category_name, cat.icon_class, d.dept_name, d.dept_head_email,
                   u.name as citizen_name, u.phone as citizen_phone,
                   o.name as officer_name
            FROM complaints c
            JOIN complaint_categories cat ON c.category_id = cat.category_id
            JOIN departments d ON c.dept_id = d.dept_id
            JOIN users u ON c.citizen_id = u.user_id
            LEFT JOIN users o ON c.assigned_officer_id = o.user_id
            WHERE c.reference_number = ?
        ");
        $stmt->execute([$refInput]);
        $complaint = $stmt->fetch();

        if ($complaint) {
            // Fetch status logs
            $logStmt = $pdo->prepare("
                SELECT l.*, u.name as acting_user_name, u.role as acting_role
                FROM complaint_logs l
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE l.complaint_id = ?
                ORDER BY l.created_at ASC
            ");
            $logStmt->execute([$complaint['complaint_id']]);
            $logs = $logStmt->fetchAll();

            // Fetch feedback if any
            $fbStmt = $pdo->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
            $fbStmt->execute([$complaint['complaint_id']]);
            $feedback = $fbStmt->fetch();
        } else {
            $error = "No grievance found with Reference Number: " . e($refInput);
        }
    } catch (Exception $e) {
        $error = "Database query error: " . $e->getMessage();
    }
}

$pageTitle = 'CivicTrack — Track Grievance Status';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="bg-dark text-white py-4 border-bottom border-secondary">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2 class="fw-bold text-white mb-1">
                    <i class="bi bi-search text-info me-2"></i> Track Complaint Status
                </h2>
                <p class="text-white-50 mb-0 small">
                    Enter your unique grievance reference number (e.g., <code>CT-2026-10001</code>) for real-time resolution updates.
                </p>
            </div>
            <div class="col-md-5 mt-3 mt-md-0">
                <form method="GET" action="track.php" class="d-flex gap-2">
                    <input type="text" name="ref" class="form-control form-control-lg bg-light text-dark fw-bold border-0" 
                           placeholder="Enter Reference Number..." value="<?= e($refInput) ?>" required>
                    <button type="submit" class="btn btn-primary-civic btn-lg px-4">
                        Track
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <?php if ($error): ?>
        <div class="alert alert-warning border-warning shadow-sm rounded-3 d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
            <div>
                <h5 class="fw-bold mb-1">Complaint Not Found</h5>
                <p class="mb-0 small"><?= $error ?></p>
            </div>
        </div>
        <div class="text-center py-4">
            <p class="text-muted">Want to test with sample complaint tickets? Click any reference below:</p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a href="track.php?ref=CT-2026-10001" class="btn btn-sm btn-outline-primary">CT-2026-10001 (Resolved Pothole)</a>
                <a href="track.php?ref=CT-2026-10002" class="btn btn-sm btn-outline-primary">CT-2026-10002 (In Progress Garbage)</a>
                <a href="track.php?ref=CT-2026-10003" class="btn btn-sm btn-outline-primary">CT-2026-10003 (Assigned Streetlight)</a>
            </div>
        </div>
    <?php elseif ($complaint): ?>
        <!-- Complaint Status Banner -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="card-body p-4 bg-white border-bottom">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="fs-4 fw-bold text-dark font-monospace"><?= e($complaint['reference_number']) ?></span>
                            <?= getStatusBadge($complaint['status']) ?>
                            <?= getPriorityBadge($complaint['priority']) ?>
                            <?php if (isComplaintOverdue($complaint)): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                    <i class="bi bi-alarm-fill me-1"></i> SLA Overdue
                                </span>
                            <?php endif; ?>
                        </div>
                        <h4 class="fw-bold text-dark mb-1"><?= e($complaint['title']) ?></h4>
                        <div class="text-muted small">
                            <i class="bi bi-building me-1 text-primary"></i> <strong><?= e($complaint['dept_name']) ?></strong> &bull;
                            <i class="bi bi-geo-alt-fill me-1 text-danger"></i> <?= e($complaint['ward']) ?> &bull;
                            <i class="bi bi-calendar-event me-1"></i> Submitted on <?= formatDateTime($complaint['created_at']) ?>
                        </div>
                    </div>

                    <div class="text-md-end">
                        <div class="small text-muted">Committed SLA Deadline:</div>
                        <div class="fw-bold <?= isComplaintOverdue($complaint) ? 'text-danger' : 'text-primary' ?> fs-6">
                            <i class="bi bi-clock-history me-1"></i> <?= formatDateTime($complaint['sla_deadline']) ?>
                        </div>
                        <?php if ($complaint['status'] === 'Resolved' && $complaint['resolved_at']): ?>
                            <div class="small text-success fw-bold mt-1">
                                <i class="bi bi-check-circle-fill me-1"></i> Resolved on <?= formatDateTime($complaint['resolved_at']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Progress Lifecycle Steps -->
            <?php
            $statusOrder = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved'];
            $currentIndex = array_search($complaint['status'], $statusOrder);
            if ($currentIndex === false) $currentIndex = 0;
            ?>
            <div class="card-footer bg-light p-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-diagram-3-fill me-2 text-primary"></i> Current Resolution Progress</h6>
                <div class="row text-center g-2">
                    <?php foreach ($statusOrder as $idx => $st): ?>
                        <?php
                        $isPast = $idx < $currentIndex;
                        $isCurrent = $idx === $currentIndex;
                        ?>
                        <div class="col">
                            <div class="p-2 rounded-3 border <?= $isCurrent ? 'bg-primary text-white fw-bold shadow-sm' : ($isPast ? 'bg-success-subtle text-success border-success-subtle fw-semibold' : 'bg-white text-muted') ?>">
                                <div class="small mb-1">
                                    <?php if ($isPast): ?>
                                        <i class="bi bi-check-circle-fill"></i>
                                    <?php elseif ($isCurrent): ?>
                                        <i class="bi bi-arrow-right-circle-fill"></i>
                                    <?php else: ?>
                                        <i class="bi bi-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <span style="font-size: 0.85rem;"><?= $st ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Details & Evidence Photos -->
            <div class="col-lg-7">
                <!-- Description Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                    <div class="card-header bg-white p-3 border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-file-text me-2 text-primary"></i> Grievance Details</h6>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-dark mb-4" style="line-height:1.7;"><?= nl2br(e($complaint['description'])) ?></p>

                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="small text-muted mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Exact Location & Address:</div>
                            <div class="fw-semibold text-dark"><?= e($complaint['address']) ?></div>
                            <div class="small text-muted mt-1">Ward Area: <strong><?= e($complaint['ward']) ?></strong></div>
                            <?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
                                <div class="small text-muted mt-1">
                                    GPS Coordinates: <code><?= e($complaint['latitude']) ?>, <?= e($complaint['longitude']) ?></code>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Photographic Evidence -->
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-muted mb-1">Citizen Grievance Photo:</label>
                                <?php if (!empty($complaint['image_url']) && file_exists(ROOT_PATH . '/' . $complaint['image_url'])): ?>
                                    <div class="border rounded-3 p-1 bg-light">
                                        <img src="<?= $baseUrl . e($complaint['image_url']) ?>" alt="Complaint Evidence" class="img-fluid rounded-2 w-100" style="max-height:220px; object-fit:cover;">
                                    </div>
                                <?php else: ?>
                                    <div class="border rounded-3 p-4 text-center bg-light text-muted small">
                                        <i class="bi bi-image fs-3 d-block mb-1"></i>
                                        No citizen image attached
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
                                            <i class="bi bi-check-circle-fill me-1"></i> Verified Resolution Photo
                                        </div>
                                    </div>
                                <?php elseif ($complaint['status'] === 'Resolved'): ?>
                                    <div class="border rounded-3 p-4 text-center bg-success-subtle text-success small">
                                        <i class="bi bi-patch-check-fill fs-3 d-block mb-1"></i>
                                        Resolution recorded & verified by department
                                    </div>
                                <?php else: ?>
                                    <div class="border rounded-3 p-4 text-center bg-light text-muted small">
                                        <i class="bi bi-clock-history fs-3 d-block mb-1"></i>
                                        Pending resolution upload
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Citizen Feedback Display -->
                <?php if ($feedback): ?>
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-star-fill text-warning me-2"></i> Citizen Resolution Feedback</h6>
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
            </div>

            <!-- Right Column: Audit Trail Timeline -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 bg-white">
                    <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-journal-text me-2 text-primary"></i> Resolution Activity Audit Log</h6>
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
                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                <span class="badge bg-light text-muted border small" style="font-size:0.75rem;">
                                                    Status: <?= e($log['new_status']) ?>
                                                </span>
                                                <span class="text-muted small">&bull; By <?= e($log['acting_user_name'] ?? 'System') ?></span>
                                            </div>
                                            <?php if (!empty($log['remarks'])): ?>
                                                <p class="small text-secondary mb-0 bg-white p-2 rounded border">
                                                    <?= nl2br(e($log['remarks'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-hourglass-split fs-3 d-block mb-1"></i>
                                No audit logs recorded yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Citizen Actions Box -->
                <div class="card border-0 shadow-sm rounded-4 mt-4 bg-light">
                    <div class="card-body p-3 text-center">
                        <h6 class="fw-bold text-dark mb-1">Are you the reporter of this grievance?</h6>
                        <p class="small text-muted mb-3">Sign in to your citizen account to rate resolution, message the department, or reopen if unfinished.</p>
                        <a href="login.php" class="btn btn-primary-civic btn-sm px-3">
                            <i class="bi bi-person-fill me-1"></i> Login to Citizen Portal
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Initial Blank State -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
            <div class="brand-icon mx-auto mb-3" style="width:60px; height:60px; font-size:1.8rem;">
                <i class="bi bi-qr-code-scan"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Track Any Complaint Ticket</h4>
            <p class="text-muted mx-auto mb-4" style="max-width:500px;">
                Every civic complaint lodged with the municipal corporation is assigned a unique reference code. Enter your code above to inspect live officer assignments and ground progress.
            </p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <span class="text-muted small me-2 align-self-center">Try Demo Tickets:</span>
                <a href="track.php?ref=CT-2026-10001" class="btn btn-sm btn-outline-primary">CT-2026-10001</a>
                <a href="track.php?ref=CT-2026-10002" class="btn btn-sm btn-outline-primary">CT-2026-10002</a>
                <a href="track.php?ref=CT-2026-10003" class="btn btn-sm btn-outline-primary">CT-2026-10003</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
