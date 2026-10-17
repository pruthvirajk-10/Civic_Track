<?php
/**
 * CivicTrack — Public Landing Page
 * Title: Smart City Civic Complaint Management and Resolution Tracking System
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'CivicTrack — Building a Better City, One Complaint at a Time';

// Fetch live database statistics
$stats = [
    'total_complaints' => 0,
    'resolved_complaints' => 0,
    'in_progress' => 0,
    'resolution_rate' => 0,
    'active_citizens' => 0,
    'departments_count' => 0
];
$recentResolved = [];
$categories = [];

try {
    $pdo = getDbConnection();

    // Stats queries
    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints");
    $stats['total_complaints'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'");
    $stats['resolved_complaints'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('Under Review', 'Assigned', 'In Progress')");
    $stats['in_progress'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'citizen'");
    $stats['active_citizens'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM departments WHERE is_active = 1");
    $stats['departments_count'] = (int)$stmt->fetchColumn();

    if ($stats['total_complaints'] > 0) {
        $stats['resolution_rate'] = round(($stats['resolved_complaints'] / $stats['total_complaints']) * 100);
    }

    // Recent Resolved complaints for public transparency
    $stmt = $pdo->query("
        SELECT c.complaint_id, c.reference_number, c.title, c.ward, c.resolved_at,
               cat.category_name, cat.icon_class, d.dept_name
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        JOIN departments d ON c.dept_id = d.dept_id
        WHERE c.status = 'Resolved'
        ORDER BY c.resolved_at DESC
        LIMIT 4
    ");
    $recentResolved = $stmt->fetchAll();

    // Categories
    $stmt = $pdo->query("SELECT * FROM complaint_categories WHERE is_active = 1 ORDER BY category_id ASC");
    $categories = $stmt->fetchAll();

} catch (Exception $e) {
    // If DB is not connected yet, fallbacks are clean zero values
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Hero Section -->
<section class="hero-civic">
    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="hero-badge">
                    <i class="bi bi-shield-check text-info"></i> Official Municipal Grievance Redressal
                </div>
                <h1 class="hero-title">
                    Building a Better City, <br>
                    <span class="text-info">One Complaint at a Time.</span>
                </h1>
                <p class="hero-subtitle">
                    Empowering citizens to report potholes, overflowing garbage, broken streetlights, water leakages, and civic hazards. Track transparent resolution from submission to repair with real-time updates.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="citizen/lodge-complaint.php" class="btn btn-primary-civic btn-lg px-4 py-3 shadow-lg">
                        <i class="bi bi-camera-fill me-2"></i> Report an Issue
                    </a>
                    <a href="track.php" class="btn btn-outline-light btn-lg px-4 py-3">
                        <i class="bi bi-search me-2"></i> Track Complaint
                    </a>
                    <a href="login.php" class="btn btn-teal-civic btn-lg px-4 py-3">
                        <i class="bi bi-speedometer2 me-2"></i> Explore Dashboard
                    </a>
                </div>
                <div class="mt-4 pt-2 text-white-50 small d-flex align-items-center gap-4">
                    <span><i class="bi bi-check2-circle text-info me-1"></i> Auto-Department Routing</span>
                    <span><i class="bi bi-clock-history text-info me-1"></i> Defined SLA Timelines</span>
                    <span><i class="bi bi-geo-alt-fill text-info me-1"></i> GPS Pinpoint Location</span>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Smart City Interactive Map Preview Card -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white text-dark">
                    <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="spinner-grow spinner-grow-sm text-success" role="status"></span>
                            <span class="fw-bold small">Live City Resolution Map</span>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Real-Time</span>
                    </div>
                    <div id="cityPublicMap" style="height: 280px; width:100%;"></div>
                    <div class="card-body p-3 bg-light border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block">Overall City Resolution Rate</small>
                                <span class="fw-bold fs-5 text-success"><?= $stats['resolution_rate'] ?>% On-Time</span>
                            </div>
                            <a href="track.php" class="btn btn-sm btn-outline-primary fw-semibold">
                                Search By Ticket ID &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Live Stats Counters Banner -->
<div class="container">
    <div class="stats-banner">
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3">
                <div class="stat-number"><?= number_format($stats['total_complaints']) ?></div>
                <div class="stat-label">Total Complaints</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number text-success"><?= number_format($stats['resolved_complaints']) ?></div>
                <div class="stat-label">Resolved Issues</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number text-primary"><?= number_format($stats['in_progress']) ?></div>
                <div class="stat-label">In Resolution Queue</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number text-info"><?= number_format($stats['departments_count']) ?></div>
                <div class="stat-label">Municipal Departments</div>
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<section id="how-it-works" class="py-5 mt-4">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 mb-2 fw-semibold">Streamlined Redressal</span>
            <h2 class="fw-bold">How CivicTrack Works</h2>
            <p class="text-muted mx-auto" style="max-width: 620px;">
                A transparent, step-by-step grievance lifecycle that holds civic departments accountable from submission to resolution.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="card-feature text-center">
                    <div class="workflow-number">1</div>
                    <h5 class="fw-bold mb-2">Snap & Lodge</h5>
                    <p class="text-muted small mb-0">Citizens snap a photograph, tag GPS coordinates, select category, and submit their grievance.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-feature text-center">
                    <div class="workflow-number">2</div>
                    <h5 class="fw-bold mb-2">Smart Auto-Routing</h5>
                    <p class="text-muted small mb-0">The system automatically assigns the ticket to the concerned municipal department and generates an SLA deadline.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-feature text-center">
                    <div class="workflow-number">3</div>
                    <h5 class="fw-bold mb-2">Ground Execution</h5>
                    <p class="text-muted small mb-0">Department field officers review evidence, mobilize repair crews, and update live status notes.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-feature text-center">
                    <div class="workflow-number">4</div>
                    <h5 class="fw-bold mb-2">Proof & Rating</h5>
                    <p class="text-muted small mb-0">Officers upload after-repair proof photographs. Citizens confirm resolution and rate service quality.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Civic Categories Section -->
<section id="categories" class="py-5 bg-white border-top border-bottom">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="badge bg-teal-subtle text-teal border border-teal-subtle px-3 py-1 mb-2 fw-semibold" style="color:#0f766e; background:#ccfbf1;">Civic Responsibilities</span>
            <h2 class="fw-bold">Report Issues Across Key Municipal Categories</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">
                Select a category to report urgent hazards. Each category has committed SLA resolution windows.
            </p>
        </div>

        <div class="row g-3">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <div class="col-md-6 col-lg-4">
                        <a href="citizen/lodge-complaint.php?category=<?= $cat['category_id'] ?>" class="category-card">
                            <div class="feature-icon-wrapper icon-blue mb-0">
                                <i class="bi <?= e($cat['icon_class'] ?: 'bi-exclamation-triangle') ?>"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-bold text-dark text-truncate"><?= e($cat['category_name']) ?></div>
                                <div class="small text-muted text-truncate"><?= e($cat['description']) ?></div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="badge bg-light text-dark border small" style="font-size:0.75rem;">
                                        <i class="bi bi-clock me-1"></i> SLA: <?= (int)$cat['sla_hours'] ?>h
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle small" style="font-size:0.75rem;">
                                        <?= e($cat['default_priority']) ?> Priority
                                    </span>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-4 text-muted">
                    <p>No categories loaded yet. Please run the <a href="setup.php">Database Setup Installer</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Resolution Lifecycle Timeline -->
<section class="py-5">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 mb-2 fw-semibold">Audited Workflow</span>
                <h2 class="fw-bold mb-3">Live Resolution Lifecycle</h2>
                <p class="text-muted mb-4">
                    Every grievance follows a verified, strict status hierarchy. Citizens receive instant notification updates as municipal staff progress through the stages.
                </p>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Time-Stamped Audit Trail</h6>
                            <p class="small text-muted mb-0">Every remark and status shift is recorded in an immutable log table.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-primary text-white p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Mandatory Before & After Photos</h6>
                            <p class="small text-muted mb-0">Officers must attach verified repair photographs before marking issues resolved.</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-warning text-white p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Citizen Reopen Rights</h6>
                            <p class="small text-muted mb-0">If repairs are unsatisfactory, citizens can reopen the grievance for escalation.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h5 class="fw-bold text-dark mb-4"><i class="bi bi-bar-chart-steps me-2 text-primary"></i> Complaint State Transitions</h5>
                    
                    <div class="timeline-civic">
                        <div class="timeline-step">
                            <div class="timeline-dot completed"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="timeline-title text-success"><i class="bi bi-check-circle-fill me-1"></i> 1. Submitted</div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Citizen Action</span>
                                </div>
                                <div class="timeline-notes">Ticket generated with reference number, photo, description & ward coordinates.</div>
                            </div>
                        </div>

                        <div class="timeline-step">
                            <div class="timeline-dot completed"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="timeline-title text-info"><i class="bi bi-eye me-1"></i> 2. Under Review & Assigned</div>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">System & Admin</span>
                                </div>
                                <div class="timeline-notes">Assigned to department officer based on category; SLA countdown begins.</div>
                            </div>
                        </div>

                        <div class="timeline-step">
                            <div class="timeline-dot current"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="timeline-title text-primary"><i class="bi bi-gear-wide-connected me-1"></i> 3. In Progress</div>
                                    <span class="badge bg-primary text-white">Field Officer</span>
                                </div>
                                <div class="timeline-notes">Ground crew dispatched; materials ordered; field status notes appended.</div>
                            </div>
                        </div>

                        <div class="timeline-step">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="timeline-title text-dark"><i class="bi bi-patch-check-fill me-1 text-success"></i> 4. Resolved & Rated</div>
                                    <span class="badge bg-secondary-subtle text-dark border">Resolution</span>
                                </div>
                                <div class="timeline-notes">After-repair photograph submitted; citizen rates satisfaction from 1 to 5 stars.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Recent Public Resolved Showcase -->
<?php if (!empty($recentResolved)): ?>
<section class="py-5 bg-light border-top">
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
            <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 mb-2 fw-semibold">Public Accountability</span>
                <h2 class="fw-bold mb-1">Recently Resolved Grievances</h2>
                <p class="text-muted mb-0">Public records of civic improvements restored across wards.</p>
            </div>
            <a href="track.php" class="btn btn-outline-primary mt-2 mt-md-0">
                <i class="bi bi-search me-1"></i> Track Any Ticket
            </a>
        </div>

        <div class="row g-3">
            <?php foreach ($recentResolved as $res): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle-fill me-1"></i> Resolved
                                </span>
                                <small class="text-muted"><?= timeAgo($res['resolved_at']) ?></small>
                            </div>
                            <h6 class="fw-bold text-dark text-truncate mb-1" title="<?= e($res['title']) ?>">
                                <?= e($res['title']) ?>
                            </h6>
                            <p class="small text-muted mb-2">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($res['ward']) ?>
                            </p>
                            <div class="small text-primary fw-semibold mb-2">
                                <i class="bi <?= e($res['icon_class'] ?: 'bi-tag') ?> me-1"></i> <?= e($res['category_name']) ?>
                            </div>
                            <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size:0.75rem;">Ref: <code><?= e($res['reference_number']) ?></code></span>
                                <a href="track.php?ref=<?= urlencode($res['reference_number']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:0.75rem;">
                                    View Proof
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Call to Action Banner -->
<section class="py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); color: #ffffff;">
    <div class="container py-4 text-center">
        <h2 class="fw-bold text-white mb-3">Notice an Unsafe Pothole or Broken Streetlight?</h2>
        <p class="text-white-50 mx-auto mb-4" style="max-width: 600px;">
            Your report directly notifies the zone engineer and initiates a timed resolution cycle. Join your neighbors in keeping our city safe and clean.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="citizen/lodge-complaint.php" class="btn btn-primary-civic btn-lg px-4 py-3 shadow">
                <i class="bi bi-camera-fill me-2"></i> File a Grievance Now
            </a>
            <a href="register.php" class="btn btn-outline-light btn-lg px-4 py-3">
                <i class="bi bi-person-plus-fill me-2"></i> Create Citizen Account
            </a>
        </div>
    </div>
</section>

<!-- Public Map Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof L !== 'undefined' && document.getElementById('cityPublicMap')) {
        const cityMap = L.map('cityPublicMap').setView([19.0760, 72.8777], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap'
        }).addTo(cityMap);

        // Add sample city hotspot pins
        const locations = [
            { lat: 19.0760, lng: 72.8777, title: "Pothole Repaired - SV Road (Resolved)", type: "success" },
            { lat: 19.1170, lng: 72.9050, title: "Garbage Dump Clearing - Powai (In Progress)", type: "warning" },
            { lat: 19.1136, lng: 72.8697, title: "Streetlight Cable Repair - Andheri (Assigned)", type: "info" },
            { lat: 19.0550, lng: 72.8300, title: "Water Pipeline Main - Bandra (Under Review)", type: "danger" }
        ];

        locations.forEach(loc => {
            const marker = L.circleMarker([loc.lat, loc.lng], {
                radius: 8,
                fillColor: loc.type === 'success' ? '#10b981' : (loc.type === 'warning' ? '#f59e0b' : '#3b82f6'),
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(cityMap);
            marker.bindPopup(`<strong>${loc.title}</strong>`);
        });

        setTimeout(() => { cityMap.invalidateSize(); }, 300);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
