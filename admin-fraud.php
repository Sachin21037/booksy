<?php
/**
 * Booksy - Trust & Fraud Moderation Center (Admin & Owner Portal)
 * Complete moderation pipeline: Triage Queue, Risk Scoring, Investigation Dossiers,
 * Evidence Inspector, Graduated 3-Tier Enforcement, Automated Heuristics, and Live Risk Simulator.
 */

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Trust & Fraud Moderation Center - Platform Governance';

// Require Admin or Owner privileges
if (!is_admin()) {
    set_flash('danger', 'Administrative privileges required to access the Trust & Fraud Moderation Center.');
    header("Location: login.php?redirect=admin-fraud.php");
    exit;
}

ensure_fraud_tables($pdo);

// Filtering Parameters
$activeTab    = trim($_GET['tab'] ?? 'triage');
$filterStatus = trim($_GET['status'] ?? 'pending');
$filterRisk   = trim($_GET['risk'] ?? '');
$filterReason = trim($_GET['reason'] ?? '');
$searchQuery  = trim($_GET['q'] ?? '');

$analytics = get_fraud_analytics($pdo);

// Build report filters
$filters = [];
if (!empty($filterStatus) && $filterStatus !== 'all') {
    $filters['status'] = $filterStatus;
}
if (!empty($filterRisk)) {
    $filters['priority'] = $filterRisk;
}
if (!empty($filterReason)) {
    $filters['reason'] = $filterReason;
}

$allReports = get_triage_reports($pdo, $filters);

// Filter by search query if provided
if (!empty($searchQuery)) {
    $qLower = strtolower($searchQuery);
    $allReports = array_values(array_filter($allReports, function($r) use ($qLower) {
        return (strpos(strtolower((string)$r['id']), $qLower) !== false)
            || (strpos(strtolower($r['reporter_name'] ?? ''), $qLower) !== false)
            || (strpos(strtolower($r['seller_name'] ?? ''), $qLower) !== false)
            || (strpos(strtolower($r['book_title'] ?? ''), $qLower) !== false)
            || (strpos(strtolower($r['details'] ?? ''), $qLower) !== false)
            || (strpos(strtolower($r['reason'] ?? ''), $qLower) !== false);
    }));
}

