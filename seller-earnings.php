<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Seller Earnings & Commission Hub';

if (!is_logged_in()) {
    set_flash('warning', 'Please sign in to view your seller earnings and commission dashboard.');
    header("Location: login.php?redirect=seller-earnings.php");
    exit;
}

$userId = $_SESSION['user_id'];
$isVip = is_user_vip($pdo, $userId);
$vipDetails = get_user_vip_details($pdo, $userId);

// Calendar month & year selection
$selectedMonth = isset($_GET['month']) && is_numeric($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$selectedYear  = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Fetch summary metrics
$summary = get_seller_monthly_sales_summary($pdo, $userId, $selectedMonth, $selectedYear);

// Fetch itemized order commissions for selected month
$commOrdersStmt = $pdo->prepare("
    SELECT oc.*, 
           MAX(o.created_at) AS order_date, 
           MAX(o.payment_mode) AS payment_mode, 
           MAX(b.title) AS book_title, 
           MAX(b.image_url) AS image_url
    FROM order_commissions oc
    JOIN orders o ON oc.order_id = o.id
    JOIN order_items oi ON oc.order_id = oi.order_id
    JOIN books b ON oi.book_id = b.id AND b.seller_id = ?
    WHERE oc.seller_id = ? 
      AND oc.status != 'refunded'
      AND MONTH(o.created_at) = ? 
      AND YEAR(o.created_at) = ?
    GROUP BY oc.commission_id, oc.seller_id, oc.order_id, oc.sale_amount, oc.commission_rate, oc.commission_amount, oc.net_earnings, oc.status, oc.created_at
    ORDER BY order_date DESC
");
$commOrdersStmt->execute([$userId, $userId, $selectedMonth, $selectedYear]);
$itemizedOrders = $commOrdersStmt->fetchAll();

// Fetch seller's active listing boosts
$boostsStmt = $pdo->prepare("
    SELECT pl.*, b.title, b.price AS book_price, b.image_url
    FROM promotional_listings pl
    JOIN books b ON pl.book_id = b.id
    WHERE pl.seller_id = ?
    ORDER BY pl.created_at DESC
");
$boostsStmt->execute([$userId]);
$sellerBoosts = $boostsStmt->fetchAll();

// Fetch seller's available books for boosting modal
$myBooksStmt = $pdo->prepare("SELECT id, title, price, image_url FROM books WHERE seller_id = ? AND status = 'available' ORDER BY created_at DESC");
$myBooksStmt->execute([$userId]);
$myAvailableBooks = $myBooksStmt->fetchAll();

include 'includes/header.php';
?>

<!-- Earnings Header Banner -->
<div class="hero-banner-subpage mb-4">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-teal text-white fw-bold px-3 py-1 mb-2 d-inline-flex align-items-center rounded-pill">
                    <i class="bi bi-wallet2 me-1"></i> Seller Financial Center
                </span>
                <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">Seller Earnings & Commissions</h1>
                <p class="text-white-50 mb-0">Track monthly sales volume, 0% commission threshold progress, and net payouts</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="vip.php" class="btn btn-outline-light btn-sm px-3 py-2 rounded-pill d-flex align-items-center gap-1.5">
                    <i class="bi bi-gem text-gold"></i>
                    <span><?= $isVip ? 'VIP Member Active' : 'Booksy VIP Club' ?></span>
                </a>
                <button type="button" class="btn btn-booksy-primary fw-bold px-3 py-2 shadow rounded-pill" data-bs-toggle="modal" data-bs-target="#boostListingModal">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Boost a Listing
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container py-3 py-lg-4">

    <!-- Commission Status Notification Banner -->
    <?php if ($isVip): ?>
        <div class="alert bg-gold-light border-gold text-dark d-flex align-items-center mb-4 rounded-4 shadow-sm p-3">
            <div class="seller-avatar bg-gold text-dark me-3 flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.3rem;">
                👑
            </div>
            <div>
                <h6 class="fw-bold mb-0 text-navy">Booksy VIP Membership Active</h6>
                <div class="small text-secondary">
                    You enjoy <strong>0% flat marketplace commission</strong> on all book sales regardless of your monthly sales volume! Your membership is active until <strong><?= date('M d, Y', strtotime($vipDetails['end_date'])) ?></strong>.
                </div>
            </div>
        </div>
    <?php elseif ($summary['is_over_threshold']): ?>
        <div class="alert alert-info border d-flex align-items-center mb-4 rounded-4 shadow-sm p-3">
            <i class="bi bi-info-circle-fill text-teal fs-2 me-3 flex-shrink-0"></i>
            <div>
                <h6 class="fw-bold mb-0 text-navy">You've exceeded LKR <?= number_format($summary['threshold']) ?> in monthly sales</h6>
                <div class="small text-secondary">
                    A standard <strong>3% marketplace commission</strong> now applies to qualifying sales for <strong><?= $summary['month_name'] ?> <?= $summary['year'] ?></strong>. You keep 97% of all sales!
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success border-success-subtle d-flex align-items-center mb-4 rounded-4 shadow-sm p-3 bg-white">
            <i class="bi bi-patch-check-fill text-success fs-2 me-3 flex-shrink-0"></i>
            <div>
                <h6 class="fw-bold mb-0 text-navy">Sell up to LKR <?= number_format($summary['threshold']) ?> this month with 0% commission!</h6>
                <div class="small text-secondary">
                    You have <strong>LKR <?= number_format($summary['threshold_remaining'], 2) ?></strong> remaining in your zero-commission allowance for <strong><?= $summary['month_name'] ?> <?= $summary['year'] ?></strong>.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Month Filter & Overview Toolbar -->
    <div class="card border-0 shadow-sm p-3 rounded-4 bg-white mb-4 border">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-navy"><i class="bi bi-calendar-month text-teal me-1"></i> Sales Period:</span>
                <span class="badge bg-teal-light text-teal fw-bold fs-6 px-3 py-1.5"><?= $summary['month_name'] ?> <?= $summary['year'] ?></span>
            </div>
            
            <!-- Month Picker Form -->
            <form method="GET" action="seller-earnings.php" class="d-flex align-items-center gap-2">
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

    <!-- Threshold Progress Bar Card -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4 border">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div>
                <h6 class="fw-bold text-navy mb-0">Monthly Zero-Fee Tier Progress</h6>
                <span class="small text-muted">Allowance: LKR <?= number_format($summary['threshold']) ?> / month</span>
            </div>
            <div class="text-end">
                <?php if ($isVip): ?>
                    <span class="badge bg-gold text-dark fw-bold px-3 py-1.5">VIP Unlimited (0% Fee)</span>
                <?php elseif ($summary['is_over_threshold']): ?>
                    <span class="badge bg-primary px-3 py-1.5">Standard 3% Tier Active</span>
                <?php else: ?>
                    <span class="badge bg-success px-3 py-1.5">0% Fee Tier Active</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress mb-2" style="height: 14px; border-radius: 10px; background-color: #e9ecef;">
            <div class="progress-bar <?= $summary['is_over_threshold'] ? 'bg-primary' : 'bg-teal' ?> progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width: <?= $summary['progress_percent'] ?>%;" 
                 aria-valuenow="<?= $summary['progress_percent'] ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="100"></div>
        </div>

        <div class="d-flex justify-content-between small text-muted">
            <span>Current Sales: <strong>Rs. <?= number_format($summary['total_sales'], 2) ?></strong></span>
            <?php if (!$isVip && !$summary['is_over_threshold']): ?>
                <span class="text-success fw-bold">Rs. <?= number_format($summary['threshold_remaining'], 2) ?> remaining at 0% fee</span>
            <?php elseif ($isVip): ?>
                <span class="text-teal fw-bold">VIP 0% Active on All Volume</span>
            <?php else: ?>
                <span class="text-primary fw-bold">Threshold Exceeded (3% applies)</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Gross Sales -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-semibold">Gross Sales</span>
                    <i class="bi bi-cart-check-fill text-teal"></i>
                </div>
                <div class="fs-3 fw-extrabold text-navy">Rs. <?= number_format($summary['total_sales'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.75rem;">
                    <?= $summary['completed_orders_count'] ?> completed order(s)
                </div>
            </div>
        </div>

        <!-- 2. Commission Rate -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-semibold">Commission Rate</span>
                    <i class="bi bi-percent text-gold"></i>
                </div>
                <div class="fs-3 fw-extrabold <?= $summary['commission_rate'] > 0 ? 'text-primary' : 'text-success' ?>">
                    <?= $summary['commission_rate_pct'] ?>
                </div>
                <div class="small text-muted mt-auto" style="font-size: 0.75rem;">
                    <?= $isVip ? 'VIP Privilege' : ($summary['is_over_threshold'] ? '> LKR 5k volume' : 'Under LKR 5k volume') ?>
                </div>
            </div>
        </div>

        <!-- 3. Commission Deducted -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-semibold">Commission Paid</span>
                    <i class="bi bi-receipt-cutoff text-danger"></i>
                </div>
                <div class="fs-3 fw-extrabold text-danger">Rs. <?= number_format($summary['commission_amount'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.75rem;">
                    <?= $summary['commission_amount'] == 0 ? 'LKR 0 fee applied' : 'Marketplace fee' ?>
                </div>
            </div>
        </div>

        <!-- 4. Net Earnings -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-left: 4px solid var(--teal-primary) !important;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small fw-semibold">Net Payout Earnings</span>
                    <i class="bi bi-cash-stack text-success"></i>
                </div>
                <div class="fs-3 fw-extrabold text-success">Rs. <?= number_format($summary['net_earnings'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.75rem;">
                    100% auditable take-home
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Split: Itemized Orders & Active Boosts -->
    <div class="row g-4 mb-4">
        
        <!-- Left: Itemized Commission Ledger Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white border">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-navy mb-0">
                        <i class="bi bi-list-check text-teal me-2"></i>Completed Sales Ledger (<?= count($itemizedOrders) ?>)
                    </h6>
                    <span class="badge bg-light text-dark border"><?= $summary['month_name'] ?> <?= $summary['year'] ?></span>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($itemizedOrders)): ?>
                        <div class="p-5 text-center">
                            <i class="bi bi-receipt text-muted mb-3" style="font-size: 3rem;"></i>
                            <h6 class="fw-bold text-navy">No completed orders for <?= $summary['month_name'] ?> <?= $summary['year'] ?></h6>
                            <p class="text-muted small mb-0">Completed sales and automated commission deductions will appear here in real time.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="ps-4">Order & Book</th>
                                        <th>Date</th>
                                        <th>Sale Amount</th>
                                        <th>Rate</th>
                                        <th>Fee</th>
                                        <th class="text-end pe-4">Net Payout</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itemizedOrders as $row): ?>
                                        <?php
                                        $bCover = !empty($row['image_url']) && file_exists(__DIR__ . '/uploads/' . $row['image_url'])
                                            ? 'uploads/' . htmlspecialchars($row['image_url'])
                                            : 'uploads/default_book.svg';
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-2.5">
                                                    <img src="<?= $bCover ?>" alt="" class="rounded" style="width: 36px; height: 46px; object-fit: contain; background: #17324D;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                                                    <div>
                                                        <div class="fw-bold text-navy text-truncate" style="max-width: 220px;">
                                                            <?= htmlspecialchars($row['book_title']) ?>
                                                        </div>
                                                        <span class="badge bg-light text-navy border" style="font-size: 0.7rem;">
                                                            #BKY-<?= str_pad($row['order_id'], 5, '0', STR_PAD_LEFT) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="small text-muted">
                                                <?= date('M d, Y', strtotime($row['order_date'])) ?>
                                            </td>
                                            <td class="fw-bold text-navy">
                                                Rs. <?= number_format($row['sale_amount'], 2) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $row['commission_rate'] > 0 ? 'bg-primary' : 'bg-success' ?>">
                                                    <?= ($row['commission_rate'] * 100) ?>%
                                                </span>
                                            </td>
                                            <td class="text-danger small fw-semibold">
                                                -Rs. <?= number_format($row['commission_amount'], 2) ?>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-success">
                                                Rs. <?= number_format($row['net_earnings'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="fw-bold ps-4 text-navy">Total Monthly Settlement:</td>
                                        <td class="fw-bold text-navy">Rs. <?= number_format($summary['total_sales'], 2) ?></td>
                                        <td></td>
                                        <td class="fw-bold text-danger">-Rs. <?= number_format($summary['commission_amount'], 2) ?></td>
                                        <td class="text-end pe-4 fw-extrabold text-success fs-6">Rs. <?= number_format($summary['net_earnings'], 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Active Promotions & VIP Quick Box -->
        <div class="col-lg-4">
            
            <!-- Active Listing Boosts -->
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4 border">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-navy mb-0"><i class="bi bi-lightning-fill text-gold me-1"></i> My Listing Boosts</h6>
                    <button type="button" class="btn btn-sm btn-outline-teal" data-bs-toggle="modal" data-bs-target="#boostListingModal">
                        + New Boost
                    </button>
                </div>

                <?php if (empty($sellerBoosts)): ?>
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-rocket-takeoff fs-2 d-block mb-1"></i>
                        You have no active promotional boosts. Promote your textbooks for 3x faster sales!
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($sellerBoosts as $boost): ?>
                            <?php
                            $isExpired = (strtotime($boost['end_date']) < time());
                            ?>
                            <div class="p-2.5 rounded-3 bg-light border d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold small text-navy text-truncate" style="max-width: 180px;">
                                        <?= htmlspecialchars($boost['title']) ?>
                                    </div>
                                    <span class="badge <?= $isExpired ? 'bg-secondary' : 'bg-teal' ?>" style="font-size: 0.68rem;">
                                        <?= ucwords(str_replace('_', ' ', $boost['promotion_type'])) ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <span class="small fw-bold text-navy">Rs. <?= number_format($boost['price']) ?></span>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        <?= $isExpired ? 'Expired' : 'Ends ' . date('M d', strtotime($boost['end_date'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- VIP Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-navy text-white text-center position-relative overflow-hidden">
                <div class="position-relative" style="z-index: 2;">
                    <div class="seller-avatar bg-gold text-dark mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.5rem;">
                        👑
                    </div>
                    <h5 class="fw-bold text-white font-serif-title mb-1">Booksy VIP Club</h5>
                    <p class="small text-white-50 mb-3">0% flat commission on all sales, monthly free boosts & verified gold seller badge.</p>
                    <a href="vip.php" class="btn btn-gold text-dark fw-bold btn-sm px-4 py-2 rounded-pill shadow">
                        <?= $isVip ? 'Manage VIP Subscription' : 'Upgrade to VIP (LKR 950/mo)' ?>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal: Boost a Listing -->
<div class="modal fade" id="boostListingModal" tabindex="-1" aria-labelledby="boostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white py-3 px-4" style="border-bottom: 2px solid var(--teal-primary);">
                <h5 class="modal-title fw-bold" id="boostModalLabel">
                    <i class="bi bi-lightning-charge-fill text-gold me-2"></i> Boost Listing Visibility
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <?php if (empty($myAvailableBooks)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-book text-muted mb-2 fs-1"></i>
                        <h6 class="fw-bold text-navy">No Active Books Found</h6>
                        <p class="text-muted small mb-3">You need to have active book listings before applying a promotion boost.</p>
                        <a href="sell.php" class="btn btn-booksy-primary btn-sm fw-bold">Post a Book</a>
                    </div>
                <?php else: ?>
                    <form id="boostListingForm" onsubmit="submitBoostForm(event)">
                        <!-- Select Book -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-navy text-uppercase">1. Select Book to Boost *</label>
                            <select name="book_id" id="boostBookSelect" class="form-select" required>
                                <?php foreach ($myAvailableBooks as $b): ?>
                                    <option value="<?= $b['id'] ?>">
                                        <?= htmlspecialchars($b['title']) ?> (Rs. <?= number_format($b['price'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Select Boost Tier -->
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-navy text-uppercase mb-2">2. Choose Promotion Package *</label>
                            <div class="d-flex flex-column gap-2">
                                <label class="payment-method-card selected d-flex align-items-center gap-3">
                                    <input type="radio" name="boost_type" value="featured_listing" checked class="form-check-input mt-0">
                                    <div class="flex-fill">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-navy small"><i class="bi bi-star-fill text-gold me-1"></i> Featured Listing</strong>
                                            <span class="badge bg-teal">LKR 150.00</span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">7 Days • Highlighted border & "Featured" badge across catalog</div>
                                    </div>
                                </label>

                                <label class="payment-method-card d-flex align-items-center gap-3">
                                    <input type="radio" name="boost_type" value="homepage_boost" class="form-check-input mt-0">
                                    <div class="flex-fill">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-navy small"><i class="bi bi-house-door-fill text-teal me-1"></i> Homepage Showcase</strong>
                                            <span class="badge bg-teal">LKR 350.00</span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">3 Days • Pinned on Homepage Premiere Carousel & Rails</div>
                                    </div>
                                </label>

                                <label class="payment-method-card d-flex align-items-center gap-3">
                                    <input type="radio" name="boost_type" value="category_boost" class="form-check-input mt-0">
                                    <div class="flex-fill">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-navy small"><i class="bi bi-tag-fill text-primary me-1"></i> Category Leader</strong>
                                            <span class="badge bg-teal">LKR 250.00</span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">7 Days • Top row placement in category listings</div>
                                    </div>
                                </label>

                                <label class="payment-method-card d-flex align-items-center gap-3">
                                    <input type="radio" name="boost_type" value="search_boost" class="form-check-input mt-0">
                                    <div class="flex-fill">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-navy small"><i class="bi bi-search text-dark me-1"></i> Search Priority Boost</strong>
                                            <span class="badge bg-teal">LKR 150.00</span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">7 Days • Top ranking in search suggestions & results</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div id="boostFormAlert" class="alert alert-danger d-none small"></div>

                        <button type="submit" class="btn btn-booksy-primary btn-lg w-100 fw-bold py-2.5 shadow" id="btnSubmitBoost">
                            <i class="bi bi-check-circle-fill me-1"></i> Confirm & Activate Boost
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function submitBoostForm(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitBoost');
    const alertBox = document.getElementById('boostFormAlert');
    const bookId = document.getElementById('boostBookSelect').value;
    const boostType = document.querySelector('input[name="boost_type"]:checked').value;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Activating...';
    alertBox.classList.add('d-none');

    fetch('api/purchase-boost.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ book_id: bookId, boost_type: boostType })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('success', 'Boost Activated!', data.message);
            setTimeout(() => { location.reload(); }, 1200);
        } else {
            alertBox.textContent = data.message || 'Failed to activate boost.';
            alertBox.classList.remove('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Confirm & Activate Boost';
        }
    })
    .catch(err => {
        alertBox.textContent = 'Connection error. Please try again.';
        alertBox.classList.remove('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Confirm & Activate Boost';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
