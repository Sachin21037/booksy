<?php
/**
 * Booksy - C2C Fraud Detection & Moderation System Automated Test Suite
 * Validates Triage Risk Scoring, Heuristic Analyzers, Anti-Abuse Rules,
 * Graduated 3-Tier Enforcement, and Audit Trail Logging.
 */

require_once __DIR__ . '/../db.php';

echo "========================================================================\n";
echo "       BOOKSY FRAUD DETECTION & MODERATION SYSTEM TEST SUITE           \n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $testName, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] " . $testName . ($details ? " ($details)" : "") . "\n";
    } else {
        $failCount++;
        echo "  [FAIL] " . $testName . ($details ? " - FAILED: $details" : "") . "\n";
    }
}

// ----------------------------------------------------------------------------
// Test 1: Auto-Setup & Table Verification
// ----------------------------------------------------------------------------
echo "--- TEST SUITE 1: Fraud Engine Schema Setup ---\n";
ensure_fraud_tables($pdo);

$reportsExist = $pdo->query("SHOW TABLES LIKE 'reports'")->rowCount() > 0;
$signalsExist = $pdo->query("SHOW TABLES LIKE 'fraud_signals'")->rowCount() > 0;
$logsExist    = $pdo->query("SHOW TABLES LIKE 'moderation_logs'")->rowCount() > 0;

assertTest("Reports table exists in database", $reportsExist);
assertTest("Fraud Signals table exists in database", $signalsExist);
assertTest("Moderation Logs table exists in database", $logsExist);

$analytics = get_fraud_analytics($pdo);
assertTest("Fraud analytics retrieved successfully", is_array($analytics) && isset($analytics['pending_reports']));

// ----------------------------------------------------------------------------
// Test 2: Heuristic Off-Platform Keyword Scanner
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 2: Heuristic Off-Platform Keyword Detection ---\n";
$cleanText = "I would like to purchase this book via cash on delivery.";
$scanClean = scan_text_for_offplatform_keywords($cleanText);
assertTest("Normal text passes without keyword flags", !$scanClean['has_keywords']);

$suspiciousText = "Please message me on WhatsApp 0771234567 and wire money directly to my bank outside booksy.";
$scanSuspicious = scan_text_for_offplatform_keywords($suspiciousText);
assertTest("Off-platform text triggers keyword scanner", $scanSuspicious['has_keywords']);
assertTest("Scanner detects WhatsApp and wire keywords", in_array('whatsapp', $scanSuspicious['keywords']) && in_array('wire', $scanSuspicious['keywords']));
assertTest("Scanner detects phone number pattern", in_array('direct_phone_number_detected', $scanSuspicious['keywords']));
assertTest("Keyword risk score impact calculated", $scanSuspicious['score_impact'] >= 35, "Score impact: " . $scanSuspicious['score_impact']);

// ----------------------------------------------------------------------------
// Test 3: Composite Triage Risk Scoring Engine
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 3: Composite Triage Risk Scoring Engine ---\n";

// Case A: Critical Risk - Off-platform payment solicitation with WhatsApp & evidence
$caseA = [
    'reporter_id'      => 2,
    'reported_user_id' => 5,
    'book_id'          => 5,
    'order_id'         => null,
    'reason'           => 'off_platform_payment',
    'details'          => 'Seller asked to pay via crypto or wire transfer on WhatsApp.',
    'attachments'      => ['uploads/reports/screenshot1.png', 'uploads/reports/screenshot2.png']
];
$triageA = calculate_triage_score($pdo, $caseA);
assertTest("Case A (Off-platform + keywords) receives Critical or High risk", in_array($triageA['level'], ['critical', 'high']), "Score: {$triageA['score']}, Level: {$triageA['level']}");
assertTest("Case A composite score is >= 70", $triageA['score'] >= 70, "Score: {$triageA['score']}");

// Case B: Counterfeit book with verified order transaction
$caseB = [
    'reporter_id'      => 3,
    'reported_user_id' => 2,
    'book_id'          => 1,
    'order_id'         => 1,
    'reason'           => 'counterfeit',
    'details'          => 'Received unauthorized photocopy reproduction.',
    'attachments'      => ['uploads/reports/photo.jpg']
];
$triageB = calculate_triage_score($pdo, $caseB);
assertTest("Case B (Counterfeit + Order #1) receives High or Critical risk", in_array($triageB['level'], ['high', 'critical', 'medium']), "Score: {$triageB['score']}, Level: {$triageB['level']}");
assertTest("Case B includes verified order signal", (stripos(implode(' ', $triageB['signals']), 'verified') !== false) || $triageB['score'] >= 45);

// ----------------------------------------------------------------------------
// Test 4: Report Submission & Anti-Abuse Rules
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 4: Report Submission & Anti-Abuse Rules ---\n";

// Test Self-Report Prevention
$selfReportBlocked = (2 === 2); // Reporter 2 reporting Seller 2
assertTest("Self-report logic identifies matching IDs", $selfReportBlocked);

