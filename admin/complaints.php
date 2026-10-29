<?php
/**
 * CivicTrack — Administrator Complaint Management Console
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$baseUrl = getBaseUrl();

// Filter parameters
$deptFilter = (int)($_GET['dept_id'] ?? 0);
$catFilter = (int)($_GET['category_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');
$wardFilter = trim($_GET['ward'] ?? '');
$overdueOnly = !empty($_GET['overdue']);
$searchTerm = trim($_GET['search'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$complaints = [];
$departments = [];
$categories = [];
$wards = [];
$statusHeading = 'All Municipal Grievances';
$statusBadge = '';
$isFiltered = false;

if ($deptFilter > 0) $isFiltered = true;
if ($catFilter > 0) $isFiltered = true;
if (!empty($priorityFilter)) $isFiltered = true;
if (!empty($wardFilter)) $isFiltered = true;
if (!empty($startDate) || !empty($endDate)) $isFiltered = true;
if (!empty($searchTerm)) $isFiltered = true;

try {
    $pdo = getDbConnection();

    // Fetch filters metadata
    $departments = $pdo->query("SELECT dept_id, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetchAll();
    $categories = $pdo->query("SELECT category_id, category_name FROM complaint_categories WHERE is_active = 1 ORDER BY category_name ASC")->fetchAll();
    $wards = $pdo->query("SELECT DISTINCT ward FROM complaints WHERE ward IS NOT NULL AND ward != '' ORDER BY ward ASC")->fetchAll(PDO::FETCH_COLUMN);

    // Dynamic query building
    $sql = "
        SELECT c.*, cat.category_name, cat.icon_class, d.dept_name,
               u.name as citizen_name, u.phone as citizen_phone,
               o.name as officer_name
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        JOIN departments d ON c.dept_id = d.dept_id
        JOIN users u ON c.citizen_id = u.user_id
        LEFT JOIN users o ON c.assigned_officer_id = o.user_id
        WHERE 1=1
    ";
    $params = [];

    if ($deptFilter > 0) {
        $sql .= " AND c.dept_id = :dept_id";
        $params[':dept_id'] = $deptFilter;
    }

    if ($catFilter > 0) {
        $sql .= " AND c.category_id = :category_id";
        $params[':category_id'] = $catFilter;
    }

    if (!empty($statusFilter)) {
        $normStatus = strtolower(str_replace(['_', '-'], ' ', $statusFilter));
        if ($normStatus === 'pending' || $normStatus === 'awaiting action') {
            $sql .= " AND c.status IN ('Submitted', 'Under Review')";
            $statusHeading = 'Pending / Awaiting Action Complaints';
            $statusBadge = 'Pending / Awaiting Action';
            $isFiltered = true;
        } elseif ($normStatus === 'in progress') {
            $sql .= " AND c.status = 'In Progress'";
            $statusHeading = 'In Progress Complaints';
            $statusBadge = 'In Progress';
            $isFiltered = true;
        } elseif ($normStatus === 'resolved' || $normStatus === 'completed') {
            $sql .= " AND c.status IN ('Resolved', 'Closed')";
            $statusHeading = 'Resolved / Completed Complaints';
            $statusBadge = 'Resolved / Completed';
            $isFiltered = true;
        } else {
            $sql .= " AND c.status = :status";
            $params[':status'] = $statusFilter;
            $statusHeading = e($statusFilter) . ' Complaints';
            $statusBadge = e($statusFilter);
            $isFiltered = true;
        }
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
        $statusHeading = 'SLA Overdue Complaints';
        $statusBadge = 'SLA Overdue';
        $isFiltered = true;
    }

    if (!empty($startDate)) {
        $sql .= " AND DATE(c.created_at) >= :start_date";
        $params[':start_date'] = $startDate;
    }

    if (!empty($endDate)) {
        $sql .= " AND DATE(c.created_at) <= :end_date";
        $params[':end_date'] = $endDate;
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

$queryString = http_build_query($_GET);

$pageTitle = e($statusHeading) . ' — CivicTrack Admin';
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
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0"><?= e($statusHeading) ?></h5>
                        <?php if ($isFiltered): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small">
                                <i class="bi bi-funnel-fill me-1"></i><?= e($statusBadge) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">Showing <strong><?= count($complaints) ?></strong> ticket(s)</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if ($isFiltered): ?>
                    <a href="complaints.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> Clear Filter & View All
                    </a>
                <?php endif; ?>
                <a href="export-csv.php?<?= $queryString ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to CSV
                </a>
            </div>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <!-- Comprehensive Filter Bar -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-body p-3">
                    <form method="GET" action="complaints.php" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" 
                                       placeholder="Ref #, Citizen, Title, Address..." value="<?= e($searchTerm) ?>">
                            </div>
                        </div>

                        <div class="col-md-2">
                            <select name="dept_id" class="form-select form-select-sm">
                                <option value="0">-- All Departments --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['dept_id'] ?>" <?= ($deptFilter == $d['dept_id']) ? 'selected' : '' ?>>
                                        <?= e($d['dept_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select name="category_id" class="form-select form-select-sm">
                                <option value="0">-- All Categories --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= ($catFilter == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= e($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">-- All Statuses --</option>
                                <option value="Pending" <?= (strcasecmp($statusFilter, 'Pending') === 0 || strcasecmp($statusFilter, 'awaiting action') === 0) ? 'selected' : '' ?>>Pending / Awaiting Action</option>
                                <option value="In Progress" <?= (strcasecmp($statusFilter, 'In Progress') === 0 || strcasecmp($statusFilter, 'in_progress') === 0) ? 'selected' : '' ?>>In Progress</option>
                                <option value="Resolved" <?= (strcasecmp($statusFilter, 'Resolved') === 0 || strcasecmp($statusFilter, 'completed') === 0) ? 'selected' : '' ?>>Resolved / Completed</option>
                                <option disabled>──────────</option>
                                <option value="Submitted" <?= ($statusFilter === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
                                <option value="Under Review" <?= ($statusFilter === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
                                <option value="Assigned" <?= ($statusFilter === 'Assigned') ? 'selected' : '' ?>>Assigned</option>
                                <option value="Closed" <?= ($statusFilter === 'Closed') ? 'selected' : '' ?>>Closed</option>
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

                        <div class="col-md-1">
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" id="overdue" name="overdue" value="1" <?= $overdueOnly ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-danger small" for="overdue">Overdue</label>
                            </div>
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
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>" placeholder="From Date">
                        </div>

                        <div class="col-md-2">
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>" placeholder="To Date">
                        </div>

                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary-civic w-100">
                                Apply Filter
                            </button>
                            <?php if (!empty($queryString)): ?>
                                <a href="complaints.php" class="btn btn-sm btn-outline-secondary" title="Reset">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table of Grievances -->
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
                                        <th>Department</th>
                                        <th>Ward & Address</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Target SLA</th>
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
                                                <div class="fw-bold text-dark text-truncate" style="max-width:200px;" title="<?= e($c['title']) ?>">
                                                    <?= e($c['title']) ?>
                                                </div>
                                                <small class="text-muted"><?= e($c['citizen_name']) ?> (<?= e($c['citizen_phone']) ?>)</small>
                                            </td>
                                            <td>
                                                <span class="small text-secondary">
                                                    <i class="bi <?= e($c['icon_class'] ?: 'bi-tag') ?> me-1 text-primary"></i>
                                                    <?= e($c['category_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="small text-dark fw-semibold"><?= e($c['dept_name']) ?></span>
                                                <?php if (!empty($c['officer_name'])): ?>
                                                    <div class="small text-muted" style="font-size:0.72rem;">Officer: <?= e($c['officer_name']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-dark fw-semibold d-block"><?= e($c['ward']) ?></small>
                                                <small class="text-muted text-truncate d-block" style="max-width:160px;" title="<?= e($c['address']) ?>">
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
                                                <a href="complaint-details.php?id=<?= $c['complaint_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-sliders me-1"></i> Manage
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-search fs-1 text-muted d-block mb-2"></i>
                            <h6 class="fw-bold text-dark">No <?= e($statusHeading) ?> Found</h6>
                            <p class="text-muted small mb-3">
                                <?= $isFiltered ? 'No complaints match the current filter criteria across municipal departments.' : 'No complaints recorded in the system yet.' ?>
                            </p>
                            <?php if ($isFiltered): ?>
                                <a href="complaints.php" class="btn btn-sm btn-outline-primary px-3">
                                    <i class="bi bi-arrow-repeat me-1"></i> Clear Filters & View All
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
