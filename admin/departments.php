<?php
/**
 * CivicTrack — Department Management (CRUD)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$baseUrl = getBaseUrl();
$errors = [];
$departments = [];

try {
    $pdo = getDbConnection();

    // Handle Form Submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken()) {
            $errors[] = 'Security token invalid. Please try again.';
        } else {
            $action = $_POST['action'] ?? '';

            if ($action === 'add_dept') {
                $deptName = trim($_POST['dept_name'] ?? '');
                $deptCode = strtoupper(trim($_POST['dept_code'] ?? ''));
                $headEmail = trim($_POST['dept_head_email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $description = trim($_POST['description'] ?? '');

                if (empty($deptName) || empty($deptCode)) {
                    $errors[] = 'Department Name and Department Code are required.';
                } else {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE dept_code = ?");
                    $chk->execute([$deptCode]);
                    if ($chk->fetchColumn() > 0) {
                        $errors[] = "Department code '{$deptCode}' already exists.";
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO departments (dept_name, dept_code, dept_head_email, phone, description, is_active)
                            VALUES (?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$deptName, $deptCode, $headEmail, $phone, $description]);
                        setFlash('success', "Department '{$deptName}' added successfully.");
                        header('Location: departments.php');
                        exit;
                    }
                }
            } elseif ($action === 'edit_dept') {
                $deptId = (int)($_POST['dept_id'] ?? 0);
                $deptName = trim($_POST['dept_name'] ?? '');
                $headEmail = trim($_POST['dept_head_email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if (empty($deptName)) {
                    $errors[] = 'Department Name is required.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE departments 
                        SET dept_name = ?, dept_head_email = ?, phone = ?, description = ?, is_active = ?
                        WHERE dept_id = ?
                    ");
                    $stmt->execute([$deptName, $headEmail, $phone, $description, $isActive, $deptId]);
                    setFlash('success', "Department updated successfully.");
                    header('Location: departments.php');
                    exit;
                }
            } elseif ($action === 'toggle_status') {
                $deptId = (int)($_POST['dept_id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE departments SET is_active = IF(is_active = 1, 0, 1) WHERE dept_id = ?");
                $stmt->execute([$deptId]);
                setFlash('info', "Department active status updated.");
                header('Location: departments.php');
                exit;
            }
        }
    }

    // Fetch departments with counts
    $stmt = $pdo->query("
        SELECT d.*, 
               COUNT(DISTINCT u.user_id) as officer_count,
               COUNT(DISTINCT c.complaint_id) as complaint_count,
               SUM(CASE WHEN c.status = 'Resolved' THEN 1 ELSE 0 END) as resolved_count
        FROM departments d
        LEFT JOIN users u ON d.dept_id = u.dept_id AND u.role = 'officer'
        LEFT JOIN complaints c ON d.dept_id = c.dept_id
        GROUP BY d.dept_id
        ORDER BY d.dept_name ASC
    ");
    $departments = $stmt->fetchAll();

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

$pageTitle = 'Department Management — CivicTrack Admin';
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
                    <h5 class="fw-bold text-dark mb-0">Municipal Departments</h5>
                    <small class="text-muted">Total: <?= count($departments) ?> civic divisions</small>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-primary-civic" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                <i class="bi bi-plus-lg me-1"></i> Add Department
            </button>
        </header>

        <main class="dashboard-content">
            <?php renderFlash(); ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger small py-2 mb-4">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $e): ?>
                            <li><?= e($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card-civic">
                <div class="card-civic-body p-0">
                    <div class="table-responsive">
                        <table class="table table-civic mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Department Name</th>
                                    <th>Contact Head</th>
                                    <th>Officers</th>
                                    <th>Workload</th>
                                    <th>Resolved</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $d): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-dark font-monospace"><?= e($d['dept_code']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($d['dept_name']) ?></div>
                                            <small class="text-muted"><?= e($d['description']) ?></small>
                                        </td>
                                        <td>
                                            <small class="d-block text-dark fw-semibold"><?= e($d['dept_head_email'] ?: 'N/A') ?></small>
                                            <small class="text-muted"><?= e($d['phone'] ?: '') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                <i class="bi bi-people me-1"></i> <?= $d['officer_count'] ?> Officers
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark"><?= $d['complaint_count'] ?> Total</span>
                                        </td>
                                        <td>
                                            <span class="text-success fw-bold"><?= $d['resolved_count'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($d['is_active']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $d['dept_id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" action="departments.php" class="d-inline" onsubmit="return confirm('Change status for this department?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="dept_id" value="<?= $d['dept_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-<?= $d['is_active'] ? 'danger' : 'success' ?>" title="Toggle Active">
                                                        <i class="bi bi-power"></i>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Edit Modal -->
                                            <div class="modal fade text-start" id="editModal<?= $d['dept_id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content rounded-4 border-0">
                                                        <div class="modal-header bg-dark text-white">
                                                            <h5 class="modal-title fw-bold">Edit Department</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="departments.php">
                                                            <div class="modal-body p-4">
                                                                <?= csrfField() ?>
                                                                <input type="hidden" name="action" value="edit_dept">
                                                                <input type="hidden" name="dept_id" value="<?= $d['dept_id'] ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Department Name</label>
                                                                    <input type="text" class="form-control" name="dept_name" value="<?= e($d['dept_name']) ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Head Email</label>
                                                                    <input type="email" class="form-control" name="dept_head_email" value="<?= e($d['dept_head_email']) ?>">
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Phone Number</label>
                                                                    <input type="text" class="form-control" name="phone" value="<?= e($d['phone']) ?>">
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Description</label>
                                                                    <textarea class="form-control" name="description" rows="2"><?= e($d['description']) ?></textarea>
                                                                </div>

                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="is_active" id="act_<?= $d['dept_id'] ?>" value="1" <?= $d['is_active'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label small" for="act_<?= $d['dept_id'] ?>">Department Active</label>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary-civic btn-sm">Save Changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
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

<!-- Add Department Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-building-add me-2 text-info"></i> Add New Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="departments.php">
                <div class="modal-body p-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_dept">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Department Name *</label>
                        <input type="text" class="form-control" name="dept_name" placeholder="e.g. Roads and Public Works" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Department Code (Unique 2-5 chars) *</label>
                        <input type="text" class="form-control font-monospace" name="dept_code" placeholder="e.g. RPW" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Department Head Email</label>
                        <input type="email" class="form-control" name="dept_head_email" placeholder="head@smartcity.gov">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Contact Phone</label>
                        <input type="text" class="form-control" name="phone" placeholder="+91 22 2500 1000">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Scope & Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Responsibilities of this civic division..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-civic btn-sm">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
