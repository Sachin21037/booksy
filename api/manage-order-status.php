<?php
/**
 * Booksy - API: Manage Order Status & Trigger Commission Reconciliation
 * Transitions order lifecycle states and triggers commission calculations or refund adjustments.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please sign in to update order status.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$orderId   = isset($input['order_id']) ? intval($input['order_id']) : 0;
$newStatus = trim($input['new_status'] ?? '');
$redirect  = trim($input['redirect'] ?? '');
$userId    = $_SESSION['user_id'];
$isAdmin   = is_admin();

if ($orderId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Order ID.']);
    exit;
}

$allowedStatuses = ['Pending', 'Processing', 'Completed', 'Cancelled', 'Refunded'];
if (!in_array($newStatus, $allowedStatuses)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order status transition.']);
    exit;
}

// Verify order exists
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found.']);
    exit;
}

// Check seller or admin permissions
if (!$isAdmin) {
    $checkSeller = $pdo->prepare("
        SELECT COUNT(*) 
        FROM order_items oi
        JOIN books b ON oi.book_id = b.id
        WHERE oi.order_id = ? AND b.seller_id = ?
    ");
    $checkSeller->execute([$orderId, $userId]);
    $isSeller = (bool)$checkSeller->fetchColumn();

    if (!$isSeller && intval($order['buyer_id']) !== $userId) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized to modify this order.']);
        exit;
    }
}

// Handle Status Transitions
$dbStatus = ($newStatus === 'Refunded') ? 'Cancelled' : $newStatus;

if ($newStatus === 'Completed') {
    $upd = $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?");
    $upd->execute([$orderId]);
    
    // Trigger automated backend commission calculation
    $commResult = process_order_completed_commission($pdo, $orderId);
    $msg = "Order #BKY-" . str_pad($orderId, 5, '0', STR_PAD_LEFT) . " marked as Completed. Seller commission processed.";
} elseif ($newStatus === 'Cancelled' || $newStatus === 'Refunded') {
    // Reverse sales and refund commissions
    process_order_refund_reversal($pdo, $orderId, "Order transitioned to $newStatus by User #$userId");
    $msg = "Order #BKY-" . str_pad($orderId, 5, '0', STR_PAD_LEFT) . " has been marked as $newStatus. Sales volume reversed.";
} else {
    $upd = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $upd->execute([$dbStatus, $orderId]);
    $msg = "Order status updated to $newStatus.";
}

if (!empty($redirect)) {
    set_flash('success', $msg);
    header("Location: " . $redirect);
    exit;
}

echo json_encode([
    'status'     => 'success',
    'message'    => $msg,
    'order_id'   => $orderId,
    'new_status' => $newStatus
]);
