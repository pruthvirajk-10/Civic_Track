<?php
/**
 * CivicTrack — API: Search Complaints by Reference Number or Keyword
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    jsonResponse(['success' => true, 'data' => []]);
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT complaint_id, reference_number, title, status, ward
        FROM complaints
        WHERE reference_number LIKE ? OR title LIKE ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $term = "%{$q}%";
    $stmt->execute([$term, $term]);
    $results = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $results]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
