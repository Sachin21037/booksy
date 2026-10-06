<?php
/**
 * Booksy - API: Fraud Moderation & Graduated Enforcement Action Engine
 * Handles Level 1 Warning, Level 2 Restriction/Temporary Hold, Level 3 Permanent Suspension,
 * Listing Quarantine, and Report Dismissal.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ensure_fraud_tables($pdo);

// Require Admin or Owner Authorization
if (!is_admin()) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Access Denied: Administrative privileges required to perform moderation actions.'
    ]);
    exit;
}

$adminId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action   = trim($input['action'] ?? '');
$redirect = trim($input['redirect'] ?? '../admin-fraud.php');

function send_mod_response(bool $success, string $message, array $extra = [], ?string $redirect = null) {
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
    $target = $redirect ?: '../admin-fraud.php';
    header("Location: $target");
    exit;
}

switch ($action) {
    case 'apply_penalty':
        $reportId     = intval($input['report_id'] ?? 0);
        $penaltyLevel = trim($input['penalty_level'] ?? 'level1_warning');
        $notes        = trim($input['notes'] ?? 'Action taken by moderator.');
        $targetUserId = intval($input['target_user_id'] ?? 0);
        $targetBookId = intval($input['target_book_id'] ?? 0);

        if ($reportId <= 0 && $targetUserId <= 0 && $targetBookId <= 0) {
            send_mod_response(false, 'Invalid report or target entity specified for penalty.', [], $redirect);
        }

        // If reportId provided, resolve target entities
        $report = null;
        if ($reportId > 0) {
            $report = get_report_details($pdo, $reportId);
            if ($report) {
                $targetUserId = $targetUserId ?: ($report['reported_user_id'] ?? null);
                $targetBookId = $targetBookId ?: ($report['book_id'] ?? null);
            }
        }

        // Safeguard: Cannot penalize Platform Owner
        if ($targetUserId > 0) {
            $checkOwner = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
            $checkOwner->execute([$targetUserId]);
            if ($checkOwner->fetchColumn() === 'owner') {
                send_mod_response(false, 'The Platform Owner account cannot be penalized.', [], $redirect);
            }
        }

        try {
            $pdo->beginTransaction();

            $actionMessage = '';

            if ($penaltyLevel === 'level1_warning') {
                // LEVEL 1: First Warning & Quarantine listing
                if ($targetBookId > 0) {
                    $pdo->prepare("UPDATE books SET status = 'quarantined' WHERE id = ?")->execute([$targetBookId]);
                }
                $actionMessage = "Level 1 Compliance Warning issued. Listing #$targetBookId quarantined from search.";
            } elseif ($penaltyLevel === 'level2_restriction') {
                // LEVEL 2: Temporary Hold / Restriction (Freeze payouts & hide all listings)
                if ($targetUserId > 0) {
                    $pdo->prepare("UPDATE users SET account_status = 'restricted' WHERE id = ?")->execute([$targetUserId]);
                    $pdo->prepare("UPDATE books SET status = 'quarantined' WHERE seller_id = ? AND status = 'available'")->execute([$targetUserId]);
                }
                $actionMessage = "Level 2 Temporary Restriction enforced. Seller #$targetUserId payouts frozen and all public listings quarantined.";
            } elseif ($penaltyLevel === 'level3_ban') {
                // LEVEL 3: Permanent Ban / Suspension
                if ($targetUserId > 0) {
                    $pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE id = ?")->execute([$targetUserId]);
                    $pdo->prepare("UPDATE books SET status = 'removed' WHERE seller_id = ?")->execute([$targetUserId]);
                }
                $actionMessage = "Level 3 Permanent Ban executed. Account #$targetUserId suspended and listings permanently removed.";
            }

            // Update Report Status if linked
            if ($reportId > 0) {
                $updReport = $pdo->prepare("
                    UPDATE `reports` 
                    SET `status` = 'action_taken',
                        `penalty_applied` = ?,
                        `moderation_notes` = ?,
                        `moderated_by` = ?,
                        `moderated_at` = NOW()
                    WHERE `id` = ?
                ");
                $updReport->execute([$penaltyLevel, $notes, $adminId, $reportId]);
            }

            // Log to Audit Trail
            log_moderation_action($pdo, $reportId, $adminId, $targetUserId, $targetBookId, 'apply_penalty', $penaltyLevel, $notes);

            $pdo->commit();
            send_mod_response(true, "<strong>Graduated Enforcement Action Applied:</strong> $actionMessage", [], $redirect);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            send_mod_response(false, 'Database error executing penalty: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'quarantine_listing':
        $bookId = intval($input['book_id'] ?? 0);
        $state  = trim($input['state'] ?? 'quarantine'); // 'quarantine', 'restore', 'remove'
        $notes  = trim($input['notes'] ?? 'Manual listing quarantine toggle.');

        if ($bookId <= 0) {
            send_mod_response(false, 'Invalid listing ID.', [], $redirect);
        }

        try {
            if ($state === 'restore') {
                $newStatus = 'available';
                $msg = "Listing #$bookId has been restored to available status in public catalog.";
            } elseif ($state === 'remove') {
                $newStatus = 'removed';
                $msg = "Listing #$bookId has been permanently removed from catalog.";
            } else {
                $newStatus = 'quarantined';
                $msg = "Listing #$bookId has been quarantined and hidden from public search.";
            }

            $stmt = $pdo->prepare("UPDATE books SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $bookId]);

            log_moderation_action($pdo, null, $adminId, null, $bookId, 'quarantine_listing', 'none', "Status changed to $newStatus. Notes: $notes");

            send_mod_response(true, $msg, ['book_id' => $bookId, 'new_status' => $newStatus], $redirect);
        } catch (PDOException $e) {
            send_mod_response(false, 'Failed to update listing status: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'dismiss_report':
        $reportId = intval($input['report_id'] ?? 0);
        $notes    = trim($input['notes'] ?? 'Report reviewed and dismissed. No fraud policy violation found.');

        if ($reportId <= 0) {
            send_mod_response(false, 'Invalid report ID.', [], $redirect);
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE `reports` 
                SET `status` = 'dismissed',
                    `moderation_notes` = ?,
                    `moderated_by` = ?,
                    `moderated_at` = NOW()
                WHERE `id` = ?
            ");
            $stmt->execute([$notes, $adminId, $reportId]);

            log_moderation_action($pdo, $reportId, $adminId, null, null, 'dismiss_report', 'none', $notes);
            send_mod_response(true, "Report Case #$reportId has been dismissed and closed.", [], $redirect);
        } catch (PDOException $e) {
            send_mod_response(false, 'Database error dismissing report: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'set_investigating':
        $reportId = intval($input['report_id'] ?? 0);
        if ($reportId <= 0) {
            send_mod_response(false, 'Invalid report ID.', [], $redirect);
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE `reports` 
                SET `status` = 'reviewing',
                    `moderated_by` = ?,
                    `moderated_at` = NOW()
                WHERE `id` = ?
            ");
            $stmt->execute([$adminId, $reportId]);
            send_mod_response(true, "Report Case #$reportId is now marked as Under Investigation.", [], $redirect);
        } catch (PDOException $e) {
            send_mod_response(false, 'Failed to update report status: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'resolve_signal':
        $signalId = intval($input['signal_id'] ?? 0);
        $newStatus = trim($input['status'] ?? 'resolved'); // 'acknowledged', 'resolved'

        if ($signalId <= 0) {
            send_mod_response(false, 'Invalid Signal ID.', [], $redirect);
        }

        try {
            $stmt = $pdo->prepare("UPDATE `fraud_signals` SET `status` = ? WHERE `id` = ?");
            $stmt->execute([$newStatus, $signalId]);

            log_moderation_action($pdo, null, $adminId, null, null, 'resolve_signal', 'none', "Signal #$signalId marked as $newStatus.");
            send_mod_response(true, "Heuristic Signal #$signalId marked as " . ucfirst($newStatus) . ".", [], $redirect);
        } catch (PDOException $e) {
            send_mod_response(false, 'Failed to update signal status: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'run_heuristic_scan':
        // Scan all active catalog listings for price anomalies, spam patterns, and keywords
        try {
            $books = $pdo->query("SELECT id FROM books WHERE status = 'available' LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
            $flaggedCount = 0;
            $scannedCount = count($books);

            foreach ($books as $bId) {
                $scan = scan_listing_for_anomalies($pdo, (int)$bId);
                if (!empty($scan['anomalies_found'])) {
                    $flaggedCount++;
                }
            }

            log_moderation_action($pdo, null, $adminId, null, null, 'catalog_heuristic_scan', 'none', "Scanned $scannedCount active listings; flagged $flaggedCount anomalies.");
            send_mod_response(true, "Catalog Heuristic Scan completed. Scanned <strong>$scannedCount</strong> listings; flagged <strong>$flaggedCount</strong> anomalies.", [
                'scanned' => $scannedCount,
                'flagged' => $flaggedCount
            ], $redirect);
        } catch (PDOException $e) {
            send_mod_response(false, 'Heuristic scan failed: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'bulk_action':
        $reportIds = $input['report_ids'] ?? [];
        $bulkType  = trim($input['bulk_type'] ?? '');
        $bulkNotes = trim($input['bulk_notes'] ?? 'Executed via bulk moderation toolbar.');

        if (is_string($reportIds)) {
            $reportIds = explode(',', $reportIds);
        }
        $reportIds = array_filter(array_map('intval', (array)$reportIds));

        if (empty($reportIds)) {
            send_mod_response(false, 'No reports selected for bulk action.', [], $redirect);
        }

        try {
            $pdo->beginTransaction();
            $affected = 0;

            if ($bulkType === 'bulk_reviewing') {
                $inQuery = implode(',', array_fill(0, count($reportIds), '?'));
                $stmt = $pdo->prepare("UPDATE `reports` SET `status` = 'reviewing', `moderated_by` = ?, `moderated_at` = NOW() WHERE `id` IN ($inQuery) AND `status` = 'pending'");
                $stmt->execute(array_merge([$adminId], $reportIds));
                $affected = $stmt->rowCount();
                log_moderation_action($pdo, null, $adminId, null, null, 'bulk_reviewing', 'none', "Marked $affected reports as reviewing. " . $bulkNotes);
                $msg = "Bulk update complete: $affected reports marked as Under Investigation.";
            } elseif ($bulkType === 'bulk_dismiss') {
                $inQuery = implode(',', array_fill(0, count($reportIds), '?'));
                $stmt = $pdo->prepare("UPDATE `reports` SET `status` = 'dismissed', `moderation_notes` = ?, `moderated_by` = ?, `moderated_at` = NOW() WHERE `id` IN ($inQuery)");
                $stmt->execute(array_merge([$bulkNotes, $adminId], $reportIds));
                $affected = $stmt->rowCount();
                log_moderation_action($pdo, null, $adminId, null, null, 'bulk_dismiss', 'none', "Dismissed $affected reports. " . $bulkNotes);
                $msg = "Bulk dismissal complete: $affected reports closed.";
            } elseif ($bulkType === 'bulk_level1_warning') {
                foreach ($reportIds as $rId) {
                    $rep = get_report_details($pdo, $rId);
                    if ($rep) {
                        if (!empty($rep['book_id'])) {
                            $pdo->prepare("UPDATE books SET status = 'quarantined' WHERE id = ?")->execute([$rep['book_id']]);
                        }
                        $pdo->prepare("UPDATE `reports` SET `status` = 'action_taken', `penalty_applied` = 'level1_warning', `moderation_notes` = ?, `moderated_by` = ?, `moderated_at` = NOW() WHERE `id` = ?")->execute([$bulkNotes, $adminId, $rId]);
                        log_moderation_action($pdo, $rId, $adminId, $rep['reported_user_id'] ?? null, $rep['book_id'] ?? null, 'apply_penalty', 'level1_warning', "Bulk L1 Warning: " . $bulkNotes);
                        $affected++;
                    }
                }
                $msg = "Bulk Level 1 Warning executed on $affected reports.";
            } else {
                $pdo->rollBack();
                send_mod_response(false, 'Invalid bulk action type.', [], $redirect);
            }

            $pdo->commit();
            send_mod_response(true, $msg, ['affected' => $affected], $redirect);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            send_mod_response(false, 'Bulk operation error: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'export_csv':
        $type = trim($input['type'] ?? 'audit'); // 'audit' or 'reports'
        
        if ($type === 'audit') {
            $logs = $pdo->query("
                SELECT ml.id, ml.created_at, adm.name AS admin_name, ml.action_type, ml.penalty_level, 
                       u.name AS target_user, b.title AS target_book, ml.notes
                FROM moderation_logs ml
                JOIN users adm ON ml.admin_id = adm.id
                LEFT JOIN users u ON ml.target_user_id = u.id
                LEFT JOIN books b ON ml.target_book_id = b.id
                ORDER BY ml.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=booksy_moderation_audit_' . date('Ymd_His') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Log ID', 'Timestamp', 'Moderator', 'Action Type', 'Penalty Tier', 'Target User', 'Target Book', 'Compliance Notes']);
            foreach ($logs as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['created_at'],
                    $row['admin_name'],
                    $row['action_type'],
                    $row['penalty_level'],
                    $row['target_user'] ?: 'N/A',
                    $row['target_book'] ?: 'N/A',
                    $row['notes']
                ]);
            }
            fclose($output);
            exit;
        } else {
            $reports = $pdo->query("
                SELECT r.id, r.created_at, rep.name AS reporter, sel.name AS reported_seller, 
                       b.title AS reported_book, r.reason, r.risk_level, r.risk_score, r.status, r.penalty_applied, r.details
                FROM reports r
                JOIN users rep ON r.reporter_id = rep.id
                LEFT JOIN users sel ON r.reported_user_id = sel.id
                LEFT JOIN books b ON r.book_id = b.id
                ORDER BY r.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=booksy_fraud_reports_' . date('Ymd_His') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Case #', 'Date Filed', 'Reporter', 'Reported Seller', 'Reported Book', 'Violation Reason', 'Risk Level', 'Risk Score', 'Status', 'Penalty Applied', 'Details']);
            foreach ($reports as $r) {
                fputcsv($output, [
                    $r['id'],
                    $r['created_at'],
                    $r['reporter'],
                    $r['reported_seller'] ?: 'N/A',
                    $r['reported_book'] ?: 'N/A',
                    $r['reason'],
                    $r['risk_level'],
                    $r['risk_score'],
                    $r['status'],
                    $r['penalty_applied'],
                    $r['details']
                ]);
            }
            fclose($output);
            exit;
        }
        break;

    case 'simulate_risk':
        $simReason = trim($input['reason'] ?? 'other');
        $simDetails = trim($input['details'] ?? '');
        $simSellerId = intval($input['seller_id'] ?? 0);
        $simOrderId = intval($input['order_id'] ?? 0);
        $simHasAttachment = !empty($input['has_attachment']);

        $case = [
            'reason'           => $simReason,
            'details'          => $simDetails,
            'reporter_id'      => $adminId,
            'reported_user_id' => $simSellerId,
            'order_id'         => $simOrderId,
            'attachments'      => $simHasAttachment ? ['simulated_proof.jpg'] : []
        ];

        $res = calculate_triage_score($pdo, $case);
        send_mod_response(true, "Risk Score Calculated", $res);
        break;

    default:
        send_mod_response(false, 'Invalid action specified for Moderation API.', [], $redirect);
        break;
}
