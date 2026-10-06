<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Booksy VIP Membership Club';

$userId = $_SESSION['user_id'] ?? 0;
$isVip = is_user_vip($pdo, $userId);
$vipDetails = get_user_vip_details($pdo, $userId);

$settings = get_monetization_settings($pdo);
$monthlyPrice = floatval($settings['vip_monthly_price'] ?? 950.00);
$annualPrice = round($monthlyPrice * 10, 2);

// Handle Subscription Action
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_vip'])) {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in or register to join Booksy VIP.');
        header("Location: login.php?redirect=vip.php");
        exit;
    }

    $planType = $_POST['plan_type'] === 'annual' ? 'annual' : 'monthly';
    $res = purchase_vip_membership($pdo, $userId, $planType);

    if ($res['success']) {
        set_flash('success', $res['message']);
        header("Location: vip.php");
        exit;
    } else {
        $message = 'Failed to activate VIP membership. Please try again.';
        $messageType = 'danger';
    }
}

include 'includes/header.php';
?>

<!-- VIP Club Hero Showcase -->
<div class="hero-banner-subpage mb-5 text-center position-relative overflow-hidden" style="background: linear-gradient(135deg, #0A192F 0%, #172A45 50%, #1F4068 100%);">
    <div class="container py-3 position-relative" style="z-index: 2;">
        <span class="badge bg-gold text-dark fw-bold px-3.5 py-1.5 mb-3 d-inline-flex align-items-center rounded-pill shadow-sm" style="font-size: 0.85rem;">
            👑 Premium Reseller Privilege
        </span>
        <h1 class="display-5 fw-extrabold text-white mb-2 font-serif-title">Booksy VIP Membership</h1>
        <p class="lead text-white-50 mb-0 mx-auto" style="max-width: 620px; font-size: 1.05rem;">
            Unlock 0% flat marketplace commission, complimentary monthly listing boosts, and verified Gold seller status.
        </p>
    </div>
</div>

