<?php
/**
 * Booksy - Banner & Showcase Studio (Admin & Owner Portal)
 * Complete interactive interface for customizing Hero Carousel slides, Promo cards, and Announcement banners.
 */

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Banner & Showcase Studio - Platform Governance';

// Require Admin or Owner privileges
if (!is_admin()) {
    set_flash('danger', 'Administrative privileges required to access the Banner & Showcase Studio.');
    header("Location: login.php?redirect=admin-banners.php");
    exit;
}

// Fetch all banners and summary metrics
ensure_banners_table($pdo);
$stats = get_banner_stats($pdo);
$allBanners = get_all_banners($pdo);

$heroSlides = array_values(array_filter($allBanners, fn($b) => $b['position'] === 'hero_slide'));
$promoCards = array_values(array_filter($allBanners, fn($b) => $b['position'] === 'promo_card'));
$announcements = array_values(array_filter($allBanners, fn($b) => $b['position'] === 'top_announcement'));

$activeTab = trim($_GET['tab'] ?? 'hero');

// Built-in preset images for fast selection
$presetImages = [
    'images/hero-cozy-books.jpg'      => 'Cozy Reading Stack (Default)',
    'images/hero-academic-study.jpg'  => 'Academic & Textbook Study',
    'images/hero-rare-vintage.jpg'    => 'Rare & Vintage Hardcovers',
    'images/library-wide-banner.jpg'  => 'Classic Grand Library',
    'images/hero-books-stack.jpg'     => 'Modern Bookstore Stack'
];

include 'includes/header.php';
?>

<!-- Admin Executive Header Banner -->
<div class="hero-banner-subpage mb-4" style="background: linear-gradient(135deg, #0A192F 0%, #172A45 60%, #0F3460 100%);">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-teal text-white fw-bold px-3 py-1 d-inline-flex align-items-center rounded-pill">
                        <i class="bi bi-images me-1"></i> Banner & Showcase Studio
                    </span>
                    <?php if (is_owner()): ?>
                        <span class="badge bg-warning text-dark fw-bold px-2.5 py-1 rounded-pill">
                            <i class="bi bi-crown-fill me-1"></i> Platform Owner
                        </span>
                    <?php else: ?>
                        <span class="badge bg-white-10 text-white-50 small px-2.5 py-1 rounded-pill border border-white-10">
                            <i class="bi bi-shield-lock-fill me-1"></i> Site Manager
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">Banner & Showcase Studio</h1>
                <p class="text-white-50 mb-0">Create, customize, reorder, and activate homepage hero slides, promo rail cards, and announcements</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-warning fw-bold px-3.5 py-2 rounded-pill shadow-sm d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#addBannerModal">
                    <i class="bi bi-plus-circle-fill"></i> Add New Banner
                </button>
                <button type="button" class="btn btn-outline-light btn-sm px-3 py-2 rounded-pill d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#resetDefaultsModal" title="Reset to standard Booksy banners">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset Defaults
                </button>
            </div>
        </div>

        <!-- Admin Navigation Tabs -->
        <div class="d-flex flex-wrap gap-2 pt-2 border-top border-white-10" style="border-top: 1px solid rgba(255,255,255,0.12);">
            <a href="admin-monetization.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                <i class="bi bi-graph-up-arrow me-1 text-teal"></i> Monetization & Revenue
            </a>
            <a href="admin-banners.php" class="btn btn-sm btn-light fw-bold rounded-pill px-3 py-1.5 shadow-sm">
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

