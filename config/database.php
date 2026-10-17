<?php
/**
 * CivicTrack — Database Configuration & PDO Connection
 * Safe for local XAMPP / WAMP / LAMP setups
 */

// Database credentials - customize if your local MySQL has a different password
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'civictrack_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO database connection instance.
 *
 * @return PDO
 * @throws PDOException
 */
function getDbConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error internally in production; for local dev, provide helpful setup advice
            $errorMsg = "Database connection error: " . $e->getMessage();
            error_log($errorMsg);

            // If the database does not exist, hint the user to run setup.php
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                die("
                <div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:25px; border:1px solid #fed7aa; border-radius:12px; background:#fffbeb;'>
                    <h2 style='color:#c2410c; margin-top:0;'>⚠️ Database Not Found</h2>
                    <p>The database <strong>" . htmlspecialchars(DB_NAME) . "</strong> has not been created yet.</p>
                    <p>Please run the automatic installer to initialize the database in 1 click:</p>
                    <a href='setup.php' style='display:inline-block; background:#0284c7; color:#fff; text-decoration:none; padding:10px 18px; border-radius:6px; font-weight:600;'>🚀 Run CivicTrack Setup Installer</a>
                </div>
                ");
            }

            die("
            <div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:25px; border:1px solid #fca5a5; border-radius:12px; background:#fef2f2;'>
                <h2 style='color:#dc2626; margin-top:0;'>❌ MySQL Connection Failed</h2>
                <p>Could not connect to MySQL server at <code>" . htmlspecialchars(DB_HOST) . "</code>.</p>
                <p><strong>Troubleshooting:</strong></p>
                <ul>
                    <li>Make sure <strong>Apache</strong> and <strong>MySQL</strong> are started in your XAMPP Control Panel.</li>
                    <li>Check credentials in <code>config/database.php</code> (Default: user <code>root</code> with empty password).</li>
                </ul>
                <p style='color:#6b7280; font-size:13px;'>Error detail: " . htmlspecialchars($e->getMessage()) . "</p>
            </div>
            ");
        }
    }

    return $pdo;
}
