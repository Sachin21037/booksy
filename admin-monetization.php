<?php
require_once 'db.php';
global $pdo;

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

$startDateParam = isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']) : null;
$endDateParam   = isset($_GET['end_date']) && !empty($_GET['end_date']) ? trim($_GET['end_date']) : null;

// If user selected month/year without custom start_date
if (isset($_GET['month']) && empty($_GET['start_date'])) {
    $startDateParam = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
    $endDateParam   = date('Y-m-t', strtotime($startDateParam));
}

$analytics = get_admin_monetization_analytics($pdo, $selectedMonth, $selectedYear, $startDateParam, $endDateParam);
$settings = $analytics['settings'];

// Fetch orders in selected period with commission status
$startDateTime = $analytics['start_date'] . ' 00:00:00';
$endDateTime   = $analytics['end_date'] . ' 23:59:59';

$periodOrdersStmt = $pdo->prepare("
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
    WHERE o.created_at >= ? AND o.created_at <= ?
    GROUP BY o.id, o.buyer_id, o.total_amount, o.shipping_address, o.payment_mode, o.status, o.created_at, u.name
    ORDER BY o.created_at DESC
");
$periodOrdersStmt->execute([$startDateTime, $endDateTime]);
$recentOrders = $periodOrdersStmt->fetchAll();

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

    <!-- Date Range & Period Filter Toolbar -->
    <div class="card border-0 shadow-sm p-3 rounded-4 bg-white mb-4 border">
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="fw-bold text-navy"><i class="bi bi-calendar-range text-teal me-1"></i> Reporting Period:</span>
                <span class="badge bg-teal-light text-teal fw-bold fs-6 px-3 py-1.5">
                    <i class="bi bi-calendar3 me-1"></i> <?= htmlspecialchars($analytics['period_label']) ?>
                </span>
                <?php if ($analytics['is_custom_range']): ?>
                    <span class="badge bg-primary text-white small px-2.5 py-1 rounded-pill">
                        <i class="bi bi-funnel-fill me-1"></i> Custom Range
                    </span>
                <?php endif; ?>
            </div>
            
            <form method="GET" action="admin-monetization.php" class="d-flex flex-wrap align-items-center gap-2">
                <!-- Two Date Inputs: Start Date & End Date -->
                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text bg-light text-muted fw-semibold small">
                        <i class="bi bi-calendar-event me-1 text-teal"></i> From
                    </span>
                    <input type="date" name="start_date" id="filterStartDate" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($analytics['start_date']) ?>" required>
                </div>

                <div class="input-group input-group-sm" style="width: auto;">
                    <span class="input-group-text bg-light text-muted fw-semibold small">
                        <i class="bi bi-calendar-check me-1 text-teal"></i> To
                    </span>
                    <input type="date" name="end_date" id="filterEndDate" class="form-control form-control-sm" 
                           value="<?= htmlspecialchars($analytics['end_date']) ?>" required>
                </div>

                <button type="submit" class="btn btn-booksy-primary btn-sm px-3 fw-bold shadow-sm">
                    <i class="bi bi-funnel-fill me-1"></i> Apply Filter
                </button>

                <!-- Quick Presets Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-clock-history me-1"></i> Presets
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                        <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.7rem;">Quick Date Ranges</h6></li>
                        <li><a class="dropdown-item" href="admin-monetization.php?start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>">Today</a></li>
                        <li><a class="dropdown-item" href="admin-monetization.php?start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= date('Y-m-d') ?>">Last 7 Days</a></li>
                        <li><a class="dropdown-item" href="admin-monetization.php?start_date=<?= date('Y-m-d', strtotime('-30 days')) ?>&end_date=<?= date('Y-m-d') ?>">Last 30 Days</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.7rem;">Monthly Reports</h6></li>
                        <li><a class="dropdown-item" href="admin-monetization.php?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>">This Month (<?= date('F') ?>)</a></li>
                        <li><a class="dropdown-item" href="admin-monetization.php?start_date=<?= date('Y-m-01', strtotime('first day of last month')) ?>&end_date=<?= date('Y-m-t', strtotime('last month')) ?>">Last Month (<?= date('F', strtotime('last month')) ?>)</a></li>
                        <li><a class="dropdown-item" href="admin-monetization.php?start_date=2026-09-01&end_date=2026-09-30">September 2026</a></li>
                        <li><a class="dropdown-item" href="admin-monetization.php?start_date=2026-08-01&end_date=2026-08-31">August 2026</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-semibold text-primary" href="admin-monetization.php?start_date=2026-08-01&end_date=<?= date('Y-m-d') ?>">All-Time Cumulative</a></li>
                    </ul>
                </div>

                <?php if ($analytics['is_custom_range'] || isset($_GET['start_date']) || isset($_GET['month'])): ?>
                    <a href="admin-monetization.php" class="btn btn-outline-danger btn-sm rounded-pill px-2.5" title="Reset Filter to Current Month">
                        <i class="bi bi-x-circle me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 6 Executive KPI Metric Cards (Reflecting Selected Period) -->
    <div class="row g-3 mb-4">
        <!-- 1. Marketplace Period GMV -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100">
                <span class="text-muted small fw-semibold">Period GMV</span>
                <div class="fs-4 fw-extrabold text-navy mt-1">Rs. <?= number_format($analytics['period_gmv'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    All-Time: Rs. <?= number_format($analytics['all_time_gmv']) ?>
                </div>
            </div>
        </div>

        <!-- 2. Period Total Platform Gross Profit -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-navy text-white h-100">
                <span class="text-white-50 small fw-semibold">Total Revenue</span>
                <div class="fs-4 fw-extrabold text-white mt-1">Rs. <?= number_format($analytics['period_total_revenue'], 2) ?></div>
                <div class="small text-white-50 mt-auto" style="font-size: 0.72rem;">
                    All-Time: Rs. <?= number_format($analytics['all_time_platform_revenue'], 2) ?>
                </div>
            </div>
        </div>

        <!-- 3. Period Commission Revenue -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-top: 3px solid #0d6efd !important;">
                <span class="text-muted small fw-semibold">3% Commission</span>
                <div class="fs-4 fw-extrabold text-primary mt-1">Rs. <?= number_format($analytics['period_commission_revenue'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    All-Time: Rs. <?= number_format($analytics['total_commission_revenue'], 2) ?>
                </div>
            </div>
        </div>

        <!-- 4. Promotion Boosts & VIP Subscriptions Revenue -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-top: 3px solid #f59e0b !important;">
                <span class="text-muted small fw-semibold">Boosts & VIP</span>
                <div class="fs-4 fw-extrabold text-gold mt-1">Rs. <?= number_format($analytics['period_boost_revenue'] + $analytics['period_vip_revenue'], 2) ?></div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    Boosts: Rs. <?= number_format($analytics['period_boost_revenue']) ?> | VIP: Rs. <?= number_format($analytics['period_vip_revenue']) ?>
                </div>
            </div>
        </div>

        <!-- 5. Registered Users in Period -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-top: 3px solid var(--teal-primary) !important;">
                <span class="text-muted small fw-semibold">Registered Users</span>
                <div class="fs-4 fw-extrabold text-teal mt-1"><?= number_format($analytics['period_registered_users_count']) ?> Users</div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    <a href="#registeredUsersSection" class="text-teal text-decoration-none fw-semibold">
                        View List <i class="bi bi-arrow-down-short"></i>
                    </a>
                    (Total: <?= number_format($analytics['all_time_users_count']) ?>)
                </div>
            </div>
        </div>

        <!-- 6. Period Orders -->
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border h-100" style="border-top: 3px solid #198754 !important;">
                <span class="text-muted small fw-semibold">Orders in Period</span>
                <div class="fs-4 fw-extrabold text-success mt-1"><?= number_format($analytics['period_total_orders']) ?> Orders</div>
                <div class="small text-muted mt-auto" style="font-size: 0.72rem;">
                    <a href="#revenueBreakdownSection" class="text-success text-decoration-none fw-semibold">
                        View Queue <i class="bi bi-arrow-down-short"></i>
                    </a>
                    (<?= $analytics['period_completed_orders'] ?> Completed)
                </div>
            </div>
        </div>
    </div>

    <!-- Period Executive Summary Banner -->
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #f0fdf4 0%, #e6fffa 100%); border-left: 5px solid var(--teal-primary) !important;">
        <div class="card-body p-3 p-md-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div class="d-flex align-items-start gap-3">
                <div class="seller-avatar bg-teal text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-navy mb-1">
                        Analytics Summary for <?= htmlspecialchars($analytics['period_label']) ?>
                    </h6>
                    <div class="text-muted small">
                        During this timeframe, <strong class="text-teal"><?= number_format($analytics['period_registered_users_count']) ?> new user(s) registered</strong> on Booksy, generating <strong class="text-navy">Rs. <?= number_format($analytics['period_gmv'], 2) ?></strong> in marketplace volume across <strong class="text-navy"><?= number_format($analytics['period_total_orders']) ?> orders</strong>, with <strong class="text-success">Rs. <?= number_format($analytics['period_total_revenue'], 2) ?></strong> in total platform net revenue.
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                <a href="#registeredUsersSection" class="btn btn-sm btn-white bg-white text-navy fw-bold border rounded-pill px-3 py-1.5 shadow-sm">
                    <i class="bi bi-people-fill text-teal me-1"></i> Registered Users (<?= $analytics['period_registered_users_count'] ?>)
                </a>
                <a href="#revenueBreakdownSection" class="btn btn-sm btn-booksy-primary rounded-pill px-3 py-1.5 shadow-sm">
                    <i class="bi bi-receipt me-1"></i> Orders & Revenue Queue
                </a>
            </div>
        </div>
    </div>

    <!-- Dedicated Registered Users in Selected Period Section -->
    <div id="registeredUsersSection" class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white border mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h6 class="fw-bold text-navy mb-0">
                    <i class="bi bi-people-fill text-teal me-2"></i>Registered Users During Selected Period
                </h6>
                <span class="text-muted small">
                    Accounts registered between <strong><?= date('M d, Y', strtotime($analytics['start_date'])) ?></strong> and <strong><?= date('M d, Y', strtotime($analytics['end_date'])) ?></strong> (<?= htmlspecialchars($analytics['period_label']) ?>)
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-teal-light text-teal fw-bold px-3 py-1.5 rounded-pill fs-6">
                    <i class="bi bi-person-check-fill me-1"></i> <?= number_format($analytics['period_registered_users_count']) ?> Registered
                </span>
                <?php if (is_owner()): ?>
                    <a href="admin-users.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <i class="bi bi-gear-fill me-1"></i> Manage Users
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($analytics['period_registered_users'])): ?>
                <div class="p-5 text-center text-muted">
                    <div class="mb-2"><i class="bi bi-person-x fs-1 text-muted opacity-50"></i></div>
                    <div class="fw-semibold">No users registered in this time period</div>
                    <div class="small text-muted mt-1">Try selecting a broader date range or use the quick presets in the filter bar above.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">User</th>
                                <th>Role</th>
                                <th>Account Status</th>
                                <th>Contact Phone</th>
                                <th>Registered Date & Time</th>
                                <th>Marketplace Activity</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['period_registered_users'] as $u): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem; flex-shrink: 0;">
                                                <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-navy"><?= htmlspecialchars($u['name']) ?></div>
                                                <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($u['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'owner'): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-crown-fill me-1"></i> Owner</span>
                                        <?php elseif ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-danger text-white"><i class="bi bi-shield-fill me-1"></i> Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-dark"><i class="bi bi-person me-1"></i> User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $stBadge = 'bg-success';
                                        if ($u['account_status'] === 'suspended') $stBadge = 'bg-danger';
                                        elseif ($u['account_status'] === 'restricted') $stBadge = 'bg-warning text-dark';
                                        elseif ($u['account_status'] === 'deactivated') $stBadge = 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $stBadge ?>"><?= ucfirst(htmlspecialchars($u['account_status'])) ?></span>
                                    </td>
                                    <td>
                                        <?= !empty($u['phone']) ? htmlspecialchars($u['phone']) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-navy"><?= date('M d, Y', strtotime($u['created_at'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($u['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-navy border me-1"><i class="bi bi-book me-1"></i> <?= intval($u['books_count']) ?> Listed</span>
                                        <span class="badge bg-light text-navy border"><i class="bi bi-bag me-1"></i> <?= intval($u['orders_count']) ?> Orders</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="btn btn-outline-secondary btn-sm" title="Send Email">
                                                <i class="bi bi-envelope"></i>
                                            </a>
                                            <?php if (is_owner()): ?>
                                                <a href="admin-users.php?search=<?= urlencode($u['email']) ?>" class="btn btn-outline-primary btn-sm" title="Manage in Site Managers">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                            <?php endif; ?>
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
    <div id="revenueBreakdownSection" class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white border">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-arrow-repeat text-teal me-2"></i>Order Lifecycle & Revenue Processing Queue</h6>
                <span class="text-muted small">Showing orders placed between <strong><?= date('M d, Y', strtotime($analytics['start_date'])) ?></strong> and <strong><?= date('M d, Y', strtotime($analytics['end_date'])) ?></strong> (<?= htmlspecialchars($analytics['period_label']) ?>) &bull; GMV: <strong>Rs. <?= number_format($analytics['period_gmv'], 2) ?></strong></span>
            </div>
            <div>
                <span class="badge bg-teal-light text-teal fw-bold px-3 py-1.5 rounded-pill fs-6">
                    <i class="bi bi-cart-check-fill me-1"></i> <?= count($recentOrders) ?> Order(s) in Period
                </span>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($recentOrders)): ?>
                <div class="p-5 text-center text-muted">
                    <div class="mb-2"><i class="bi bi-receipt-cutoff fs-1 text-muted opacity-50"></i></div>
                    <div class="fw-semibold">No orders recorded during this time period</div>
                    <div class="small text-muted mt-1">No purchases occurred between <?= htmlspecialchars($analytics['start_date']) ?> and <?= htmlspecialchars($analytics['end_date']) ?>. Adjust your date filters above to view other transactions.</div>
                </div>
            <?php else: ?>
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
                                        elseif ($ord['status'] === 'Confirmed') $stClass = 'bg-info text-white';
                                        elseif ($ord['status'] === 'Processing') $stClass = 'bg-primary';
                                        elseif ($ord['status'] === 'Cancelled') $stClass = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $stClass ?>"><?= htmlspecialchars($ord['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($ord['commission_amount'] > 0): ?>
                                            <span class="badge bg-primary">Rs. <?= number_format($ord['commission_amount'], 2) ?> (3%)</span>
                                        <?php elseif (in_array($ord['status'], ['Completed', 'Confirmed'])): ?>
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
                                                    <input type="hidden" name="redirect" value="admin-monetization.php?start_date=<?= urlencode($analytics['start_date']) ?>&end_date=<?= urlencode($analytics['end_date']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed & Compute Commission">
                                                        <i class="bi bi-check-circle"></i> Complete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($ord['status'] === 'Completed'): ?>
                                                <form method="POST" action="api/manage-order-status.php" class="d-inline" onsubmit="return confirm('Refund this order and reverse seller sales/commission?');">
                                                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                                    <input type="hidden" name="new_status" value="Refunded">
                                                    <input type="hidden" name="redirect" value="admin-monetization.php?start_date=<?= urlencode($analytics['start_date']) ?>&end_date=<?= urlencode($analytics['end_date']) ?>">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
