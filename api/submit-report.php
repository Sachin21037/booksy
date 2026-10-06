<?php
/**
 * Booksy - API: Buyer Report Submission
 * Allows registered buyers and community members to report fraudulent listings,
 * off-platform payment solicitations, counterfeits, or suspicious seller behavior with evidence attachments.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ensure_fraud_tables($pdo);

// Require authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Please sign in to your Booksy account to report a listing or seller.'
    ]);
    exit;
}

$reporterId = (int)$_SESSION['user_id'];
$redirect   = trim($_POST['redirect'] ?? ($_GET['redirect'] ?? ''));

// Helper for responses
function send_report_response(bool $success, string $message, array $extra = [], ?string $redirect = null) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || isset($_GET['ajax'])) {
        if (!$success) {
            http_response_code(400);
        }
        echo json_encode(array_merge([
            'status'  => $success ? 'success' : 'error',
            'message' => $message
        ], $extra));
        exit;
    }

    set_flash($success ? 'success' : 'danger', $message);
    $target = $redirect ?: ($extra['fallback_url'] ?? '../index.php');
    header("Location: $target");
    exit;
}

// 1. Extract Form Data
$bookId         = isset($_POST['book_id']) && is_numeric($_POST['book_id']) ? intval($_POST['book_id']) : null;
$reportedUserId = isset($_POST['reported_user_id']) && is_numeric($_POST['reported_user_id']) ? intval($_POST['reported_user_id']) : null;
$orderId        = isset($_POST['order_id']) && is_numeric($_POST['order_id']) ? intval($_POST['order_id']) : null;
$reason         = trim($_POST['reason'] ?? '');
$details        = trim($_POST['details'] ?? '');

// If bookId is provided but reportedUserId is not, resolve seller from book
if ($bookId && !$reportedUserId) {
    $bStmt = $pdo->prepare("SELECT seller_id FROM books WHERE id = ? LIMIT 1");
    $bStmt->execute([$bookId]);
    $reportedUserId = (int)$bStmt->fetchColumn() ?: null;
}

// 2. Validate Inputs
$validReasons = [
    'counterfeit',
    'not_received',
    'off_platform_payment',
    'fake_profile',
    'misleading',
    'inappropriate',
    'spam',
    'other'
];

if (!in_array($reason, $validReasons)) {
    send_report_response(false, 'Please select a valid report category from the dropdown.', [], $redirect);
}

if (empty($details) || strlen($details) < 10) {
    send_report_response(false, 'Please provide a clear explanation with at least 10 characters describing the issue.', [], $redirect);
}

// 3. Anti-Abuse Checks

// A. Self-Report Check
if ($reportedUserId === $reporterId) {
    send_report_response(false, 'You cannot file a fraud report against your own account or listing.', [], $redirect);
}

// B. Duplicate Active Report Check (Limit 1 active report per seller/listing)
try {
    $dupSql = "
        SELECT id FROM `reports` 
        WHERE `reporter_id` = ? 
          AND `status` IN ('pending', 'reviewing')
    ";
    $dupParams = [$reporterId];

    if ($bookId) {
        $dupSql .= " AND `book_id` = ?";
        $dupParams[] = $bookId;
    } elseif ($reportedUserId) {
        $dupSql .= " AND `reported_user_id` = ?";
        $dupParams[] = $reportedUserId;
    }

    $dupStmt = $pdo->prepare($dupSql . " LIMIT 1");
    $dupStmt->execute($dupParams);
    if ($dupStmt->fetch()) {
        send_report_response(false, 'You already have an active pending report under investigation for this seller/listing. Our Trust & Safety team is reviewing it.', [], $redirect);
    }
} catch (PDOException $e) {}

// C. Rate Limiting Check (Max 5 submissions per 60 minutes)
try {
    $rateStmt = $pdo->prepare("
        SELECT COUNT(*) FROM `reports` 
        WHERE `reporter_id` = ? AND `created_at` >= NOW() - INTERVAL 1 HOUR
    ");
    $rateStmt->execute([$reporterId]);
    $recentReports = (int)$rateStmt->fetchColumn();

    if ($recentReports >= 5) {
        send_report_response(false, 'Report submission limit reached (maximum 5 reports per hour). Please try again later.', [], $redirect);
    }
} catch (PDOException $e) {}

// 4. Process Multi-File Evidence Attachments (Max 3 files, up to 5MB each)
$uploadedAttachments = [];
if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
    $uploadDir = __DIR__ . '/../uploads/reports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedExts  = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

    $fileCount = min(3, count($_FILES['attachments']['name']));
    for ($i = 0; $i < $fileCount; $i++) {
        $tmpName = $_FILES['attachments']['tmp_name'][$i];
        $origName = $_FILES['attachments']['name'][$i];
        $err = $_FILES['attachments']['error'][$i];
        $size = $_FILES['attachments']['size'][$i];

        if ($err === UPLOAD_ERR_NO_FILE || empty($tmpName)) continue;
        if ($err !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (in_array($ext, $allowedExts) && in_array($mime, $allowedMimes) && $size <= 5 * 1024 * 1024) {
            $newName = 'evidence_' . uniqid() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (move_uploaded_file($tmpName, $uploadDir . $newName)) {
                $uploadedAttachments[] = 'uploads/reports/' . $newName;
            }
        }
    }
}

// 5. Automated Triage Risk Scoring
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$reportData = [
    'reporter_id'      => $reporterId,
    'reported_user_id' => $reportedUserId,
    'book_id'          => $bookId,
    'order_id'         => $orderId,
    'reason'           => $reason,
    'details'          => $details,
    'attachments'      => $uploadedAttachments
];

$triage = calculate_triage_score($pdo, $reportData);
$riskScore = $triage['score'];
$riskLevel = $triage['level'];
$riskSignals = $triage['signals'];

// 6. Save Report into Database
try {
    $stmt = $pdo->prepare("
        INSERT INTO `reports` 
        (`reporter_id`, `reported_user_id`, `book_id`, `order_id`, `reason`, `details`, `attachments`, `risk_score`, `risk_level`, `risk_signals`, `status`, `ip_address`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([
        $reporterId,
        $reportedUserId,
        $bookId,
        $orderId,
        $reason,
        $details,
        !empty($uploadedAttachments) ? json_encode($uploadedAttachments) : null,
        $riskScore,
        $riskLevel,
        !empty($riskSignals) ? json_encode($riskSignals) : null,
        $ipAddress
    ]);

    $reportId = (int)$pdo->lastInsertId();

    // Check report density & auto-quarantine if threshold reached
    if ($reportedUserId) {
        check_report_density($pdo, $reportedUserId, 72);
    }

    $fallbackUrl = $bookId ? "../book-details.php?id={$bookId}" : "../index.php";
    send_report_response(
        true, 
        'Thank you. Your report has been submitted to the Trust & Safety Moderation queue (Case #' . $reportId . '). We take community integrity seriously and will investigate promptly.', 
        ['report_id' => $reportId, 'fallback_url' => $fallbackUrl],
        $redirect
    );
} catch (PDOException $e) {
    send_report_response(false, 'Database error submitting report: ' . $e->getMessage(), [], $redirect);
}
