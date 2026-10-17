<?php
/**
 * CivicTrack — Authentication & Access Control System
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Checks if a user is currently logged in.
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Returns the currently authenticated user's ID or null.
 */
function getUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Returns the currently authenticated user's role (citizen, officer, admin) or null.
 */
function getUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Returns the currently authenticated user's full name or empty string.
 */
function getUserName(): string {
    return $_SESSION['user_name'] ?? 'Guest';
}

/**
 * Returns the user's department ID if role is officer.
 */
function getUserDeptId(): ?int {
    return isset($_SESSION['user_dept_id']) ? (int)$_SESSION['user_dept_id'] : null;
}

/**
 * Check if the logged in user matches given role(s).
 *
 * @param string|array $roles
 * @return bool
 */
function hasRole($roles): bool {
    if (!isLoggedIn()) {
        return false;
    }
    $userRole = getUserRole();
    if (is_array($roles)) {
        return in_array($userRole, $roles, true);
    }
    return $userRole === $roles;
}

/**
 * Returns appropriate dashboard URL for a given role.
 */
function getDashboardUrlByRole(?string $role = null): string {
    if ($role === null) {
        $role = getUserRole();
    }
    $baseUrl = getBaseUrl();
    switch ($role) {
        case 'admin':
            return $baseUrl . 'admin/dashboard.php';
        case 'officer':
            return $baseUrl . 'officer/dashboard.php';
        case 'citizen':
            return $baseUrl . 'citizen/dashboard.php';
        default:
            return $baseUrl . 'login.php';
    }
}

/**
 * Enforces authentication. If not logged in, redirects to login page.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $redirectUrl = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . getBaseUrl() . 'login.php?redirect=' . $redirectUrl);
        exit;
    }
}

/**
 * Enforces role-based authorization. If user does not have permission, redirects or shows 403.
 *
 * @param string|array $allowedRoles
 */
function requireRole($allowedRoles): void {
    requireLogin();

    if (!hasRole($allowedRoles)) {
        http_response_code(403);
        $expected = is_array($allowedRoles) ? implode(', ', $allowedRoles) : $allowedRoles;
        die('
        <div style="font-family:sans-serif; max-width:550px; margin:60px auto; padding:30px; border:1px solid #f87171; border-radius:12px; background:#fff5f5; text-align:center;">
            <div style="font-size:48px; margin-bottom:10px;">🛡️</div>
            <h2 style="color:#b91c1c; margin-top:0;">403 Access Denied</h2>
            <p style="color:#4b5563;">You do not have the required permissions to view this portal. Required role: <strong>' . htmlspecialchars($expected) . '</strong>.</p>
            <a href="' . htmlspecialchars(getDashboardUrlByRole()) . '" style="display:inline-block; margin-top:15px; background:#1e40af; color:#fff; text-decoration:none; padding:10px 20px; border-radius:6px; font-weight:600;">Return to Your Dashboard</a>
        </div>
        ');
    }
}

/**
 * Logs in a user, regenerates session ID to prevent session fixation,
 * and sets session variables.
 *
 * @param array $user User record from MySQL database
 */
function loginUser(array $user): void {
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_phone'] = $user['phone'] ?? '';
    $_SESSION['user_dept_id'] = !empty($user['dept_id']) ? (int)$user['dept_id'] : null;
    $_SESSION['last_login_time'] = time();
}

/**
 * Destroys session and logs out user safely.
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
