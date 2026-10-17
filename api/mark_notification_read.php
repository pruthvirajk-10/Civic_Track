<?php
/**
 * CivicTrack — API: Mark Notification Read
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

$userId = getUserId();
$notifId = (int)($_POST['notification_id'] ?? 0);

if ($notifId <= 0) {
    jsonResponse(['success' => false, 'error' => 'Invalid notification ID'], 400);
}

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);
    jsonResponse(['success' => true]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
