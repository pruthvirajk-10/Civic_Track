<?php
/**
 * CivicTrack — Application Configuration
 */

// Set Default Timezone
date_default_timezone_set('Asia/Kolkata');

// App metadata
define('APP_NAME', 'CivicTrack');
define('APP_TAGLINE', 'Smart City Civic Complaint Management & Resolution Tracking System');
define('APP_VERSION', '1.0.0');
define('APP_AUTHOR', 'Team 4 - Smart City Civic Tech');

// File Upload Constraints
define('MAX_FILE_SIZE_BYTES', 5 * 1024 * 1024); // 5 MB max
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Root paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
define('UPLOAD_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('COMPLAINT_UPLOAD_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'complaints');
define('RESOLUTION_UPLOAD_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'resolutions');

// Start secure session if not started
if (session_status() === PHP_SESSION_NONE) {
    // Session security configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // Enable cookie_secure if HTTPS is used
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * Returns dynamic Base URL of the project
 */
function getBaseUrl(): string {
    // Check if script is executed from command line
    if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
        return 'http://localhost/civictrack/';
    }

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Determine the base folder from script path
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    
    // If script is in a subdirectory (citizen, officer, admin, api), navigate to root
    $subfolders = ['/citizen', '/officer', '/admin', '/api'];
    foreach ($subfolders as $sub) {
        if (substr($scriptDir, -strlen($sub)) === $sub) {
            $scriptDir = substr($scriptDir, 0, -strlen($sub));
            break;
        }
    }

    $base = rtrim($protocol . $host . $scriptDir, '/') . '/';
    return $base;
}
