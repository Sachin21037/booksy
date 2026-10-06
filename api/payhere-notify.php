<?php
/**
 * Booksy - PayHere IPN (Instant Payment Notification) Webhook Endpoint
 * Validates server-to-server payment confirmation callbacks from PayHere Sandbox.
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../db.php';

ensure_payment_tables($pdo);

// Extract POST payload
$merchantId      = trim($_POST['merchant_id'] ?? '');
$orderId         = trim($_POST['order_id'] ?? '');
$paymentId       = trim($_POST['payment_id'] ?? '');
$payhereAmount   = trim($_POST['payhere_amount'] ?? '');
$payhereCurrency = trim($_POST['payhere_currency'] ?? '');
$statusCode      = trim($_POST['status_code'] ?? '');
$receivedMd5Sig  = trim($_POST['md5sig'] ?? '');
$method          = trim($_POST['method'] ?? 'TEST');
$statusMessage   = trim($_POST['status_message'] ?? '');

// Log callback attempt for debugging
$logData = [
    'time'     => date('Y-m-d H:i:s'),
    'order_id' => $orderId,
    'payment_id' => $paymentId,
    'amount'   => $payhereAmount,
    'currency' => $payhereCurrency,
    'status'   => $statusCode,
    'sig'      => $receivedMd5Sig
];

// Validate merchant ID
if ($merchantId !== PAYHERE_MERCHANT_ID) {
    http_response_code(400);
    echo "INVALID_MERCHANT_ID";
    exit;
}

// Verify MD5 Signature
$isValidSignature = verify_payhere_ipn_signature(
    $merchantId,
    $orderId,
    $payhereAmount,
    $payhereCurrency,
    $statusCode,
    PAYHERE_MERCHANT_SECRET,
    $receivedMd5Sig
);

if (!$isValidSignature) {
    http_response_code(400);
    echo "INVALID_SIGNATURE";
    exit;
}

$orderIdInt = intval($orderId);
if ($orderIdInt <= 0) {
    http_response_code(400);
    echo "INVALID_ORDER_ID";
    exit;
}

try {
    if ($statusCode === '2') {
        // Status 2: Payment Successful / Approved
        $pdo->beginTransaction();

        // 1. Update order status to 'Confirmed'
        $updOrder = $pdo->prepare("
            UPDATE `orders` 
            SET `status` = 'Confirmed',
                `payment_mode` = 'Online Payment (PayHere)' 
            WHERE `id` = ?
        ");
        $updOrder->execute([$orderIdInt]);

        // 2. Record in payments table
        record_payhere_transaction(
            $pdo,
            $orderIdInt,
            floatval($payhereAmount),
            $paymentId,
            'Completed',
            $_POST
        );

        $pdo->commit();
        http_response_code(200);
        echo "PAYMENT_SUCCESS_CONFIRMED";
        exit;

    } elseif ($statusCode === '0') {
        // Status 0: Pending
        record_payhere_transaction(
            $pdo,
            $orderIdInt,
            floatval($payhereAmount),
            $paymentId,
            'Pending',
            $_POST
        );
        http_response_code(200);
        echo "PAYMENT_PENDING";
        exit;

    } else {
        // Status -1 (Canceled), -2 (Failed), -3 (Chargedback)
        $pdo->beginTransaction();

        $updOrder = $pdo->prepare("
            UPDATE `orders` 
            SET `status` = 'Cancelled' 
            WHERE `id` = ? AND `status` = 'Pending'
        ");
        $updOrder->execute([$orderIdInt]);

        record_payhere_transaction(
            $pdo,
            $orderIdInt,
            floatval($payhereAmount),
            $paymentId,
            ($statusCode === '-1' ? 'Cancelled' : 'Failed'),
            $_POST
        );

        $pdo->commit();
        http_response_code(200);
        echo "PAYMENT_FAILED_OR_CANCELLED";
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "DATABASE_ERROR: " . $e->getMessage();
    exit;
}
