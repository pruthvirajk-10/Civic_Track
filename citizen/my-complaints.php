<?php
/**
 * CivicTrack — Citizen My Complaints List
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('citizen');

$userId = getUserId();
$userName = getUserName();
$baseUrl = getBaseUrl();

// Filter parameters
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);
$searchTerm = trim($_GET['search'] ?? '');

$complaints = [];
$categories = [];
$statusHeading = 'All Lodged Complaints';
$statusBadge = '';
$isFiltered = false;

try {
    $pdo = getDbConnection();

    // Categories list for filter dropdown
    $catStmt = $pdo->query("SELECT category_id, category_name FROM complaint_categories WHERE is_active = 1 ORDER BY category_name ASC");
    $categories = $catStmt->fetchAll();

    // Query builder
    $sql = "
        SELECT c.*, cat.category_name, cat.icon_class, d.dept_name
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        JOIN departments d ON c.dept_id = d.dept_id
        WHERE c.citizen_id = :citizen_id
    ";
    $params = [':citizen_id' => $userId];

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

    if ($categoryFilter > 0) {
        $sql .= " AND c.category_id = :category_id";
        $params[':category_id'] = $categoryFilter;
        $isFiltered = true;
    }

    if (!empty($searchTerm)) {
        $sql .= " AND (c.reference_number LIKE :search OR c.title LIKE :search OR c.address LIKE :search)";
        $params[':search'] = '%' . $searchTerm . '%';
        $isFiltered = true;
    }

    $sql .= " ORDER BY c.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = e($statusHeading) . ' — CivicTrack';
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
                    <small class="text-muted">
                        Showing <strong><?= count($complaints) ?></strong> complaint record(s)
                    </small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if ($isFiltered): ?>
                    <a href="my-complaints.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> Clear Filter & View All
                    </a>
                <?php endif; ?>
                <a href="lodge-complaint.php" class="btn btn-sm btn-primary-civic d-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle-fill"></i> Lodge New Grievance
                </a>
            </div>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <!-- Filter Controls -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-body p-3">
                    <form method="GET" action="my-complaints.php" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light text-muted border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control border-start-0" 
                                       placeholder="Search reference # or keywords..." value="<?= e($searchTerm) ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
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

                        <div class="col-md-3">
                            <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="0">-- All Categories --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= ($categoryFilter == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= e($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary-civic w-100">
                                Filter
                            </button>
                            <?php if ($isFiltered): ?>
                                <a href="my-complaints.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Complaints Table -->
            <div class="card-civic">
                <div class="card-civic-body p-0">
                    <?php if (!empty($complaints)): ?>
                        <div class="table-responsive">
                            <table class="table table-civic mb-0">
                                <thead>
                                    <tr>
                                        <th>Ref Number</th>
                                        <th>Title & Location</th>
                                        <th>Category</th>
                                        <th>Department</th>
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
                                                <div class="fw-bold text-dark text-truncate" style="max-width: 250px;" title="<?= e($c['title']) ?>">
                                                    <?= e($c['title']) ?>
                                                </div>
                                                <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                                    <i class="bi bi-geo-alt text-danger me-1"></i><?= e($c['ward']) ?> &bull; <?= e($c['address']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="small text-secondary">
                                                    <i class="bi <?= e($c['icon_class'] ?: 'bi-tag') ?> me-1 text-primary"></i>
                                                    <?= e($c['category_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="small text-muted"><?= e($c['dept_name']) ?></span>
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
                                                        <span class="d-block text-danger fw-bold" style="font-size:0.7rem;"><i class="bi bi-exclamation-circle-fill"></i> Overdue</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <a href="complaint-details.php?id=<?= $c['complaint_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-arrow-right-circle"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-folder-x fs-1 text-muted d-block mb-2"></i>
                            <h6 class="fw-bold text-dark">No <?= e($statusHeading) ?> Found</h6>
                            <p class="text-muted small mb-3">
                                <?= $isFiltered ? 'No grievances match the current status or filter criteria.' : 'You have not lodged any complaints yet.' ?>
                            </p>
                            <?php if ($isFiltered): ?>
                                <a href="my-complaints.php" class="btn btn-outline-primary btn-sm px-3 me-2">
                                    <i class="bi bi-arrow-repeat me-1"></i> Clear Filters & View All
                                </a>
                            <?php endif; ?>
                            <a href="lodge-complaint.php" class="btn btn-primary-civic btn-sm px-3">
                                <i class="bi bi-plus-circle me-1"></i> Lodge New Grievance
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
