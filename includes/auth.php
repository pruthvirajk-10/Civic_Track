<?php
/**
 * CivicTrack — Authentication & Access Control System
 * Strict Role-Based Access Control, Session Security, and Inactivity Timeout
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Session inactivity timeout in seconds (2 hours)
if (!defined('SESSION_TIMEOUT_SECONDS')) {
    define('SESSION_TIMEOUT_SECONDS', 7200);
}

/**
 * Checks if a user is currently logged in and session is not timed out.
 */
function isLoggedIn(): bool {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        return false;
    }

    // Check inactivity timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
        logoutUser();
        return false;
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Returns the currently authenticated user's ID or null.
 */
function getUserId(): ?int {
    return isLoggedIn() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Returns the currently authenticated user's role (citizen, officer, admin) or null.
 */
function getUserRole(): ?string {
    return isLoggedIn() ? (string)$_SESSION['user_role'] : null;
}

/**
 * Returns the currently authenticated user's full name or empty string.
 */
function getUserName(): string {
    return isLoggedIn() ? (string)($_SESSION['user_name'] ?? 'User') : 'Guest';
}

/**
 * Returns the user's department ID if role is officer.
 */
function getUserDeptId(): ?int {
    return (isLoggedIn() && isset($_SESSION['user_dept_id'])) ? (int)$_SESSION['user_dept_id'] : null;
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
        $redirectUrl = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . getBaseUrl() . 'login.php?redirect=' . $redirectUrl);
        exit;
    }
}

/**
 * Enforces role-based authorization. If user does not have permission, terminates with HTTP 403.
 *
 * @param string|array $allowedRoles
 */
function requireRole($allowedRoles): void {
    requireLogin();

    if (!hasRole($allowedRoles)) {
        http_response_code(403);
        $expected = is_array($allowedRoles) ? implode(', ', $allowedRoles) : $allowedRoles;
        die('
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>403 Access Denied — CivicTrack</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        </head>
        <body class="bg-light d-flex align-items-center justify-content-center vh-100">
            <div class="card border-0 shadow-sm rounded-4 text-center p-5 bg-white" style="max-width: 520px;">
                <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px; font-size: 2rem;">
                    <i class="bi bi-shield-x"></i>
                </div>
                <h3 class="fw-bold text-dark mb-2">403 Access Forbidden</h3>
                <p class="text-muted small mb-4">
                    Your account does not have permission to access this portal. Required authorization: <strong class="text-dark">' . htmlspecialchars($expected) . '</strong>.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="' . htmlspecialchars(getDashboardUrlByRole()) . '" class="btn btn-primary px-4 py-2">
                        Return to Your Dashboard
                    </a>
                    <a href="' . htmlspecialchars(getBaseUrl()) . 'logout.php" class="btn btn-outline-secondary px-3 py-2">
                        Logout
                    </a>
                </div>
            </div>
        </body>
        </html>
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
    $_SESSION['last_activity'] = time();
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
