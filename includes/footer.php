<?php
/**
 * CivicTrack — Shared HTML Footer Component
 */
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config/app.php';
}

$baseUrl = getBaseUrl();
$isDashboard = $isDashboard ?? false;
?>

<?php if (!$isDashboard): ?>
    <!-- Public Footer -->
    <footer class="footer-civic">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="brand-icon">
                            <i class="bi bi-buildings-fill"></i>
                        </span>
                        <span class="fs-4 fw-bold text-white">Civic<span class="text-info">Track</span></span>
                    </div>
                    <p class="small text-slate-400">
                        Smart City Civic Complaint Management & Resolution Tracking System. Empowering citizens with real-time grievance tracking, transparent municipal response, and automated department routing.
                    </p>
                    <div class="d-flex gap-2 text-white">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Team 4 Internship Project</span>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Smart City Tech</span>
                    </div>
                </div>

                <div class="col-6 col-lg-2">
                    <h6 class="text-white fw-bold mb-3">Citizen Links</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="<?= $baseUrl ?>login.php">Citizen Login</a></li>
                        <li class="mb-2"><a href="<?= $baseUrl ?>register.php">Register Account</a></li>
                        <li class="mb-2"><a href="<?= $baseUrl ?>track.php">Track Complaint</a></li>
                        <li class="mb-2"><a href="<?= $baseUrl ?>setup.php">Database Installer</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-3">
                    <h6 class="text-white fw-bold mb-3">Municipal Portals</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="<?= $baseUrl ?>login.php">Department Officer Login</a></li>
                        <li class="mb-2"><a href="<?= $baseUrl ?>login.php">Administrator Console</a></li>
                        <li class="mb-2"><a href="<?= $baseUrl ?>track.php">Live Resolution Tracker</a></li>
                    </ul>
                </div>

                <div class="col-lg-3">
                    <h6 class="text-white fw-bold mb-3">24x7 Citizen Helpline</h6>
                    <p class="small text-slate-400 mb-1"><i class="bi bi-telephone-fill text-info me-2"></i>Toll-Free: 1800-22-2026</p>
                    <p class="small text-slate-400 mb-1"><i class="bi bi-envelope-fill text-info me-2"></i>support@smartcity.gov</p>
                    <p class="small text-slate-400"><i class="bi bi-geo-alt-fill text-info me-2"></i>Municipal Corporation Headquarters</p>
                </div>
            </div>

            <hr class="border-secondary my-4 opacity-25">

            <div class="d-flex flex-wrap align-items-center justify-content-between small text-slate-400">
                <div>&copy; <?= date('Y') ?> CivicTrack — Smart City Civic Complaint Management System. All rights reserved.</div>
                <div class="mt-2 mt-md-0">
                    Developed for <strong>Team 4 Internship Project</strong>
                </div>
            </div>
        </div>
    </footer>
<?php endif; ?>

<!-- Bootstrap 5.3 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Leaflet Map JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<!-- Custom App JS -->
<script src="<?= $baseUrl ?>assets/js/main.js"></script>
<script src="<?= $baseUrl ?>assets/js/complaints.js"></script>
<script src="<?= $baseUrl ?>assets/js/dashboard.js"></script>

</body>
</html>
