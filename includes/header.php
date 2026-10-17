<?php
/**
 * CivicTrack — Shared HTML Header Component
 */
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config/app.php';
}

$pageTitle = $pageTitle ?? APP_NAME . ' — ' . APP_TAGLINE;
$isDashboard = $isDashboard ?? false;
$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CivicTrack is a next-generation Smart City Civic Complaint Management and Resolution Tracking System.">
    <meta name="author" content="Team 4 - Smart City Civic Tech">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Leaflet Maps CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

    <!-- Custom Main Styles -->
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/style.css">
    <?php if ($isDashboard): ?>
        <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/dashboard.css">
    <?php endif; ?>
</head>
<body class="<?= $isDashboard ? 'dashboard-layout' : 'public-layout' ?>">
