<?php
/**
 * CivicTrack — Analytical Reports & Governance Metrics
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$baseUrl = getBaseUrl();

// Filter parameters
$startDate = trim($_GET['start_date'] ?? date('Y-m-01')); // start of month default
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));
$deptFilter = (int)($_GET['dept_id'] ?? 0);
$wardFilter = trim($_GET['ward'] ?? '');

$deptStats = [];
$wardStats = [];
$summary = [
    'total' => 0,
    'resolved' => 0,
    'overdue' => 0,
    'avg_resolution_hours' => 0,
    'avg_rating' => 0
];

$departments = [];
$wards = [];

try {
    $pdo = getDbConnection();

    $departments = $pdo->query("SELECT dept_id, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetchAll();
    $wards = $pdo->query("SELECT DISTINCT ward FROM complaints WHERE ward IS NOT NULL AND ward != '' ORDER BY ward ASC")->fetchAll(PDO::FETCH_COLUMN);

    // Summary queries
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_count,
               SUM(CASE WHEN status NOT IN ('Resolved', 'Closed') AND sla_deadline < NOW() THEN 1 ELSE 0 END) as overdue_count,
               AVG(CASE WHEN status = 'Resolved' AND resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) ELSE NULL END) as avg_hours
        FROM complaints
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $res = $stmt->fetch();
    if ($res) {
        $summary['total'] = (int)$res['total'];
        $summary['resolved'] = (int)$res['resolved_count'];
        $summary['overdue'] = (int)$res['overdue_count'];
        $summary['avg_resolution_hours'] = round((float)$res['avg_hours'], 1);
    }

    // Average rating
    $stmt = $pdo->prepare("
        SELECT AVG(f.rating) as avg_rating
        FROM feedback f
        JOIN complaints c ON f.complaint_id = c.complaint_id
        WHERE DATE(c.created_at) >= ? AND DATE(c.created_at) <= ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $summary['avg_rating'] = round((float)$stmt->fetchColumn(), 1);

    // Department Performance Breakdown
    $stmt = $pdo->prepare("
        SELECT d.dept_name,
               COUNT(c.complaint_id) as total_received,
               SUM(CASE WHEN c.status = 'Resolved' THEN 1 ELSE 0 END) as total_resolved,
               SUM(CASE WHEN c.status IN ('Submitted', 'Under Review', 'Assigned', 'In Progress') THEN 1 ELSE 0 END) as total_pending,
               SUM(CASE WHEN c.status NOT IN ('Resolved', 'Closed') AND c.sla_deadline < NOW() THEN 1 ELSE 0 END) as total_overdue,
               AVG(f.rating) as avg_dept_rating
        FROM departments d
        LEFT JOIN complaints c ON d.dept_id = c.dept_id AND DATE(c.created_at) >= ? AND DATE(c.created_at) <= ?
        LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
        WHERE d.is_active = 1
        GROUP BY d.dept_id, d.dept_name
        ORDER BY total_received DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $deptStats = $stmt->fetchAll();

    // Ward-wise Distribution
    $stmt = $pdo->prepare("
        SELECT c.ward,
               COUNT(c.complaint_id) as total_ward,
               SUM(CASE WHEN c.status = 'Resolved' THEN 1 ELSE 0 END) as resolved_ward,
               SUM(CASE WHEN c.status NOT IN ('Resolved', 'Closed') AND c.sla_deadline < NOW() THEN 1 ELSE 0 END) as overdue_ward
        FROM complaints c
        WHERE DATE(c.created_at) >= ? AND DATE(c.created_at) <= ? AND c.ward IS NOT NULL AND c.ward != ''
        GROUP BY c.ward
        ORDER BY total_ward DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $wardStats = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$exportQuery = http_build_query([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'dept_id' => $deptFilter,
    'ward' => $wardFilter
]);

$pageTitle = 'Municipal Analytics & Reports — CivicTrack Admin';
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
                    <h5 class="fw-bold text-dark mb-0">Analytics & SLA Reports</h5>
                    <small class="text-muted">Period: <?= date('d M Y', strtotime($startDate)) ?> &ndash; <?= date('d M Y', strtotime($endDate)) ?></small>
                </div>
            </div>

            <a href="export-csv.php?<?= $exportQuery ?>" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Filtered CSV
            </a>
        </header>

        <main class="dashboard-content">
            <!-- Filter Bar -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-body p-3">
                    <form method="GET" action="reports.php" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-0">From Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-0">To Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-0">Department</label>
                            <select name="dept_id" class="form-select form-select-sm">
                                <option value="0">-- All Departments --</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['dept_id'] ?>" <?= ($deptFilter == $d['dept_id']) ? 'selected' : '' ?>>
                                        <?= e($d['dept_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-sm btn-primary-civic w-100">
                                <i class="bi bi-filter me-1"></i> Generate Report
                            </button>
                            <a href="reports.php" class="btn btn-sm btn-outline-secondary" title="Reset">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">Period Tickets</div>
                            <div class="kpi-value"><?= number_format($summary['total']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-blue">
                            <i class="bi bi-journal-text"></i>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">Period Resolved</div>
                            <div class="kpi-value text-success"><?= number_format($summary['resolved']) ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-green">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">Avg Resolution</div>
                            <div class="kpi-value text-primary"><?= $summary['avg_resolution_hours'] ?> hrs</div>
                        </div>
                        <div class="kpi-icon-circle kpi-purple">
                            <i class="bi bi-stopwatch-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">Citizen Rating</div>
                            <div class="kpi-value text-amber"><?= ($summary['avg_rating'] > 0) ? $summary['avg_rating'] . ' ★' : 'N/A' ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-amber">
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Performance Table -->
            <div class="card-civic mb-4">
                <div class="card-civic-header">
                    <div>
                        <h6 class="card-civic-title">Department-Wise Grievance Redressal Performance</h6>
                        <small class="text-muted">Workload clearance, SLA compliance, and citizen satisfaction per division</small>
                    </div>
                </div>
                <div class="card-civic-body p-0">
                    <div class="table-responsive">
                        <table class="table table-civic mb-0">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Tickets Received</th>
                                    <th>Resolved</th>
                                    <th>In Queue</th>
                                    <th>SLA Overdue</th>
                                    <th>Resolution Rate</th>
                                    <th>Satisfaction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deptStats as $ds): ?>
                                    <?php 
                                    $rate = ($ds['total_received'] > 0) ? round(($ds['total_resolved'] / $ds['total_received']) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($ds['dept_name']) ?></div>
                                        </td>
                                        <td><span class="fw-bold text-dark"><?= $ds['total_received'] ?></span></td>
                                        <td><span class="text-success fw-bold"><?= $ds['total_resolved'] ?></span></td>
                                        <td><span class="text-primary fw-semibold"><?= $ds['total_pending'] ?></span></td>
                                        <td>
                                            <?php if ($ds['total_overdue'] > 0): ?>
                                                <span class="badge bg-danger text-white"><?= $ds['total_overdue'] ?> Overdue</span>
                                            <?php else: ?>
                                                <span class="text-muted small">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $rate ?>%;"></div>
                                                </div>
                                                <span class="small fw-bold text-dark"><?= $rate ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($ds['avg_dept_rating'])): ?>
                                                <span class="fw-bold text-warning"><?= round($ds['avg_dept_rating'], 1) ?> ★</span>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Ward Distribution Table -->
            <div class="card-civic">
                <div class="card-civic-header">
                    <div>
                        <h6 class="card-civic-title">Ward & Area Hotspot Distribution</h6>
                        <small class="text-muted">Concentration of civic grievances by municipal ward</small>
                    </div>
                </div>
                <div class="card-civic-body p-0">
                    <div class="table-responsive">
                        <table class="table table-civic mb-0">
                            <thead>
                                <tr>
                                    <th>Municipal Ward</th>
                                    <th>Reported Issues</th>
                                    <th>Resolved Issues</th>
                                    <th>Overdue Issues</th>
                                    <th>Resolution %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wardStats as $ws): ?>
                                    <?php 
                                    $wRate = ($ws['total_ward'] > 0) ? round(($ws['resolved_ward'] / $ws['total_ward']) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                            <span class="fw-bold text-dark"><?= e($ws['ward']) ?></span>
                                        </td>
                                        <td><span class="fw-bold text-dark"><?= $ws['total_ward'] ?></span></td>
                                        <td><span class="text-success fw-bold"><?= $ws['resolved_ward'] ?></span></td>
                                        <td>
                                            <?php if ($ws['overdue_ward'] > 0): ?>
                                                <span class="badge bg-danger text-white"><?= $ws['overdue_ward'] ?> Overdue</span>
                                            <?php else: ?>
                                                <span class="text-muted small">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-primary"><?= $wRate ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
