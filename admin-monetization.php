<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Admin Monetization & Revenue Governance';

if (!is_admin()) {
    set_flash('danger', 'Administrative privileges required to access the financial monetization dashboard.');
    header("Location: login.php?redirect=admin-monetization.php");
    exit;
}

// Calendar Month & Year for reports
$selectedMonth = isset($_GET['month']) && is_numeric($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$selectedYear  = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

$analytics = get_admin_monetization_analytics($pdo, $selectedMonth, $selectedYear);
$settings = $analytics['settings'];

// Fetch recent orders with commission status
$recentOrdersStmt = $pdo->prepare("
    SELECT o.id, o.buyer_id, o.total_amount, o.shipping_address, o.payment_mode, o.status, o.created_at,
           u.name AS buyer_name, 
           COALESCE(MAX(oc.commission_amount), 0.00) AS commission_amount,
           COALESCE(MAX(oc.commission_rate), 0.0000) AS commission_rate,
           COALESCE(MAX(oc.status), 'pending') AS commission_status,
           MAX(b.title) AS sample_book_title, 
           MAX(s.name) AS seller_name, 
           MAX(s.id) AS seller_id
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN books b ON oi.book_id = b.id
    LEFT JOIN users s ON b.seller_id = s.id
    LEFT JOIN order_commissions oc ON o.id = oc.order_id
    GROUP BY o.id, o.buyer_id, o.total_amount, o.shipping_address, o.payment_mode, o.status, o.created_at, u.name
    ORDER BY o.created_at DESC
    LIMIT 15
");
$recentOrdersStmt->execute();
$recentOrders = $recentOrdersStmt->fetchAll();

// Month name
$monthName = date('F', mktime(0, 0, 0, $selectedMonth, 10));

include 'includes/header.php';
?>

<!-- Admin Header Banner -->
<div class="hero-banner-subpage mb-4" style="background: linear-gradient(135deg, #0A192F 0%, #172A45 60%, #0F3460 100%);">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-danger text-white fw-bold px-3 py-1 d-inline-flex align-items-center rounded-pill">
                        <i class="bi bi-shield-lock-fill me-1"></i> <?= is_owner() ? 'Owner Control Center' : 'Site Manager Portal' ?>
                    </span>
                    <?php if (is_owner()): ?>
                        <span class="badge bg-warning text-dark fw-bold px-2.5 py-1 rounded-pill">
                            <i class="bi bi-crown-fill me-1"></i> Platform Owner
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">Monetization & Revenue Dashboard</h1>
                <p class="text-white-50 mb-0">Marketplace Gross Merchandise Value (GMV), Seller Commissions, Boosts & VIP Subscriptions</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-light btn-sm px-3 py-2 rounded-pill">
                    <i class="bi bi-printer me-1"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Admin Navigation Tabs -->
        <div class="d-flex flex-wrap gap-2 pt-2 border-top border-white-10" style="border-top: 1px solid rgba(255,255,255,0.12);">
            <a href="admin-monetization.php" class="btn btn-sm btn-light fw-bold rounded-pill px-3 py-1.5 shadow-sm">
                <i class="bi bi-graph-up-arrow me-1 text-teal"></i> Monetization & Revenue
            </a>
            <a href="admin-banners.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                <i class="bi bi-images me-1 text-teal"></i> Hero & Promo Banners
            </a>
            <a href="admin-fraud.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                <i class="bi bi-shield-exclamation me-1 text-danger"></i> Trust & Fraud Moderation
            </a>
            <?php if (is_owner()): ?>
                <a href="admin-users.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                    <i class="bi bi-people-fill me-1 text-warning"></i> Site Managers & Users
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container py-3 py-lg-4">

    <!-- Month Filter & Overview Toolbar -->
    <div class="card border-0 shadow-sm p-3 rounded-4 bg-white mb-4 border">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-navy"><i class="bi bi-graph-up-arrow text-teal me-1"></i> Financial Reporting Period:</span>
                <span class="badge bg-teal-light text-teal fw-bold fs-6 px-3 py-1.5"><?= $monthName ?> <?= $selectedYear ?></span>
            </div>
            
            <form method="GET" action="admin-monetization.php" class="d-flex align-items-center gap-2">
                <select name="month" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="2026" <?= $selectedYear === 2026 ? 'selected' : '' ?>>2026</option>
                    <option value="2025" <?= $selectedYear === 2025 ? 'selected' : '' ?>>2025</option>
                </select>
            </form>
        </div>
    </div>

    <!-- 6 Executive KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Marketplace Total GMV -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100">
                <span class="text-muted small fw-semibold">Monthly GMV</span>
                <div class="fs-4 fw-extrabold text-navy mt-1">Rs. <?= number_format($analytics['monthly_gmv']) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    All-Time: Rs. <?= number_format($analytics['all_time_gmv']) ?>
                </div>
            </div>
        </div>

        <!-- 2. Monthly Commission Revenue -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-top: 3px solid #0d6efd !important;">
                <span class="text-muted small fw-semibold">3% Commission</span>
                <div class="fs-4 fw-extrabold text-primary mt-1">Rs. <?= number_format($analytics['monthly_commission_revenue'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    All-Time: Rs. <?= number_format($analytics['total_commission_revenue'], 2) ?>
                </div>
            </div>
        </div>

        <!-- 3. Promotion Boosts Revenue -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-top: 3px solid #198754 !important;">
                <span class="text-muted small fw-semibold">Listing Boosts</span>
                <div class="fs-4 fw-extrabold text-success mt-1">Rs. <?= number_format($analytics['total_boost_revenue'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    Micro-promotions
                </div>
            </div>
        </div>

        <!-- 4. VIP Memberships Revenue -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-top: 3px solid #f59e0b !important;">
                <span class="text-muted small fw-semibold">VIP Memberships</span>
                <div class="fs-4 fw-extrabold text-gold mt-1">Rs. <?= number_format($analytics['total_vip_revenue'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    Recurring subs
                </div>
            </div>
        </div>

        <!-- 5. Total Platform Gross Profit -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-navy text-white h-100">
                <span class="text-white-50 small fw-semibold">Total Revenue</span>
                <div class="fs-4 fw-extrabold text-white mt-1">Rs. <?= number_format($analytics['total_platform_revenue'], 2) ?></div>
                <div class="small text-white-50 mt-auto" style="font-size: 0.72rem;">
                    All streams combined
                </div>
            </div>
        </div>

        <!-- 6. Student Subsidy Metric -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-top: 3px solid var(--teal-primary) !important;">
                <span class="text-muted small fw-semibold">0% Tier Volume</span>
                <div class="fs-4 fw-extrabold text-teal mt-1">Rs. <?= number_format($analytics['student_subsidized_volume']) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    Student/Casual subsidy
                </div>
            </div>
        </div>
    </div>

    <!-- Main Grid: Top Sellers & Dynamic Settings Editor -->
    <div class="row g-4 mb-4">
        
        <!-- Left: Top Sellers Leaderboard & Threshold Breakdown -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white border h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-navy mb-0">
                        <i class="bi bi-trophy-fill text-gold me-2"></i>Seller Volume & Commission Status (<?= $monthName ?>)
                    </h6>
                    <div class="d-flex gap-1.5">
                        <span class="badge bg-success-subtle text-success border small"><?= $analytics['threshold_counts']['under_threshold_count'] ?> Free Tier</span>
                        <span class="badge bg-primary-subtle text-primary border small"><?= $analytics['threshold_counts']['over_threshold_count'] ?> 3% Tier</span>
                        <span class="badge bg-gold-subtle text-dark border small"><?= $analytics['threshold_counts']['vip_count'] ?> VIP</span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($analytics['top_sellers'])): ?>
                        <div class="p-4 text-center text-muted small">No active seller data recorded for this month.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Seller</th>
                                        <th>Monthly Sales</th>
                                        <th>Tier</th>
                                        <th>Commission</th>
                                        <th class="text-end pe-4">Net Payout</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analytics['top_sellers'] as $seller): ?>
                                        <?php
                                        $salesVal = floatval($seller['current_month_sales']);
                                        $isOver = ($salesVal > $settings['commission_free_threshold']);
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-navy"><?= htmlspecialchars($seller['seller_name']) ?></div>
                                                <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($seller['seller_email']) ?></div>
                                            </td>
                                            <td class="fw-bold text-navy">
                                                Rs. <?= number_format($salesVal, 2) ?>
                                            </td>
                                            <td>
                                                <?php if ($seller['is_vip']): ?>
                                                    <span class="badge bg-gold text-dark"><i class="bi bi-gem"></i> VIP (0%)</span>
                                                <?php elseif ($isOver): ?>
                                                    <span class="badge bg-primary">3% (> 5k)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">0% (≤ 5k)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-danger fw-semibold">
                                                Rs. <?= number_format($seller['commission_paid'], 2) ?>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-success">
                                                Rs. <?= number_format($seller['net_earnings'], 2) ?>
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

        <!-- Right: Dynamic Monetization Parameters Configuration -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white border">
                <div class="d-flex align-items-center mb-3">
                    <div class="seller-avatar bg-navy text-teal me-3" style="width: 40px; height: 40px; font-size: 1.1rem;">
                        <i class="bi bi-sliders"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-navy mb-0">Configure Monetization Rules</h6>
                        <span class="text-muted small">Live platform parameters (No code changes needed)</span>
                    </div>
                </div>

                <form method="POST" action="api/update-monetization-config.php">
                    <input type="hidden" name="redirect" value="admin-monetization.php">

                    <!-- Commission Threshold -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy text-uppercase">1. Zero-Commission Threshold (LKR)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light fw-bold">LKR</span>
                            <input type="number" step="100" name="commission_free_threshold" class="form-control" value="<?= htmlspecialchars($settings['commission_free_threshold']) ?>" required>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">Sellers with sales $\le$ this amount pay 0% fee.</div>
                    </div>

                    <!-- Standard Commission Percentage -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy text-uppercase">2. Standard Commission Rate (%)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.1" name="standard_commission_rate" class="form-control" value="<?= htmlspecialchars($settings['standard_commission_rate'] * 100) ?>" required>
                            <span class="input-group-text bg-light fw-bold">%</span>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">Applied to qualifying sales above threshold (Default: 3%).</div>
                    </div>

                    <!-- Boost Fees -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small text-navy">Featured Boost (LKR)</label>
                            <input type="number" name="boost_featured_fee" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['boost_featured_fee']) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small text-navy">Homepage Boost (LKR)</label>
                            <input type="number" name="boost_homepage_fee" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['boost_homepage_fee']) ?>" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small text-navy">Category Boost (LKR)</label>
                            <input type="number" name="boost_category_fee" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['boost_category_fee']) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small text-navy">Search Boost (LKR)</label>
                            <input type="number" name="boost_search_fee" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['boost_search_fee']) ?>" required>
                        </div>
                    </div>

                    <!-- VIP Price -->
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-navy text-uppercase">VIP Membership Price (LKR / Month)</label>
                        <input type="number" name="vip_monthly_price" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['vip_monthly_price']) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-booksy-primary w-100 fw-bold py-2 shadow">
                        <i class="bi bi-save me-1"></i> Save Monetization Rules
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Order Management & Commission Trigger Queue -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white border">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-arrow-repeat text-teal me-2"></i>Order Lifecycle & Commission Processing Queue</h6>
                <span class="text-muted small">Update order status to trigger automated commission calculations or refund reversals</span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Order #</th>
                            <th>Buyer & Seller</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Commission</th>
                            <th class="text-end pe-4">Lifecycle Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ord): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-navy">
                                    <a href="order-success.php?order_id=<?= $ord['id'] ?>" class="text-navy text-decoration-none">
                                        #BKY-<?= str_pad($ord['id'], 5, '0', STR_PAD_LEFT) ?>
                                    </a>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= date('M d, Y', strtotime($ord['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div>Buyer: <strong><?= htmlspecialchars($ord['buyer_name']) ?></strong></div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Seller: <?= htmlspecialchars($ord['seller_name'] ?? 'Marketplace') ?></div>
                                </td>
                                <td class="fw-bold text-navy">
                                    Rs. <?= number_format($ord['total_amount'], 2) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ord['payment_mode']) ?>
                                </td>
                                <td>
                                    <?php
                                    $stClass = 'bg-warning text-dark';
                                    if ($ord['status'] === 'Completed') $stClass = 'bg-success';
                                    elseif ($ord['status'] === 'Processing') $stClass = 'bg-primary';
                                    elseif ($ord['status'] === 'Cancelled') $stClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $stClass ?>"><?= htmlspecialchars($ord['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($ord['commission_amount'] > 0): ?>
                                        <span class="badge bg-primary">Rs. <?= number_format($ord['commission_amount'], 2) ?> (3%)</span>
                                    <?php elseif ($ord['status'] === 'Completed'): ?>
                                        <span class="badge bg-success">Rs. 0.00 (0%)</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($ord['status'] !== 'Completed'): ?>
                                            <form method="POST" action="api/manage-order-status.php" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                                <input type="hidden" name="new_status" value="Completed">
                                                <input type="hidden" name="redirect" value="admin-monetization.php">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed & Compute Commission">
                                                    <i class="bi bi-check-circle"></i> Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($ord['status'] === 'Completed'): ?>
                                            <form method="POST" action="api/manage-order-status.php" class="d-inline" onsubmit="return confirm('Refund this order and reverse seller sales/commission?');">
                                                <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                                <input type="hidden" name="new_status" value="Refunded">
                                                <input type="hidden" name="redirect" value="admin-monetization.php">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Refund & Reverse">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Refund
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