<div class="container py-2 pb-5">

    <?php if ($isVip && $vipDetails): ?>
        <!-- Active VIP Member Card -->
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-5 bg-white border" style="border-top: 4px solid var(--gold-primary) !important;">
            <div class="card-body p-4 p-md-5">
                <div class="row align-items-center g-4">
                    <div class="col-md-3 text-center">
                        <div class="seller-avatar bg-gold text-dark mx-auto mb-2" style="width: 70px; height: 70px; font-size: 2.2rem;">
                            👑
                        </div>
                        <span class="badge bg-gold text-dark fw-bold px-3 py-1 rounded-pill">Active VIP Member</span>
                    </div>
                    <div class="col-md-6">
                        <h4 class="fw-bold text-navy mb-1 font-serif-title">Welcome Back, VIP Seller!</h4>
                        <p class="text-secondary small mb-2">
                            Your <strong><?= ucfirst($vipDetails['membership_type']) ?> Plan</strong> is active until <strong><?= date('F d, Y', strtotime($vipDetails['end_date'])) ?></strong>.
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1.5 px-2.5">
                                <i class="bi bi-check-circle-fill me-1"></i> 0% Flat Commission Active
                            </span>
                            <span class="badge bg-teal-subtle text-teal border border-teal-subtle py-1.5 px-2.5">
                                <i class="bi bi-patch-check-fill me-1"></i> Verified Gold Badge Live
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <a href="seller-earnings.php" class="btn btn-booksy-primary fw-bold w-100 mb-2">
                            <i class="bi bi-wallet2 me-1"></i> View Earnings
                        </a>
                        <a href="sell.php" class="btn btn-outline-dark btn-sm w-100">
                            Post New Book
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4 Core Benefits Grid -->
    <div class="text-center mb-5">
        <h3 class="fw-bold text-navy font-serif-title mb-2">Why Power Sellers Choose VIP</h3>
        <p class="text-muted small mx-auto" style="max-width: 500px;">
            Designed for students reselling multiple course packs, tuition book distributors, and passionate collectors.
        </p>
    </div>

    <div class="row g-4 mb-5">
        <!-- Benefit 1 -->
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white border h-100">
                <div class="rounded-circle bg-teal-light text-teal d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 55px; height: 55px; font-size: 1.5rem;">
                    <i class="bi bi-percent"></i>
                </div>
                <h5 class="fw-bold text-navy mb-2">0% Flat Commission</h5>
                <p class="text-muted small mb-0">
                    Sell LKR 10,000, LKR 50,000 or more with zero commission. Keep 100% of every sale!
                </p>
            </div>
        </div>

        <!-- Benefit 2 -->
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white border h-100">
                <div class="rounded-circle bg-gold-light text-dark d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 55px; height: 55px; font-size: 1.5rem;">
                    <i class="bi bi-rocket-takeoff-fill"></i>
                </div>
                <h5 class="fw-bold text-navy mb-2">Free Monthly Boosts</h5>
                <p class="text-muted small mb-0">
                    Receive 2 complimentary 7-day listing boosts every billing cycle (LKR 500 value).
                </p>
            </div>
        </div>

        <!-- Benefit 3 -->
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white border h-100">
                <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 55px; height: 55px; font-size: 1.5rem;">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <h5 class="fw-bold text-navy mb-2">Gold Verified Badge</h5>
                <p class="text-muted small mb-0">
                    Display a distinguished Gold Shield on your profile and listings to build immediate buyer trust.
                </p>
            </div>
        </div>

        <!-- Benefit 4 -->
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white border h-100">
                <div class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 55px; height: 55px; font-size: 1.5rem;">
                    <i class="bi bi-headset"></i>
                </div>
                <h5 class="fw-bold text-navy mb-2">Priority Payouts</h5>
                <p class="text-muted small mb-0">
                    24-hour fast-track courier payout clearance and dedicated VIP support on WhatsApp.
                </p>
            </div>
        </div>
    </div>

    <!-- Pricing Comparison & Subscribe Cards -->
    <div class="row g-4 justify-content-center">
        <!-- Monthly Plan -->
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white border h-100 d-flex flex-column">
                <div class="p-4 p-md-5 border-bottom bg-light">
                    <span class="badge bg-navy text-white px-3 py-1 rounded-pill small mb-2">Flexible Plan</span>
                    <h4 class="fw-bold text-navy mb-1 font-serif-title">Monthly VIP</h4>
                    <p class="text-muted small mb-3">Pay as you go month-to-month. Cancel anytime.</p>
                    <div class="display-5 fw-extrabold text-navy">
                        Rs. <?= number_format($monthlyPrice, 2) ?>
                        <span class="fs-6 text-muted fw-normal">/ month</span>
                    </div>
                </div>

                <div class="p-4 p-md-5 d-flex flex-column flex-fill">
                    <ul class="list-unstyled d-flex flex-column gap-2.5 small text-secondary mb-4 flex-fill">
                        <li><i class="bi bi-check-lg text-success fw-bold me-2"></i> <strong>0% Commission</strong> on all sales</li>
                        <li><i class="bi bi-check-lg text-success fw-bold me-2"></i> 2 Complimentary listing boosts / month</li>
                        <li><i class="bi bi-check-lg text-success fw-bold me-2"></i> Verified Gold VIP Seller badge</li>
                        <li><i class="bi bi-check-lg text-success fw-bold me-2"></i> Early access price drop alerts</li>
                        <li><i class="bi bi-check-lg text-success fw-bold me-2"></i> Priority WhatsApp support</li>
                    </ul>

                    <form method="POST" action="vip.php">
                        <input type="hidden" name="subscribe_vip" value="1">
                        <input type="hidden" name="plan_type" value="monthly">
                        <button type="submit" class="btn btn-outline-dark btn-lg w-100 fw-bold py-2.5">
                            <?= $isVip ? 'Renew Monthly VIP' : 'Join Monthly VIP' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Annual Plan (Recommended) -->
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white border h-100 d-flex flex-column position-relative" style="border: 2px solid var(--teal-primary) !important;">
                <div class="position-absolute top-0 end-0 m-3">
                    <span class="badge bg-teal px-3 py-1.5 rounded-pill shadow-sm">Save 17% (2 Months Free)</span>
                </div>

                <div class="p-4 p-md-5 border-bottom bg-navy text-white">
                    <span class="badge bg-gold text-dark px-3 py-1 rounded-pill small mb-2 fw-bold">Best Value</span>
                    <h4 class="fw-bold text-white mb-1 font-serif-title">Annual VIP Master</h4>
                    <p class="text-white-50 small mb-3">12 Full Months of unlimited selling & top exposure.</p>
                    <div class="display-5 fw-extrabold text-white">
                        Rs. <?= number_format($annualPrice, 2) ?>
                        <span class="fs-6 text-white-50 fw-normal">/ year</span>
                    </div>
                </div>

                <div class="p-4 p-md-5 d-flex flex-column flex-fill">
                    <ul class="list-unstyled d-flex flex-column gap-2.5 small text-secondary mb-4 flex-fill">
                        <li><i class="bi bi-check-lg text-teal fw-bold me-2"></i> <strong>0% Commission</strong> for a full 365 days</li>
                        <li><i class="bi bi-check-lg text-teal fw-bold me-2"></i> <strong>24 Free listing boosts</strong> included</li>
                        <li><i class="bi bi-check-lg text-teal fw-bold me-2"></i> Permanent Gold Verified badge</li>
                        <li><i class="bi bi-check-lg text-teal fw-bold me-2"></i> Dedicated account manager</li>
                        <li><i class="bi bi-check-lg text-teal fw-bold me-2"></i> 24-hour express courier settlement</li>
                    </ul>

                    <form method="POST" action="vip.php">
                        <input type="hidden" name="subscribe_vip" value="1">
                        <input type="hidden" name="plan_type" value="annual">
                        <button type="submit" class="btn btn-booksy-primary btn-lg w-100 fw-bold py-2.5 shadow">
                            <i class="bi bi-gem me-1 text-gold"></i> <?= $isVip ? 'Extend Annual VIP' : 'Get Annual VIP Access' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
