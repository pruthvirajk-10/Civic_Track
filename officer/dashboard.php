<?php
/**
 * CivicTrack — Department Officer Dashboard
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('officer');

$userId = getUserId();
$userName = getUserName();
$deptId = getUserDeptId();
$baseUrl = getBaseUrl();

$deptInfo = null;
$kpi = [
    'total' => 0,
    'new' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'overdue' => 0
];
$activeQueue = [];

try {
    $pdo = getDbConnection();

    // Fetch department details
    if ($deptId) {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE dept_id = ?");
        $stmt->execute([$deptId]);
        $deptInfo = $stmt->fetch();
    }

    // KPI queries for this department
    if ($deptId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE dept_id = ?");
        $stmt->execute([$deptId]);
        $kpi['total'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE dept_id = ? AND status IN ('Submitted', 'Under Review')");
        $stmt->execute([$deptId]);
        $kpi['new'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE dept_id = ? AND status = 'In Progress'");
        $stmt->execute([$deptId]);
        $kpi['in_progress'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE dept_id = ? AND status IN ('Resolved', 'Closed')");
        $stmt->execute([$deptId]);
        $kpi['resolved'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE dept_id = ? AND status NOT IN ('Resolved', 'Closed') AND sla_deadline < NOW()");
        $stmt->execute([$deptId]);
        $kpi['overdue'] = (int)$stmt->fetchColumn();

        // High priority active queue
        $stmt = $pdo->prepare("
            SELECT c.*, cat.category_name, cat.icon_class, u.name as citizen_name, u.phone as citizen_phone
            FROM complaints c
            JOIN complaint_categories cat ON c.category_id = cat.category_id
            JOIN users u ON c.citizen_id = u.user_id
            WHERE c.dept_id = ? AND c.status NOT IN ('Resolved', 'Closed')
            ORDER BY 
                CASE c.priority 
                    WHEN 'Urgent' THEN 1 
                    WHEN 'High' THEN 2 
                    WHEN 'Medium' THEN 3 
                    ELSE 4 
                END, 
                c.sla_deadline ASC
            LIMIT 8
        ");
        $stmt->execute([$deptId]);
        $activeQueue = $stmt->fetchAll();
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Officer Desk — CivicTrack';
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
                    <h5 class="fw-bold text-dark mb-0">Department Officer Desk</h5>
                    <small class="text-muted">
                        <?= e($userName) ?> &bull; <strong class="text-primary"><?= e($deptInfo['dept_name'] ?? 'Unassigned Department') ?></strong>
                    </small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="assigned-complaints.php" class="btn btn-sm btn-teal-civic">
                    <i class="bi bi-inbox-fill me-1"></i> View Full Department Queue
                </a>
            </div>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <!-- SLA Overdue Alert Banner if any tickets breached -->
            <?php if ($kpi['overdue'] > 0): ?>
                <div class="alert alert-danger shadow-sm border-danger d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-alarm-fill fs-2 text-danger"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Attention: <?= $kpi['overdue'] ?> Grievance(s) Have Breached Resolution SLA!</h6>
                            <p class="mb-0 small">These tickets have passed their committed municipal resolution deadline. Expedite field crews to avoid escalation.</p>
                        </div>
                    </div>
                    <a href="assigned-complaints.php?overdue=1" class="btn btn-sm btn-danger fw-bold px-3">
                        Inspect Overdue Tickets
                    </a>
                </div>
            <?php endif; ?>

            <!-- KPI Metric Cards (Clickable) -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg">
                    <a href="assigned-complaints.php" class="kpi-card kpi-card-clickable" title="View All Department Complaints">
                        <div>
                            <div class="kpi-title">Department Total <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value"><?= $kpi['total'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-blue">
                            <i class="bi bi-folder-fill"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-lg">
                    <a href="assigned-complaints.php?status=Pending" class="kpi-card kpi-card-clickable" title="View New Review / Awaiting Action Complaints">
                        <div>
                            <div class="kpi-title">New Review <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value text-amber"><?= $kpi['new'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-amber">
                            <i class="bi bi-eye-fill"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-lg">
                    <a href="assigned-complaints.php?status=In+Progress" class="kpi-card kpi-card-clickable" title="View Active In Progress Complaints">
                        <div>
                            <div class="kpi-title">In Progress <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value text-primary"><?= $kpi['in_progress'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-purple">
                            <i class="bi bi-gear-wide-connected"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-lg">
                    <a href="assigned-complaints.php?status=Resolved" class="kpi-card kpi-card-clickable" title="View Resolved / Completed Complaints">
                        <div>
                            <div class="kpi-title">Resolved Total <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value text-success"><?= $kpi['resolved'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-green">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-lg">
                    <a href="assigned-complaints.php?overdue=1" class="kpi-card kpi-card-clickable" title="View SLA Overdue Complaints">
                        <div>
                            <div class="kpi-title">SLA Overdue <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value text-danger"><?= $kpi['overdue'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-red">
                            <i class="bi bi-alarm-fill"></i>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Priority Resolution Queue -->
            <div class="card-civic">
                <div class="card-civic-header">
                    <div>
                        <h6 class="card-civic-title">High Priority Action Queue</h6>
                        <small class="text-muted">Tickets sorted by urgency level and closest SLA deadlines</small>
                    </div>
                    <a href="assigned-complaints.php" class="btn btn-sm btn-outline-primary">
                        View All Assigned (<?= $kpi['total'] ?>) &rarr;
                    </a>
                </div>

                <div class="card-civic-body p-0">
                    <?php if (!empty($activeQueue)): ?>
                        <div class="table-responsive">
                            <table class="table table-civic mb-0">
                                <thead>
                                    <tr>
                                        <th>Ticket ID</th>
                                        <th>Citizen & Summary</th>
                                        <th>Category</th>
                                        <th>Ward & Address</th>
                                        <th>Priority</th>
                                        <th>Current Status</th>
                                        <th>SLA Deadline</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeQueue as $ticket): ?>
                                        <tr>
                                            <td>
                                                <a href="complaint-details.php?id=<?= $ticket['complaint_id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                    <?= e($ticket['reference_number']) ?>
                                                </a>
                                                <div class="text-muted" style="font-size:0.75rem;"><?= timeAgo($ticket['created_at']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark text-truncate" style="max-width:220px;" title="<?= e($ticket['title']) ?>">
                                                    <?= e($ticket['title']) ?>
                                                </div>
                                                <small class="text-muted">By <?= e($ticket['citizen_name']) ?> (<?= e($ticket['citizen_phone']) ?>)</small>
                                            </td>
                                            <td>
                                                <span class="small text-secondary">
                                                    <i class="bi <?= e($ticket['icon_class'] ?: 'bi-tag') ?> me-1 text-primary"></i>
                                                    <?= e($ticket['category_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-dark fw-semibold d-block"><?= e($ticket['ward']) ?></small>
                                                <small class="text-muted text-truncate d-block" style="max-width:180px;" title="<?= e($ticket['address']) ?>">
                                                    <?= e($ticket['address']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?= getPriorityBadge($ticket['priority']) ?>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($ticket['status']) ?>
                                            </td>
                                            <td>
                                                <small class="<?= isComplaintOverdue($ticket) ? 'text-danger fw-bold' : 'text-muted' ?>">
                                                    <?= formatDateTime($ticket['sla_deadline']) ?>
                                                    <?php if (isComplaintOverdue($ticket)): ?>
                                                        <span class="d-block text-danger fw-bold" style="font-size:0.7rem;"><i class="bi bi-alarm-fill"></i> OVERDUE</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <a href="complaint-details.php?id=<?= $ticket['complaint_id'] ?>" class="btn btn-sm btn-primary-civic">
                                                    <i class="bi bi-tools me-1"></i> Manage
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="feature-icon-wrapper icon-teal mx-auto mb-3">
                                <i class="bi bi-check2-all"></i>
                            </div>
                            <h6 class="fw-bold text-dark">No Pending Complaints in Queue</h6>
                            <p class="text-muted small mb-0">All complaints assigned to your department have been resolved or attended to.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