<div class="container py-2 py-lg-4">

    <!-- KPI Summary Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Total Banners -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small fw-bold text-uppercase">Total Banners</span>
                    <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-collection-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-navy mb-0"><?= $stats['total'] ?></h3>
                <span class="text-muted small">Configured elements</span>
            </div>
        </div>

        <!-- 2. Active Hero Slides -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small fw-bold text-uppercase">Active Hero Slides</span>
                    <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success">
                        <i class="bi bi-sliders fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-success mb-0"><?= $stats['hero_active'] ?></h3>
                <span class="text-muted small">Rotating in carousel</span>
            </div>
        </div>

        <!-- 3. Active Promo Rail Cards -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small fw-bold text-uppercase">Active Promo Cards</span>
                    <div class="rounded-circle p-2 bg-info bg-opacity-10 text-info">
                        <i class="bi bi-card-checklist fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-navy mb-0"><?= $stats['promo_active'] ?></h3>
                <span class="text-muted small">Homepage right rail</span>
            </div>
        </div>

        <!-- 4. Inactive / Hidden -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-secondary">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small fw-bold text-uppercase">Inactive / Hidden</span>
                    <div class="rounded-circle p-2 bg-secondary bg-opacity-10 text-secondary">
                        <i class="bi bi-eye-slash-fill fs-5"></i>
                    </div>
                </div>
                <h3 class="fw-bold text-muted mb-0"><?= $stats['inactive'] ?></h3>
                <span class="text-muted small">Unpublished banners</span>
            </div>
        </div>
    </div>

    <!-- Main Workspace Navigation Tabs -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4 border">
        <div class="card-header bg-white border-bottom p-3">
            <ul class="nav nav-pills nav-fill gap-2" id="bannerTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'hero' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="hero-tab" data-bs-toggle="pill" data-bs-target="#hero-pane" type="button" role="tab" aria-controls="hero-pane" aria-selected="<?= $activeTab === 'hero' ? 'true' : 'false' ?>">
                        <i class="bi bi-film"></i>
                        <span>Hero Carousel Slides</span>
                        <span class="badge bg-light text-dark rounded-pill ms-1"><?= count($heroSlides) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'promo' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="promo-tab" data-bs-toggle="pill" data-bs-target="#promo-pane" type="button" role="tab" aria-controls="promo-pane" aria-selected="<?= $activeTab === 'promo' ? 'true' : 'false' ?>">
                        <i class="bi bi-grid-fill"></i>
                        <span>Promo Rail Cards</span>
                        <span class="badge bg-light text-dark rounded-pill ms-1"><?= count($promoCards) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'announcement' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="announcement-tab" data-bs-toggle="pill" data-bs-target="#announcement-pane" type="button" role="tab" aria-controls="announcement-pane" aria-selected="<?= $activeTab === 'announcement' ? 'true' : 'false' ?>">
                        <i class="bi bi-megaphone-fill"></i>
                        <span>Top Announcements</span>
                        <span class="badge bg-light text-dark rounded-pill ms-1"><?= count($announcements) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'preview' ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="preview-tab" data-bs-toggle="pill" data-bs-target="#preview-pane" type="button" role="tab" aria-controls="preview-pane" aria-selected="<?= $activeTab === 'preview' ? 'true' : 'false' ?>">
                        <i class="bi bi-eye-fill"></i>
                        <span>Live Carousel Preview</span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="bannerTabsContent">
                
                <!-- TAB 1: HERO SHOWCASE SLIDES -->
                <div class="tab-pane fade <?= $activeTab === 'hero' ? 'show active' : '' ?>" id="hero-pane" role="tabpanel" aria-labelledby="hero-tab">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
                        <div>
                            <h5 class="fw-bold text-navy mb-1"><i class="bi bi-film text-teal me-2"></i> Homepage Hero Carousel Slides</h5>
                            <p class="text-muted small mb-0">These slides rotate automatically at the top of the homepage to showcase featured books and promotions.</p>
                        </div>
                        <button type="button" class="btn btn-booksy-primary btn-sm px-3 py-2 rounded-pill fw-bold" onclick="openAddModalWithPosition('hero_slide')">
                            <i class="bi bi-plus-lg me-1"></i> Add Hero Slide
                        </button>
                    </div>

                    <?php if (empty($heroSlides)): ?>
                        <div class="text-center py-5 bg-light rounded-4 border">
                            <i class="bi bi-images text-muted" style="font-size: 3rem;"></i>
                            <h5 class="fw-bold text-navy mt-3">No Hero Slides Configured</h5>
                            <p class="text-muted mb-3">Add your first interactive slide or restore defaults to populate the homepage showcase.</p>
                            <button type="button" class="btn btn-warning fw-bold px-4 rounded-pill" onclick="openAddModalWithPosition('hero_slide')">
                                <i class="bi bi-plus-circle me-1"></i> Create First Slide
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($heroSlides as $slide): ?>
                                <div class="col-lg-6">
                                    <div class="card h-100 border rounded-4 shadow-sm overflow-hidden position-relative banner-admin-card <?= $slide['is_active'] ? 'border-teal' : 'opacity-75 bg-light' ?>">
                                        <!-- Top Badge Strip -->
                                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2.5 px-3 border-bottom">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-navy text-white rounded-pill px-2.5 py-1 small">Order #<?= $slide['display_order'] ?></span>
                                                <?php if ($slide['is_active']): ?>
                                                    <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1">
                                                        <i class="bi bi-check-circle-fill me-1"></i> Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary bg-opacity-15 text-secondary border border-secondary border-opacity-25 rounded-pill px-2.5 py-1">
                                                        <i class="bi bi-eye-slash-fill me-1"></i> Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Toggle Switch Form -->
                                            <form method="POST" action="api/manage-banners.php" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $slide['id'] ?>">
                                                <input type="hidden" name="redirect" value="../admin-banners.php?tab=hero">
                                                <div class="form-check form-switch m-0" title="Toggle active status">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="toggleHero<?= $slide['id'] ?>" <?= $slide['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                                    <label class="form-check-label small fw-semibold" for="toggleHero<?= $slide['id'] ?>"><?= $slide['is_active'] ? 'Live' : 'Hidden' ?></label>
                                                </div>
                                            </form>
                                        </div>

                                        <!-- Slide Content Preview Box -->
                                        <div class="card-body p-3 p-md-4">
                                            <div class="row g-3 align-items-center">
                                                <!-- Image Thumbnail -->
                                                <div class="col-sm-5 text-center">
                                                    <div class="p-2 bg-light rounded-3 shadow-sm border position-relative overflow-hidden" style="max-height: 180px; height: 160px; display: flex; align-items: center; justify-content: center;">
                                                        <img src="<?= htmlspecialchars($slide['image_url']) ?>" alt="<?= htmlspecialchars($slide['title']) ?>" class="img-fluid rounded" style="max-height: 100%; object-fit: cover; width: 100%;" onerror="this.onerror=null;this.src='images/hero-cozy-books.jpg';">
                                                    </div>
                                                    <small class="text-muted d-block mt-1 text-truncate" style="font-size: 0.72rem;">
                                                        <?= htmlspecialchars($slide['image_url']) ?>
                                                    </small>
                                                </div>

                                                <!-- Text Content -->
                                                <div class="col-sm-7">
                                                    <?php if (!empty($slide['tag'])): ?>
                                                        <span class="hero-tag-pill mb-1" style="font-size: 0.75rem; padding: 0.2rem 0.6rem;">
                                                            <?= htmlspecialchars($slide['tag']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <h5 class="fw-bold text-navy font-serif-title mb-1"><?= htmlspecialchars($slide['title']) ?></h5>
                                                    <p class="text-muted small mb-2 line-clamp-2" style="font-size: 0.84rem;"><?= htmlspecialchars($slide['description'] ?: 'No description.') ?></p>
                                                    
                                                    <div class="d-flex align-items-center gap-2 mt-2">
                                                        <span class="badge bg-teal-light text-teal border border-teal-subtle py-1 px-2 text-truncate" style="max-width: 160px;">
                                                            <i class="bi bi-box-arrow-up-right me-1"></i> <?= htmlspecialchars($slide['btn_text'] ?: 'Browse') ?>
                                                        </span>
                                                        <span class="text-muted small text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($slide['btn_link']) ?>">
                                                            <code><?= htmlspecialchars($slide['btn_link']) ?></code>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Card Action Footer -->
                                        <div class="card-footer bg-light border-top d-flex justify-content-between align-items-center py-2 px-3">
                                            <small class="text-muted">
                                                <i class="bi bi-person me-1"></i> <?= htmlspecialchars($slide['creator_name'] ?? 'Admin') ?>
                                            </small>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 py-1 rounded-pill" onclick='openEditModal(<?= json_encode($slide) ?>)'>
                                                    <i class="bi bi-pencil-square me-1"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3 py-1 rounded-pill" onclick="openDeleteModal(<?= $slide['id'] ?>, '<?= htmlspecialchars(addslashes($slide['title'])) ?>')">
                                                    <i class="bi bi-trash3 me-1"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 2: PROMO RAIL CARDS -->
                <div class="tab-pane fade <?= $activeTab === 'promo' ? 'show active' : '' ?>" id="promo-pane" role="tabpanel" aria-labelledby="promo-tab">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
                        <div>
                            <h5 class="fw-bold text-navy mb-1"><i class="bi bi-grid-fill text-teal me-2"></i> Right Promo Rail Cards</h5>
                            <p class="text-muted small mb-0">These colorful action cards sit beside the hero carousel on desktop to direct readers to key categories and features.</p>
                        </div>
                        <button type="button" class="btn btn-booksy-primary btn-sm px-3 py-2 rounded-pill fw-bold" onclick="openAddModalWithPosition('promo_card')">
                            <i class="bi bi-plus-lg me-1"></i> Add Promo Card
                        </button>
                    </div>

                    <?php if (empty($promoCards)): ?>
                        <div class="text-center py-5 bg-light rounded-4 border">
                            <i class="bi bi-card-checklist text-muted" style="font-size: 3rem;"></i>
                            <h5 class="fw-bold text-navy mt-3">No Promo Rail Cards Found</h5>
                            <p class="text-muted mb-3">Add promo cards to direct users to bestsellers, textbooks, selling, or delivery guarantees.</p>
                            <button type="button" class="btn btn-warning fw-bold px-4 rounded-pill" onclick="openAddModalWithPosition('promo_card')">
                                <i class="bi bi-plus-circle me-1"></i> Add Promo Card
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($promoCards as $promo): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 border rounded-4 shadow-sm overflow-hidden <?= $promo['is_active'] ? '' : 'opacity-75 bg-light' ?>">
                                        <!-- Status Strip -->
                                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2 px-3 border-bottom">
                                            <span class="badge bg-navy text-white rounded-pill px-2 py-0.5 small">Order #<?= $promo['display_order'] ?></span>
                                            <form method="POST" action="api/manage-banners.php" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                                <input type="hidden" name="redirect" value="../admin-banners.php?tab=promo">
                                                <div class="form-check form-switch m-0">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="togglePromo<?= $promo['id'] ?>" <?= $promo['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                                    <label class="form-check-label small fw-semibold" for="togglePromo<?= $promo['id'] ?>"><?= $promo['is_active'] ? 'Active' : 'Off' ?></label>
                                                </div>
                                            </form>
                                        </div>

                                        <!-- Actual Visual Preview of Promo Card -->
                                        <div class="card-body p-3">
                                            <a href="javascript:void(0)" class="promo-rail-card <?= htmlspecialchars($promo['bg_color'] ?: 'promo-blue') ?> text-decoration-none d-block mb-3" style="cursor: default;">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="promo-rail-icon">
                                                        <i class="bi <?= htmlspecialchars($promo['icon'] ?: 'bi-fire') ?>"></i>
                                                    </div>
                                                    <div>
                                                        <div class="promo-rail-title"><?= htmlspecialchars($promo['title']) ?></div>
                                                        <p class="promo-rail-desc"><?= htmlspecialchars($promo['description'] ?: 'Special spotlight') ?></p>
                                                    </div>
                                                </div>
                                                <i class="bi bi-chevron-right promo-rail-arrow"></i>
                                            </a>

                                            <div class="small text-muted d-flex align-items-center justify-content-between">
                                                <span><i class="bi bi-link-45deg me-1"></i> Link: <code><?= htmlspecialchars($promo['btn_link'] ?: 'index.php') ?></code></span>
                                                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($promo['bg_color'] ?: 'promo-blue') ?></span>
                                            </div>
                                        </div>

                                        <div class="card-footer bg-light border-top d-flex justify-content-end gap-2 py-2 px-3">
                                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 py-1 rounded-pill" onclick='openEditModal(<?= json_encode($promo) ?>)'>
                                                <i class="bi bi-pencil-square me-1"></i> Edit
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3 py-1 rounded-pill" onclick="openDeleteModal(<?= $promo['id'] ?>, '<?= htmlspecialchars(addslashes($promo['title'])) ?>')">
                                                <i class="bi bi-trash3 me-1"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 3: TOP ANNOUNCEMENTS -->
                <div class="tab-pane fade <?= $activeTab === 'announcement' ? 'show active' : '' ?>" id="announcement-pane" role="tabpanel" aria-labelledby="announcement-tab">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
                        <div>
                            <h5 class="fw-bold text-navy mb-1"><i class="bi bi-megaphone-fill text-teal me-2"></i> Global Top Announcement Banners</h5>
                            <p class="text-muted small mb-0">Display store-wide alerts, delivery notices, or seasonal book sales at the very top of the website.</p>
                        </div>
                        <button type="button" class="btn btn-booksy-primary btn-sm px-3 py-2 rounded-pill fw-bold" onclick="openAddModalWithPosition('top_announcement')">
                            <i class="bi bi-plus-lg me-1"></i> Add Announcement
                        </button>
                    </div>

                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-5 bg-light rounded-4 border">
                            <i class="bi bi-bell-slash text-muted" style="font-size: 3rem;"></i>
                            <h5 class="fw-bold text-navy mt-3">No Active Announcements</h5>
                            <p class="text-muted mb-3">You can create global promotional alerts to notify buyers and sellers about promotions or marketplace updates.</p>
                            <button type="button" class="btn btn-warning fw-bold px-4 rounded-pill" onclick="openAddModalWithPosition('top_announcement')">
                                <i class="bi bi-megaphone me-1"></i> Create Top Announcement
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($announcements as $ann): ?>
                                <div class="col-12">
                                    <div class="card border rounded-4 shadow-sm p-3 bg-white <?= $ann['is_active'] ? 'border-teal' : 'opacity-75' ?>">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-circle p-2.5 bg-teal text-white">
                                                    <i class="bi <?= htmlspecialchars($ann['icon'] ?: 'bi-megaphone-fill') ?> fs-5"></i>
                                                </div>
                                                <div>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <h6 class="fw-bold text-navy mb-0"><?= htmlspecialchars($ann['title']) ?></h6>
                                                        <?php if ($ann['is_active']): ?>
                                                            <span class="badge bg-success rounded-pill px-2 py-0.5 small">Live Top Banner</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary rounded-pill px-2 py-0.5 small">Inactive</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-muted small mb-0"><?= htmlspecialchars($ann['description'] ?: '') ?></p>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center gap-3">
                                                <form method="POST" action="api/manage-banners.php" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                                                    <input type="hidden" name="redirect" value="../admin-banners.php?tab=announcement">
                                                    <div class="form-check form-switch m-0">
                                                        <input class="form-check-input" type="checkbox" role="switch" id="toggleAnn<?= $ann['id'] ?>" <?= $ann['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                                        <label class="form-check-label small fw-semibold" for="toggleAnn<?= $ann['id'] ?>"><?= $ann['is_active'] ? 'Published' : 'Hidden' ?></label>
                                                    </div>
                                                </form>
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 py-1 rounded-pill" onclick='openEditModal(<?= json_encode($ann) ?>)'>
                                                    <i class="bi bi-pencil-square me-1"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3 py-1 rounded-pill" onclick="openDeleteModal(<?= $ann['id'] ?>, '<?= htmlspecialchars(addslashes($ann['title'])) ?>')">
                                                    <i class="bi bi-trash3 me-1"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 4: LIVE CAROUSEL PREVIEW -->
                <div class="tab-pane fade <?= $activeTab === 'preview' ? 'show active' : '' ?>" id="preview-pane" role="tabpanel" aria-labelledby="preview-tab">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold text-navy mb-1"><i class="bi bi-eye-fill text-teal me-2"></i> Live Frontend Carousel Visualizer</h5>
                            <p class="text-muted small mb-0">See how active hero slides render for buyers and visitors on the homepage in real-time.</p>
                        </div>
                        <a href="index.php" target="_blank" class="btn btn-sm btn-booksy-navy rounded-pill px-3 fw-bold">
                            <i class="bi bi-box-arrow-up-right me-1"></i> View Live Homepage
                        </a>
                    </div>

                    <?php 
                    $activeSlidesForPreview = array_values(array_filter($heroSlides, fn($s) => $s['is_active'] == 1));
                    ?>

                    <?php if (empty($activeSlidesForPreview)): ?>
                        <div class="alert alert-warning rounded-4 p-4 text-center">
                            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning mb-2 d-block"></i>
                            <h6 class="fw-bold">No Active Hero Slides to Preview</h6>
                            <p class="small mb-0">Please activate at least one hero slide in the Hero Carousel Slides tab to see the live preview.</p>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-light rounded-4 border">
                            <div class="hero-premiere-card position-relative" id="adminHeroPreviewWrapper" style="min-height: 380px;">
                                <!-- Arrows -->
                                <button type="button" class="hero-nav-arrow prev" onclick="adminChangeHeroSlide(-1)" aria-label="Previous Slide">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <button type="button" class="hero-nav-arrow next" onclick="adminChangeHeroSlide(1)" aria-label="Next Slide">
                                    <i class="bi bi-chevron-right"></i>
                                </button>

                                <!-- Slide Container -->
                                <div class="row align-items-center g-4">
                                    <div class="col-md-6" id="adminHeroTextContainer">
                                        <div class="hero-tag-pill" id="adminHeroTag">
                                            <?= htmlspecialchars($activeSlidesForPreview[0]['tag'] ?: '✨ Featured') ?>
                                        </div>
                                        <h2 class="hero-premiere-title font-serif-title" id="adminHeroTitle">
                                            <?= htmlspecialchars($activeSlidesForPreview[0]['title']) ?>
                                        </h2>
                                        <p class="hero-premiere-desc" id="adminHeroDesc">
                                            <?= htmlspecialchars($activeSlidesForPreview[0]['description']) ?>
                                        </p>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <a href="javascript:void(0)" class="btn-hero-browse" id="adminHeroMainBtn">
                                                <span><?= htmlspecialchars($activeSlidesForPreview[0]['btn_text'] ?: 'Browse Now') ?></span>
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                            <a href="javascript:void(0)" class="btn-hero-viewall">
                                                <i class="bi bi-grid-fill small"></i>
                                                <span>View All</span>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="col-md-6 text-center">
                                        <div class="hero-img-wrap">
                                            <img src="<?= htmlspecialchars($activeSlidesForPreview[0]['image_url']) ?>" alt="Hero Preview" id="adminHeroImage" class="img-fluid" onerror="this.onerror=null;this.src='images/hero-cozy-books.jpg';">
                                        </div>
                                    </div>
                                </div>

                                <!-- Dots -->
                                <div class="hero-carousel-dots d-flex justify-content-center align-items-center gap-1.5 mt-3 pt-2" id="adminHeroDots">
                                    <?php foreach ($activeSlidesForPreview as $idx => $slide): ?>
                                        <button type="button" class="hero-dot <?= $idx === 0 ? 'active' : '' ?>" onclick="adminGoToHeroSlide(<?= $idx ?>)"></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: ADD NEW BANNER                                                        -->
<!-- ============================================================================ -->
<div class="modal fade" id="addBannerModal" tabindex="-1" aria-labelledby="addBannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 py-3" style="border-bottom: 2px solid var(--teal-primary);">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="addBannerModalLabel">
                    <i class="bi bi-plus-circle-fill text-teal"></i>
                    <span>Create New Banner / Showcase Element</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="api/manage-banners.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="redirect" value="../admin-banners.php">

                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        <!-- Banner Position Type -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy">Position / Type <span class="text-danger">*</span></label>
                            <select name="position" id="addPositionSelect" class="form-select" onchange="togglePositionFields('add')" required>
                                <option value="hero_slide">Hero Showcase Slide (Carousel)</option>
                                <option value="promo_card">Right Rail Promo Card (Sidebar)</option>
                                <option value="top_announcement">Top Store Announcement</option>
                            </select>
                            <small class="text-muted">Where this banner is displayed on the platform.</small>
                        </div>

                        <!-- Display Order -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-navy">Display Order</label>
                            <input type="number" name="display_order" class="form-control" value="1" min="1" max="99">
                            <small class="text-muted">Sequence ranking</small>
                        </div>

                        <!-- Active State -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-navy">Publish Status</label>
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="addIsActive" value="1" checked>
                                <label class="form-check-label small fw-semibold" for="addIsActive">Active & Visible</label>
                            </div>
                        </div>

                        <!-- Headline / Title -->
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-navy">Title / Headline <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Campus Essentials or New Arrivals" required>
                        </div>

                        <!-- Tag / Pill Subtitle -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-navy">Tag / Badge Pill</label>
                            <input type="text" name="tag" class="form-control" placeholder="e.g. ✨ Book Premiere or 🎓 Academic">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label fw-bold small text-navy">Description / Teaser Text</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief compelling summary for readers and buyers..."></textarea>
                        </div>

                        <!-- Button Text & Link -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy">Button Label</label>
                            <input type="text" name="btn_text" class="form-control" value="Browse Now" placeholder="e.g. Browse Now or Explore Textbooks">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy">Button Destination Link</label>
                            <input type="text" name="btn_link" class="form-control" value="#bookCatalogGrid" placeholder="e.g. index.php?category=educational-academic">
                        </div>

                        <!-- Dynamic Section: Image Selection (For Hero Slides) -->
                        <div class="col-12" id="addImageSection">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label fw-bold small text-navy d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-image text-teal me-1"></i> Hero Slide Image (Upload or Choose Preset)</span>
                                </label>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Upload Custom Image (JPG, PNG, WebP max 8MB)</label>
                                        <input type="file" name="image" id="addHeroFileInput" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml" onchange="previewUploadImage(this, 'addHeroPreviewImg')">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Or Select Preset Stock Image</label>
                                        <select name="preset_image" id="addPresetSelect" class="form-select" onchange="selectPresetImage(this.value, 'addHeroPreviewImg')">
                                            <option value="">-- Choose Stock Photo --</option>
                                            <?php foreach ($presetImages as $path => $label): ?>
                                                <option value="<?= $path ?>"><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 text-center mt-2">
                                        <div class="p-2 bg-white rounded border d-inline-block shadow-sm" style="max-height: 140px;">
                                            <img src="images/hero-cozy-books.jpg" id="addHeroPreviewImg" alt="Preview" style="max-height: 120px; object-fit: cover;" class="rounded">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Section: Promo Card Styling (For Promo Cards) -->
                        <div class="col-12 d-none" id="addPromoCardSection">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label fw-bold small text-navy mb-2"><i class="bi bi-palette text-teal me-1"></i> Promo Card Color & Icon</label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Card Theme Color</label>
                                        <select name="bg_color" class="form-select">
                                            <option value="promo-blue">Navy Blue (promo-blue)</option>
                                            <option value="promo-teal">Teal Cyan (promo-teal)</option>
                                            <option value="promo-gold">Warm Gold (promo-gold)</option>
                                            <option value="promo-green">Emerald Green (promo-green)</option>
                                            <option value="promo-purple">Royal Purple (promo-purple)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Bootstrap Icon Class</label>
                                        <select name="icon" class="form-select">
                                            <option value="bi-fire">🔥 Flame (bi-fire)</option>
                                            <option value="bi-mortarboard-fill">🎓 Graduation (bi-mortarboard-fill)</option>
                                            <option value="bi-tag-fill">🏷️ Price Tag (bi-tag-fill)</option>
                                            <option value="bi-truck">🚚 Delivery Truck (bi-truck)</option>
                                            <option value="bi-shield-check">🛡️ Shield / Verified (bi-shield-check)</option>
                                            <option value="bi-gem">💎 Gem / VIP (bi-gem)</option>
                                            <option value="bi-stars">✨ Sparkles (bi-stars)</option>
                                            <option value="bi-book-half">📖 Book (bi-book-half)</option>
                                            <option value="bi-heart-fill">❤️ Heart / Wishlist (bi-heart-fill)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-booksy-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-check-circle me-1"></i> Create Banner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: EDIT BANNER                                                           -->
<!-- ============================================================================ -->
<div class="modal fade" id="editBannerModal" tabindex="-1" aria-labelledby="editBannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 py-3" style="border-bottom: 2px solid var(--teal-primary);">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="editBannerModalLabel">
                    <i class="bi bi-pencil-square text-teal"></i>
                    <span>Edit Banner / Showcase Element</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="api/manage-banners.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editBannerId" value="">
                <input type="hidden" name="redirect" value="../admin-banners.php">

                <div class="modal-body p-4 bg-white">
                    <div class="row g-3">
                        <!-- Banner Position Type -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy">Position / Type <span class="text-danger">*</span></label>
                            <select name="position" id="editPositionSelect" class="form-select" onchange="togglePositionFields('edit')" required>
                                <option value="hero_slide">Hero Showcase Slide (Carousel)</option>
                                <option value="promo_card">Right Rail Promo Card (Sidebar)</option>
                                <option value="top_announcement">Top Store Announcement</option>
                            </select>
                        </div>

                        <!-- Display Order -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-navy">Display Order</label>
                            <input type="number" name="display_order" id="editDisplayOrder" class="form-control" min="1" max="99">
                        </div>

                        <!-- Active State -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-navy">Publish Status</label>
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" value="1">
                                <label class="form-check-label small fw-semibold" for="editIsActive">Active</label>
                            </div>
                        </div>

                        <!-- Headline / Title -->
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-navy">Title / Headline <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="editTitle" class="form-control" required>
                        </div>

                        <!-- Tag / Pill Subtitle -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-navy">Tag / Badge Pill</label>
                            <input type="text" name="tag" id="editTag" class="form-control">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label fw-bold small text-navy">Description / Teaser Text</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="2"></textarea>
                        </div>

                        <!-- Button Text & Link -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy">Button Label</label>
                            <input type="text" name="btn_text" id="editBtnText" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy">Button Destination Link</label>
                            <input type="text" name="btn_link" id="editBtnLink" class="form-control">
                        </div>

                        <!-- Dynamic Section: Image Selection (For Hero Slides) -->
                        <div class="col-12" id="editImageSection">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label fw-bold small text-navy d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-image text-teal me-1"></i> Slide Background Image</span>
                                </label>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Upload New Image File (Optional)</label>
                                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml" onchange="previewUploadImage(this, 'editHeroPreviewImg')">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Or Pick Stock Preset</label>
                                        <select name="preset_image" id="editPresetSelect" class="form-select" onchange="selectPresetImage(this.value, 'editHeroPreviewImg')">
                                            <option value="">-- Keep Current / Custom --</option>
                                            <?php foreach ($presetImages as $path => $label): ?>
                                                <option value="<?= $path ?>"><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 text-center mt-2">
                                        <div class="p-2 bg-white rounded border d-inline-block shadow-sm" style="max-height: 140px;">
                                            <img src="images/hero-cozy-books.jpg" id="editHeroPreviewImg" alt="Preview" style="max-height: 120px; object-fit: cover;" class="rounded">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Section: Promo Card Styling (For Promo Cards) -->
                        <div class="col-12 d-none" id="editPromoCardSection">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label fw-bold small text-navy mb-2"><i class="bi bi-palette text-teal me-1"></i> Promo Card Color & Icon</label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Card Theme Color</label>
                                        <select name="bg_color" id="editBgColor" class="form-select">
                                            <option value="promo-blue">Navy Blue (promo-blue)</option>
                                            <option value="promo-teal">Teal Cyan (promo-teal)</option>
                                            <option value="promo-gold">Warm Gold (promo-gold)</option>
                                            <option value="promo-green">Emerald Green (promo-green)</option>
                                            <option value="promo-purple">Royal Purple (promo-purple)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Bootstrap Icon Class</label>
                                        <select name="icon" id="editIcon" class="form-select">
                                            <option value="bi-fire">🔥 Flame (bi-fire)</option>
                                            <option value="bi-mortarboard-fill">🎓 Graduation (bi-mortarboard-fill)</option>
                                            <option value="bi-tag-fill">🏷️ Price Tag (bi-tag-fill)</option>
                                            <option value="bi-truck">🚚 Delivery Truck (bi-truck)</option>
                                            <option value="bi-shield-check">🛡️ Shield / Verified (bi-shield-check)</option>
                                            <option value="bi-gem">💎 Gem / VIP (bi-gem)</option>
                                            <option value="bi-stars">✨ Sparkles (bi-stars)</option>
                                            <option value="bi-book-half">📖 Book (bi-book-half)</option>
                                            <option value="bi-heart-fill">❤️ Heart / Wishlist (bi-heart-fill)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-booksy-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-check-circle me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: DELETE CONFIRMATION                                                   -->
<!-- ============================================================================ -->
<div class="modal fade" id="deleteBannerModal" tabindex="-1" aria-labelledby="deleteBannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white border-0 py-3">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="deleteBannerModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Confirm Banner Deletion</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="api/manage-banners.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteBannerId" value="">
                <input type="hidden" name="redirect" value="../admin-banners.php">

                <div class="modal-body p-4 bg-white text-center">
                    <i class="bi bi-trash3 text-danger mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-navy mb-2">Permanently Delete Banner?</h5>
                    <p class="text-muted mb-0">Are you sure you want to delete <strong id="deleteBannerTitleName" class="text-dark"></strong>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                        <i class="bi bi-trash3 me-1"></i> Confirm Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================================ -->
<!-- MODAL: RESET DEFAULTS CONFIRMATION                                           -->
<!-- ============================================================================ -->
<div class="modal fade" id="resetDefaultsModal" tabindex="-1" aria-labelledby="resetDefaultsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning text-dark border-0 py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="resetDefaultsModalLabel">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reset Banners to Defaults</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="api/manage-banners.php">
                <input type="hidden" name="action" value="reset_defaults">
                <input type="hidden" name="redirect" value="../admin-banners.php">

                <div class="modal-body p-4 bg-white text-center">
                    <i class="bi bi-arrow-counterclockwise text-warning mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-navy mb-2">Restore Factory Default Banners?</h5>
                    <p class="text-muted mb-0">This will reset the 4 standard hero showcase slides and 5 right rail promo cards to original factory presets.</p>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Banners
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Admin Scripts for Live Previews & Modal Control -->
<script>
const activePreviewSlides = <?= json_encode($activeSlidesForPreview ?? []) ?>;
let adminSlideIndex = 0;

function adminGoToHeroSlide(index) {
    if (!activePreviewSlides.length) return;
    adminSlideIndex = (index + activePreviewSlides.length) % activePreviewSlides.length;
    adminRenderHeroSlide(adminSlideIndex);
}

function adminChangeHeroSlide(direction) {
    if (!activePreviewSlides.length) return;
    adminSlideIndex = (adminSlideIndex + direction + activePreviewSlides.length) % activePreviewSlides.length;
    adminRenderHeroSlide(adminSlideIndex);
}

function adminRenderHeroSlide(index) {
    if (!activePreviewSlides.length) return;
    const slide = activePreviewSlides[index];

    const tagEl = document.getElementById('adminHeroTag');
    const titleEl = document.getElementById('adminHeroTitle');
    const descEl = document.getElementById('adminHeroDesc');
    const btnEl = document.getElementById('adminHeroMainBtn');
    const imgEl = document.getElementById('adminHeroImage');

    if (tagEl) tagEl.textContent = slide.tag || '✨ Featured';
    if (titleEl) titleEl.textContent = slide.title;
    if (descEl) descEl.textContent = slide.description || '';
    if (btnEl) {
        btnEl.href = slide.btn_link || '#';
        const span = btnEl.querySelector('span');
        if (span) span.textContent = slide.btn_text || 'Browse Now';
    }
    if (imgEl) {
        imgEl.src = slide.image_url || 'images/hero-cozy-books.jpg';
    }

    const dots = document.querySelectorAll('#adminHeroDots .hero-dot');
    dots.forEach((dot, dotIdx) => {
        if (dotIdx === index) {
            dot.classList.add('active');
        } else {
            dot.classList.remove('active');
        }
    });
}

function togglePositionFields(prefix) {
    const select = document.getElementById(prefix + 'PositionSelect');
    const imgSection = document.getElementById(prefix + 'ImageSection');
    const promoSection = document.getElementById(prefix + 'PromoCardSection');

    if (!select) return;
    const val = select.value;

    if (val === 'hero_slide') {
        if (imgSection) imgSection.classList.remove('d-none');
        if (promoSection) promoSection.classList.add('d-none');
    } else if (val === 'promo_card') {
        if (imgSection) imgSection.classList.add('d-none');
        if (promoSection) promoSection.classList.remove('d-none');
    } else {
        if (imgSection) imgSection.classList.add('d-none');
        if (promoSection) promoSection.classList.add('d-none');
    }
}

function openAddModalWithPosition(pos) {
    const select = document.getElementById('addPositionSelect');
    if (select) {
        select.value = pos;
        togglePositionFields('add');
    }
    const modal = new bootstrap.Modal(document.getElementById('addBannerModal'));
    modal.show();
}

function openEditModal(banner) {
    document.getElementById('editBannerId').value = banner.id;
    document.getElementById('editPositionSelect').value = banner.position;
    document.getElementById('editTitle').value = banner.title;
    document.getElementById('editTag').value = banner.tag || '';
    document.getElementById('editDescription').value = banner.description || '';
    document.getElementById('editBtnText').value = banner.btn_text || 'Browse Now';
    document.getElementById('editBtnLink').value = banner.btn_link || '#bookCatalogGrid';
    document.getElementById('editDisplayOrder').value = banner.display_order;
    document.getElementById('editIsActive').checked = (banner.is_active == 1);

    if (document.getElementById('editBgColor')) {
        document.getElementById('editBgColor').value = banner.bg_color || 'promo-blue';
    }
    if (document.getElementById('editIcon')) {
        document.getElementById('editIcon').value = banner.icon || 'bi-fire';
    }

    const previewImg = document.getElementById('editHeroPreviewImg');
    if (previewImg && banner.image_url) {
        previewImg.src = banner.image_url;
    }

    togglePositionFields('edit');

    const modal = new bootstrap.Modal(document.getElementById('editBannerModal'));
    modal.show();
}

function openDeleteModal(id, title) {
    document.getElementById('deleteBannerId').value = id;
    document.getElementById('deleteBannerTitleName').textContent = '"' + title + '"';
    const modal = new bootstrap.Modal(document.getElementById('deleteBannerModal'));
    modal.show();
}

function previewUploadImage(input, previewImgId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewImgId);
            if (preview) {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function selectPresetImage(path, previewImgId) {
    if (path) {
        const preview = document.getElementById(previewImgId);
        if (preview) {
            preview.src = path;
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>
