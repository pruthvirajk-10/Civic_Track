<?php
/**
 * CivicTrack — API: Fetch Live City Analytics
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDbConnection();

    // Summary
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status IN ('Submitted', 'Under Review', 'Assigned', 'In Progress') THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status NOT IN ('Resolved', 'Closed') AND sla_deadline < NOW() THEN 1 ELSE 0 END) as overdue
        FROM complaints
    ");
    $stats = $stmt->fetch();

    jsonResponse(['success' => true, 'data' => $stats]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