// Quarantined Listings
$quarantinedListings = $pdo->query("
    SELECT b.*, c.name AS category_name, u.name AS seller_name, u.email AS seller_email, u.phone AS seller_phone, u.account_status AS seller_status 
    FROM books b 
    JOIN categories c ON b.category_id = c.id 
    JOIN users u ON b.seller_id = u.id 
    WHERE b.status = 'quarantined'
    ORDER BY b.created_at DESC, b.id DESC
")->fetchAll();

// Automated Signals
$signals = $pdo->query("
    SELECT fs.*, u.name AS user_name, u.email AS user_email, b.title AS book_title 
    FROM fraud_signals fs 
    LEFT JOIN users u ON fs.user_id = u.id 
    LEFT JOIN books b ON fs.book_id = b.id 
    ORDER BY fs.created_at DESC 
    LIMIT 50
")->fetchAll();

// Moderation Logs
$auditLogs = $pdo->query("
    SELECT ml.*, adm.name AS admin_name, u.name AS target_user_name, b.title AS target_book_title 
    FROM moderation_logs ml 
    JOIN users adm ON ml.admin_id = adm.id 
    LEFT JOIN users u ON ml.target_user_id = u.id 
    LEFT JOIN books b ON ml.target_book_id = b.id 
    ORDER BY ml.created_at DESC 
    LIMIT 60
")->fetchAll();

include 'includes/header.php';
?>

<!-- Admin Executive Header Banner -->
<div class="hero-banner-subpage mb-4" style="background: linear-gradient(135deg, #071322 0%, #102A43 50%, #1A365D 100%); border-bottom: 2px solid var(--teal-primary, #0D9488);">
    <div class="container py-2">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-danger text-white fw-bold px-3 py-1.5 d-inline-flex align-items-center rounded-pill shadow-sm">
                        <i class="bi bi-shield-lock-fill me-1.5"></i> Trust, Safety & Fraud Control
                    </span>
                    <?php if (is_owner()): ?>
                        <span class="badge bg-warning text-dark fw-bold px-2.5 py-1.5 rounded-pill shadow-sm">
                            <i class="bi bi-crown-fill me-1"></i> Platform Owner
                        </span>
                    <?php else: ?>
                        <span class="badge bg-white-10 text-white-50 small px-2.5 py-1.5 rounded-pill border border-white-10">
                            <i class="bi bi-person-badge-fill me-1"></i> Site Manager
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-teal-dark text-teal-light small px-2.5 py-1.5 rounded-pill border border-teal-subtle">
                        <i class="bi bi-activity me-1"></i> Live Heuristic Engine Active
                    </span>
                </div>
                <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">Trust & Fraud Moderation Center</h1>
                <p class="text-white-50 mb-0">Community reports triage, automated risk heuristics, and graduated 3-tier scam enforcement</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="api/moderate-fraud.php" class="d-inline">
                    <input type="hidden" name="action" value="run_heuristic_scan">
                    <input type="hidden" name="redirect" value="../admin-fraud.php?tab=signals">
                    <button type="submit" class="btn btn-warning fw-bold px-3 py-2 rounded-pill shadow-sm d-flex align-items-center gap-1.5 text-dark">
                        <i class="bi bi-cpu-fill"></i> Run Heuristic Scan
                    </button>
                </form>
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm px-3 py-2 rounded-pill dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download"></i> Export Data
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                        <li>
                            <form method="POST" action="api/moderate-fraud.php">
                                <input type="hidden" name="action" value="export_csv">
                                <input type="hidden" name="type" value="reports">
                                <button type="submit" class="dropdown-item py-2"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i> Export Fraud Reports CSV</button>
                            </form>
                        </li>
                        <li>
                            <form method="POST" action="api/moderate-fraud.php">
                                <input type="hidden" name="action" value="export_csv">
                                <input type="hidden" name="type" value="audit">
                                <button type="submit" class="dropdown-item py-2"><i class="bi bi-journal-text me-2 text-success"></i> Export Enforcement Audit CSV</button>
                            </form>
                        </li>
                    </ul>
                </div>
                <a href="admin-fraud.php" class="btn btn-outline-light btn-sm px-3 py-2 rounded-pill d-flex align-items-center gap-1">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Queue
                </a>
            </div>
        </div>

        <!-- Admin Navigation Tabs -->
        <div class="d-flex flex-wrap gap-2 pt-2 border-top border-white-10" style="border-top: 1px solid rgba(255,255,255,0.12);">
            <a href="admin-monetization.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                <i class="bi bi-graph-up-arrow me-1 text-teal"></i> Monetization & Revenue
            </a>
            <a href="admin-banners.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                <i class="bi bi-images me-1 text-teal"></i> Hero & Promo Banners
            </a>
            <a href="admin-fraud.php" class="btn btn-sm btn-light fw-bold rounded-pill px-3 py-1.5 shadow-sm">
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

<div class="container py-2 py-lg-3">

    <!-- KPI Summary Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Critical & High Risk -->
        <div class="col-6 col-md-4 col-lg-2">
            <a href="admin-fraud.php?tab=triage&status=pending&risk=critical" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-danger hover-card transition-all">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Critical / High</span>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    </div>
                    <h3 class="fw-bold text-danger mb-0"><?= $analytics['critical_high_risk'] ?></h3>
                    <span class="text-muted small" style="font-size: 0.72rem;">Priority Triage Cases</span>
                </div>
            </a>
        </div>

        <!-- 2. Pending Triage Reports -->
        <div class="col-6 col-md-4 col-lg-2">
            <a href="admin-fraud.php?tab=triage&status=pending" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-warning hover-card transition-all">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Pending Reports</span>
                        <span class="badge bg-warning bg-opacity-10 text-dark rounded-pill"><i class="bi bi-clock-history"></i></span>
                    </div>
                    <h3 class="fw-bold text-navy mb-0"><?= $analytics['pending_reports'] ?></h3>
                    <span class="text-muted small" style="font-size: 0.72rem;">Buyer Submissions</span>
                </div>
            </a>
        </div>

        <!-- 3. Quarantined Listings -->
        <div class="col-6 col-md-4 col-lg-2">
            <a href="admin-fraud.php?tab=quarantine" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-dark hover-card transition-all">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Quarantined</span>
                        <span class="badge bg-dark bg-opacity-10 text-dark rounded-pill"><i class="bi bi-shield-slash"></i></span>
                    </div>
                    <h3 class="fw-bold text-dark mb-0"><?= $analytics['quarantined_listings'] ?></h3>
                    <span class="text-muted small" style="font-size: 0.72rem;">Hidden from Search</span>
                </div>
            </a>
        </div>

        <!-- 4. Restricted / Suspended Sellers -->
        <div class="col-6 col-md-4 col-lg-2">
            <a href="admin-fraud.php?tab=audit" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-danger hover-card transition-all">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Sanctioned</span>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill"><i class="bi bi-person-x-fill"></i></span>
                    </div>
                    <h3 class="fw-bold text-danger mb-0"><?= $analytics['restricted_sellers'] ?></h3>
                    <span class="text-muted small" style="font-size: 0.72rem;">Held or Suspended</span>
                </div>
            </a>
        </div>

        <!-- 5. Automated Risk Signals -->
        <div class="col-6 col-md-4 col-lg-2">
            <a href="admin-fraud.php?tab=signals" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-info hover-card transition-all">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Heuristics</span>
                        <span class="badge bg-info bg-opacity-10 text-info rounded-pill"><i class="bi bi-cpu"></i></span>
                    </div>
                    <h3 class="fw-bold text-info mb-0"><?= $analytics['active_signals'] ?></h3>
                    <span class="text-muted small" style="font-size: 0.72rem;">Automated Flags</span>
                </div>
            </a>
        </div>

        <!-- 6. Resolved Cases -->
        <div class="col-6 col-md-4 col-lg-2">
            <a href="admin-fraud.php?tab=triage&status=action_taken" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-success hover-card transition-all">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Resolved Cases</span>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill"><i class="bi bi-check2-circle"></i></span>
                    </div>
                    <h3 class="fw-bold text-success mb-0"><?= $analytics['resolved_count'] ?></h3>
                    <span class="text-muted small" style="font-size: 0.72rem;">Actions / Dismissals</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Main Workspace Navigation Tabs -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4 border">
        <div class="card-header bg-white border-bottom p-3">
            <ul class="nav nav-pills nav-fill gap-2" id="fraudTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'triage' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="triage-tab" data-bs-toggle="pill" data-bs-target="#triage-pane" type="button" role="tab">
                        <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                        <span>Triage Moderation Queue</span>
                        <span class="badge bg-danger rounded-pill ms-1"><?= count($allReports) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'quarantine' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="quarantine-tab" data-bs-toggle="pill" data-bs-target="#quarantine-pane" type="button" role="tab">
                        <i class="bi bi-shield-slash-fill text-dark"></i>
                        <span>Quarantined Listings</span>
                        <span class="badge bg-light text-dark rounded-pill ms-1"><?= count($quarantinedListings) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'signals' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="signals-tab" data-bs-toggle="pill" data-bs-target="#signals-pane" type="button" role="tab">
                        <i class="bi bi-cpu text-teal"></i>
                        <span>Automated Risk Signals</span>
                        <span class="badge bg-light text-dark rounded-pill ms-1"><?= count($signals) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'audit' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="audit-tab" data-bs-toggle="pill" data-bs-target="#audit-pane" type="button" role="tab">
                        <i class="bi bi-journal-check text-primary"></i>
                        <span>Moderation Audit Trail</span>
                        <span class="badge bg-light text-dark rounded-pill ms-1"><?= count($auditLogs) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'rules' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="rules-tab" data-bs-toggle="pill" data-bs-target="#rules-pane" type="button" role="tab">
                        <i class="bi bi-sliders text-success"></i>
                        <span>Rules & Risk Simulator</span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-3 p-lg-4">
            <div class="tab-content" id="fraudTabsContent">

                <!-- ================================================================= -->
                <!-- TAB 1: TRIAGE MODERATION QUEUE                                    -->
                <!-- ================================================================= -->
                <div class="tab-pane fade <?= $activeTab === 'triage' ? 'show active' : '' ?>" id="triage-pane" role="tabpanel" aria-labelledby="triage-tab">
                    
                    <!-- Filters & Search Toolbar -->
                    <div class="p-3 bg-light rounded-4 border mb-4">
                        <form method="GET" action="admin-fraud.php" class="row g-2 align-items-center">
                            <input type="hidden" name="tab" value="triage">
                            
                            <div class="col-md-3 col-lg-3">
                                <label class="small text-muted fw-bold mb-1">Search Queue:</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control border-start-0 ps-0" placeholder="Case #, buyer, seller, book...">
                                </div>
                            </div>

                            <div class="col-md-3 col-lg-2">
                                <label class="small text-muted fw-bold mb-1">Status:</label>
                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Triage (<?= $analytics['pending_reports'] ?>)</option>
                                    <option value="reviewing" <?= $filterStatus === 'reviewing' ? 'selected' : '' ?>>Under Review</option>
                                    <option value="action_taken" <?= $filterStatus === 'action_taken' ? 'selected' : '' ?>>Action Enforced</option>
                                    <option value="dismissed" <?= $filterStatus === 'dismissed' ? 'selected' : '' ?>>Dismissed / Closed</option>
                                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                </select>
                            </div>

                            <div class="col-md-3 col-lg-2">
                                <label class="small text-muted fw-bold mb-1">Risk Priority:</label>
                                <select name="risk" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">All Risk Levels</option>
                                    <option value="critical" <?= $filterRisk === 'critical' ? 'selected' : '' ?>>🔴 Critical (75+)</option>
                                    <option value="high" <?= $filterRisk === 'high' ? 'selected' : '' ?>>🟠 High (60-74)</option>
                                    <option value="medium" <?= $filterRisk === 'medium' ? 'selected' : '' ?>>🟡 Medium (40-59)</option>
                                    <option value="low" <?= $filterRisk === 'low' ? 'selected' : '' ?>>🔵 Low (&lt;40)</option>
                                </select>
                            </div>

                            <div class="col-md-3 col-lg-3">
                                <label class="small text-muted fw-bold mb-1">Violation Reason:</label>
                                <select name="reason" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">All Violation Reasons</option>
                                    <option value="off_platform_payment" <?= $filterReason === 'off_platform_payment' ? 'selected' : '' ?>>Off-Platform Solicitation</option>
                                    <option value="counterfeit" <?= $filterReason === 'counterfeit' ? 'selected' : '' ?>>Counterfeit / Replica</option>
                                    <option value="not_received" <?= $filterReason === 'not_received' ? 'selected' : '' ?>>Item Not Received</option>
                                    <option value="misleading" <?= $filterReason === 'misleading' ? 'selected' : '' ?>>Misleading Description</option>
                                    <option value="fake_profile" <?= $filterReason === 'fake_profile' ? 'selected' : '' ?>>Fake Profile / Impersonation</option>
                                    <option value="inappropriate" <?= $filterReason === 'inappropriate' ? 'selected' : '' ?>>Inappropriate Content</option>
                                    <option value="spam" <?= $filterReason === 'spam' ? 'selected' : '' ?>>Spam & Harassment</option>
                                </select>
                            </div>

                            <div class="col-md-12 col-lg-2 d-flex align-items-end gap-1">
                                <button type="submit" class="btn btn-sm btn-navy fw-bold w-100">
                                    <i class="bi bi-funnel-fill me-1"></i> Filter
                                </button>
                                <a href="admin-fraud.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Bulk Actions Bar (hidden until items selected) -->
                    <div id="bulkActionsToolbar" class="p-2.5 bg-navy text-white rounded-3 shadow-sm mb-3 d-none align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning text-dark fw-bold" id="selectedCountBadge">0 selected</span>
                            <span class="small text-white-50">Bulk Actions:</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light fw-bold rounded-pill px-3" onclick="executeBulkAction('bulk_reviewing')">
                                <i class="bi bi-search me-1 text-info"></i> Mark Reviewing
                            </button>
                            <button type="button" class="btn btn-sm btn-warning fw-bold rounded-pill px-3 text-dark" onclick="executeBulkAction('bulk_level1_warning')">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Bulk Level 1 Warning
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="executeBulkAction('bulk_dismiss')">
                                <i class="bi bi-x-circle me-1"></i> Bulk Dismiss
                            </button>
                        </div>
                    </div>

                    <?php if (empty($allReports)): ?>
                        <div class="text-center py-5 bg-light rounded-4 border">
                            <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center p-4 mb-3">
                                <i class="bi bi-shield-check text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="fw-bold text-navy mt-1">Triage Queue is Clear!</h5>
                            <p class="text-muted mb-3" style="max-width: 450px; margin: 0 auto;">No moderation cases match your current filter parameters. All buyer reports are currently triaged and resolved.</p>
                            <a href="admin-fraud.php?tab=triage&status=all" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                                View All Historical Cases
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border rounded-4 overflow-hidden mb-0">
                                <thead class="table-light text-uppercase small text-navy fw-bold">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" class="form-check-input" id="selectAllReports" onchange="toggleSelectAllReports(this)">
                                        </th>
                                        <th style="width: 70px;">Case #</th>
                                        <th style="width: 140px;">Risk Score</th>
                                        <th>Reason & Summary</th>
                                        <th>Target Seller & Item</th>
                                        <th>Reporter</th>
                                        <th style="width: 100px;">Evidence</th>
                                        <th style="width: 120px;">Status</th>
                                        <th style="width: 200px;" class="text-end">Moderation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allReports as $r): ?>
                                        <?php 
                                        $badgeClass = 'bg-secondary';
                                        if ($r['risk_level'] === 'critical') $badgeClass = 'bg-danger';
                                        elseif ($r['risk_level'] === 'high') $badgeClass = 'bg-warning text-dark';
                                        elseif ($r['risk_level'] === 'medium') $badgeClass = 'bg-info text-dark';
                                        elseif ($r['risk_level'] === 'low') $badgeClass = 'bg-primary';

                                        $reasonFormatted = ucwords(str_replace('_', ' ', $r['reason']));
                                        $hasAttachments = !empty($r['attachments_arr']);
                                        ?>
                                        <tr class="<?= $r['risk_level'] === 'critical' && $r['status'] === 'pending' ? 'table-danger bg-opacity-10' : '' ?>">
                                            <td>
                                                <input type="checkbox" class="form-check-input report-row-check" value="<?= $r['id'] ?>" onchange="updateBulkToolbar()">
                                            </td>
                                            <td class="fw-bold text-navy">
                                                #<?= $r['id'] ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <span class="badge <?= $badgeClass ?> px-2.5 py-1 text-uppercase fw-bold rounded-pill" style="font-size: 0.72rem;">
                                                        <?= $r['risk_level'] ?> (<?= $r['risk_score'] ?>/100)
                                                    </span>
                                                    <div class="progress" style="height: 5px; background-color: #e2e8f0;">
                                                        <div class="progress-bar <?= $r['risk_score'] >= 75 ? 'bg-danger' : ($r['risk_score'] >= 50 ? 'bg-warning' : 'bg-info') ?>" style="width: <?= $r['risk_score'] ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold text-navy mb-0.5">
                                                        <?= htmlspecialchars($reasonFormatted) ?>
                                                    </span>
                                                    <span class="text-muted small line-clamp-1" style="max-width: 320px;">
                                                        <?= htmlspecialchars($r['details']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <?php if (!empty($r['seller_name'])): ?>
                                                        <div class="d-flex align-items-center gap-1 mb-0.5">
                                                            <i class="bi bi-person text-danger"></i>
                                                            <strong><?= htmlspecialchars($r['seller_name']) ?></strong>
                                                            <?php if (!empty($r['seller_status']) && $r['seller_status'] !== 'active'): ?>
                                                                <span class="badge bg-danger rounded-pill px-1.5 py-0.5" style="font-size: 0.65rem;"><?= $r['seller_status'] ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($r['book_title'])): ?>
                                                        <div class="text-muted text-truncate" style="max-width: 200px;">
                                                            <i class="bi bi-book text-teal me-1"></i><?= htmlspecialchars($r['book_title']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <strong class="text-navy"><?= htmlspecialchars($r['reporter_name']) ?></strong>
                                                    <div class="text-muted" style="font-size: 0.72rem;"><?= date('M d, H:i', strtotime($r['created_at'])) ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($hasAttachments): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-teal rounded-pill px-2.5 py-0.5 small" onclick="openLightbox('<?= htmlspecialchars($r['attachments_arr'][0]) ?>')" title="Preview Evidence Image">
                                                        <i class="bi bi-paperclip me-1"></i> <?= count($r['attachments_arr']) ?> file(s)
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($r['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i> Pending</span>
                                                <?php elseif ($r['status'] === 'reviewing'): ?>
                                                    <span class="badge bg-info text-dark"><i class="bi bi-search me-1"></i> Reviewing</span>
                                                <?php elseif ($r['status'] === 'action_taken'): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-shield-fill-check me-1"></i> Action Taken</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i> Dismissed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end align-items-center gap-1.5 flex-wrap">
                                                    <button type="button" class="btn btn-sm btn-navy fw-bold px-2.5 py-1 rounded-pill" onclick="openInvestigationDossier(<?= $r['id'] ?>)">
                                                        <i class="bi bi-search me-1"></i> Dossier
                                                    </button>
                                                    <?php if ($r['status'] === 'pending'): ?>
                                                        <form method="POST" action="api/moderate-fraud.php" class="d-inline">
                                                            <input type="hidden" name="action" value="set_investigating">
                                                            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                            <input type="hidden" name="redirect" value="../admin-fraud.php?tab=triage&status=<?= $filterStatus ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-info rounded-pill px-2 py-1" title="Mark Under Review">
                                                                <i class="bi bi-hourglass-split"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-2 py-1 rounded-pill" onclick="openPenaltyModal(<?= $r['id'] ?>, <?= $r['reported_user_id'] ?: 0 ?>, <?= $r['book_id'] ?: 0 ?>, '<?= htmlspecialchars(addslashes($r['seller_name'] ?? 'Seller')) ?>')" title="Enforce Graduated Penalty">
                                                        <i class="bi bi-shield-slash"></i>
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

                <!-- ================================================================= -->
                <!-- TAB 2: QUARANTINED LISTINGS                                       -->
                <!-- ================================================================= -->
                <div class="tab-pane fade <?= $activeTab === 'quarantine' ? 'show active' : '' ?>" id="quarantine-pane" role="tabpanel" aria-labelledby="quarantine-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-navy mb-1"><i class="bi bi-shield-slash-fill text-dark me-2"></i> Quarantined / Hidden Listings</h5>
                            <p class="text-muted small mb-0">These listings are hidden from buyer catalog search due to automated risk anomalies or active fraud reports.</p>
                        </div>
                    </div>

                    <?php if (empty($quarantinedListings)): ?>
                        <div class="text-center py-5 bg-light rounded-4 border">
                            <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center p-4 mb-3">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="fw-bold text-navy mt-1">No Quarantined Listings</h5>
                            <p class="text-muted mb-0">All available marketplace books are currently in good standing.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($quarantinedListings as $q): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 border border-danger rounded-4 shadow-sm p-3 bg-white">
                                        <div class="d-flex align-items-start gap-3 mb-2">
                                            <div class="p-2 bg-light rounded border text-center" style="width: 75px; height: 95px; flex-shrink: 0;">
                                                <img src="<?= htmlspecialchars(!empty($q['image_url']) ? 'uploads/'.$q['image_url'] : 'uploads/default_book.svg') ?>" alt="Cover" class="img-fluid rounded" style="max-height: 100%; object-fit: contain;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                                            </div>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <span class="badge bg-danger rounded-pill px-2 py-0.5 small mb-1">Quarantined</span>
                                                <h6 class="fw-bold text-navy mb-1 text-truncate"><?= htmlspecialchars($q['title']) ?></h6>
                                                <p class="text-muted small mb-1 text-truncate">By <?= htmlspecialchars($q['author']) ?></p>
                                                <strong class="text-navy">Rs. <?= number_format($q['price'], 2) ?></strong>
                                            </div>
                                        </div>

                                        <div class="small text-muted border-top pt-2 mb-3">
                                            <div><i class="bi bi-person me-1"></i> Seller: <strong><?= htmlspecialchars($q['seller_name']) ?></strong></div>
                                            <div><i class="bi bi-telephone me-1"></i> Phone: <?= htmlspecialchars($q['seller_phone'] ?: 'N/A') ?></div>
                                            <div><i class="bi bi-tag me-1"></i> Condition: <?= htmlspecialchars($q['book_condition']) ?></div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <form method="POST" action="api/moderate-fraud.php" class="flex-fill">
                                                <input type="hidden" name="action" value="quarantine_listing">
                                                <input type="hidden" name="book_id" value="<?= $q['id'] ?>">
                                                <input type="hidden" name="state" value="restore">
                                                <input type="hidden" name="redirect" value="../admin-fraud.php?tab=quarantine">
                                                <button type="submit" class="btn btn-sm btn-outline-success w-100 fw-bold rounded-pill">
                                                    <i class="bi bi-check-circle me-1"></i> Restore
                                                </button>
                                            </form>
                                            <form method="POST" action="api/moderate-fraud.php" onsubmit="return confirm('Permanently remove this listing from catalog?');">
                                                <input type="hidden" name="action" value="quarantine_listing">
                                                <input type="hidden" name="book_id" value="<?= $q['id'] ?>">
                                                <input type="hidden" name="state" value="remove">
                                                <input type="hidden" name="redirect" value="../admin-fraud.php?tab=quarantine">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="Permanently Remove">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <a href="book-details.php?id=<?= $q['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-2.5" title="View Book Page">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================================================================= -->
                <!-- TAB 3: AUTOMATED HEURISTIC SIGNALS                                -->
                <!-- ================================================================= -->
                <div class="tab-pane fade <?= $activeTab === 'signals' ? 'show active' : '' ?>" id="signals-pane" role="tabpanel" aria-labelledby="signals-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-navy mb-1"><i class="bi bi-cpu text-teal me-2"></i> Automated Heuristic Risk Signals</h5>
                            <p class="text-muted small mb-0">Algorithms continuously analyze listings for price anomalies, off-platform solicitations, and account velocity spikes.</p>
                        </div>
                        <form method="POST" action="api/moderate-fraud.php">
                            <input type="hidden" name="action" value="run_heuristic_scan">
                            <input type="hidden" name="redirect" value="../admin-fraud.php?tab=signals">
                            <button type="submit" class="btn btn-sm btn-teal fw-bold rounded-pill px-3 shadow-sm">
                                <i class="bi bi-play-circle-fill me-1"></i> Scan Catalog Listings Now
                            </button>
                        </form>
                    </div>

                    <?php if (empty($signals)): ?>
                        <div class="text-center py-5 bg-light rounded-4 border">
                            <div class="avatar-lg bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center p-4 mb-3">
                                <i class="bi bi-shield-check text-info" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="fw-bold text-navy mt-1">No Active Heuristic Signals</h5>
                            <p class="text-muted mb-0">The heuristic engine has not flagged any new automated listing anomalies.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border rounded-4 overflow-hidden mb-0">
                                <thead class="table-light text-uppercase small text-navy fw-bold">
                                    <tr>
                                        <th>Signal Type</th>
                                        <th>Severity</th>
                                        <th>Target / Listing</th>
                                        <th>Detected Anomaly Details</th>
                                        <th>Timestamp</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($signals as $s): ?>
                                        <tr>
                                            <td class="fw-bold text-navy">
                                                <code><?= htmlspecialchars($s['signal_type']) ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $s['severity'] === 'critical' ? 'danger' : ($s['severity'] === 'high' ? 'warning text-dark' : 'info text-dark') ?> rounded-pill px-2.5 py-1 text-uppercase small">
                                                    <?= $s['severity'] ?> (+<?= $s['score_impact'] ?>)
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <?php if (!empty($s['user_name'])): ?>
                                                        <div><i class="bi bi-person text-danger me-1"></i> <?= htmlspecialchars($s['user_name']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($s['book_title'])): ?>
                                                        <div class="text-muted text-truncate" style="max-width: 220px;"><i class="bi bi-book text-teal me-1"></i> <?= htmlspecialchars($s['book_title']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-secondary"><?= htmlspecialchars($s['details']) ?></small>
                                            </td>
                                            <td class="small text-muted">
                                                <?= date('M d, H:i', strtotime($s['created_at'])) ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($s['status'] === 'active'): ?>
                                                    <form method="POST" action="api/moderate-fraud.php" class="d-inline">
                                                        <input type="hidden" name="action" value="resolve_signal">
                                                        <input type="hidden" name="signal_id" value="<?= $s['id'] ?>">
                                                        <input type="hidden" name="status" value="resolved">
                                                        <input type="hidden" name="redirect" value="../admin-fraud.php?tab=signals">
                                                        <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2.5 py-0.5 small">
                                                            <i class="bi bi-check2"></i> Resolve
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary rounded-pill px-2 py-0.5 small"><?= ucfirst($s['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================================================================= -->
                <!-- TAB 4: MODERATION AUDIT LOGS                                      -->
                <!-- ================================================================= -->
                <div class="tab-pane fade <?= $activeTab === 'audit' ? 'show active' : '' ?>" id="audit-pane" role="tabpanel" aria-labelledby="audit-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-navy mb-1"><i class="bi bi-journal-check text-primary me-2"></i> Enforcement Audit Trail</h5>
                            <p class="text-muted small mb-0">Immutable record of graduated penalties, warnings, quarantines, and dismissals executed by platform staff.</p>
                        </div>
                        <form method="POST" action="api/moderate-fraud.php">
                            <input type="hidden" name="action" value="export_csv">
                            <input type="hidden" name="type" value="audit">
                            <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-download me-1"></i> Export Audit Log CSV
                            </button>
                        </form>
                    </div>

                    <?php if (empty($auditLogs)): ?>
                        <div class="text-center py-5 bg-light rounded-4 border">
                            <i class="bi bi-journal-x text-muted" style="font-size: 3rem;"></i>
                            <h5 class="fw-bold text-navy mt-3">Audit Log is Empty</h5>
                            <p class="text-muted mb-0">Enforcement actions taken by staff will be recorded here.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border rounded-4 overflow-hidden mb-0">
                                <thead class="table-light text-uppercase small text-navy fw-bold">
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Moderator</th>
                                        <th>Action Executed</th>
                                        <th>Penalty Tier</th>
                                        <th>Target</th>
                                        <th>Compliance Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td class="small text-muted">
                                                <?= date('M d, Y H:i', strtotime($log['created_at'])) ?>
                                            </td>
                                            <td>
                                                <strong class="text-navy small"><i class="bi bi-shield-check text-success me-1"></i> <?= htmlspecialchars($log['admin_name']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?= htmlspecialchars($log['action_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['penalty_level'] === 'level1_warning'): ?>
                                                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i> Level 1: Warning</span>
                                                <?php elseif ($log['penalty_level'] === 'level2_restriction'): ?>
                                                    <span class="badge bg-danger bg-opacity-75 text-white"><i class="bi bi-slash-circle me-1"></i> Level 2: Hold</span>
                                                <?php elseif ($log['penalty_level'] === 'level3_ban'): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-ban me-1"></i> Level 3: Ban</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">None / Dismissed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <?php if (!empty($log['target_user_name'])): ?>
                                                        <div>User: <strong><?= htmlspecialchars($log['target_user_name']) ?></strong></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($log['target_book_title'])): ?>
                                                        <div class="text-muted text-truncate" style="max-width: 180px;">Item: <?= htmlspecialchars($log['target_book_title']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-secondary"><?= htmlspecialchars($log['notes']) ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================================================================= -->
                <!-- TAB 5: HEURISTIC RULES & LIVE RISK SIMULATOR                     -->
                <!-- ================================================================= -->
                <div class="tab-pane fade <?= $activeTab === 'rules' ? 'show active' : '' ?>" id="rules-pane" role="tabpanel" aria-labelledby="rules-tab">
                    <div class="row g-4">
                        <!-- Left Column: Policy Matrix & Thresholds -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4 border">
                                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-shield-check text-teal me-2"></i> Graduated 3-Tier Enforcement Policy</h5>
                                
                                <div class="list-group list-group-flush gap-3">
                                    <div class="list-group-item p-3 rounded-3 border bg-light">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-warning text-dark fw-bold">Level 1</span>
                                            <h6 class="fw-bold text-navy mb-0">Compliance Warning & Quarantine</h6>
                                        </div>
                                        <p class="text-muted small mb-0">Hides reported listing from buyer catalog. Issues formal email warning on policy violation. Seller can update listing details for reinstatement.</p>
                                    </div>

                                    <div class="list-group-item p-3 rounded-3 border bg-light">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-danger bg-opacity-75 text-white fw-bold">Level 2</span>
                                            <h6 class="fw-bold text-navy mb-0">Temporary Account Hold & Payout Freeze</h6>
                                        </div>
                                        <p class="text-muted small mb-0">Freezes seller escrow payouts, quarantines all active listings, and requires identity/proof reverification before unlocking.</p>
                                    </div>

                                    <div class="list-group-item p-3 rounded-3 border bg-light">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-danger fw-bold">Level 3</span>
                                            <h6 class="fw-bold text-navy mb-0">Permanent Ban & Blacklist</h6>
                                        </div>
                                        <p class="text-muted small mb-0">Permanently suspends account, blacklists email/phone credentials, cancels open orders, and removes all marketplace inventory.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Automated Safeguard Triggers -->
                            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white border">
                                <h5 class="fw-bold text-navy mb-3"><i class="bi bi-cpu text-primary me-2"></i> Automated Heuristic Safeguard Rules</h5>
                                <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small text-secondary">
                                    <li class="p-2.5 bg-light rounded-3 border">
                                        <strong>1. Report Density Guard:</strong> If a seller receives <strong>2+ distinct buyer reports within 72 hours</strong>, active listings are auto-quarantined to protect community members.
                                    </li>
                                    <li class="p-2.5 bg-light rounded-3 border">
                                        <strong>2. Extreme Price Anomaly Guard:</strong> Listings priced <strong>65%+ below category average</strong> for brand new condition are flagged as suspicious bait-and-switch candidates.
                                    </li>
                                    <li class="p-2.5 bg-light rounded-3 border">
                                        <strong>3. Fresh Account Velocity Guard:</strong> Accounts created <strong>&lt;48 hours ago</strong> that rapidly publish 4+ items trigger velocity anomaly flags.
                                    </li>
                                    <li class="p-2.5 bg-light rounded-3 border">
                                        <strong>4. Off-Platform Payment Keywords:</strong> Text scanning triggers on: <code>whatsapp</code>, <code>wire transfer</code>, <code>crypto</code>, <code>zelle</code>, <code>cashapp</code>, <code>direct bank</code>.
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Right Column: Live Risk Score Simulator -->
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white border h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold text-navy mb-0"><i class="bi bi-calculator text-teal me-2"></i> Live Risk Score Simulator</h5>
                                    <span class="badge bg-teal-light text-teal rounded-pill px-2.5 py-1">Risk Evaluator</span>
                                </div>
                                <p class="text-muted small mb-3">Simulate and test how the Composite Triage Engine evaluates potential violation scenarios in real-time.</p>

                                <form id="riskSimulatorForm" onsubmit="event.preventDefault(); runRiskSimulation();">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-navy">Violation Reason:</label>
                                        <select id="simReason" class="form-select form-select-sm">
                                            <option value="off_platform_payment">Off-Platform Payment Solicitation (+35 pts)</option>
                                            <option value="counterfeit">Counterfeit / Replica Goods (+30 pts)</option>
                                            <option value="not_received">Item Not Received / Tracking Fraud (+28 pts)</option>
                                            <option value="fake_profile">Identity Theft / Fake Profile (+25 pts)</option>
                                            <option value="misleading">Misleading Description (+18 pts)</option>
                                            <option value="inappropriate">Inappropriate Content (+15 pts)</option>
                                            <option value="spam">Spam / Harassment (+10 pts)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-navy">Stated Scenario Details / Messages:</label>
                                        <textarea id="simDetails" class="form-control form-control-sm" rows="3" placeholder="e.g. Please message me on WhatsApp 0771234567 and send money directly to my bank...">Seller asked to bypass Booksy checkout and wire money on WhatsApp.</textarea>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="simHasAttachment" checked>
                                            <label class="form-check-label small fw-semibold text-navy" for="simHasAttachment">Reporter attached screenshot evidence (+10 pts)</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="simHasOrder">
                                            <label class="form-check-label small fw-semibold text-navy" for="simHasOrder">Verified purchase Order ID attached (+15 pts)</label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-navy btn-sm fw-bold rounded-pill px-4 w-100 mb-3">
                                        <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Calculate Composite Risk Score
                                    </button>
                                </form>

                                <!-- Simulation Results Box -->
                                <div id="simResultsBox" class="p-3 bg-light rounded-4 border">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-bold text-navy">Simulated Result:</span>
                                        <span class="badge bg-danger rounded-pill px-3 py-1" id="simResultBadge">CRITICAL RISK</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <h2 class="fw-bold mb-0 text-danger" id="simResultScore">85<small class="text-muted fs-6">/100</small></h2>
                                        <div class="flex-grow-1">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-danger" id="simResultProgress" style="width: 85%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <h6 class="small fw-bold text-navy mb-1">Triggered Heuristics Breakdown:</h6>
                                    <ul class="small text-secondary mb-0 ps-3" id="simResultSignals">
                                        <li>Base violation severity weight: +35 pts (OFF PLATFORM PAYMENT)</li>
                                        <li>Off-platform trigger keywords detected: whatsapp, wire (+50 pts)</li>
                                        <li>Documented proof attached: 1 file(s) (+5 pts)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: INVESTIGATION DOSSIER                                                 -->
<!-- ============================================================================ -->
<div class="modal fade" id="investigationModal" tabindex="-1" aria-labelledby="investigationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 py-3" style="border-bottom: 2px solid var(--teal-primary, #0D9488);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-shaded text-teal fs-4"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="dossierCaseTitle">Fraud Investigation Dossier</h5>
                        <small class="text-white-50" id="dossierCaseSub">Case Analysis & Evidence Vault</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light" id="dossierModalBody">
                <!-- Dynamically populated via JS from report dataset -->
                <div class="text-center py-4">
                    <div class="spinner-border text-teal" role="status"></div>
                    <p class="text-muted small mt-2">Loading Investigation Dossier...</p>
                </div>
            </div>

            <!-- Graduated Enforcement Footer Action Bar -->
            <div class="modal-footer bg-white border-top p-3 d-flex justify-content-between align-items-center flex-wrap gap-2" id="dossierFooterActions">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close Dossier</button>
                <div class="d-flex flex-wrap gap-2" id="dossierActionButtons">
                    <!-- Dynamic Graduated Action Buttons -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: GRADUATED PENALTY ENFORCEMENT                                         -->
<!-- ============================================================================ -->
<div class="modal fade" id="penaltyActionModal" tabindex="-1" aria-labelledby="penaltyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="penaltyModalLabel">
                    <i class="bi bi-shield-slash-fill"></i>
                    <span>Execute Graduated Enforcement Penalty</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="api/moderate-fraud.php">
                <input type="hidden" name="action" value="apply_penalty">
                <input type="hidden" name="report_id" id="penReportId" value="">
                <input type="hidden" name="target_user_id" id="penTargetUserId" value="">
                <input type="hidden" name="target_book_id" id="penTargetBookId" value="">
                <input type="hidden" name="redirect" value="../admin-fraud.php">

                <div class="modal-body p-4 bg-white">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy">Target Seller / Account:</label>
                        <div class="p-2.5 bg-light rounded-3 border fw-semibold text-dark" id="penTargetNameDisplay"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy">Select Graduated Enforcement Tier <span class="text-danger">*</span></label>
                        <select name="penalty_level" id="penLevelSelect" class="form-select" required>
                            <option value="level1_warning">⚠️ Level 1 (First Warning) - Quarantine listing & issue warning</option>
                            <option value="level2_restriction">🚫 Level 2 (Temporary Hold / Restriction) - Freeze payouts & hide all seller listings</option>
                            <option value="level3_ban">⛔ Level 3 (Permanent Ban) - Suspend account & blacklist details</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy">Compliance & Audit Rationale Notes <span class="text-danger">*</span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Enter reason and findings for audit trail log..." required></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                        <i class="bi bi-shield-fill-exclamation me-1"></i> Apply Penalty
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: IMAGE EVIDENCE LIGHTBOX PREVIEW                                       -->
<!-- ============================================================================ -->
<div class="modal fade" id="evidenceLightboxModal" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 bg-dark text-white rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-white"><i class="bi bi-image me-1 text-teal"></i> Evidence Attachment Viewer</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img src="" id="lightboxEvidenceImg" alt="Evidence" class="img-fluid rounded" style="max-height: 80vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<!-- Bulk Action Hidden Form -->
<form id="bulkActionForm" method="POST" action="api/moderate-fraud.php" class="d-none">
    <input type="hidden" name="action" value="bulk_action">
    <input type="hidden" name="bulk_type" id="bulkFormType" value="">
    <input type="hidden" name="report_ids" id="bulkFormIds" value="">
    <input type="hidden" name="redirect" value="../admin-fraud.php?tab=triage">
</form>

<!-- Dossier & Triage JavaScript Controller -->
<script>
const allReportsData = <?= json_encode($allReports) ?>;

function openInvestigationDossier(reportId) {
    const report = allReportsData.find(r => r.id == reportId);
    if (!report) return;

    document.getElementById('dossierCaseTitle').textContent = `Investigation Dossier: Case #${report.id}`;
    document.getElementById('dossierCaseSub').textContent = `Violation Category: ${report.reason.replace(/_/g, ' ').toUpperCase()} • Priority: ${report.risk_level.toUpperCase()} (${report.risk_score}/100)`;

    let attachmentsHtml = '<p class="text-muted small mb-0">No file attachments provided by reporter.</p>';
    if (report.attachments_arr && report.attachments_arr.length > 0) {
        attachmentsHtml = '<div class="row g-2">';
        report.attachments_arr.forEach(att => {
            attachmentsHtml += `
                <div class="col-4 text-center">
                    <div class="p-1 bg-white rounded border shadow-sm" style="cursor: pointer;" onclick="openLightbox('${att}')">
                        <img src="${att}" class="img-fluid rounded" style="max-height: 120px; width: 100%; object-fit: cover;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                        <small class="text-muted d-block text-truncate mt-1" style="font-size: 0.7rem;"><i class="bi bi-zoom-in me-0.5"></i> Click to Zoom</small>
                    </div>
                </div>
            `;
        });
        attachmentsHtml += '</div>';
    }

    let signalsHtml = '<li class="text-muted small">No automated heuristics triggered.</li>';
    if (report.risk_signals_arr && report.risk_signals_arr.length > 0) {
        signalsHtml = '';
        report.risk_signals_arr.forEach(sig => {
            signalsHtml += `<li class="text-danger small mb-1"><i class="bi bi-shield-fill-exclamation me-1"></i> ${sig}</li>`;
        });
    }

    const html = `
        <div class="row g-4">
            <!-- Left: Case Overview & Signals -->
            <div class="col-lg-7">
                <!-- Case Summary Card -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3 border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-${report.risk_level === 'critical' ? 'danger' : (report.risk_level === 'high' ? 'warning text-dark' : 'info text-dark')} px-3 py-1 rounded-pill fw-bold text-uppercase">
                            ${report.risk_level} Risk (${report.risk_score}/100)
                        </span>
                        <span class="text-muted small"><i class="bi bi-clock me-1"></i> Reported on ${report.created_at}</span>
                    </div>
                    <h6 class="fw-bold text-navy mb-1">Reporter's Stated Issue:</h6>
                    <div class="p-3 bg-light rounded-3 text-secondary border mb-3">
                        ${report.details.replace(/\n/g, '<br>')}
                    </div>

                    <!-- Evidence Vault -->
                    <h6 class="fw-bold text-navy mb-2"><i class="bi bi-paperclip text-teal me-1"></i> Evidence Attachments:</h6>
                    <div class="p-3 bg-light rounded-3 border">
                        ${attachmentsHtml}
                    </div>
                </div>

                <!-- Automated Heuristics Breakdown -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border">
                    <h6 class="fw-bold text-navy mb-2"><i class="bi bi-cpu text-danger me-1"></i> Automated Risk Signals Breakdown:</h6>
                    <ul class="mb-0 ps-3">
                        ${signalsHtml}
                    </ul>
                </div>
            </div>

            <!-- Right: Target Entities & History -->
            <div class="col-lg-5">
                <!-- Seller Dossier -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3 border">
                    <h6 class="fw-bold text-navy mb-2"><i class="bi bi-person-badge text-danger me-1"></i> Target Seller Dossier:</h6>
                    <div class="p-2.5 bg-light rounded-3 border small">
                        <div><strong>Name:</strong> ${report.seller_name || 'N/A'}</div>
                        <div><strong>Email:</strong> ${report.seller_email || 'N/A'}</div>
                        <div><strong>Phone:</strong> ${report.seller_phone || 'N/A'}</div>
                        <div><strong>Account Status:</strong> <span class="badge bg-${report.seller_status === 'active' ? 'success' : 'danger'}">${report.seller_status || 'active'}</span></div>
                        <div><strong>Member Since:</strong> ${report.seller_joined ? report.seller_joined.substring(0, 10) : 'N/A'}</div>
                    </div>
                </div>

                <!-- Reported Listing Dossier -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-3 border">
                    <h6 class="fw-bold text-navy mb-2"><i class="bi bi-book text-teal me-1"></i> Reported Item:</h6>
                    <div class="p-2.5 bg-light rounded-3 border small">
                        <div><strong>Title:</strong> ${report.book_title || 'N/A'}</div>
                        <div><strong>Price:</strong> Rs. ${report.book_price ? Number(report.book_price).toFixed(2) : 'N/A'}</div>
                        <div><strong>Listing Status:</strong> <span class="badge bg-${report.book_status === 'available' ? 'success' : 'dark'}">${report.book_status || 'N/A'}</span></div>
                    </div>
                </div>

                <!-- Reporter Credibility -->
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border">
                    <h6 class="fw-bold text-navy mb-2"><i class="bi bi-person-check text-teal me-1"></i> Reporter Info:</h6>
                    <div class="p-2.5 bg-light rounded-3 border small">
                        <div><strong>Reporter:</strong> ${report.reporter_name}</div>
                        <div><strong>Email:</strong> ${report.reporter_email}</div>
                        <div><strong>Member Since:</strong> ${report.reporter_joined ? report.reporter_joined.substring(0, 10) : 'N/A'}</div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('dossierModalBody').innerHTML = html;

    // Build Action Buttons
    let actionsHtml = `
        <form method="POST" action="api/moderate-fraud.php" class="d-inline">
            <input type="hidden" name="action" value="dismiss_report">
            <input type="hidden" name="report_id" value="${report.id}">
            <input type="hidden" name="redirect" value="../admin-fraud.php">
            <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill fw-bold px-3">
                <i class="bi bi-x-circle me-1"></i> Dismiss Case
            </button>
        </form>
        <button type="button" class="btn btn-warning btn-sm rounded-pill fw-bold px-3 text-dark" onclick="openPenaltyModal(${report.id}, ${report.reported_user_id || 0}, ${report.book_id || 0}, '${report.seller_name || 'Seller'}', 'level1_warning')">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Level 1: Warning
        </button>
        <button type="button" class="btn btn-danger btn-sm rounded-pill fw-bold px-3 bg-opacity-75" onclick="openPenaltyModal(${report.id}, ${report.reported_user_id || 0}, ${report.book_id || 0}, '${report.seller_name || 'Seller'}', 'level2_restriction')">
            <i class="bi bi-slash-circle me-1"></i> Level 2: Restrict
        </button>
        <button type="button" class="btn btn-danger btn-sm rounded-pill fw-bold px-3" onclick="openPenaltyModal(${report.id}, ${report.reported_user_id || 0}, ${report.book_id || 0}, '${report.seller_name || 'Seller'}', 'level3_ban')">
            <i class="bi bi-ban me-1"></i> Level 3: Permanent Ban
        </button>
    `;

    document.getElementById('dossierActionButtons').innerHTML = actionsHtml;

    const modal = new bootstrap.Modal(document.getElementById('investigationModal'));
    modal.show();
}

function openPenaltyModal(reportId, userId, bookId, targetName, defaultLevel = 'level1_warning') {
    document.getElementById('penReportId').value = reportId || '';
    document.getElementById('penTargetUserId').value = userId || '';
    document.getElementById('penTargetBookId').value = bookId || '';
    document.getElementById('penTargetNameDisplay').textContent = targetName || 'Target Seller';
    document.getElementById('penLevelSelect').value = defaultLevel;

    const modal = new bootstrap.Modal(document.getElementById('penaltyActionModal'));
    modal.show();
}

function openLightbox(imgSrc) {
    document.getElementById('lightboxEvidenceImg').src = imgSrc;
    const modal = new bootstrap.Modal(document.getElementById('evidenceLightboxModal'));
    modal.show();
}

// Bulk Selection Management
function toggleSelectAllReports(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.report-row-check');
    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
    updateBulkToolbar();
}

function updateBulkToolbar() {
    const selected = Array.from(document.querySelectorAll('.report-row-check:checked')).map(cb => cb.value);
    const toolbar = document.getElementById('bulkActionsToolbar');
    const badge = document.getElementById('selectedCountBadge');

    if (selected.length > 0) {
        toolbar.classList.remove('d-none');
        toolbar.classList.add('d-flex');
        badge.textContent = `${selected.length} selected`;
    } else {
        toolbar.classList.remove('d-flex');
        toolbar.classList.add('d-none');
        const selectAll = document.getElementById('selectAllReports');
        if (selectAll) selectAll.checked = false;
    }
}

function executeBulkAction(bulkType) {
    const selected = Array.from(document.querySelectorAll('.report-row-check:checked')).map(cb => cb.value);
    if (selected.length === 0) return;

    if (bulkType === 'bulk_dismiss' && !confirm(`Are you sure you want to dismiss ${selected.length} reports?`)) {
        return;
    }
    if (bulkType === 'bulk_level1_warning' && !confirm(`Execute Level 1 Warning on ${selected.length} reports and quarantine items?`)) {
        return;
    }

    document.getElementById('bulkFormType').value = bulkType;
    document.getElementById('bulkFormIds').value = selected.join(',');
    document.getElementById('bulkActionForm').submit();
}

// Live Risk Score Simulator Sandbox
function runRiskSimulation() {
    const reason = document.getElementById('simReason').value;
    const details = document.getElementById('simDetails').value;
    const hasAttachment = document.getElementById('simHasAttachment').checked;
    const hasOrder = document.getElementById('simHasOrder').checked;

    let score = 0;
    const signals = [];

    const reasonScores = {
        'off_platform_payment': 35,
        'counterfeit': 30,
        'not_received': 28,
        'fake_profile': 25,
        'misleading': 18,
        'inappropriate': 15,
        'spam': 10
    };

    const baseReason = reasonScores[reason] || 10;
    score += baseReason;
    signals.push(`Base violation severity weight: +${baseReason} pts (${reason.replace(/_/g, ' ').toUpperCase()})`);

    // Keyword detection
    const keywords = ['whatsapp', 'wire', 'crypto', 'bitcoin', 'usdt', 'cashapp', 'zelle', 'direct bank', 'telegram', 'viber', 'pay outside'];
    const lower = details.toLowerCase();
    const detected = [];
    let kwImpact = 0;
    keywords.forEach(kw => {
        if (lower.includes(kw)) {
            detected.push(kw);
            kwImpact += 25;
        }
    });

    if (detected.length > 0) {
        const impact = Math.min(50, kwImpact);
        score += impact;
        signals.push(`Off-platform trigger keywords detected: ${detected.join(', ')} (+${impact} pts)`);
    }

    if (hasAttachment) {
        score += 10;
        signals.push('Documented proof screenshot attached (+10 pts)');
    }

    if (hasOrder) {
        score += 15;
        signals.push('Verified marketplace purchase order confirmed (+15 pts)');
    }

    const finalScore = Math.min(100, Math.max(5, score));
    let level = 'LOW RISK';
    let badgeClass = 'bg-primary';
    let textClass = 'text-primary';

    if (finalScore >= 75) {
        level = 'CRITICAL RISK';
        badgeClass = 'bg-danger';
        textClass = 'text-danger';
    } else if (finalScore >= 60) {
        level = 'HIGH RISK';
        badgeClass = 'bg-warning text-dark';
        textClass = 'text-warning';
    } else if (finalScore >= 40) {
        level = 'MEDIUM RISK';
        badgeClass = 'bg-info text-dark';
        textClass = 'text-info';
    }

    const badgeEl = document.getElementById('simResultBadge');
    badgeEl.className = `badge rounded-pill px-3 py-1 ${badgeClass}`;
    badgeEl.textContent = level;

    const scoreEl = document.getElementById('simResultScore');
    scoreEl.className = `fw-bold mb-0 ${textClass}`;
    scoreEl.innerHTML = `${finalScore}<small class="text-muted fs-6">/100</small>`;

    const progressEl = document.getElementById('simResultProgress');
    progressEl.className = `progress-bar ${badgeClass}`;
    progressEl.style.width = `${finalScore}%`;

    const listEl = document.getElementById('simResultSignals');
    listEl.innerHTML = signals.map(s => `<li>${s}</li>`).join('');
}
</script>

<?php include 'includes/footer.php'; ?>
