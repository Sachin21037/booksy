<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

$page_title = 'Order Confirmation #' . $order_id;

// Fetch order
$order_stmt = $pdo->prepare("
    SELECT o.*, u.name AS buyer_account_name, u.email AS buyer_account_email 
    FROM orders o 
    LEFT JOIN users u ON o.buyer_id = u.id 
    WHERE o.id = ? 
    LIMIT 1
");
$order_stmt->execute([$order_id]);
$order = $order_stmt->fetch();

if (!$order) {
    set_flash('danger', 'Order not found.');
    header("Location: index.php");
    exit;
}

// Check for PayHere simulation or callback update
if (isset($_GET['payhere'])) {
    if ($_GET['payhere'] === 'simulated_success' || $_GET['payhere'] === 'success' || $_GET['payhere'] === 'completed') {
        if ($order['status'] === 'Pending') {
            $pdo->prepare("UPDATE orders SET status = 'Confirmed', payment_mode = 'Online Payment (PayHere)' WHERE id = ?")->execute([$order_id]);
            $order['status'] = 'Confirmed';
            $order['payment_mode'] = 'Online Payment (PayHere)';
            record_payhere_transaction($pdo, $order_id, floatval($order['total_amount']), 'PH_SANDBOX_' . time(), 'Completed', ['simulated' => true]);
        }
    }
}

// Fetch payment record if exists
$payment_info = null;
try {
    ensure_payment_tables($pdo);
    $p_stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $p_stmt->execute([$order_id]);
    $payment_info = $p_stmt->fetch();
} catch (PDOException $e) {}

// Fetch order items
$items_stmt = $pdo->prepare("
    SELECT oi.*, b.title, b.author, b.image_url, b.book_condition, c.name AS category_name, u.name AS seller_name, u.phone AS seller_phone
    FROM order_items oi
    JOIN books b ON oi.book_id = b.id
    JOIN categories c ON b.category_id = c.id
    JOIN users u ON b.seller_id = u.id
    WHERE oi.order_id = ?
");
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Order Success Header Banner -->
<div class="hero-banner-subpage mb-4">
    <div class="container text-center">
        <span class="badge bg-teal text-white fw-bold px-3 py-1 mb-2 d-inline-flex align-items-center rounded-pill">
            <i class="bi bi-patch-check-fill me-1"></i> Transaction Complete
        </span>
        <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">Order Confirmed</h1>
        <p class="text-white-50 mb-0">Order Reference: <strong>#BKY-<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></strong></p>
    </div>
</div>

<div class="container py-3 py-lg-4">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <!-- Success Notification Card -->
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 border">
                <div class="bg-navy text-white text-center p-4 position-relative" style="border-bottom: 3px solid var(--teal-primary);">
                    <div class="rounded-circle bg-teal-light text-teal d-inline-flex align-items-center justify-content-center mb-2 shadow" style="width: 65px; height: 65px; border: 2px solid var(--teal-primary);">
                        <i class="bi bi-check-lg display-6 fw-bold text-teal"></i>
                    </div>
                    <h3 class="fw-bold mb-1 text-white font-serif-title">Thank You For Your Order!</h3>
                    <p class="lead mb-0 text-white-50 fs-6">Receipt & Delivery Details are shown below</p>
                </div>

                <div class="card-body p-3 p-md-4 p-lg-5 bg-white">
                    
                    <!-- Visual Order Progress Timeline Stepper -->
                    <div class="order-stepper">
                        <div class="step-node completed">
                            <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                            <div class="step-label">Order Placed</div>
                        </div>
                        <div class="step-node active">
                            <div class="step-circle">2</div>
                            <div class="step-label">Seller Dispatching</div>
                        </div>
                        <div class="step-node">
                            <div class="step-circle">3</div>
                            <div class="step-label">In Transit via Courier</div>
                        </div>
                        <div class="step-node">
                            <div class="step-circle">4</div>
                            <div class="step-label">Delivered</div>
                        </div>
                    </div>

                    <div class="alert alert-light border d-flex align-items-center mb-4 rounded-3">
                        <i class="bi bi-bell-fill text-teal fs-4 me-3"></i>
                        <div class="small text-secondary">
                            Your seller has been notified to prepare and dispatch the book package. You can inspect the books upon courier arrival before paying.
                        </div>
                    </div>

                    <!-- Itemized Breakdown -->
                    <h5 class="fw-bold mb-3 text-navy"><i class="bi bi-receipt me-2 text-teal"></i>Purchased Books</h5>
                    <div class="table-responsive mb-4">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Book Item</th>
                                    <th>Seller</th>
                                    <th>Condition</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <?php
                                    $thumb = !empty($item['image_url']) && file_exists(__DIR__ . '/uploads/' . $item['image_url']) 
                                        ? 'uploads/' . htmlspecialchars($item['image_url']) 
                                        : 'uploads/default_book.svg';
                                    $qty = isset($item['quantity']) ? max(1, intval($item['quantity'])) : 1;
                                    $line_total = floatval($item['price']) * $qty;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2.5">
                                                <img src="<?= $thumb ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="rounded" style="width: 40px; height: 50px; object-fit: contain; background: #17324D;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                                                <div>
                                                    <div class="fw-bold text-navy"><?= htmlspecialchars($item['title']) ?></div>
                                                    <div class="small text-muted">By <?= htmlspecialchars($item['author']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small">
                                            <div class="fw-semibold text-navy"><?= htmlspecialchars($item['seller_name']) ?></div>
                                            <div class="text-muted"><?= htmlspecialchars($item['seller_phone'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-navy border"><?= htmlspecialchars($item['book_condition']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-navy border fw-bold px-2.5 py-1"><?= $qty ?></span>
                                        </td>
                                        <td class="text-end fw-bold text-navy">
                                            Rs. <?= number_format($line_total, 2) ?>
                                            <?php if ($qty > 1): ?>
                                                <div class="small text-muted fw-normal" style="font-size: 0.75rem;">(Rs. <?= number_format($item['price'], 2) ?> ea)</div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="fw-bold text-end text-navy">Total Amount:</td>
                                    <td class="text-end fw-bold fs-5 text-teal">Rs. <?= number_format($order['total_amount'], 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Shipping & Payment Information Details -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100 border">
                                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Delivery Details</h6>
                                <p class="small text-secondary mb-0" style="white-space: pre-line;">
                                    <?= htmlspecialchars($order['shipping_address']) ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100 border">
                                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-wallet2 text-teal me-1"></i> Payment Details</h6>
                                <p class="small text-secondary mb-1">
                                    Method: <strong><?= htmlspecialchars($order['payment_mode']) ?></strong>
                                </p>
                                <p class="small text-secondary mb-1">
                                    Order Status: <span class="badge bg-<?= $order['status'] === 'Confirmed' ? 'success' : ($order['status'] === 'Cancelled' ? 'danger' : 'warning text-dark') ?>"><?= htmlspecialchars($order['status']) ?></span>
                                </p>
                                <?php if (!empty($payment_info)): ?>
                                    <?php 
                                    $raw_p = !empty($payment_info['raw_response']) ? json_decode($payment_info['raw_response'], true) : [];
                                    $p_stat = $payment_info['payment_status'] ?? 'Pending';
                                    ?>
                                    <div class="small text-muted border-top pt-2 mt-2">
                                        <?php if (stripos($order['payment_mode'], 'Visa') !== false || stripos($order['payment_mode'], 'MasterCard') !== false || stripos($order['payment_mode'], 'AMEX') !== false || stripos($order['payment_mode'], 'Card') !== false): ?>
                                            <div>Payment Status: <span class="badge bg-success"><i class="bi bi-shield-fill-check me-1"></i> Paid (Direct Card 3DS)</span></div>
                                            <?php if (!empty($raw_p['cardholder'])): ?>
                                                <div class="mt-1" style="font-size: 0.72rem;">Cardholder: <strong><?= htmlspecialchars($raw_p['cardholder']) ?></strong></div>
                                            <?php endif; ?>
                                        <?php elseif (stripos($order['payment_mode'], 'Bank Transfer') !== false): ?>
                                            <div>Payment Status: <span class="badge bg-info text-dark"><i class="bi bi-bank me-1"></i> Escrow Verification Pending</span></div>
                                            <?php if (!empty($raw_p['sender_name'])): ?>
                                                <div class="mt-1" style="font-size: 0.72rem;">Depositor: <strong><?= htmlspecialchars($raw_p['sender_name']) ?></strong></div>
                                            <?php endif; ?>
                                        <?php elseif (stripos($order['payment_mode'], 'PayHere') !== false): ?>
                                            <div>Payment Status: <span class="badge bg-teal text-white"><i class="bi bi-patch-check-fill me-1"></i> Paid (PayHere Verified)</span></div>
                                        <?php else: ?>
                                            <div>Payment Status: <span class="badge bg-secondary"><i class="bi bi-cash-stack me-1"></i> Payable at Doorstep</span></div>
                                        <?php endif; ?>
                                        <div class="mt-0.5" style="font-size: 0.72rem;">Transaction Ref: <code><?= htmlspecialchars($payment_info['transaction_id'] ?? 'N/A') ?></code></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center pt-2">
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="bi bi-printer me-1"></i> Print Formal Receipt
                        </button>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="my-orders.php" class="btn btn-outline-dark">
                                <i class="bi bi-box-seam me-1"></i> View Order History
                            </a>
                            <a href="index.php" class="btn btn-booksy-primary fw-bold">
                                <i class="bi bi-grid me-1"></i> Continue Browsing
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
