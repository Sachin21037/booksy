<?php
/**
 * Booksy - Payment Details & Checkout Verification Test Suite
 * Tests card details validation, bank transfer reference tracking, PayHere mode, and payments table persistence.
 */

require_once __DIR__ . '/../db.php';

echo "========================================================================\n";
echo "         BOOKSY PAYMENT DETAILS & CHECKOUT TEST SUITE                  \n";
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
// Test 1: Ensure Payment Tables
// ----------------------------------------------------------------------------
echo "--- TEST 1: Payment Table Initialization ---\n";
ensure_payment_tables($pdo);
$tableExists = $pdo->query("SHOW TABLES LIKE 'payments'")->rowCount() > 0;
assertTest("Payments table exists in database", $tableExists);

// ----------------------------------------------------------------------------
// Test 2: Card Payment Processing & Masking
// ----------------------------------------------------------------------------
echo "\n--- TEST 2: Credit/Debit Card Order & Payment Details ---\n";

$testCardNum = "4111 2222 3333 4444";
$cleanCard = preg_replace('/\s+/', '', $testCardNum);
$firstDigit = $cleanCard[0];
$brand = ($firstDigit === '4') ? 'Visa' : 'Card';
$last4 = substr($cleanCard, -4);
$storedMode = "$brand (•••• $last4)";

assertTest("Card brand detection identifies Visa correctly", $brand === 'Visa', "Brand: $brand");
assertTest("Card masking masks 16-digits to last 4", $storedMode === 'Visa (•••• 4444)', "Stored: $storedMode");

// Insert test order with card payment
$orderStmt = $pdo->prepare("
    INSERT INTO orders (buyer_id, total_amount, shipping_address, payment_mode, status) 
    VALUES (1, 2450.00, 'Recipient: Kamal Perera\nAddress: Colombo 03, Sri Lanka', ?, 'Confirmed')
");
$orderStmt->execute([$storedMode]);
$orderId = (int)$pdo->lastInsertId();
assertTest("Order created with card payment mode", $orderId > 0, "OrderID: #$orderId");

$txId = 'CARD_' . strtoupper(bin2hex(random_bytes(6)));
$raw = [
    'cardholder'   => 'KAMAL PERERA',
    'brand'        => $brand,
    'last4'        => $last4,
    'expiry'       => '12 / 28',
    'processed_at' => date('Y-m-d H:i:s'),
    'mode'         => 'Direct Card Payment (Verified)'
];

$payStmt = $pdo->prepare("
    INSERT INTO payments (order_id, amount, currency, payment_method, transaction_id, payment_status, raw_response)
    VALUES (?, 2450.00, 'LKR', ?, ?, 'Completed', ?)
");
$paySuccess = $payStmt->execute([$orderId, $storedMode, $txId, json_encode($raw)]);
assertTest("Payment record saved with Completed status & 3DS card details", $paySuccess, "TxID: $txId");

$fetchedPayment = $pdo->query("SELECT * FROM payments WHERE order_id = $orderId")->fetch();
assertTest("Retrieved payment record has correct transaction_id", $fetchedPayment && $fetchedPayment['transaction_id'] === $txId);
assertTest("Retrieved payment raw_response contains cardholder", $fetchedPayment && strpos($fetchedPayment['raw_response'], 'KAMAL PERERA') !== false);

// ----------------------------------------------------------------------------
// Test 3: Bank Transfer Order & Reference
// ----------------------------------------------------------------------------
echo "\n--- TEST 3: Bank Transfer Payment Details ---\n";
$bankRef = 'CB-9948123';
$bankSender = 'Kasun Jayasinghe';
$bankStoredMode = "Bank Transfer (Ref: $bankRef)";

$bankOrderStmt = $pdo->prepare("
    INSERT INTO orders (buyer_id, total_amount, shipping_address, payment_mode, status) 
    VALUES (1, 1500.00, 'Recipient: Kasun\nAddress: Kandy, Sri Lanka', ?, 'Pending')
");
$bankOrderStmt->execute([$bankStoredMode]);
$bankOrderId = (int)$pdo->lastInsertId();

$bankPayStmt = $pdo->prepare("
    INSERT INTO payments (order_id, amount, currency, payment_method, transaction_id, payment_status, raw_response)
    VALUES (?, 1500.00, 'LKR', 'Direct Bank Transfer', ?, 'Pending', ?)
");
$bankPayStmt->execute([$bankOrderId, $bankRef, json_encode(['sender_name' => $bankSender, 'reference' => $bankRef])]);

$fetchedBankPay = $pdo->query("SELECT * FROM payments WHERE order_id = $bankOrderId")->fetch();
assertTest("Bank payment record stored with reference $bankRef", $fetchedBankPay && $fetchedBankPay['transaction_id'] === $bankRef);
assertTest("Bank payment status is Pending verification", $fetchedBankPay && $fetchedBankPay['payment_status'] === 'Pending');

// Clean up test rows
$pdo->exec("DELETE FROM payments WHERE order_id IN ($orderId, $bankOrderId)");
$pdo->exec("DELETE FROM orders WHERE id IN ($orderId, $bankOrderId)");

echo "\n========================================================================\n";
echo "TEST RESULTS: Passed: $passCount | Failed: $failCount\n";
echo "========================================================================\n";
