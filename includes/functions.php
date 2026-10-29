<?php
/**
 * CivicTrack — Core Helper Functions & Utilities
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Escapes HTML output safely to prevent XSS.
 *
 * @param mixed $string
 * @return string
 */
function e($string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sets a flash message to be displayed on next page view.
 *
 * @param string $type success | error | warning | info
 * @param string $message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieves and clears the flash message.
 *
 * @return array|null
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Renders the flash message HTML if present.
 */
function renderFlash(): void {
    $flash = getFlash();
    if ($flash) {
        $type = $flash['type'];
        $bsClass = 'alert-info';
        $icon = 'bi-info-circle-fill';
        if ($type === 'success') {
            $bsClass = 'alert-success';
            $icon = 'bi-check-circle-fill';
        } elseif ($type === 'error' || $type === 'danger') {
            $bsClass = 'alert-danger';
            $icon = 'bi-exclamation-triangle-fill';
        } elseif ($type === 'warning') {
            $bsClass = 'alert-warning';
            $icon = 'bi-exclamation-circle-fill';
        }

        echo '<div class="alert ' . $bsClass . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
        echo '  <i class="bi ' . $icon . ' fs-5 me-2"></i>';
        echo '  <div class="flex-grow-1">' . e($flash['message']) . '</div>';
        echo '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Generates a unique complaint reference number: CT-YYYY-XXXXX
 */
function generateReferenceNumber(PDO $pdo): string {
    $year = date('Y');
    $unique = false;
    $refNumber = '';

    while (!$unique) {
        $random = mt_rand(10000, 99999);
        $candidate = "CT-{$year}-{$random}";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE reference_number = ?");
        $stmt->execute([$candidate]);
        if ($stmt->fetchColumn() == 0) {
            $refNumber = $candidate;
            $unique = true;
        }
    }

    return $refNumber;
}

/**
 * Calculates the SLA deadline based on category SLA hours and priority.
 */
function calculateSlaDeadline(PDO $pdo, int $categoryId, string $priority = 'Medium'): string {
    $stmt = $pdo->prepare("SELECT sla_hours FROM complaint_categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $slaHours = (int)$stmt->fetchColumn();

    if ($slaHours <= 0) {
        $slaHours = 48; // Default 48 hours
    }

    // Priority multiplier
    switch ($priority) {
        case 'Urgent':
            $slaHours = max(12, (int)round($slaHours * 0.5)); // 50% time for urgent
            break;
        case 'High':
            $slaHours = max(18, (int)round($slaHours * 0.75));
            break;
        case 'Low':
            $slaHours = (int)round($slaHours * 1.5);
            break;
    }

    return date('Y-m-d H:i:s', strtotime("+{$slaHours} hours"));
}

/**
 * Determines whether a complaint is overdue.
 */
function isComplaintOverdue(array $complaint): bool {
    if (in_array($complaint['status'], ['Resolved', 'Closed'], true)) {
        return false;
    }
    return strtotime($complaint['sla_deadline']) < time();
}

/**
 * Returns formatted HTML badge for complaint status.
 */
function getStatusBadge(string $status): string {
    switch ($status) {
        case 'Submitted':
            return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-file-earmark-plus me-1"></i>Submitted</span>';
        case 'Under Review':
            return '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1"><i class="bi bi-eye me-1"></i>Under Review</span>';
        case 'Assigned':
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-person-check me-1"></i>Assigned</span>';
        case 'In Progress':
            return '<span class="badge bg-primary text-white px-2 py-1"><i class="bi bi-gear-wide-connected me-1"></i>In Progress</span>';
        case 'Resolved':
            return '<span class="badge bg-success text-white px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Resolved</span>';
        case 'Closed':
            return '<span class="badge bg-secondary text-white px-2 py-1"><i class="bi bi-archive-fill me-1"></i>Closed</span>';
        case 'Reopened':
            return '<span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-arrow-counterclockwise me-1"></i>Reopened</span>';
        default:
            return '<span class="badge bg-light text-dark border px-2 py-1">' . e($status) . '</span>';
    }
}

/**
 * Returns formatted HTML badge for priority.
 */
function getPriorityBadge(string $priority): string {
    switch ($priority) {
        case 'Urgent':
            return '<span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-lightning-fill me-1"></i>Urgent</span>';
        case 'High':
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-arrow-up-circle me-1"></i>High</span>';
        case 'Medium':
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-dash-circle me-1"></i>Medium</span>';
        case 'Low':
            return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="bi bi-arrow-down-circle me-1"></i>Low</span>';
        default:
            return '<span class="badge bg-light text-dark">' . e($priority) . '</span>';
    }
}

/**
 * Inserts an entry into complaint_logs table for audit trail.
 */
function logComplaintActivity(PDO $pdo, int $complaintId, int $userId, string $action, ?string $prevStatus, string $newStatus, ?string $remarks = null, ?string $proofImage = null): bool {
    $stmt = $pdo->prepare("
        INSERT INTO complaint_logs (complaint_id, user_id, action, previous_status, new_status, remarks, proof_image)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$complaintId, $userId, $action, $prevStatus, $newStatus, $remarks, $proofImage]);
}

/**
 * Inserts an in-app notification.
 */
function createNotification(PDO $pdo, int $userId, ?int $complaintId, string $title, string $message): bool {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, complaint_id, title, message, is_read)
        VALUES (?, ?, ?, ?, 0)
    ");
    return $stmt->execute([$userId, $complaintId, $title, $message]);
}

/**
 * Gets count of unread notifications for a user.
 */
function getUnreadNotificationCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Securely handles file uploads (photos, evidence, resolution proof).
 *
 * @param array $file $_FILES['input_name']
 * @param string $subfolder 'complaints' or 'resolutions'
 * @return string Relative URL path to saved file
 * @throws Exception On validation or upload failure
 */
function handleFileUpload(array $file, string $subfolder = 'complaints'): string {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid upload parameters.');
    }

    // Check PHP upload error code
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new Exception('No file was uploaded.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('Exceeded maximum file size limit (5MB).');
        default:
            throw new Exception('Unknown upload error occurred.');
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE_BYTES) {
        throw new Exception('File size exceeds the 5MB limit.');
    }

    // Validate MIME type securely
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, ALLOWED_MIME_TYPES, true)) {
        throw new Exception('Invalid file format. Allowed formats: JPG, PNG, WEBP.');
    }

    // Determine extension
    $extension = '';
    switch ($mime) {
        case 'image/jpeg':
            $extension = 'jpg';
            break;
        case 'image/png':
            $extension = 'png';
            break;
        case 'image/webp':
            $extension = 'webp';
            break;
        default:
            throw new Exception('Unsupported file type.');
    }

    // Destination directory
    $targetDir = ($subfolder === 'resolutions') ? RESOLUTION_UPLOAD_DIR : COMPLAINT_UPLOAD_DIR;
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new Exception('Failed to create upload destination directory.');
        }
    }

    // Generate safe unique filename
    $uniqueName = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetFilePath = $targetDir . DIRECTORY_SEPARATOR . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        throw new Exception('Failed to save uploaded file. Check server folder permissions.');
    }

    return 'uploads/' . $subfolder . '/' . $uniqueName;
}

