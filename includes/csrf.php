<?php
/**
 * CivicTrack — Cross-Site Request Forgery (CSRF) Protection
 */

/**
 * Generates or retrieves the current user session CSRF token.
 *
 * @return string
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden input field containing the CSRF token.
 *
 * @return string
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validates the submitted CSRF token against the session token.
 *
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Helper to enforce CSRF token validity or terminate with HTTP 403.
 */
function validateCsrfOrDie(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken()) {
            http_response_code(403);
            die('
            <div style="font-family:sans-serif; max-width:500px; margin:50px auto; padding:25px; border:1px solid #f87171; border-radius:10px; background:#fef2f2;">
                <h3 style="color:#b91c1c; margin-top:0;">403 Forbidden: Invalid Security Token</h3>
                <p>Your session may have expired or the request security token was invalid. Please refresh the page and try again.</p>
                <a href="javascript:history.back()" style="color:#2563eb; text-decoration:underline;">&larr; Go Back</a>
            </div>
            ');
        }
    }
}
