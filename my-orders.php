<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'My Orders';

if (!is_logged_in()) {
    set_flash('warning', 'Please sign in to view your order history.');
    header("Location: login.php?redirect=my-orders.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_search = trim($_GET['search'] ?? '');

// Fetch buyer orders
$sql = "
    SELECT o.*, COUNT(oi.id) AS item_count 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.buyer_id = ?
";
$params = [$user_id];

if (!empty($order_search)) {
    $sql .= " AND (o.id = ? OR o.payment_mode LIKE ? OR o.status LIKE ?)";
    $params[] = intval($order_search);
    $params[] = "%$order_search%";
    $params[] = "%$order_search%";
}

$sql .= " GROUP BY o.id, o.buyer_id, o.total_amount, o.shipping_address, o.payment_mode, o.status, o.created_at ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- My Orders Header Banner -->
<div class="hero-banner-subpage mb-4">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-teal text-white fw-bold px-3 py-1 mb-2 d-inline-flex align-items-center rounded-pill">
                    <i class="bi bi-box-seam me-1"></i> Purchase History & Tracking
                </span>
                <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">My Orders & Purchases</h1>
                <p class="text-white-50 mb-0">Track your book deliveries and view itemized order receipts</p>
            </div>
            <a href="index.php" class="btn btn-booksy-primary fw-bold px-4 py-2.5 shadow rounded-pill">
                <i class="bi bi-cart-plus me-1"></i> Browse More Books
            </a>
        </div>
    </div>
</div>

<div class="container py-3 py-lg-4">

    <!-- Search / Filter Orders Bar -->
    <div class="card border-0 shadow-sm p-3 rounded-4 bg-white mb-3 border">
        <form method="GET" action="my-orders.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by Order ID or status..." value="<?= htmlspecialchars($order_search) ?>">
                    <button type="submit" class="btn btn-teal">Search</button>
                </div>
            </div>
            <?php if (!empty($order_search)): ?>
                <div class="col-md-3">
                    <a href="my-orders.php" class="btn btn-sm btn-link text-danger text-decoration-none">Clear Search</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white border">
        <div class="card-body p-0">
            <?php if (empty($orders)): ?>
                <div class="p-5 text-center">
                    <i class="bi bi-bag-x text-muted mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-navy">No orders found</h5>
                    <p class="text-muted small mb-3">You haven't placed any book orders yet or no orders match your search.</p>
                    <a href="index.php" class="btn btn-booksy-primary fw-bold">Explore Marketplace</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Order #</th>
                                <th>Date Placed</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Delivery Status</th>
                                <th class="text-end pe-4">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $ord): ?>
                                <tr>
                                    <td class="ps-4 fw-bold">
                                        <a href="order-success.php?order_id=<?= $ord['id'] ?>" class="text-navy text-decoration-none">
                                            #BKY-<?= str_pad($ord['id'], 5, '0', STR_PAD_LEFT) ?>
                                        </a>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('M d, Y h:i A', strtotime($ord['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-navy border"><?= $ord['item_count'] ?> Book<?= $ord['item_count'] > 1 ? 's' : '' ?></span>
                                    </td>
                                    <td class="fw-bold text-teal">
                                        Rs. <?= number_format($ord['total_amount'], 2) ?>
                                    </td>
                                    <td class="small">
                                        <?php
                                        $p_icon = 'bi-wallet2 text-teal';
                                        if (stripos($ord['payment_mode'], 'Cash on Delivery') !== false) {
                                            $p_icon = 'bi-cash text-success';
                                        } elseif (stripos($ord['payment_mode'], 'Bank') !== false) {
                                            $p_icon = 'bi-bank text-primary';
                                        } elseif (stripos($ord['payment_mode'], 'Visa') !== false || stripos($ord['payment_mode'], 'MasterCard') !== false || stripos($ord['payment_mode'], 'AMEX') !== false || stripos($ord['payment_mode'], 'Card') !== false) {
                                            $p_icon = 'bi-credit-card-2-front-fill text-teal';
                                        }
                                        ?>
                                        <i class="bi <?= $p_icon ?> me-1"></i>
                                        <?= htmlspecialchars($ord['payment_mode']) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_st = 'bg-warning text-dark';
                                        $icon_st = 'bi-hourglass-split';
                                        if ($ord['status'] === 'Completed') {
                                            $badge_st = 'bg-success';
                                            $icon_st = 'bi-check-circle-fill';
                                        } elseif ($ord['status'] === 'Processing') {
                                            $badge_st = 'bg-primary';
                                            $icon_st = 'bi-truck';
                                        } elseif ($ord['status'] === 'Cancelled') {
                                            $badge_st = 'bg-danger';
                                            $icon_st = 'bi-x-circle-fill';
                                        }
                                        ?>
                                        <span class="badge <?= $badge_st ?>">
                                            <i class="bi <?= $icon_st ?> me-1"></i>
                                            <?= htmlspecialchars($ord['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1.5">
                                            <a href="order-success.php?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-dark">
                                                <i class="bi bi-receipt me-1"></i> Receipt
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="openOrderReportModal(<?= $ord['id'] ?>)" title="Report Issue with this Order or Seller">
                                                <i class="bi bi-flag-fill me-1"></i> Report
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Order Issue Report Modal -->
<div class="modal fade" id="orderReportModal" tabindex="-1" aria-labelledby="orderReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 py-3" style="border-bottom: 2px solid var(--teal-primary);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-flag-fill text-danger fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="orderReportModalLabel">Report Fraud or Order Issue</h5>
                        <small class="text-white-50">Booksy Trust & Safety Protection</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="api/submit-report.php" enctype="multipart/form-data">
                <input type="hidden" name="order_id" id="modalOrderId" value="">
                <input type="hidden" name="redirect" value="../my-orders.php">

                <div class="modal-body p-4 bg-white">
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <span class="small text-muted fw-bold">Reporting Order:</span>
                        <h6 class="fw-bold text-navy mb-0" id="modalOrderDisplay">#BKY-00000</h6>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy">Select Reason for Report <span class="text-danger">*</span></label>
                        <select name="reason" class="form-select" required>
                            <option value="">-- Choose specific violation reason --</option>
                            <option value="not_received">Item Not Received / Fake Courier Tracking Number</option>
                            <option value="counterfeit">Counterfeit / Replica Goods or Unauthorized Photocopy</option>
                            <option value="off_platform_payment">Off-Platform Payment Solicitation (Asked to wire/pay outside Booksy)</option>
                            <option value="misleading">Misleading Description or Bait-and-Switch (Damaged or wrong item)</option>
                            <option value="fake_profile">Identity Theft or Fake / Suspicious Seller Profile</option>
                            <option value="other">Other Fraudulent or Suspicious Activity</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy">Detailed Explanation <span class="text-danger">*</span></label>
                        <textarea name="details" class="form-control" rows="3" placeholder="Please describe what happened in detail (minimum 10 characters)..." minlength="10" required></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-navy">Evidence Attachments (Up to 3 Screenshots or Photos)</label>
                        <input type="file" name="attachments[]" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml" multiple>
                        <small class="text-muted d-block mt-1">Upload screenshots of chat logs, bank transfer solicitations, or photos of received items (JPG, PNG, WebP max 5MB each).</small>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                        <i class="bi bi-shield-fill-check me-1"></i> Submit Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openOrderReportModal(orderId) {
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('modalOrderDisplay').textContent = '#BKY-' + String(orderId).padStart(5, '0');
    const modal = new bootstrap.Modal(document.getElementById('orderReportModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
