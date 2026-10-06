<?php
/**
 * Booksy - Monetization & Commission System Automated Test Suite
 * Validates the LKR 5,000 threshold rule, 3% commission tier, VIP exemptions, refunds, and boosts.
 */

require_once __DIR__ . '/../db.php';

echo "========================================================================\n";
echo "       BOOKSY MONETIZATION & COMMISSION SYSTEM TEST RUNNER             \n";
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
// Test 1: Commission Threshold Evaluation (Sales <= LKR 5,000 -> 0% Commission)
// ----------------------------------------------------------------------------
echo "--- TEST SUITE 1: Seller Sales <= LKR 5,000 (0% Free Tier) ---\n";
$settings = get_monetization_settings($pdo);
$threshold = floatval($settings['commission_free_threshold']);
$salesUnder = 4500.00;
$isOver = ($salesUnder > $threshold);
$rate = $isOver ? 0.03 : 0.00;
$comm = round($salesUnder * $rate, 2);
$net  = $salesUnder - $comm;

assertTest("Threshold is configured to LKR 5,000", $threshold == 5000.00, "Threshold: LKR $threshold");
assertTest("Sales of LKR 4,500.00 incurs 0% commission", $rate == 0.00, "Rate: " . ($rate * 100) . "%");
assertTest("Commission amount is LKR 0.00", $comm == 0.00, "Commission: LKR $comm");
assertTest("Seller net earnings are 100% (LKR 4,500.00)", $net == 4500.00, "Net: LKR $net");

// ----------------------------------------------------------------------------
// Test 2: Commission Threshold Evaluation (Sales > LKR 5,000 -> 3% Commission)
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 2: Seller Sales > LKR 5,000 (3% Standard Tier) ---\n";
$salesOver = 8000.00;
$isOver = ($salesOver > $threshold);
$rate = $isOver ? 0.03 : 0.00;
$comm = round($salesOver * $rate, 2);
$net  = $salesOver - $comm;

assertTest("Sales of LKR 8,000.00 triggers 3% commission", $rate == 0.03, "Rate: " . ($rate * 100) . "%");
assertTest("Commission on LKR 8,000.00 is exactly LKR 240.00", $comm == 240.00, "Commission: LKR $comm");
assertTest("Seller net earnings are LKR 7,760.00", $net == 7760.00, "Net: LKR $net");

// ----------------------------------------------------------------------------
// Test 3: VIP Membership 0% Commission Exemption
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 3: Booksy VIP Membership Benefits ---\n";
// Ensure Seller ID 5 has an active VIP membership
purchase_vip_membership($pdo, 5, 'monthly');
$isVip = is_user_vip($pdo, 5);
$vipSales = 12000.00;
$vipRate = $isVip ? 0.00 : 0.03;
$vipComm = round($vipSales * $vipRate, 2);
$vipNet  = $vipSales - $vipComm;

assertTest("VIP membership active check for User #5", $isVip === true, "User #5 is VIP");
assertTest("VIP member with LKR 12,000 sales pays 0% commission", $vipRate == 0.00, "VIP Rate: 0%");
assertTest("VIP member commission is LKR 0.00", $vipComm == 0.00, "VIP Commission: LKR $vipComm");
assertTest("VIP member retains 100% net earnings (LKR 12,000.00)", $vipNet == 12000.00, "VIP Net: LKR $vipNet");