/**
 * Returns human-readable relative time (e.g., "2 hours ago").
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

/**
 * Formats a timestamp into human-readable date and time.
 */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    return date('d M Y, h:i A', strtotime($datetime));
}

/**
 * Outputs a JSON response and exits.
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Retrieves a system setting by key with a fallback default.
 */
function getSystemSetting(string $key, $default = null) {
    static $settingsCache = null;
    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch()) {
                $settingsCache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Database not reachable or table missing
        }
    }
    return $settingsCache[$key] ?? $default;
}

/**
 * Masks a phone number for privacy display (e.g., +91 9876543210 -> +91 98****3210).
 */
function maskPhoneNumber(?string $phone): string {
    if (!$phone) return 'N/A';
    $clean = trim($phone);
    $len = strlen($clean);
    if ($len <= 5) return $clean;
    $start = substr($clean, 0, max(3, (int)floor($len / 2) - 1));
    $end = substr($clean, -2);
    $stars = str_repeat('*', max(3, $len - strlen($start) - strlen($end)));
    return $start . $stars . $end;
}

/**
 * Dispatches real SMS to physical Indian mobile handsets via Fast2SMS Cellular Gateway.
 *
 * @param string $phone 10-digit Indian mobile number
 * @param string $message Text content
 * @return array ['status' => 'Delivered'|'Failed', 'response' => string]
 */
function dispatchFast2Sms(string $phone, string $message): array {
    $apiKey = 'JHKGENFySEKal93IVQPvpEJJf22SFiBibZzyYIgVG20vR7L9QIZ8UmprI1YA';

    // Normalize phone number to 10 digits
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) === 12 && str_starts_with($cleanPhone, '91')) {
        $cleanPhone = substr($cleanPhone, 2);
    }

    if (strlen($cleanPhone) !== 10) {
        return [
            'status' => 'Failed',
            'response' => 'Invalid mobile number format: ' . $phone
        ];
    }

    $postData = [
        'route' => 'q',
        'message' => $message,
        'language' => 'english',
        'flash' => 0,
        'numbers' => $cleanPhone
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.fast2sms.com/dev/bulkV2');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'authorization: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded',
        'Cache-Control: no-cache'
    ]);

    $responseRaw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            'status' => 'Failed',
            'response' => 'Network error connecting to Fast2SMS: ' . $curlErr
        ];
    }

    $json = json_decode($responseRaw, true);
    if ($httpCode === 200 && !empty($json['return']) && $json['return'] === true) {
        $reqId = $json['request_id'] ?? 'OK';
        return [
            'status' => 'Delivered',
            'response' => "Fast2SMS Gateway: Dispatched (Req ID: {$reqId})"
        ];
    }

    $errorMsg = $json['message'] ?? ($json['status_message'] ?? 'Fast2SMS returned HTTP ' . $httpCode);
    return [
        'status' => 'Failed',
        'response' => 'Fast2SMS Gateway Notice: ' . $errorMsg
    ];
}

