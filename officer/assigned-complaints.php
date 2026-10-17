<?php
/**
 * CivicTrack — Officer Assigned Complaints Queue
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

// Filter parameters
$statusFilter = trim($_GET['status'] ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');
$wardFilter = trim($_GET['ward'] ?? '');
$overdueOnly = !empty($_GET['overdue']);
$searchTerm = trim($_GET['search'] ?? '');

$complaints = [];
$wards = [];

try {
    $pdo = getDbConnection();

    // Fetch distinct wards
    $wardStmt = $pdo->query("SELECT DISTINCT ward FROM complaints WHERE ward IS NOT NULL AND ward != '' ORDER BY ward ASC");
    $wards = $wardStmt->fetchAll(PDO::FETCH_COLUMN);

    // Query builder
    $sql = "
        SELECT c.*, cat.category_name, cat.icon_class,
               u.name as citizen_name, u.phone as citizen_phone
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        JOIN users u ON c.citizen_id = u.user_id
        WHERE c.dept_id = :dept_id
    ";
    $params = [':dept_id' => $deptId];

    if (!empty($statusFilter)) {
        $sql .= " AND c.status = :status";
        $params[':status'] = $statusFilter;
    }

    if (!empty($priorityFilter)) {
        $sql .= " AND c.priority = :priority";
        $params[':priority'] = $priorityFilter;
    }

    if (!empty($wardFilter)) {
        $sql .= " AND c.ward = :ward";
        $params[':ward'] = $wardFilter;
    }

    if ($overdueOnly) {
        $sql .= " AND c.status NOT IN ('Resolved', 'Closed') AND c.sla_deadline < NOW()";
    }

    if (!empty($searchTerm)) {
        $sql .= " AND (c.reference_number LIKE :search OR c.title LIKE :search OR u.name LIKE :search OR c.address LIKE :search)";
        $params[':search'] = '%' . $searchTerm . '%';
    }

    $sql .= " ORDER BY c.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Department Complaints Queue — CivicTrack';
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
                    <h5 class="fw-bold text-dark mb-0">Department Queue</h5>
                    <small class="text-muted">Showing <?= count($complaints) ?> complaints</small>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Desk Overview
                </a>
            </div>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <!-- Advanced Filter Bar -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-body p-3">
                    <form method="GET" action="assigned-complaints.php" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" 
                                       placeholder="Ref #, Citizen, Title..." value="<?= e($searchTerm) ?>">
                            </div>
                        </div>

                        <div class="col-md-2">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">-- All Statuses --</option>
                                <option value="Submitted" <?= ($statusFilter === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
                                <option value="Under Review" <?= ($statusFilter === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
                                <option value="Assigned" <?= ($statusFilter === 'Assigned') ? 'selected' : '' ?>>Assigned</option>
                                <option value="In Progress" <?= ($statusFilter === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                                <option value="Resolved" <?= ($statusFilter === 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                                <option value="Reopened" <?= ($statusFilter === 'Reopened') ? 'selected' : '' ?>>Reopened</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select name="priority" class="form-select form-select-sm">
                                <option value="">-- All Priorities --</option>
                                <option value="Urgent" <?= ($priorityFilter === 'Urgent') ? 'selected' : '' ?>>Urgent</option>
                                <option value="High" <?= ($priorityFilter === 'High') ? 'selected' : '' ?>>High</option>
                                <option value="Medium" <?= ($priorityFilter === 'Medium') ? 'selected' : '' ?>>Medium</option>
                                <option value="Low" <?= ($priorityFilter === 'Low') ? 'selected' : '' ?>>Low</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select name="ward" class="form-select form-select-sm">
                                <option value="">-- All Wards --</option>
                                <?php foreach ($wards as $w): ?>
                                    <option value="<?= e($w) ?>" <?= ($wardFilter === $w) ? 'selected' : '' ?>><?= e($w) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <div class="form-check form-check-inline small">
                                <input class="form-check-input" type="checkbox" id="overdue" name="overdue" value="1" <?= $overdueOnly ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-danger" for="overdue">Overdue Only</label>
                            </div>
                        </div>

                        <div class="col-md-1 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary-civic w-100">
                                Apply
                            </button>
                            <?php if (!empty($statusFilter) || !empty($priorityFilter) || !empty($wardFilter) || $overdueOnly || !empty($searchTerm)): ?>
                                <a href="assigned-complaints.php" class="btn btn-sm btn-outline-secondary" title="Clear">
                                    <i class="bi bi-x"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table of Department Complaints -->
            <div class="card-civic">
                <div class="card-civic-body p-0">
                    <?php if (!empty($complaints)): ?>
                        <div class="table-responsive">
                            <table class="table table-civic mb-0">
                                <thead>
                                    <tr>
                                        <th>Ref Number</th>
                                        <th>Citizen & Summary</th>
                                        <th>Category</th>
                                        <th>Ward & Address</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>SLA Target</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($complaints as $c): ?>
                                        <tr>
                                            <td>
                                                <a href="complaint-details.php?id=<?= $c['complaint_id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                    <?= e($c['reference_number']) ?>
                                                </a>
                                                <div class="text-muted" style="font-size:0.75rem;"><?= timeAgo($c['created_at']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark text-truncate" style="max-width:220px;" title="<?= e($c['title']) ?>">
                                                    <?= e($c['title']) ?>
                                                </div>
                                                <small class="text-muted"><?= e($c['citizen_name']) ?> &bull; <?= e($c['citizen_phone']) ?></small>
                                            </td>
                                            <td>
                                                <span class="small text-secondary">
                                                    <i class="bi <?= e($c['icon_class'] ?: 'bi-tag') ?> me-1 text-primary"></i>
                                                    <?= e($c['category_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-dark fw-semibold d-block"><?= e($c['ward']) ?></small>
                                                <small class="text-muted text-truncate d-block" style="max-width:180px;" title="<?= e($c['address']) ?>">
                                                    <?= e($c['address']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?= getPriorityBadge($c['priority']) ?>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($c['status']) ?>
                                            </td>
                                            <td>
                                                <small class="<?= isComplaintOverdue($c) ? 'text-danger fw-bold' : 'text-muted' ?>">
                                                    <?= formatDateTime($c['sla_deadline']) ?>
                                                    <?php if (isComplaintOverdue($c)): ?>
                                                        <span class="d-block text-danger fw-bold" style="font-size:0.7rem;"><i class="bi bi-alarm-fill"></i> OVERDUE</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <a href="complaint-details.php?id=<?= $c['complaint_id'] ?>" class="btn btn-sm btn-primary-civic">
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
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                            <h6 class="fw-bold text-dark">No Matching Complaints</h6>
                            <p class="text-muted small mb-0">No complaints matching your current filters were found in this department queue.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
