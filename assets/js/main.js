/**
 * CivicTrack — Main Client Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
    // Enable Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Enable Bootstrap popovers
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

    // Mobile sidebar toggle handler
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.querySelector('.dashboard-sidebar');
    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            let backdrop = document.querySelector('.sidebar-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'sidebar-backdrop';
                document.body.appendChild(backdrop);
                backdrop.addEventListener('click', function () {
                    sidebar.classList.remove('show');
                    backdrop.remove();
                });
            } else {
                backdrop.remove();
            }
        });
    }

    // Auto-dismiss alerts after 5 seconds if not persistent
    setTimeout(function () {
        const autoAlerts = document.querySelectorAll('.alert.alert-dismissible');
        autoAlerts.forEach(alert => {
            const bsAlert = bootstrap.Alert.getInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        });
    }, 6000);
});
