<?php
/**
 * CivicTrack — User Accounts Management Console
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$baseUrl = getBaseUrl();
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$searchTerm = trim($_GET['search'] ?? '');

$users = [];
$departments = [];
$errors = [];

try {
    $pdo = getDbConnection();

    // Departments for officer assignment
    $departments = $pdo->query("SELECT dept_id, dept_name FROM departments WHERE is_active = 1 ORDER BY dept_name ASC")->fetchAll();

    // Handle User Creation / Edit / Status Toggle
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken()) {
            $errors[] = 'Security token invalid. Please try again.';
        } else {
            $action = $_POST['action'] ?? '';

            if ($action === 'create_officer') {
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $deptId = (int)($_POST['dept_id'] ?? 0);
                $password = $_POST['password'] ?? '';

                if (empty($name) || empty($email) || empty($phone) || $deptId <= 0 || strlen($password) < 6) {
                    $errors[] = 'Please fill all required fields. Password must be at least 6 characters.';
                } else {
                    // Check duplicate email
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $chk->execute([$email]);
                    if ($chk->fetchColumn() > 0) {
                        $errors[] = "An account with email '{$email}' already exists.";
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("
                            INSERT INTO users (name, email, phone, password, role, dept_id, status)
                            VALUES (?, ?, ?, ?, 'officer', ?, 'active')
                        ");
                        $stmt->execute([$name, $email, $phone, $hash, $deptId]);
                        setFlash('success', "Officer account for '{$name}' created successfully.");
                        header('Location: users.php?role=officer');
                        exit;
                    }
                }
            } elseif ($action === 'edit_user') {
                $targetUserId = (int)($_POST['user_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $role = trim($_POST['role'] ?? 'citizen');
                $deptId = !empty($_POST['dept_id']) ? (int)$_POST['dept_id'] : null;
                $status = trim($_POST['status'] ?? 'active');

                if (empty($name) || empty($phone)) {
                    $errors[] = 'Name and phone are required.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, phone = ?, role = ?, dept_id = ?, status = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$name, $phone, $role, $deptId, $status, $targetUserId]);
                    setFlash('success', "User details updated.");
                    header('Location: users.php');
                    exit;
                }
            } elseif ($action === 'toggle_user_status') {
                $targetUserId = (int)($_POST['user_id'] ?? 0);
                // Prevent self-lockout
                if ($targetUserId === getUserId()) {
                    setFlash('error', 'You cannot disable your own active administrator account.');
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE user_id = ?");
                    $stmt->execute([$targetUserId]);
                    setFlash('info', 'User status updated.');
                }
                header('Location: users.php');
                exit;
            }
        }
    }

    // Dynamic Query
    $sql = "
        SELECT u.*, d.dept_name
        FROM users u
        LEFT JOIN departments d ON u.dept_id = d.dept_id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($roleFilter)) {
        $sql .= " AND u.role = :role";
        $params[':role'] = $roleFilter;
    }

    if (!empty($statusFilter)) {
        $sql .= " AND u.status = :status";
        $params[':status'] = $statusFilter;
    }

    if (!empty($searchTerm)) {
        $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search OR u.ward LIKE :search)";
        $params[':search'] = '%' . $searchTerm . '%';
    }

    $sql .= " ORDER BY u.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

$pageTitle = 'User Management — CivicTrack Admin';
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
                    <h5 class="fw-bold text-dark mb-0">User Account Administration</h5>
                    <small class="text-muted">Total accounts: <?= count($users) ?></small>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-primary-civic" data-bs-toggle="modal" data-bs-target="#createOfficerModal">
                <i class="bi bi-person-plus-fill me-1"></i> Add Department Officer
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

            <!-- Filter Controls -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                <div class="card-body p-3">
                    <form method="GET" action="users.php" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" 
                                       placeholder="Search name, email, phone, ward..." value="<?= e($searchTerm) ?>">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <select name="role" class="form-select form-select-sm">
                                <option value="">-- All Roles --</option>
                                <option value="citizen" <?= ($roleFilter === 'citizen') ? 'selected' : '' ?>>Citizens</option>
                                <option value="officer" <?= ($roleFilter === 'officer') ? 'selected' : '' ?>>Department Officers</option>
                                <option value="admin" <?= ($roleFilter === 'admin') ? 'selected' : '' ?>>Administrators</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">-- All Statuses --</option>
                                <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                <option value="suspended" <?= ($statusFilter === 'suspended') ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary-civic w-100">Filter</button>
                            <?php if (!empty($roleFilter) || !empty($statusFilter) || !empty($searchTerm)): ?>
                                <a href="users.php" class="btn btn-sm btn-outline-secondary" title="Clear"><i class="bi bi-x"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card-civic">
                <div class="card-civic-body p-0">
                    <div class="table-responsive">
                        <table class="table table-civic mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Phone</th>
                                    <th>Ward</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($u['name']) ?></div>
                                            <small class="text-muted"><?= e($u['email']) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($u['role'] === 'admin'): ?>
                                                <span class="badge text-white px-2 py-1" style="background:#7c3aed;">Admin</span>
                                            <?php elseif ($u['role'] === 'officer'): ?>
                                                <span class="badge text-white px-2 py-1" style="background:#0d9488;">Officer</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary text-white px-2 py-1">Citizen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small text-muted"><?= e($u['dept_name'] ?? '—') ?></span>
                                        </td>
                                        <td>
                                            <small class="font-monospace text-dark"><?= e($u['phone']) ?></small>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= e($u['ward'] ?? '—') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($u['status'] === 'active'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                            <?php elseif ($u['status'] === 'suspended'): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Suspended</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></small>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['user_id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($u['user_id'] !== getUserId()): ?>
                                                    <form method="POST" action="users.php" class="d-inline" onsubmit="return confirm('Toggle status for this user?');">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="toggle_user_status">
                                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                                        <button type="submit" class="btn btn-outline-<?= ($u['status'] === 'active') ? 'danger' : 'success' ?>" title="Toggle Active">
                                                            <i class="bi bi-power"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Edit User Modal -->
                                            <div class="modal fade text-start" id="editUserModal<?= $u['user_id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content rounded-4 border-0">
                                                        <div class="modal-header bg-dark text-white">
                                                            <h5 class="modal-title fw-bold">Edit Account</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="users.php">
                                                            <div class="modal-body p-4">
                                                                <?= csrfField() ?>
                                                                <input type="hidden" name="action" value="edit_user">
                                                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Email (Read-Only)</label>
                                                                    <input type="text" class="form-control bg-light" value="<?= e($u['email']) ?>" readonly>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Full Name *</label>
                                                                    <input type="text" class="form-control" name="name" value="<?= e($u['name']) ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Phone Number *</label>
                                                                    <input type="text" class="form-control" name="phone" value="<?= e($u['phone']) ?>" required>
                                                                </div>

                                                                <div class="row g-2 mb-3">
                                                                    <div class="col-6">
                                                                        <label class="form-label small fw-bold text-dark">Role</label>
                                                                        <select class="form-select" name="role">
                                                                            <option value="citizen" <?= ($u['role'] === 'citizen') ? 'selected' : '' ?>>Citizen</option>
                                                                            <option value="officer" <?= ($u['role'] === 'officer') ? 'selected' : '' ?>>Officer</option>
                                                                            <option value="admin" <?= ($u['role'] === 'admin') ? 'selected' : '' ?>>Administrator</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label small fw-bold text-dark">Account Status</label>
                                                                        <select class="form-select" name="status">
                                                                            <option value="active" <?= ($u['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                                                                            <option value="inactive" <?= ($u['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                                                            <option value="suspended" <?= ($u['status'] === 'suspended') ? 'selected' : '' ?>>Suspended</option>
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-dark">Assigned Department (For Officers)</label>
                                                                    <select class="form-select" name="dept_id">
                                                                        <option value="">-- None --</option>
                                                                        <?php foreach ($departments as $d): ?>
                                                                            <option value="<?= $d['dept_id'] ?>" <?= ($u['dept_id'] == $d['dept_id']) ? 'selected' : '' ?>>
                                                                                <?= e($d['dept_name']) ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
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

<!-- Create Officer Modal -->
<div class="modal fade" id="createOfficerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-badge-fill me-2 text-info"></i> Create Officer Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <div class="modal-body p-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_officer">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Officer Full Name *</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. Ramesh Kulkarni" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Official Email *</label>
                        <input type="email" class="form-control" name="email" placeholder="officer.roads@civictrack.gov" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Phone Number *</label>
                        <input type="tel" class="form-control" name="phone" placeholder="+91 98200 11223" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Assign to Department *</label>
                        <select class="form-select" name="dept_id" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['dept_id'] ?>"><?= e($d['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Initial Password (At least 6 chars) *</label>
                        <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-teal-civic btn-sm">Create Officer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