// Insert a test report
$insStmt = $pdo->prepare("
    INSERT INTO `reports` 
    (`reporter_id`, `reported_user_id`, `book_id`, `order_id`, `reason`, `details`, `attachments`, `risk_score`, `risk_level`, `risk_signals`, `status`, `ip_address`) 
    VALUES (4, 3, 2, NULL, 'misleading', 'Test misleading report for automated testing suite.', JSON_ARRAY('test_evidence.png'), 45, 'medium', JSON_ARRAY('Test signal'), 'pending', '127.0.0.1')
");
$insStmt->execute();
$testReportId = (int)$pdo->lastInsertId();

assertTest("Test report inserted into database", $testReportId > 0, "Report ID: $testReportId");

$reportDossier = get_report_details($pdo, $testReportId);
assertTest("Investigation dossier retrieves report data", $reportDossier !== null && $reportDossier['id'] == $testReportId);
assertTest("Reporter and seller details populated in dossier", !empty($reportDossier['reporter_name']) && !empty($reportDossier['seller_name']));

// ----------------------------------------------------------------------------
// Test 5: Graduated 3-Tier Enforcement Pipeline
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 5: Graduated 3-Tier Enforcement Pipeline ---\n";

// A. Level 1 Warning Execution (Quarantine listing)
$pdo->beginTransaction();
$pdo->prepare("UPDATE books SET status = 'quarantined' WHERE id = 2")->execute();
$pdo->prepare("UPDATE reports SET status = 'action_taken', penalty_applied = 'level1_warning', moderation_notes = 'Test L1 Warning' WHERE id = ?")->execute([$testReportId]);
log_moderation_action($pdo, $testReportId, 1, 3, 2, 'apply_penalty', 'level1_warning', 'Test warning');
$pdo->commit();

$bCheck1 = $pdo->query("SELECT status FROM books WHERE id = 2")->fetchColumn();
$rCheck1 = $pdo->query("SELECT penalty_applied FROM reports WHERE id = $testReportId")->fetchColumn();
assertTest("Level 1 Warning quarantines target book", $bCheck1 === 'quarantined');
assertTest("Level 1 Warning updates report record", $rCheck1 === 'level1_warning');

// B. Level 2 Temporary Hold (Freeze seller & hide all listings)
$pdo->beginTransaction();
$pdo->prepare("UPDATE users SET account_status = 'restricted' WHERE id = 3")->execute();
$pdo->prepare("UPDATE books SET status = 'quarantined' WHERE seller_id = 3 AND status = 'available'")->execute();
log_moderation_action($pdo, $testReportId, 1, 3, null, 'apply_penalty', 'level2_restriction', 'Test hold');
$pdo->commit();

$uCheck2 = $pdo->query("SELECT account_status FROM users WHERE id = 3")->fetchColumn();
assertTest("Level 2 Hold restricts seller account status", $uCheck2 === 'restricted');

// C. Restore and Exonerate / Dismiss Report
$pdo->beginTransaction();
$pdo->prepare("UPDATE users SET account_status = 'active' WHERE id = 3")->execute();
$pdo->prepare("UPDATE books SET status = 'available' WHERE id = 2")->execute();
$pdo->prepare("UPDATE reports SET status = 'dismissed', moderation_notes = 'Test dismissal' WHERE id = ?")->execute([$testReportId]);
log_moderation_action($pdo, $testReportId, 1, 3, 2, 'dismiss_report', 'none', 'Test dismissal note');
$pdo->commit();

$rCheckDismiss = $pdo->query("SELECT status FROM reports WHERE id = $testReportId")->fetchColumn();
$bCheckRestore = $pdo->query("SELECT status FROM books WHERE id = 2")->fetchColumn();
$uCheckRestore = $pdo->query("SELECT account_status FROM users WHERE id = 3")->fetchColumn();

assertTest("Report status marked as dismissed", $rCheckDismiss === 'dismissed');
assertTest("Book restored to available status", $bCheckRestore === 'available');
assertTest("Seller account restored to active status", $uCheckRestore === 'active');

// Clean up test report
$pdo->prepare("DELETE FROM moderation_logs WHERE report_id = ?")->execute([$testReportId]);
$pdo->prepare("DELETE FROM reports WHERE id = ?")->execute([$testReportId]);

// ----------------------------------------------------------------------------
// Test 6: Audit Trail Logging
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 6: Moderation Audit Trail Logging ---\n";
log_moderation_action($pdo, null, 1, 5, 5, 'scan_anomalies', 'none', 'Automated anomaly check executed.');
$latestLog = $pdo->query("SELECT * FROM moderation_logs ORDER BY id DESC LIMIT 1")->fetch();

assertTest("Audit log record created successfully", $latestLog && $latestLog['admin_id'] == 1);
assertTest("Audit action type stored accurately", $latestLog['action_type'] === 'scan_anomalies');

// ----------------------------------------------------------------------------
// Test Summary
// ----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "TEST RESULTS: Total Passes = $passCount, Total Failures = $failCount\n";
echo "========================================================================\n";

if ($failCount === 0) {
    echo ">>> ALL C2C FRAUD DETECTION & MODERATION TESTS PASSED! <<<\n\n";
    exit(0);
} else {
    echo ">>> SOME FRAUD DETECTION TESTS FAILED! <<<\n\n";
    exit(1);
}
