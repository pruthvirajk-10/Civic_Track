<?php
/**
 * CivicTrack — Administrator Executive Dashboard
 * Municipal Corporation Command Center
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$userId = getUserId();
$userName = getUserName();
$baseUrl = getBaseUrl();

$kpi = [
    'total_complaints' => 0,
    'new_submitted' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'overdue' => 0,
    'total_citizens' => 0,
    'total_officers' => 0,
    'resolution_rate' => 0
];

$recentComplaints = [];

// Chart data arrays
$catLabels = [];
$catData = [];

$statusLabels = ['Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed', 'Reopened'];
$statusData = [0, 0, 0, 0, 0, 0, 0];

$trendLabels = [];
$trendData = [];

$deptLabels = [];
$deptTotalData = [];
$deptResolvedData = [];

try {
    $pdo = getDbConnection();

    // KPI queries
    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints");
    $kpi['total_complaints'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Submitted', 'Under Review')");
    $kpi['new_submitted'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'In Progress'");
    $kpi['in_progress'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Resolved', 'Closed')");
    $kpi['resolved'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status NOT IN ('Resolved', 'Closed') AND sla_deadline < NOW()");
    $kpi['overdue'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'citizen'");
    $kpi['total_citizens'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'officer'");
    $kpi['total_officers'] = (int)$stmt->fetchColumn();

    if ($kpi['total_complaints'] > 0) {
        $kpi['resolution_rate'] = round(($kpi['resolved'] / $kpi['total_complaints']) * 100, 1);
    }

    // Recent 6 complaints
    $stmt = $pdo->query("
        SELECT c.*, cat.category_name, cat.icon_class, d.dept_name, u.name as citizen_name
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        JOIN departments d ON c.dept_id = d.dept_id
        JOIN users u ON c.citizen_id = u.user_id
        ORDER BY c.created_at DESC
        LIMIT 6
    ");
    $recentComplaints = $stmt->fetchAll();

    // 1. Complaints by Category
    $stmt = $pdo->query("
        SELECT cat.category_name, COUNT(c.complaint_id) as total
        FROM complaint_categories cat
        LEFT JOIN complaints c ON cat.category_id = c.category_id
        GROUP BY cat.category_id, cat.category_name
        ORDER BY total DESC
    ");
    while ($row = $stmt->fetch()) {
        $catLabels[] = $row['category_name'];
        $catData[] = (int)$row['total'];
    }

    // 2. Complaints by Status
    $stmt = $pdo->query("SELECT status, COUNT(*) as total FROM complaints GROUP BY status");
    $statusMap = [];
    while ($row = $stmt->fetch()) {
        $statusMap[$row['status']] = (int)$row['total'];
    }
    foreach ($statusLabels as $i => $s) {
        $statusData[$i] = $statusMap[$s] ?? 0;
    }

    // 3. Department workload & resolution
    $stmt = $pdo->query("
        SELECT d.dept_name, 
               COUNT(c.complaint_id) as total,
               SUM(CASE WHEN c.status = 'Resolved' THEN 1 ELSE 0 END) as resolved_count
        FROM departments d
        LEFT JOIN complaints c ON d.dept_id = c.dept_id
        WHERE d.is_active = 1
        GROUP BY d.dept_id, d.dept_name
        LIMIT 6
    ");
    while ($row = $stmt->fetch()) {
        $deptLabels[] = $row['dept_name'];
        $deptTotalData[] = (int)$row['total'];
        $deptResolvedData[] = (int)$row['resolved_count'];
    }

    // 4. Trend over past 7 days
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $display = date('M d', strtotime($date));
        $trendLabels[] = $display;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $trendData[] = (int)$stmt->fetchColumn();
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'City Command Center — CivicTrack Admin';
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
                    <h5 class="fw-bold text-dark mb-0">Municipal Command Console</h5>
                    <small class="text-muted">Smart City Live Analytics & Grievance Governance</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="reports.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports & CSV
                </a>
                <a href="complaints.php" class="btn btn-sm btn-primary-civic">
                    <i class="bi bi-folder-fill me-1"></i> Manage All Tickets
                </a>
            </div>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <!-- SLA Overdue Alert Banner -->
            <?php if ($kpi['overdue'] > 0): ?>
                <div class="alert alert-danger shadow-sm border-danger d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-exclamation-octagon-fill fs-2 text-danger"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Executive Escalation: <?= $kpi['overdue'] ?> Complaint(s) Overdue Across City!</h6>
                            <p class="mb-0 small">These municipal tickets have exceeded their SLA resolution deadlines. Review and reassign to expedite clearance.</p>
                        </div>
                    </div>
                    <a href="complaints.php?overdue=1" class="btn btn-sm btn-danger fw-bold px-3">
                        Review Overdue Tickets &rarr;
                    </a>
                </div>
            <?php endif; ?>

            <!-- Executive KPI Cards Row (Clickable) -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <a href="complaints.php" class="kpi-card kpi-card-clickable" title="View All Lodged Complaints">
                        <div>
                            <div class="kpi-title">Total Lodged <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value"><?= number_format($kpi['total_complaints']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-blue">
                            <i class="bi bi-journal-check"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="complaints.php?status=Pending" class="kpi-card kpi-card-clickable" title="View Pending / Newly Submitted Complaints">
                        <div>
                            <div class="kpi-title">Pending / New <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value text-amber"><?= number_format($kpi['new_submitted']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-amber">
                            <i class="bi bi-inbox-fill"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="complaints.php?status=In+Progress" class="kpi-card kpi-card-clickable" title="View In Progress Complaints">
                        <div>
                            <div class="kpi-title">In Progress <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value text-primary"><?= number_format($kpi['in_progress']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-purple">
                            <i class="bi bi-gear-wide-connected"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="complaints.php?status=Resolved" class="kpi-card kpi-card-clickable" title="View Resolved / Completed Complaints">
                        <div>
                            <div class="kpi-title">Resolved Issues <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value text-success"><?= number_format($kpi['resolved']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-green">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="complaints.php?overdue=1" class="kpi-card kpi-card-clickable" title="View SLA Overdue Complaints">
                        <div>
                            <div class="kpi-title">SLA Overdue <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value text-danger"><?= number_format($kpi['overdue']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-red">
                            <i class="bi bi-alarm-fill"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">Resolution Rate</div>
                            <div class="kpi-value text-info"><?= $kpi['resolution_rate'] ?>%</div>
                        </div>
                        <div class="kpi-icon-circle kpi-teal">
                            <i class="bi bi-speedometer"></i>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <a href="users.php?role=citizen" class="kpi-card kpi-card-clickable" title="Manage Registered Citizens">
                        <div>
                            <div class="kpi-title">Active Citizens <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value"><?= number_format($kpi['total_citizens']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-blue">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="users.php?role=officer" class="kpi-card kpi-card-clickable" title="Manage Department Officers">
                        <div>
                            <div class="kpi-title">Field Officers <i class="bi bi-arrow-right-short"></i></div>
                            <div class="kpi-value"><?= number_format($kpi['total_officers']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-teal">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Charts Row 1: Category Doughnut & Submission Trend -->
            <div class="row g-4 mb-4">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-pie-chart-fill me-2 text-primary"></i> Complaints by Category
                            </h6>
                            <span class="badge bg-light text-muted border">Distribution</span>
                        </div>
                        <div class="card-body p-3 d-flex align-items-center justify-content-center" style="position:relative; height:320px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-graph-up me-2 text-primary"></i> Weekly Submission Trend (Past 7 Days)
                            </h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Volume</span>
                        </div>
                        <div class="card-body p-3" style="position:relative; height:320px;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2: Status Breakdown & Department Workload -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-bar-chart-fill me-2 text-primary"></i> Complaints by Status Lifecycle
                            </h6>
                            <span class="badge bg-light text-muted border">Lifecycle</span>
                        </div>
                        <div class="card-body p-3" style="position:relative; height:300px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                        <div class="card-header bg-white p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-buildings-fill me-2 text-primary"></i> Department Workload & Resolution
                            </h6>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Clearance</span>
                        </div>
                        <div class="card-body p-3" style="position:relative; height:300px;">
                            <canvas id="deptChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Municipal Grievances Table -->
            <div class="card-civic">
                <div class="card-civic-header">
                    <div>
                        <h6 class="card-civic-title">Recent Citywide Grievances</h6>
                        <small class="text-muted">Live feed of reported municipal issues across all wards</small>
                    </div>
                    <a href="complaints.php" class="btn btn-sm btn-outline-primary">
                        View All (<?= $kpi['total_complaints'] ?>) &rarr;
                    </a>
                </div>

                <div class="card-civic-body p-0">
                    <?php if (!empty($recentComplaints)): ?>
                        <div class="table-responsive">
                            <table class="table table-civic mb-0">
                                <thead>
                                    <tr>
                                        <th>Ref Number</th>
                                        <th>Citizen & Summary</th>
                                        <th>Category</th>
                                        <th>Department</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>SLA Deadline</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentComplaints as $c): ?>
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
                                                <small class="text-muted"><?= e($c['citizen_name']) ?> &bull; <?= e($c['ward']) ?></small>
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
                                                        <span class="d-block text-danger fw-bold" style="font-size:0.7rem;"><i class="bi bi-alarm-fill"></i> OVERDUE</span>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <a href="complaint-details.php?id=<?= $c['complaint_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-gear-fill me-1"></i> Manage
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <h6 class="fw-bold text-dark">No Complaints in Database</h6>
                            <p class="text-muted small mb-0">Use the <a href="../setup.php">Database Installer</a> to seed sample demonstration complaints.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js Initializations -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Category Chart
    const catLabels = <?= json_encode($catLabels) ?>;
    const catData = <?= json_encode($catData) ?>;
    initCategoryChart('categoryChart', catLabels, catData);

    // 2. Trend Line Chart
    const trendLabels = <?= json_encode($trendLabels) ?>;
    const trendData = <?= json_encode($trendData) ?>;
    initTrendChart('trendChart', trendLabels, trendData);

    // 3. Status Bar Chart
    const statusLabels = <?= json_encode($statusLabels) ?>;
    const statusData = <?= json_encode($statusData) ?>;
    initStatusChart('statusChart', statusLabels, statusData);

    // 4. Dept Performance Chart
    const deptLabels = <?= json_encode($deptLabels) ?>;
    const deptTotalData = <?= json_encode($deptTotalData) ?>;
    const deptResolvedData = <?= json_encode($deptResolvedData) ?>;
    initDeptChart('deptChart', deptLabels, deptTotalData, deptResolvedData);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
