<?php
/**
 * CivicTrack — Categories & SLA Configuration Console
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$baseUrl = getBaseUrl();
$errors = [];
$categories = [];
$departments = [];

try {
    $pdo = getDbConnection();

    // Fetch departments for mapping
    $departments = $pdo->query("SELECT dept_id, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetchAll();

    // Handle Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken()) {
            $errors[] = 'Security token invalid. Please try again.';
        } else {
            $action = $_POST['action'] ?? '';

            if ($action === 'add_category') {
                $catName = trim($_POST['category_name'] ?? '');
                $deptId = (int)($_POST['dept_id'] ?? 0);
                $slaHours = max(1, (int)($_POST['sla_hours'] ?? 48));
                $priority = trim($_POST['default_priority'] ?? 'Medium');
                $iconClass = trim($_POST['icon_class'] ?? 'bi-exclamation-triangle');
                $description = trim($_POST['description'] ?? '');

                if (empty($catName) || $deptId <= 0) {
                    $errors[] = 'Category Name and Mapped Department are required.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO complaint_categories (category_name, dept_id, sla_hours, default_priority, icon_class, description, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$catName, $deptId, $slaHours, $priority, $iconClass, $description]);
                    setFlash('success', "Category '{$catName}' added with {$slaHours}h SLA.");
                    header('Location: categories.php');
                    exit;
                }
            } elseif ($action === 'edit_category') {
                $catId = (int)($_POST['category_id'] ?? 0);
                $catName = trim($_POST['category_name'] ?? '');
                $deptId = (int)($_POST['dept_id'] ?? 0);
                $slaHours = max(1, (int)($_POST['sla_hours'] ?? 48));
                $priority = trim($_POST['default_priority'] ?? 'Medium');
                $iconClass = trim($_POST['icon_class'] ?? 'bi-exclamation-triangle');
                $description = trim($_POST['description'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if (empty($catName) || $deptId <= 0) {
                    $errors[] = 'Category Name and Department are required.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE complaint_categories 
                        SET category_name = ?, dept_id = ?, sla_hours = ?, default_priority = ?, icon_class = ?, description = ?, is_active = ?
                        WHERE category_id = ?
                    ");
                    $stmt->execute([$catName, $deptId, $slaHours, $priority, $iconClass, $description, $isActive, $catId]);
                    setFlash('success', "Category updated successfully.");
                    header('Location: categories.php');
                    exit;
                }
            } elseif ($action === 'toggle_status') {
                $catId = (int)($_POST['category_id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE complaint_categories SET is_active = IF(is_active = 1, 0, 1) WHERE category_id = ?");
                $stmt->execute([$catId]);
                setFlash('info', "Category status toggled.");
                header('Location: categories.php');
                exit;
            }
        }
    }

    // Fetch categories with dept joins
    $stmt = $pdo->query("
        SELECT cat.*, d.dept_name,
               COUNT(c.complaint_id) as total_complaints
        FROM complaint_categories cat
        JOIN departments d ON cat.dept_id = d.dept_id
        LEFT JOIN complaints c ON cat.category_id = c.category_id
        GROUP BY cat.category_id
        ORDER BY cat.category_name ASC
    ");
    $categories = $stmt->fetchAll();

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

$pageTitle = 'SLA & Categories — CivicTrack Admin';
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
                    <h5 class="fw-bold text-dark mb-0">Categories & SLA Rules</h5>
                    <small class="text-muted">Automated department routing and resolution deadlines</small>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-primary-civic" data-bs-toggle="modal" data-bs-target="#addCatModal">
                <i class="bi bi-plus-lg me-1"></i> Add Category
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
                                    <th>Category Name</th>
                                    <th>Auto-Routed Department</th>
                                    <th>Target SLA</th>
                                    <th>Default Priority</th>
                                    <th>Complaints Filed</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi <?= e($cat['icon_class'] ?: 'bi-tag') ?> fs-5 text-primary"></i>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= e($cat['category_name']) ?></div>
                                                    <small class="text-muted"><?= e($cat['description']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1">
                                                <i class="bi bi-building me-1"></i> <?= e($cat['dept_name']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-primary font-monospace"><?= (int)$cat['sla_hours'] ?> Hours</span>
                                        </td>
                                        <td>
                                            <?= getPriorityBadge($cat['default_priority']) ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark"><?= $cat['total_complaints'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($cat['is_active']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $cat['category_id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" action="categories.php" class="d-inline" onsubmit="return confirm('Toggle status for this category?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-<?= $cat['is_active'] ? 'danger' : 'success' ?>">
                                                        <i class="bi bi-power"></i>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Edit Modal -->
                                            <div class="modal fade text-start" id="editModal<?= $cat['category_id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content rounded-4 border-0">
                                                        <div class="modal-header bg-dark text-white">
                                                            <h5 class="modal-title fw-bold">Edit Category</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="categories.php">
                                                            <div class="modal-body p-4">
                                                                <?= csrfField() ?>
                                                                <input type="hidden" name="action" value="edit_category">
                                                                <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Category Name</label>
                                                                    <input type="text" class="form-control" name="category_name" value="<?= e($cat['category_name']) ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Mapped Department</label>
                                                                    <select class="form-select" name="dept_id" required>
                                                                        <?php foreach ($departments as $d): ?>
                                                                            <option value="<?= $d['dept_id'] ?>" <?= ($cat['dept_id'] == $d['dept_id']) ? 'selected' : '' ?>>
                                                                                <?= e($d['dept_name']) ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>

                                                                <div class="row g-2 mb-3">
                                                                    <div class="col-6">
                                                                        <label class="form-label small fw-bold text-dark">SLA Hours</label>
                                                                        <input type="number" class="form-control" name="sla_hours" value="<?= (int)$cat['sla_hours'] ?>" min="1" required>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label small fw-bold text-dark">Default Priority</label>
                                                                        <select class="form-select" name="default_priority">
                                                                            <option value="Low" <?= ($cat['default_priority'] === 'Low') ? 'selected' : '' ?>>Low</option>
                                                                            <option value="Medium" <?= ($cat['default_priority'] === 'Medium') ? 'selected' : '' ?>>Medium</option>
                                                                            <option value="High" <?= ($cat['default_priority'] === 'High') ? 'selected' : '' ?>>High</option>
                                                                            <option value="Urgent" <?= ($cat['default_priority'] === 'Urgent') ? 'selected' : '' ?>>Urgent</option>
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Bootstrap Icon Class</label>
                                                                    <input type="text" class="form-control font-monospace" name="icon_class" value="<?= e($cat['icon_class']) ?>">
                                                                </div>

                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="is_active" id="act_cat_<?= $cat['category_id'] ?>" value="1" <?= $cat['is_active'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label small" for="act_cat_<?= $cat['category_id'] ?>">Active Category</label>
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-tag-fill me-2 text-info"></i> Add Complaint Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php">
                <div class="modal-body p-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_category">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Category Name *</label>
                        <input type="text" class="form-control" name="category_name" placeholder="e.g. Streetlights and Electricity" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Concerned Municipal Department *</label>
                        <select class="form-select" name="dept_id" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['dept_id'] ?>"><?= e($d['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Committed SLA (Hours) *</label>
                            <input type="number" class="form-control" name="sla_hours" value="48" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-dark">Default Priority</label>
                            <select class="form-select" name="default_priority">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Icon Class (Bootstrap Icons)</label>
                        <input type="text" class="form-control font-monospace" name="icon_class" value="bi-exclamation-triangle">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Brief Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Citizen guidance description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-civic btn-sm">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