/**
 * Dispatches a case status / milestone SMS update to the registered mobile number of the citizen.
 *
 * @param PDO $pdo
 * @param int $complaintId
 * @param string $eventType e.g. 'Status Update', 'Complaint Lodged', 'Officer Assigned', 'Complaint Reopened'
 * @param string|null $customNote Optional progress remarks or officer note
 * @return array Result array with success status, masked phone, and message
 */
function sendCaseSmsUpdate(PDO $pdo, int $complaintId, string $eventType, ?string $customNote = null): array {
    try {
        // Fetch complaint & citizen registered mobile
        $stmt = $pdo->prepare("
            SELECT c.complaint_id, c.reference_number, c.title, c.status, c.priority, c.sla_deadline,
                   u.user_id as citizen_id, u.name as citizen_name, u.phone as citizen_phone,
                   d.dept_name,
                   off.name as officer_name
            FROM complaints c
            JOIN users u ON c.citizen_id = u.user_id
            LEFT JOIN departments d ON c.dept_id = d.dept_id
            LEFT JOIN users off ON c.assigned_officer_id = off.user_id
            WHERE c.complaint_id = ?
        ");
        $stmt->execute([$complaintId]);
        $comp = $stmt->fetch();

        if (!$comp || empty($comp['citizen_phone'])) {
            return [
                'success' => false,
                'reason' => 'Citizen has no registered mobile number on record.'
            ];
        }

        $citizenName = trim($comp['citizen_name']);
        $phone = trim($comp['citizen_phone']);
        $ref = $comp['reference_number'];
        $status = $comp['status'];
        $baseUrl = getBaseUrl();
        $trackUrl = $baseUrl . 'track.php?ref=' . urlencode($ref);
        $appName = getSystemSetting('app_name', 'CivicTrack');
        $muniName = getSystemSetting('municipality_name', 'PMC');

        // Build clean SMS message body
        switch ($eventType) {
            case 'Complaint Lodged':
                $smsText = "Dear {$citizenName}, your grievance #{$ref} ('" . substr($comp['title'], 0, 30) . "...') has been registered with {$muniName}. SLA Target: " . formatDateTime($comp['sla_deadline']) . ". Track: {$trackUrl}";
                break;

            case 'Status Update':
                $noteText = !empty($customNote) ? " Note: " . trim($customNote) : "";
                $smsText = "Dear {$citizenName}, status of your grievance #{$ref} is updated to '{$status}'.{$noteText} Track: {$trackUrl} - {$muniName}";
                break;

            case 'Officer Assigned':
                $officerInfo = !empty($comp['officer_name']) ? "assigned to Officer {$comp['officer_name']}" : "assigned to the field crew";
                $smsText = "Dear {$citizenName}, grievance #{$ref} is now {$officerInfo} for resolution. Track: {$trackUrl} - {$muniName}";
                break;

            case 'Complaint Reopened':
                $reopenNote = !empty($customNote) ? " Reason: " . trim($customNote) : "";
                $smsText = "Dear {$citizenName}, grievance #{$ref} has been marked as reopened and escalated back to {$comp['dept_name']}.{$reopenNote} Track: {$trackUrl}";
                break;

            case 'Administrative Override':
                $noteText = !empty($customNote) ? " Remarks: " . trim($customNote) : "";
                $smsText = "Dear {$citizenName}, municipal administration updated ticket #{$ref}. Status: '{$status}'.{$noteText} Track: {$trackUrl}";
                break;

            default:
                $noteText = !empty($customNote) ? " Details: " . trim($customNote) : "";
                $smsText = "Dear {$citizenName}, update on grievance #{$ref}: {$eventType}. Status: '{$status}'.{$noteText} Track: {$trackUrl} - {$muniName}";
                break;
        }

        // Dispatch real SMS using Fast2SMS Cellular Gateway API
        $dispatchResult = dispatchFast2Sms($phone, $smsText);
        $gatewayStatus = $dispatchResult['status'];
        $gatewayResponse = $dispatchResult['response'];

        // Log to sms_logs table
        $insStmt = $pdo->prepare("
            INSERT INTO sms_logs (
                complaint_id, recipient_user_id, recipient_name, recipient_phone,
                event_type, message, status, gateway_response
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insStmt->execute([
            $complaintId,
            (int)$comp['citizen_id'],
            $citizenName,
            $phone,
            $eventType,
            $smsText,
            $gatewayStatus,
            $gatewayResponse
        ]);

        $smsId = (int)$pdo->lastInsertId();

        return [
            'success' => true,
            'sms_id' => $smsId,
            'phone' => $phone,
            'masked_phone' => maskPhoneNumber($phone),
            'message' => $smsText,
            'created_at' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        error_log('SMS Dispatch Error: ' . $e->getMessage());
        return [
            'success' => false,
            'reason' => $e->getMessage()
        ];
    }
}

/**
 * Retrieves SMS audit history for a specific complaint.
 */
function getComplaintSmsLogs(PDO $pdo, int $complaintId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM sms_logs 
            WHERE complaint_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$complaintId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}


