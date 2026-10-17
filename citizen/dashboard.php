<?php
/**
 * CivicTrack — Citizen Dashboard
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('citizen');

$userId = getUserId();
$userName = getUserName();
$baseUrl = getBaseUrl();

$kpi = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0
];
$recentComplaints = [];

try {
    $pdo = getDbConnection();

    // KPI queries for this citizen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE citizen_id = ?");
    $stmt->execute([$userId]);
    $kpi['total'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE citizen_id = ? AND status IN ('Submitted', 'Under Review')");
    $stmt->execute([$userId]);
    $kpi['pending'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE citizen_id = ? AND status IN ('Assigned', 'In Progress', 'Reopened')");
    $stmt->execute([$userId]);
    $kpi['in_progress'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE citizen_id = ? AND status = 'Resolved'");
    $stmt->execute([$userId]);
    $kpi['resolved'] = (int)$stmt->fetchColumn();

    // Recent complaints for this citizen
    $stmt = $pdo->prepare("
        SELECT c.*, cat.category_name, cat.icon_class, d.dept_name
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        JOIN departments d ON c.dept_id = d.dept_id
        WHERE c.citizen_id = ?
        ORDER BY c.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$userId]);
    $recentComplaints = $stmt->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'Citizen Dashboard — CivicTrack';
$isDashboard = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="dashboard-main">
        <!-- Topbar -->
        <header class="dashboard-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-light border d-lg-none" id="sidebarToggleBtn">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div>
                    <h5 class="fw-bold text-dark mb-0">Citizen Grievance Desk</h5>
                    <small class="text-muted">Welcome back, <?= e($userName) ?></small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="lodge-complaint.php" class="btn btn-sm btn-primary-civic d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle-fill"></i> Lodge New Grievance
                </a>
                <a href="notifications.php" class="btn btn-light position-relative p-2 rounded-circle border" title="Notifications">
                    <i class="bi bi-bell-fill text-muted"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                            <?= $unreadCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <!-- Action Prompt Banner -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 text-white overflow-hidden" 
                 style="background: linear-gradient(135deg, #1e3a8a 0%, #0d9488 100%);">
                <div class="card-body p-4 p-md-5 position-relative">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <span class="badge bg-white text-dark mb-2 fw-semibold px-3 py-1">Direct Municipal Grievance Redressal</span>
                            <h3 class="fw-bold mb-2">Notice a Civic Problem in Your Area?</h3>
                            <p class="mb-3 text-white-50" style="max-width: 550px;">
                                Snap a photo of potholes, uncollected waste, open drains, or dark streets. We auto-route your ticket directly to the concerned department with committed SLA repair deadlines.
                            </p>
                            <a href="lodge-complaint.php" class="btn btn-light btn-lg text-primary fw-bold px-4 py-2 shadow-sm">
                                <i class="bi bi-camera-fill me-2"></i> Lodge a Complaint Now
                            </a>
                        </div>
                        <div class="col-md-4 text-center d-none d-md-block">
                            <i class="bi bi-geo-alt-fill text-white opacity-25" style="font-size: 7rem;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">Total Lodged</div>
                            <div class="kpi-value"><?= $kpi['total'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-blue">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">Awaiting Action</div>
                            <div class="kpi-value text-amber"><?= $kpi['pending'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-amber">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">In Progress</div>
                            <div class="kpi-value text-primary"><?= $kpi['in_progress'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-purple">
                            <i class="bi bi-gear-wide-connected"></i>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="kpi-card">
                        <div>
                            <div class="kpi-title">Resolved Issues</div>
                            <div class="kpi-value text-success"><?= $kpi['resolved'] ?></div>
                        </div>
                        <div class="kpi-icon-circle kpi-green">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Complaints Table -->
            <div class="card-civic">
                <div class="card-civic-header">
                    <div>
                        <h6 class="card-civic-title">My Recent Grievances</h6>
                        <small class="text-muted">Live tracking of your submitted municipal reports</small>
                    </div>
                    <a href="my-complaints.php" class="btn btn-sm btn-outline-primary">
                        View All (<?= $kpi['total'] ?>) &rarr;
                    </a>
                </div>

                <div class="card-civic-body p-0">
                    <?php if (!empty($recentComplaints)): ?>
                        <div class="table-responsive">
                            <table class="table table-civic mb-0">
                                <thead>
                                    <tr>
                                        <th>Ref Number</th>
                                        <th>Grievance Title</th>
                                        <th>Category</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>SLA Deadline</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentComplaints as $row): ?>
                                        <tr>
                                            <td>
                                                <a href="complaint-details.php?id=<?= $row['complaint_id'] ?>" class="fw-bold font-monospace text-primary text-decoration-none">
                                                    <?= e($row['reference_number']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="<?= e($row['title']) ?>">
                                                    <?= e($row['title']) ?>
                                                </div>
                                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($row['ward']) ?></small>
                                            </td>
                                            <td>
                                                <span class="small text-secondary">
                                                    <i class="bi <?= e($row['icon_class'] ?: 'bi-tag') ?> me-1 text-primary"></i>
                                                    <?= e($row['category_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="small text-muted"><?= e($row['dept_name']) ?></span>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($row['status']) ?>
                                            </td>
                                            <td>
                                                <small class="<?= isComplaintOverdue($row) ? 'text-danger fw-bold' : 'text-muted' ?>">
                                                    <?= formatDateTime($row['sla_deadline']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="complaint-details.php?id=<?= $row['complaint_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="feature-icon-wrapper icon-blue mx-auto mb-3">
                                <i class="bi bi-clipboard-check"></i>
                            </div>
                            <h5 class="fw-bold text-dark">No Grievances Lodged Yet</h5>
                            <p class="text-muted small mx-auto mb-3" style="max-width: 400px;">
                                You have not submitted any civic complaints. Click below whenever you notice an infrastructure problem in your ward.
                            </p>
                            <a href="lodge-complaint.php" class="btn btn-primary-civic btn-sm px-3">
                                <i class="bi bi-plus-circle me-1"></i> Lodge Your First Complaint
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
