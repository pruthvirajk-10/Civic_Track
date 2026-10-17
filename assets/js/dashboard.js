/**
 * CivicTrack — Dashboard Charts & Analytics Scripts
 */

// Initialize Category Doughnut Chart
function initCategoryChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#1d4ed8', '#0d9488', '#f59e0b', '#7c3aed', '#0284c7', '#10b981', '#64748b'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 12, font: { family: 'Plus Jakarta Sans', size: 12 } }
                }
            },
            cutout: '68%'
        }
    });
}

// Initialize Status Distribution Chart
function initStatusChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Complaints',
                data: data,
                backgroundColor: [
                    '#93c5fd', '#67e8f9', '#fde047', '#3b82f6', '#22c55e', '#94a3b8', '#f87171'
                ],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
}

// Initialize Trend Line Chart
function initTrendChart(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Complaints Submitted',
                data: data,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointBackgroundColor: '#2563eb'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
}

// Initialize Department Performance Horizontal Bar Chart
function initDeptChart(canvasId, labels, totalData, resolvedData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || typeof Chart === 'undefined') return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Received',
                    data: totalData,
                    backgroundColor: '#93c5fd',
                    borderRadius: 4
                },
                {
                    label: 'Resolved',
                    data: resolvedData,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12 } }
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
}

// Mark in-app notification as read via AJAX
function markNotificationRead(notifId, targetUrl = null) {
    fetch('../api/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + encodeURIComponent(notifId)
    })
    .then(res => res.json())
    .then(data => {
        if (targetUrl) {
            window.location.href = targetUrl;
        } else {
            const notifItem = document.getElementById('notif-item-' + notifId);
            if (notifItem) {
                notifItem.classList.remove('bg-light');
                notifItem.classList.add('opacity-75');
            }
        }
    })
    .catch(err => {
        if (targetUrl) window.location.href = targetUrl;
    });
}
