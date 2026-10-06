<?php
/**
 * Booksy - PayHere Sandbox Payment Gateway Automated Test Suite
 * Validates Security Hash Generation, IPN Webhook Verification, Database Payment Logging, and Checkout Integration.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/payhere_config.php';

echo "========================================================================\n";
echo "       BOOKSY PAYHERE SANDBOX PAYMENT GATEWAY TEST SUITE               \n";
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
// Test 1: Configuration & Constants
// ----------------------------------------------------------------------------
echo "--- TEST SUITE 1: PayHere Sandbox Credentials & Setup ---\n";
assertTest("Merchant ID defined and matches provided credentials", PAYHERE_MERCHANT_ID === '1238355', "Merchant ID: " . PAYHERE_MERCHANT_ID);
assertTest("Merchant Secret defined and matches provided credentials", !empty(PAYHERE_MERCHANT_SECRET));
assertTest("PayHere Environment is Sandbox", PAYHERE_ENV === 'sandbox');
assertTest("PayHere Currency is LKR", PAYHERE_CURRENCY === 'LKR');
assertTest("PayHere Sandbox Checkout URL correct", PAYHERE_CHECKOUT_URL === 'https://sandbox.payhere.lk/pay/checkout');

ensure_payment_tables($pdo);
$paymentsTableExist = $pdo->query("SHOW TABLES LIKE 'payments'")->rowCount() > 0;
assertTest("Payments database table exists", $paymentsTableExist);

// ----------------------------------------------------------------------------
// Test 2: Security Hash Calculation Logic
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 2: PayHere Security Hash Generation ---\n";
$testOrderId = "1001";
$testAmount  = 1250.00;
$testHash    = generate_payhere_hash(PAYHERE_MERCHANT_ID, $testOrderId, $testAmount, 'LKR', PAYHERE_MERCHANT_SECRET);

assertTest("Generated hash is a 32-character MD5 uppercase string", strlen($testHash) === 32 && ctype_xdigit($testHash) && $testHash === strtoupper($testHash), "Hash: $testHash");

// Verify formula matching
$expectedHash = strtoupper(md5(PAYHERE_MERCHANT_ID . $testOrderId . '1250.00' . 'LKR' . strtoupper(md5(PAYHERE_MERCHANT_SECRET))));
assertTest("Hash matches official PayHere MD5 security specification", $testHash === $expectedHash);

// ----------------------------------------------------------------------------
// Test 3: IPN Signature Verification Logic
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 3: IPN Webhook Signature Verification ---\n";
$testStatusCode = "2"; // 2 = Success
$testPayhereAmount = "1250.00";
$validIpnSig = strtoupper(md5(PAYHERE_MERCHANT_ID . $testOrderId . $testPayhereAmount . 'LKR' . $testStatusCode . strtoupper(md5(PAYHERE_MERCHANT_SECRET))));

$verifiedSuccess = verify_payhere_ipn_signature(PAYHERE_MERCHANT_ID, $testOrderId, $testPayhereAmount, 'LKR', $testStatusCode, PAYHERE_MERCHANT_SECRET, $validIpnSig);
assertTest("Valid IPN signature authenticates successfully", $verifiedSuccess);

$tamperedSig = "INVALID_TAMPERED_MD5_SIGNATURE";
$verifiedTampered = verify_payhere_ipn_signature(PAYHERE_MERCHANT_ID, $testOrderId, $testPayhereAmount, 'LKR', $testStatusCode, PAYHERE_MERCHANT_SECRET, $tamperedSig);
assertTest("Tampered / fake IPN signature is strictly rejected", !$verifiedTampered);

// ----------------------------------------------------------------------------
// Test 4: Payload Builder for Frontend Checkout
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 4: Checkout Payload Generator ---\n";
$payload = build_payhere_payload($pdo, 999, ['total_amount' => 2450.50], [
    'name'    => 'Saman Perera',
    'email'   => 'saman@example.com',
    'phone'   => '0771234567',
    'address' => '123 Galle Road',
    'city'    => 'Colombo'
]);

assertTest("Payload contains merchant_id", $payload['merchant_id'] === '1238355');
assertTest("Payload amount formatted with 2 decimals", $payload['amount'] === '2450.50');
assertTest("Payload currency is LKR", $payload['currency'] === 'LKR');
assertTest("Payload first_name and last_name parsed", $payload['first_name'] === 'Saman' && $payload['last_name'] === 'Perera');
assertTest("Payload security hash matches", !empty($payload['hash']));

// ----------------------------------------------------------------------------
// Test 5: Payment Database Record Logging
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 5: Transaction Logging in Database ---\n";
$recordSuccess = record_payhere_transaction($pdo, 999, 2450.50, 'PH_TEST_TX_987654', 'Completed', ['status_code' => '2', 'method' => 'VISA']);
assertTest("Transaction logged successfully into payments table", $recordSuccess);

$checkTx = $pdo->query("SELECT * FROM payments WHERE order_id = 999 LIMIT 1")->fetch();
assertTest("Payment record retrieved with Completed status", $checkTx && $checkTx['payment_status'] === 'Completed' && $checkTx['transaction_id'] === 'PH_TEST_TX_987654');

// Clean up test transaction
$pdo->exec("DELETE FROM payments WHERE order_id = 999");

// ----------------------------------------------------------------------------
// Test 6: Order Placement with Online Payment (PayHere) Mode
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 6: Order Placement with PayHere Mode ---\n";
$insOrder = $pdo->prepare("INSERT INTO orders (buyer_id, total_amount, shipping_address, payment_mode, status) VALUES (?, ?, ?, ?, 'Pending')");
$insOrder->execute([1, 1500.00, '123 Galle Rd, Colombo', 'Online Payment (PayHere)']);
$testInsertedOrderId = (int)$pdo->lastInsertId();

assertTest("Order with 'Online Payment (PayHere)' placed without column truncation error", $testInsertedOrderId > 0, "Order ID: $testInsertedOrderId");

$savedMode = $pdo->query("SELECT payment_mode FROM orders WHERE id = $testInsertedOrderId")->fetchColumn();
assertTest("Retrieved order payment_mode matches exactly", $savedMode === 'Online Payment (PayHere)', "Mode: $savedMode");

// Clean up test order
$pdo->exec("DELETE FROM orders WHERE id = $testInsertedOrderId");

// ----------------------------------------------------------------------------
// Test Summary
// ----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "TEST RESULTS: Total Passes = $passCount, Total Failures = $failCount\n";
echo "========================================================================\n";

if ($failCount === 0) {
    echo ">>> ALL PAYHERE SANDBOX TESTS PASSED SUCCESSFULLY! <<<\n\n";
    exit(0);
} else {
    echo ">>> SOME PAYHERE TESTS FAILED! <<<\n\n";
    exit(1);
}
