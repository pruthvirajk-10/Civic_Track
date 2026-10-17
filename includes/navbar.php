<?php
/**
 * CivicTrack — Public Navigation Bar
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

$baseUrl = getBaseUrl();
$loggedIn = isLoggedIn();
$userRole = getUserRole();
?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-civic sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= $baseUrl ?>index.php">
            <span class="brand-icon">
                <i class="bi bi-buildings-fill"></i>
            </span>
            <span>Civic<span class="text-info">Track</span></span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#civicNavbar" aria-controls="civicNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="civicNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>index.php#how-it-works">How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>index.php#categories">Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>track.php">
                        <i class="bi bi-search me-1 text-info"></i> Track Complaint
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if ($loggedIn): ?>
                    <a href="<?= getDashboardUrlByRole() ?>" class="btn btn-sm btn-primary-civic">
                        <i class="bi bi-speedometer2 me-1"></i> My Dashboard
                    </a>
                    <a href="<?= $baseUrl ?>logout.php" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>login.php" class="btn btn-sm btn-outline-light px-3">
                        <i class="bi bi-person-fill me-1"></i> Login
                    </a>
                    <a href="<?= $baseUrl ?>register.php" class="btn btn-sm btn-teal-civic px-3">
                        <i class="bi bi-person-plus-fill me-1"></i> Register
                    </a>
                    <a href="<?= $baseUrl ?>setup.php" class="btn btn-sm btn-outline-secondary text-light px-2" title="Database Setup Installer">
                        <i class="bi bi-gear-fill"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
