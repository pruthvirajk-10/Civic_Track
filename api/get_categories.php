<?php
/**
 * CivicTrack — API: Fetch Categories with SLA & Department Info
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT cat.category_id, cat.category_name, cat.sla_hours, cat.default_priority, cat.icon_class,
               d.dept_id, d.dept_name
        FROM complaint_categories cat
        JOIN departments d ON cat.dept_id = d.dept_id
        WHERE cat.is_active = 1
        ORDER BY cat.category_name ASC
    ");
    $categories = $stmt->fetchAll();
    jsonResponse(['success' => true, 'data' => $categories]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
