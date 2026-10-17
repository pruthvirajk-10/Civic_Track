<?php
/**
 * CivicTrack — Secure CSV Report Generator
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$deptId = (int)($_GET['dept_id'] ?? 0);
$categoryId = (int)($_GET['category_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
$ward = trim($_GET['ward'] ?? '');

try {
    $pdo = getDbConnection();

    $sql = "
        SELECT c.reference_number, c.created_at, c.title, cat.category_name, d.dept_name,
               c.priority, c.status, c.ward, c.address, c.sla_deadline, c.resolved_at,
               u.name as citizen_name, u.email as citizen_email, u.phone as citizen_phone,
               o.name as officer_name, f.rating as citizen_rating, f.comments as citizen_comments
        FROM complaints c
        JOIN complaint_categories cat ON c.category_id = cat.category_id
        JOIN departments d ON c.dept_id = d.dept_id
        JOIN users u ON c.citizen_id = u.user_id
        LEFT JOIN users o ON c.assigned_officer_id = o.user_id
        LEFT JOIN feedback f ON c.complaint_id = f.complaint_id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($startDate)) {
        $sql .= " AND DATE(c.created_at) >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if (!empty($endDate)) {
        $sql .= " AND DATE(c.created_at) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    if ($deptId > 0) {
        $sql .= " AND c.dept_id = :dept_id";
        $params[':dept_id'] = $deptId;
    }
    if ($categoryId > 0) {
        $sql .= " AND c.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }
    if (!empty($status)) {
        $sql .= " AND c.status = :status";
        $params[':status'] = $status;
    }
    if (!empty($ward)) {
        $sql .= " AND c.ward = :ward";
        $params[':ward'] = $ward;
    }

    $sql .= " ORDER BY c.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $filename = 'civictrack_report_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Output UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Header row
    fputcsv($output, [
        'Reference Number',
        'Lodged Date',
        'Title',
        'Category',
        'Department',
        'Priority',
        'Current Status',
        'Ward',
        'Address',
        'SLA Deadline',
        'Resolved Date',
        'Citizen Name',
        'Citizen Email',
        'Citizen Phone',
        'Assigned Officer',
        'Citizen Rating (1-5)',
        'Citizen Feedback Comments'
    ]);

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['reference_number'],
            $row['created_at'],
            $row['title'],
            $row['category_name'],
            $row['dept_name'],
            $row['priority'],
            $row['status'],
            $row['ward'],
            $row['address'],
            $row['sla_deadline'],
            $row['resolved_at'] ?? 'N/A',
            $row['citizen_name'],
            $row['citizen_email'],
            $row['citizen_phone'],
            $row['officer_name'] ?? 'Unassigned',
            $row['citizen_rating'] ?? 'Unrated',
            $row['citizen_comments'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die('CSV Export Error: ' . $e->getMessage());
}
