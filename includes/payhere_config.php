<?php
/**
 * Booksy - PayHere Sandbox Payment Gateway Configuration & Utilities
 * Integrated for Sri Lanka LKR Transactions (Visa, MasterCard, AMEX, Genie, eZ Cash, Frimi)
 */

if (!defined('PAYHERE_MERCHANT_ID')) {
    define('PAYHERE_MERCHANT_ID', '1238355');
}

if (!defined('PAYHERE_MERCHANT_SECRET')) {
    define('PAYHERE_MERCHANT_SECRET', 'MzczMjgyMDExMjQwNTM2OTg0MjM3MDU0MDQzMjY3MjU5Mzk4MjU=');
}

if (!defined('PAYHERE_ENV')) {
    define('PAYHERE_ENV', 'sandbox'); // 'sandbox' or 'live'
}

if (!defined('PAYHERE_CHECKOUT_URL')) {
    define('PAYHERE_CHECKOUT_URL', 'https://sandbox.payhere.lk/pay/checkout');
}

if (!defined('PAYHERE_CURRENCY')) {
    define('PAYHERE_CURRENCY', 'LKR');
}

/**
 * Ensure payments table and required order columns exist in database.
 */
function ensure_payment_tables(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `payments` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `order_id` INT UNSIGNED NOT NULL,
              `amount` DECIMAL(10,2) NOT NULL,
              `currency` VARCHAR(10) NOT NULL DEFAULT 'LKR',
              `payment_method` VARCHAR(100) NOT NULL DEFAULT 'Online Payment (PayHere)',
              `transaction_id` VARCHAR(100) NULL,
              `payment_status` ENUM('Pending', 'Completed', 'Failed', 'Cancelled', 'Refunded') NOT NULL DEFAULT 'Pending',
              `raw_response` JSON NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_payments_order` (`order_id`),
              INDEX `idx_payments_tx` (`transaction_id`),
              INDEX `idx_payments_status` (`payment_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Ensure orders table payment_mode and status columns accommodate all modes and statuses
        try {
            $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `payment_mode` VARCHAR(100) NOT NULL DEFAULT 'Cash on Delivery'");
            $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'Pending'");
        } catch (PDOException $e) {}

        $checked = true;
    } catch (PDOException $e) {
        error_log("Payment Table Setup Error: " . $e->getMessage());
    }
}

/**
 * Generate PayHere Checkout Security Hash (MD5)
 * Formula: strtoupper(md5(merchant_id + order_id + number_format(amount, 2, '.', '') + currency + strtoupper(md5(merchant_secret))))
 */
function generate_payhere_hash(string $merchantId, string $orderId, float $amount, string $currency, string $merchantSecret): string {
    $formattedAmount = number_format($amount, 2, '.', '');
    $hashedSecret    = strtoupper(md5($merchantSecret));
    return strtoupper(md5($merchantId . $orderId . $formattedAmount . $currency . $hashedSecret));
}

/**
 * Verify PayHere IPN / Webhook Notification Signature
 * Formula: strtoupper(md5(merchant_id + order_id + payhere_amount + payhere_currency + status_code + strtoupper(md5(merchant_secret))))
 */
function verify_payhere_ipn_signature(string $merchantId, string $orderId, string $payhereAmount, string $payhereCurrency, string $statusCode, string $merchantSecret, string $receivedMd5Sig): bool {
    $hashedSecret = strtoupper(md5($merchantSecret));
    $computedSig  = strtoupper(md5($merchantId . $orderId . $payhereAmount . $payhereCurrency . $statusCode . $hashedSecret));
    return hash_equals($computedSig, strtoupper(trim($receivedMd5Sig)));
}

/**
 * Build complete PayHere payment request payload array for an order.
 */
function build_payhere_payload(PDO $pdo, int $orderId, array $orderData, array $buyerData): array {
    ensure_payment_tables($pdo);

    $amount   = floatval($orderData['total_amount'] ?? 0);
    $currency = PAYHERE_CURRENCY;
    $orderRef = (string)$orderId;

    // Generate security hash
    $hash = generate_payhere_hash(PAYHERE_MERCHANT_ID, $orderRef, $amount, $currency, PAYHERE_MERCHANT_SECRET);

    // Determine base URL dynamically
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/ebusiness/bp final');
    $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    $baseUrl  = $protocol . $host . $scriptDir;

    $returnUrl = $baseUrl . '/order-success.php?order_id=' . $orderId . '&payhere=success';
    $cancelUrl = $baseUrl . '/cart.php?order_id=' . $orderId . '&payhere=cancelled';
    $notifyUrl = $baseUrl . '/api/payhere-notify.php';

    // Split name into first and last name
    $fullName = trim($buyerData['name'] ?? 'Booksy Customer');
    $parts    = explode(' ', $fullName, 2);
    $firstName = $parts[0] ?? 'Booksy';
    $lastName  = $parts[1] ?? 'Customer';

    return [
        'sandbox'         => (PAYHERE_ENV === 'sandbox'),
        'checkout_url'    => PAYHERE_CHECKOUT_URL,
        'merchant_id'     => PAYHERE_MERCHANT_ID,
        'return_url'      => $returnUrl,
        'cancel_url'      => $cancelUrl,
        'notify_url'      => $notifyUrl,
        'order_id'        => $orderRef,
        'items'           => 'Booksy Order #' . $orderId,
        'currency'        => $currency,
        'amount'          => number_format($amount, 2, '.', ''),
        'hash'            => $hash,
        'first_name'      => $firstName,
        'last_name'       => $lastName,
        'email'           => $buyerData['email'] ?? 'customer@booksy.lk',
        'phone'           => $buyerData['phone'] ?? '0771234567',
        'address'         => $buyerData['address'] ?? 'Sri Lanka',
        'city'            => $buyerData['city'] ?? 'Colombo',
        'country'         => 'Sri Lanka'
    ];
}

/**
 * Record or update PayHere payment transaction in database.
 */
function record_payhere_transaction(PDO $pdo, int $orderId, float $amount, ?string $transactionId, string $status, array $rawResponse = []): bool {
    ensure_payment_tables($pdo);
    try {
        $stmt = $pdo->prepare("
            INSERT INTO `payments` (`order_id`, `amount`, `currency`, `payment_method`, `transaction_id`, `payment_status`, `raw_response`)
            VALUES (?, ?, 'LKR', 'Online Payment (PayHere)', ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                `transaction_id` = VALUES(`transaction_id`),
                `payment_status` = VALUES(`payment_status`),
                `raw_response`   = VALUES(`raw_response`),
                `updated_at`     = NOW()
        ");
        return $stmt->execute([
            $orderId,
            $amount,
            $transactionId,
            $status,
            json_encode($rawResponse)
        ]);
    } catch (PDOException $e) {
        error_log("Record Payment Error: " . $e->getMessage());
        return false;
    }
}