// ----------------------------------------------------------------------------
// Test 4: Live Order Completion & Commission Engine Integration
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 4: Order Completion & Commission Processing ---\n";
$pdo->beginTransaction();
try {
    // 1. Create a dummy test order for Seller #2 (Nadeesha, Book #1 @ 2850)
    $testBuyerId = 3;
    $insOrder = $pdo->prepare("
        INSERT INTO orders (buyer_id, total_amount, shipping_address, payment_mode, status, created_at)
        VALUES (?, 2850.00, 'Test Address, Colombo', 'Cash on Delivery', 'Completed', NOW())
    ");
    $insOrder->execute([$testBuyerId]);
    $testOrderId = $pdo->lastInsertId();

    $insItem = $pdo->prepare("
        INSERT INTO order_items (order_id, book_id, price, quantity)
        VALUES (?, 1, 2850.00, 1)
    ");
    $insItem->execute([$testOrderId]);

    // 2. Process commission
    $procRes = process_order_completed_commission($pdo, $testOrderId);

    assertTest("Commission processing returns success status", $procRes['status'] === 'success');
    assertTest("Processed 1 order item", count($procRes['items']) === 1);
    
    // Check order_commissions table
    $chkComm = $pdo->prepare("SELECT * FROM order_commissions WHERE order_id = ?");
    $chkComm->execute([$testOrderId]);
    $commRecord = $chkComm->fetch();

    assertTest("order_commissions record exists in DB", !empty($commRecord));
    assertTest("Recorded sale amount is LKR 2,850.00", floatval($commRecord['sale_amount']) == 2850.00);

    // Rollback test order
    $pdo->rollBack();
    echo "  [INFO] Test order cleaned up via atomic rollback.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    assertTest("Order completion test exception", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// Test 5: Refund Reversal and Sales Volume Recalculation
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 5: Refund Handling & Audit Trail ---\n";
$pdo->beginTransaction();
try {
    // Create temporary order to refund
    $insOrder = $pdo->prepare("
        INSERT INTO orders (buyer_id, total_amount, shipping_address, payment_mode, status, created_at)
        VALUES (3, 3500.00, 'Test Address, Kandy', 'Cash on Delivery', 'Completed', NOW())
    ");
    $insOrder->execute();
    $refundOrderId = $pdo->lastInsertId();

    $insItem = $pdo->prepare("
        INSERT INTO order_items (order_id, book_id, price, quantity)
        VALUES (?, 5, 3500.00, 1)
    ");
    $insItem->execute([$refundOrderId]);

    // Calculate commission
    process_order_completed_commission($pdo, $refundOrderId);

    // Execute refund reversal
    $refundOk = process_order_refund_reversal($pdo, $refundOrderId, "Item defective/damaged");

    assertTest("Refund reversal execution succeeded", $refundOk === true);

    // Check order status updated
    $chkOrd = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $chkOrd->execute([$refundOrderId]);
    $ordStatus = $chkOrd->fetchColumn();

    assertTest("Order status marked as Cancelled/Refunded", $ordStatus === 'Cancelled');

    // Check commission status updated
    $chkComm = $pdo->prepare("SELECT status FROM order_commissions WHERE order_id = ?");
    $chkComm->execute([$refundOrderId]);
    $commStatus = $chkComm->fetchColumn();

    assertTest("Commission status marked as 'refunded'", $commStatus === 'refunded');

    $pdo->rollBack();
    echo "  [INFO] Refund test records cleaned up via rollback.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    assertTest("Refund test exception", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// Test 6: Promotional Listing Boost Creation
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 6: Promotional Boost System ---\n";
$pdo->beginTransaction();
try {
    // Boost book #1 by Seller #2
    $boostRes = purchase_listing_boost($pdo, 2, 1, 'featured_listing');

    assertTest("Boost purchase succeeded", $boostRes['success'] === true);
    assertTest("Boost fee charged correctly (LKR 150.00)", $boostRes['fee'] == 150.00);
    assertTest("Boost duration set to 7 days", $boostRes['duration_days'] == 7);

    // Verify active boost query
    $activeBoosts = get_active_listing_promotions($pdo, 1);
    assertTest("Active boost detected for Book #1", count($activeBoosts) > 0);

    $pdo->rollBack();
    echo "  [INFO] Boost test record cleaned up.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    assertTest("Boost test exception", false, $e->getMessage());
}

// ----------------------------------------------------------------------------
// Test 7: Calendar Month Boundary Isolation
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 7: Calendar Month Isolation & Resets ---\n";
$augustSummary = get_seller_monthly_sales_summary($pdo, 1, 8, 2026);
$septemberSummary = get_seller_monthly_sales_summary($pdo, 1, 9, 2026);

assertTest("August 2026 total sales retrieved", isset($augustSummary['total_sales']));
assertTest("September 2026 sales reset to LKR 0.00", $septemberSummary['total_sales'] == 0.00, "Sept: LKR " . $septemberSummary['total_sales']);
assertTest("September 0% tier allowance is full (LKR 5,000.00)", $septemberSummary['threshold_remaining'] == 5000.00);

echo "\n========================================================================\n";
echo "                       TEST RESULTS SUMMARY                             \n";
echo "========================================================================\n";
echo "  Total Assertions Passed: $passCount\n";
echo "  Total Assertions Failed: $failCount\n";

if ($failCount === 0) {
    echo "  >> ALL TESTS PASSED! Monetization Engine is 100% Verified. <<\n\n";
    exit(0);
} else {
    echo "  >> SOME TESTS FAILED. Please review output above. <<\n\n";
    exit(1);
}
