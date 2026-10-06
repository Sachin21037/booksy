<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Finding New Homes For Books';

// Fetch Categories with live available book counts (only books with photos)
$cat_stmt = $pdo->query("
    SELECT c.*, COUNT(b.id) AS book_count 
    FROM categories c 
    LEFT JOIN books b ON c.id = b.category_id AND b.status = 'available' AND b.image_url IS NOT NULL AND b.image_url != '' AND b.image_url != 'default_book.svg'
    GROUP BY c.id 
    ORDER BY c.name ASC
");
$categories = $cat_stmt->fetchAll();

// Dynamic Search & Filtering parameters
$search = trim($_GET['search'] ?? '');
$cat_filter = trim($_GET['category'] ?? '');
$condition_filter = trim($_GET['condition'] ?? '');
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$sort = trim($_GET['sort'] ?? 'latest');

// Build SQL Query with Promotional Boosts and VIP Membership data (only books with photos)
$sql = "SELECT b.*, c.name AS category_name, c.slug AS category_slug, 
               u.name AS seller_name, u.phone AS seller_phone, u.email AS seller_email,
               MAX(pl.promotion_type) AS active_boost_type,
               MAX(CASE WHEN mem.membership_id IS NOT NULL THEN 1 ELSE 0 END) AS seller_is_vip,
               MAX(CASE WHEN pl.promotion_id IS NOT NULL THEN 1 ELSE 0 END) AS is_promoted
        FROM books b 
        JOIN categories c ON b.category_id = c.id 
        JOIN users u ON b.seller_id = u.id 
        LEFT JOIN promotional_listings pl ON b.id = pl.book_id AND pl.status = 'active' AND pl.end_date >= NOW()
        LEFT JOIN memberships mem ON u.id = mem.user_id AND mem.status = 'active' AND mem.end_date >= CURDATE()
        WHERE b.status = 'available'
          AND b.image_url IS NOT NULL 
          AND b.image_url != '' 
          AND b.image_url != 'default_book.svg'";

$params = [];

if (!empty($search)) {
    $sql .= " AND (b.title LIKE :search1 OR b.author LIKE :search2 OR b.description LIKE :search3)";
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}

if (!empty($cat_filter)) {
    $sql .= " AND c.slug = :category";
    $params['category'] = $cat_filter;
}

if (!empty($condition_filter)) {
    $sql .= " AND b.book_condition = :condition";
    $params['condition'] = $condition_filter;
}

if ($min_price !== null && $min_price >= 0) {
    $sql .= " AND b.price >= :min_price";
    $params['min_price'] = $min_price;
}

if ($max_price !== null && $max_price > 0) {
    $sql .= " AND b.price <= :max_price";
    $params['max_price'] = $max_price;
}

$order_by = "b.created_at DESC";
switch ($sort) {
    case 'price_asc':
        $order_by = "b.price ASC";
        break;
    case 'price_desc':
        $order_by = "b.price DESC";
        break;
    case 'title_asc':
        $order_by = "b.title ASC";
        break;
    case 'latest':
    default:
        $order_by = "b.created_at DESC";
        break;
}

$sql .= " GROUP BY b.id, b.seller_id, b.category_id, b.title, b.author, b.price, b.book_condition, b.image_url, b.description, b.status, b.created_at, c.name, c.slug, u.name, u.phone, u.email ORDER BY is_promoted DESC, " . $order_by;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Filter out any books whose image file is not physically on disk
$books = array_values(array_filter($books, function($b) {
    return !empty($b['image_url']) && file_exists(__DIR__ . '/uploads/' . $b['image_url']);
}));

// Total count of available books with photos overall
$total_available = count($books);
if (empty($search) && empty($cat_filter) && empty($condition_filter) && $min_price === null && $max_price === null) {
    $total_available = $pdo->query("SELECT COUNT(*) FROM books WHERE status = 'available' AND image_url IS NOT NULL AND image_url != '' AND image_url != 'default_book.svg'")->fetchColumn();
}

// Dynamic Hero Slides from database with fallback
$db_hero_banners = function_exists('get_active_banners') ? get_active_banners($pdo, 'hero_slide') : [];
$hero_slides = [];

if (!empty($db_hero_banners)) {
    foreach ($db_hero_banners as $b) {
        $hero_slides[] = [
            'tag'      => $b['tag'] ?: '✨ Book Premiere',
            'title'    => $b['title'],
            'desc'     => $b['description'] ?: '',
            'btn_text' => $b['btn_text'] ?: 'Browse Now',
            'btn_link' => $b['btn_link'] ?: '#bookCatalogGrid',
            'img'      => $b['image_url'] ?: 'images/hero-cozy-books.jpg'
        ];
    }
}

// Fallback to defaults if no active hero slides in database
if (empty($hero_slides)) {
    $hero_slides = [
        [
            'tag' => '✨ Book Premiere',
            'title' => 'New Arrivals',
            'desc' => 'Discover the latest additions to our collection - curated for readers who love cozy reads and great finds.',
            'btn_text' => 'Browse Now',
            'btn_link' => '#bookCatalogGrid',
            'img' => 'images/hero-cozy-books.jpg'
        ],
        [
            'tag' => '🎓 Campus Essentials',
            'title' => 'Academic Picks',
            'desc' => 'Save up to 70% on pre-loved engineering, medicine, A/L, and university revision textbooks.',
            'btn_text' => 'Explore Textbooks',
            'btn_link' => 'index.php?category=educational-academic',
            'img' => 'images/hero-academic-study.jpg'
        ],
        [
            'tag' => '💎 Rare & Vintage',
            'title' => 'Collector Editions',
            'desc' => 'Timeless Sinhala literature classics, vintage magazines, and rare out-of-print collectibles.',
            'btn_text' => 'View Collectibles',
            'btn_link' => 'index.php?category=biography-history-poetry',
            'img' => 'images/hero-rare-vintage.jpg'
        ],
        [
            'tag' => '🏡 Community Nook',
            'title' => 'Reader Favorites',
            'desc' => 'Connect directly with fellow book lovers across Sri Lanka to buy and resell pre-owned novels and stories.',
            'btn_text' => 'Explore Bestsellers',
            'btn_link' => 'index.php?sort=latest',
            'img' => 'images/library-wide-banner.jpg'
        ]
    ];
}

// Dynamic Promo Rail Cards from database with fallback
$db_promo_cards = function_exists('get_active_banners') ? get_active_banners($pdo, 'promo_card') : [];
$promo_rail_cards = [];
if (!empty($db_promo_cards)) {
    foreach ($db_promo_cards as $p) {
        $promo_rail_cards[] = [
            'title' => $p['title'],
            'desc'  => $p['description'] ?: '',
            'icon'  => $p['icon'] ?: 'bi-fire',
            'link'  => $p['btn_link'] ?: 'index.php',
            'class' => $p['bg_color'] ?: 'promo-blue'
        ];
    }
}
if (empty($promo_rail_cards)) {
    $promo_rail_cards = [
        ['title' => 'Best Sellers & Trending', 'desc' => 'Top rated novels & fiction reads', 'icon' => 'bi-fire', 'link' => 'index.php?sort=latest', 'class' => 'promo-blue'],
        ['title' => 'Academic & University', 'desc' => 'Save up to 70% on textbooks', 'icon' => 'bi-mortarboard-fill', 'link' => 'index.php?category=educational-academic', 'class' => 'promo-teal'],
        ['title' => 'Sell Your Used Books', 'desc' => 'Turn old books into instant cash', 'icon' => 'bi-tag-fill', 'link' => 'sell.php', 'class' => 'promo-gold'],
        ['title' => 'Islandwide Cash on Delivery', 'desc' => 'Fast courier directly to doorstep', 'icon' => 'bi-truck', 'link' => 'cart.php', 'class' => 'promo-green'],
        ['title' => 'Brand New & Like New', 'desc' => 'Pristine condition guaranteed', 'icon' => 'bi-shield-check', 'link' => 'index.php?condition=Brand+New', 'class' => 'promo-purple']
    ];
}

// Helper to remove a single param from query string
function removeQueryParam($param) {
    $p = $_GET;
    unset($p[$param]);
    return 'index.php' . (!empty($p) ? '?' . http_build_query($p) : '');
}

// Helper to remove price params
function removePriceParams() {
    $p = $_GET;
    unset($p['min_price'], $p['max_price']);
    return 'index.php' . (!empty($p) ? '?' . http_build_query($p) : '');
}

// Active filters count
$has_active_filters = !empty($search) || !empty($cat_filter) || !empty($condition_filter) || $min_price !== null || $max_price !== null;

include 'includes/header.php';
?>

<div class="container py-2">

    <!-- Top Showcase Grid: Hero Banner + Right Promo Rail -->
    <div class="row g-3 g-lg-4 mb-4 align-items-stretch">
        
        <!-- Left: Book Premiere Hero Showcase Carousel with Autoplay -->
        <div class="col-lg-8 d-flex flex-column">
            <div class="hero-premiere-card position-relative" id="heroCarouselWrapper" onmouseenter="pauseHeroAutoplay()" onmouseleave="resumeHeroAutoplay()">
                
                <!-- Slide Navigation Arrows -->
                <button type="button" class="hero-nav-arrow prev" onclick="changeHeroSlide(-1); resetHeroTimer();" aria-label="Previous Slide">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <button type="button" class="hero-nav-arrow next" onclick="changeHeroSlide(1); resetHeroTimer();" aria-label="Next Slide">
                    <i class="bi bi-chevron-right"></i>
                </button>

                <!-- Dynamic Slide Content -->
                <div class="row align-items-center g-4">
                    <!-- Text / CTA Column -->
                    <div class="col-md-6" id="heroTextContainer">
                        <div class="hero-tag-pill" id="heroTag">
                            <?= htmlspecialchars($hero_slides[0]['tag']) ?>
                        </div>
                        <h1 class="hero-premiere-title font-serif-title" id="heroTitle">
                            <?= htmlspecialchars($hero_slides[0]['title']) ?>
                        </h1>
                        <p class="hero-premiere-desc" id="heroDesc">
                            <?= htmlspecialchars($hero_slides[0]['desc']) ?>
                        </p>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="<?= htmlspecialchars($hero_slides[0]['btn_link']) ?>" class="btn-hero-browse" id="heroMainBtn">
                                <span><?= htmlspecialchars($hero_slides[0]['btn_text']) ?></span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                            <a href="#bookCatalogGrid" class="btn-hero-viewall">
                                <i class="bi bi-grid-fill small"></i>
                                <span>View All</span>
                            </a>
                        </div>
                    </div>

                    <!-- Image Showcase Column -->
                    <div class="col-md-6 text-center">
                        <div class="hero-img-wrap">
                            <img src="<?= htmlspecialchars($hero_slides[0]['img']) ?>" alt="Book Premiere" id="heroImage" class="img-fluid" onerror="this.onerror=null;this.src='images/hero-cozy-books.jpg';">
                        </div>
                    </div>
                </div>

                <!-- Slide Indicator Dots -->
                <div class="hero-carousel-dots d-flex justify-content-center align-items-center gap-1.5 mt-3 pt-2" id="heroDotsContainer">
                    <?php foreach ($hero_slides as $idx => $slide): ?>
                        <button type="button" class="hero-dot <?= $idx === 0 ? 'active' : '' ?>" onclick="goToHeroSlide(<?= $idx ?>); resetHeroTimer();" aria-label="Go to slide <?= $idx + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Promo Rail Cards Stack -->
        <div class="col-lg-4 d-flex flex-column">
            <div class="promo-rail-container">
                <?php foreach ($promo_rail_cards as $promo): ?>
                    <a href="<?= htmlspecialchars($promo['link']) ?>" class="promo-rail-card <?= htmlspecialchars($promo['class']) ?>">
                        <div class="d-flex align-items-center gap-3">
                            <div class="promo-rail-icon">
                                <i class="bi <?= htmlspecialchars($promo['icon']) ?>"></i>
                            </div>
                            <div>
                                <div class="promo-rail-title"><?= htmlspecialchars($promo['title']) ?></div>
                                <p class="promo-rail-desc"><?= htmlspecialchars($promo['desc']) ?></p>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right promo-rail-arrow"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Category Quick Navigation Pills Bar -->
    <div class="category-pills-wrap mb-4">
        <div class="category-pills-scroll">
            <a href="index.php" class="category-pill <?= empty($cat_filter) ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i>
                <span>All Books</span>
                <span class="badge bg-light text-dark rounded-pill"><?= $total_available ?></span>
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="index.php?category=<?= urlencode($cat['slug']) ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>" class="category-pill <?= $cat_filter === $cat['slug'] ? 'active' : '' ?>">
                    <span><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="badge bg-light text-dark rounded-pill"><?= $cat['book_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active Filter Chips Bar (If filters applied) -->
    <?php if ($has_active_filters): ?>
        <div class="filter-chips-container bg-white p-2.5 rounded-3 border shadow-sm mb-3">
            <span class="small fw-bold text-navy me-2"><i class="bi bi-funnel text-teal me-1"></i> Active Filters:</span>
            
            <?php if (!empty($search)): ?>
                <span class="filter-chip">
                    <span>Search: "<?= htmlspecialchars($search) ?>"</span>
                    <a href="<?= removeQueryParam('search') ?>" class="filter-chip-remove" title="Remove search filter">&times;</a>
                </span>
            <?php endif; ?>

            <?php if (!empty($cat_filter)): ?>
                <span class="filter-chip">
                    <span>Category: <?= htmlspecialchars(str_replace('-', ' ', $cat_filter)) ?></span>
                    <a href="<?= removeQueryParam('category') ?>" class="filter-chip-remove" title="Remove category filter">&times;</a>
                </span>
            <?php endif; ?>

            <?php if (!empty($condition_filter)): ?>
                <span class="filter-chip">
                    <span>Condition: <?= htmlspecialchars($condition_filter) ?></span>
                    <a href="<?= removeQueryParam('condition') ?>" class="filter-chip-remove" title="Remove condition filter">&times;</a>
                </span>
            <?php endif; ?>

            <?php if ($min_price !== null || $max_price !== null): ?>
                <span class="filter-chip">
                    <span>Price: Rs. <?= number_format($min_price ?? 0) ?> - <?= $max_price !== null ? 'Rs. '.number_format($max_price) : 'Max' ?></span>
                    <a href="<?= removePriceParams() ?>" class="filter-chip-remove" title="Remove price filter">&times;</a>
                </span>
            <?php endif; ?>

            <a href="index.php" class="btn btn-sm btn-link text-danger p-0 ms-auto text-decoration-none small fw-bold">
                <i class="bi bi-arrow-counterclockwise"></i> Reset All
            </a>
        </div>
    <?php endif; ?>

    <!-- Mobile Filter Toggle Bar (Visible on mobile/tablet) -->
    <div class="d-lg-none mb-3">
        <div class="card border-0 shadow-sm p-2 rounded-3 bg-white border">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <button class="btn btn-sm btn-booksy-outline flex-fill d-flex align-items-center justify-content-center gap-2 py-2" type="button" data-bs-toggle="collapse" data-bs-target="#filterSidebarCollapse" aria-expanded="false" aria-controls="filterSidebarCollapse">
                    <i class="bi bi-funnel-fill"></i>
                    <span>Filter & Refine</span>
                    <?php if ($has_active_filters): ?>
                        <span class="badge bg-teal rounded-pill">Active</span>
                    <?php endif; ?>
                </button>

                <div class="d-flex align-items-center gap-1">
                    <?php
                    $current_params = $_GET;
                    $buildSortUrl = function($sort_val) use ($current_params) {
                        $p = $current_params;
                        $p['sort'] = $sort_val;
                        return 'index.php?' . http_build_query($p);
                    };
                    ?>
                    <select class="form-select form-select-sm py-2" style="width: auto; font-size: 0.85rem;" onchange="location = this.value;">
                        <option value="<?= $buildSortUrl('latest') ?>" <?= $sort === 'latest' ? 'selected' : '' ?>>Newly Listed</option>
                        <option value="<?= $buildSortUrl('price_asc') ?>" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="<?= $buildSortUrl('price_desc') ?>" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="<?= $buildSortUrl('title_asc') ?>" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title: A to Z</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Catalog & Filters Section -->
    <div class="row g-4" id="bookCatalogGrid">
        
        <!-- Filter Sidebar (Sticky on Desktop, Collapsible on Mobile) -->
        <div class="col-lg-3 col-xl-3 col-xxl-2">
            <div class="collapse d-lg-block" id="filterSidebarCollapse">
                <div class="filter-card sticky-top" style="top: 95px; z-index: 10;">
                    <div class="filter-title">
                        <span><i class="bi bi-funnel-fill text-teal me-1"></i> Filter Catalog</span>
                        <?php if ($has_active_filters): ?>
                            <a href="index.php" class="btn btn-sm btn-link text-danger p-0 text-decoration-none small">Clear All</a>
                        <?php endif; ?>
                    </div>

                    <form method="GET" action="index.php" id="filterSidebarForm">
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <?php endif; ?>
                        <?php if (!empty($cat_filter)): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($cat_filter) ?>">
                        <?php endif; ?>

                        <!-- Quick Condition Filter -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold small text-navy text-uppercase mb-0">Book Condition</label>
                                <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#conditionGuideModal" class="small text-teal text-decoration-none" style="font-size: 0.72rem;">Guide?</a>
                            </div>
                            <select name="condition" class="form-select form-select-sm" onchange="document.getElementById('filterSidebarForm').submit()">
                                <option value="">Any Condition</option>
                                <option value="Brand New" <?= $condition_filter === 'Brand New' ? 'selected' : '' ?>>Brand New (Sealed)</option>
                                <option value="Like New" <?= $condition_filter === 'Like New' ? 'selected' : '' ?>>Like New (Crisp)</option>
                                <option value="Good" <?= $condition_filter === 'Good' ? 'selected' : '' ?>>Good (Clean, Minor Signs)</option>
                                <option value="Fair" <?= $condition_filter === 'Fair' ? 'selected' : '' ?>>Fair (Readable / Marked)</option>
                                <option value="Poor" <?= $condition_filter === 'Poor' ? 'selected' : '' ?>>Poor (Heavy Wear)</option>
                            </select>
                        </div>

                        <!-- Price Range Filter with Quick Presets -->
                        <div class="mb-4 mt-3">
                            <label class="form-label fw-bold small text-navy text-uppercase mb-2">Price Range (Rs.)</label>
                            
                            <!-- Quick Price Presets -->
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <?php
                                $pricePresetUrl = function($min, $max) use ($current_params) {
                                    $p = $current_params;
                                    if ($min !== null) $p['min_price'] = $min; else unset($p['min_price']);
                                    if ($max !== null) $p['max_price'] = $max; else unset($p['max_price']);
                                    return 'index.php?' . http_build_query($p);
                                };
                                ?>
                                <a href="<?= $pricePresetUrl(null, 1000) ?>" class="price-preset-pill <?= ($min_price === null && $max_price == 1000) ? 'active' : '' ?>">&lt; Rs. 1k</a>
                                <a href="<?= $pricePresetUrl(1000, 2500) ?>" class="price-preset-pill <?= ($min_price == 1000 && $max_price == 2500) ? 'active' : '' ?>">1k - 2.5k</a>
                                <a href="<?= $pricePresetUrl(2500, null) ?>" class="price-preset-pill <?= ($min_price == 2500 && $max_price === null) ? 'active' : '' ?>">&gt; Rs. 2.5k</a>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" step="50" name="min_price" class="form-control form-control-sm" placeholder="Min (Rs.)" value="<?= $min_price !== null ? htmlspecialchars($min_price) : '' ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" step="50" name="max_price" class="form-control form-control-sm" placeholder="Max (Rs.)" value="<?= $max_price !== null ? htmlspecialchars($max_price) : '' ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-booksy-primary w-100 mt-2">Apply Custom Price</button>
                        </div>

                        <!-- Categories Filter List -->
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-navy text-uppercase mb-2">Browse by Category</label>
                            <div class="d-flex flex-column gap-1">
                                <a href="index.php<?= !empty($search) ? '?search='.urlencode($search) : '' ?>" class="filter-category-item <?= empty($cat_filter) ? 'active' : '' ?>">
                                    <span>All Categories</span>
                                    <span class="badge bg-secondary"><?= $total_available ?></span>
                                </a>
                                <?php foreach ($categories as $cat): ?>
                                    <a href="index.php?category=<?= urlencode($cat['slug']) ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($condition_filter) ? '&condition='.urlencode($condition_filter) : '' ?>" class="filter-category-item <?= $cat_filter === $cat['slug'] ? 'active' : '' ?>">
                                        <span class="text-truncate"><?= htmlspecialchars($cat['name']) ?></span>
                                        <span class="badge bg-light text-dark"><?= $cat['book_count'] ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Book Product Grid -->
        <div class="col-lg-9 col-xl-9 col-xxl-10">
            
            <!-- Result Header & Controls (Desktop) -->
            <div class="card border-0 shadow-sm p-3 mb-4 rounded-3 bg-white border d-none d-lg-block">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                        <span class="text-muted small">Showing <strong><?= count($books) ?></strong> book listing(s)</span>
                        <?php if (!empty($cat_filter)): ?>
                            <span class="badge bg-teal-light text-teal ms-2 text-capitalize"><?= htmlspecialchars(str_replace('-', ' ', $cat_filter)) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($condition_filter)): ?>
                            <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($condition_filter) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <!-- View Mode Switcher -->
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="view-mode-btn active" id="viewModeGrid" title="Grid View">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </button>
                            <button type="button" class="view-mode-btn" id="viewModeList" title="List View">
                                <i class="bi bi-view-list"></i>
                            </button>
                        </div>

                        <!-- Sort By -->
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted text-nowrap fw-semibold">Sort By:</label>
                            <select class="form-select form-select-sm" style="width: auto;" onchange="location = this.value;">
                                <option value="<?= $buildSortUrl('latest') ?>" <?= $sort === 'latest' ? 'selected' : '' ?>>Newly Listed</option>
                                <option value="<?= $buildSortUrl('price_asc') ?>" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="<?= $buildSortUrl('price_desc') ?>" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="<?= $buildSortUrl('title_asc') ?>" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title: A to Z</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Book Cards Grid / List Container -->
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-4 g-3 g-xl-4" id="bookProductGridContainer">
                <?php if (count($books) > 0): ?>
                    <?php foreach ($books as $book): ?>
                        <?php
                        $cover_img = !empty($book['image_url']) && file_exists(__DIR__ . '/uploads/' . $book['image_url']) 
                            ? 'uploads/' . htmlspecialchars($book['image_url']) 
                            : 'uploads/default_book.svg';
                        
                        $condition_class = 'badge-good';
                        if ($book['book_condition'] === 'Brand New') $condition_class = 'badge-brand-new';
                        elseif ($book['book_condition'] === 'Like New') $condition_class = 'badge-like-new';
                        elseif ($book['book_condition'] === 'Fair') $condition_class = 'badge-fair';
                        elseif ($book['book_condition'] === 'Poor') $condition_class = 'badge-poor';
                        ?>
                        <div class="col">
                            <div class="card book-card h-100">
                                
                                <!-- Cover Image & Wishlist Button -->
                                <div class="book-card-cover-wrap position-relative <?= !empty($book['active_boost_type']) ? 'border-warning' : '' ?>">
                                    
                                    <!-- Promotional Boost Badge -->
                                    <?php if (!empty($book['active_boost_type'])): ?>
                                        <span class="position-absolute top-0 start-0 m-2 badge bg-gold text-dark fw-bold shadow-sm" style="z-index: 2; font-size: 0.72rem;">
                                            <i class="bi bi-lightning-fill"></i> <?= $book['active_boost_type'] === 'homepage_boost' ? 'Featured' : ucwords(str_replace('_', ' ', $book['active_boost_type'])) ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Heart Wishlist Toggle Button -->
                                    <button type="button" class="btn-wishlist-toggle" 
                                            data-book-id="<?= $book['id'] ?>" 
                                            data-book-title="<?= htmlspecialchars($book['title']) ?>" 
                                            data-book-price="<?= $book['price'] ?>"
                                            data-book-author="<?= htmlspecialchars($book['author']) ?>"
                                            data-book-cover="<?= $cover_img ?>"
                                            onclick="toggleWishlistItem(this, <?= $book['id'] ?>)" 
                                            title="Save to Wishlist"
                                            style="<?= !empty($book['active_boost_type']) ? 'top: 38px;' : '' ?>">
                                        <i class="bi bi-heart"></i>
                                    </button>

                                    <!-- Book Cover Link -->
                                    <a href="book-details.php?id=<?= $book['id'] ?>" class="d-flex align-items-center justify-content-center w-100 h-100">
                                        <img src="<?= $cover_img ?>" alt="<?= htmlspecialchars($book['title']) ?>" loading="lazy" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                                    </a>

                                    <!-- Condition Badge with guide trigger -->
                                    <span class="position-absolute top-0 end-0 m-2 badge badge-condition <?= $condition_class ?>" 
                                          style="cursor: pointer;" 
                                          data-bs-toggle="modal" 
                                          data-bs-target="#conditionGuideModal" 
                                          title="Click to view condition guide">
                                        <?= htmlspecialchars($book['book_condition']) ?>
                                    </span>
                                </div>

                                <!-- Card Details -->
                                <div class="book-card-body">
                                    <div class="mb-1">
                                        <a href="index.php?category=<?= urlencode($book['category_slug']) ?>" class="badge bg-light text-navy text-decoration-none small border">
                                            <?= htmlspecialchars($book['category_name']) ?>
                                        </a>
                                    </div>
                                    <h6 class="book-card-title" title="<?= htmlspecialchars($book['title']) ?>">
                                        <a href="book-details.php?id=<?= $book['id'] ?>"><?= htmlspecialchars($book['title']) ?></a>
                                    </h6>
                                    <p class="book-card-author">By <?= htmlspecialchars($book['author']) ?></p>

                                    <!-- Price & Seller Info -->
                                    <div class="d-flex justify-content-between align-items-end mb-3 mt-auto pt-2 border-top">
                                        <div>
                                            <div class="small text-muted" style="font-size: 0.72rem;">Marketplace Price</div>
                                            <div class="book-card-price">Rs. <?= number_format($book['price'], 2) ?></div>
                                        </div>
                                        <div class="text-end">
                                            <div class="small text-muted" style="font-size: 0.72rem;"><i class="bi bi-person me-1"></i>Seller</div>
                                            <span class="badge bg-light text-navy border text-truncate d-inline-block" style="max-width: 115px;">
                                                <?= htmlspecialchars($book['seller_name']) ?>
                                                <?php if (!empty($book['seller_is_vip'])): ?>
                                                    <i class="bi bi-gem text-gold ms-0.5" title="Verified VIP Seller"></i>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <form method="POST" action="cart.php">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                            <input type="hidden" name="title" value="<?= htmlspecialchars($book['title']) ?>">
                                            <input type="hidden" name="price" value="<?= $book['price'] ?>">
                                            <input type="hidden" name="image_url" value="<?= htmlspecialchars($book['image_url'] ?? '') ?>">
                                            <button type="submit" class="btn btn-booksy-primary btn-sm w-100 fw-bold">
                                                <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                            </button>
                                        </form>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#quickViewModal<?= $book['id'] ?>">
                                                <i class="bi bi-eye me-1"></i> Quick View
                                            </button>
                                            <a href="book-details.php?id=<?= $book['id'] ?>" class="btn btn-light btn-sm border" title="Full Details">
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick View Modal -->
                        <div class="modal fade" id="quickViewModal<?= $book['id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $book['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                    <div class="modal-header bg-navy text-white border-0 py-3" style="border-bottom: 2px solid var(--teal-primary);">
                                        <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="modalLabel<?= $book['id'] ?>">
                                            <i class="bi bi-book-half text-teal"></i>
                                            <span><?= htmlspecialchars($book['title']) ?></span>
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 bg-white">
                                        <div class="row g-4 align-items-center">
                                            <div class="col-md-5 text-center">
                                                <div class="p-3 bg-light rounded-3 shadow-sm mb-2" style="max-height: 320px; display: flex; align-items: center; justify-content: center;">
                                                    <img src="<?= $cover_img ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="img-fluid rounded" style="max-height: 280px; object-fit: contain;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                                                </div>
                                                <span class="badge badge-condition <?= $condition_class ?> fs-6 py-2 px-3">
                                                    Condition: <?= htmlspecialchars($book['book_condition']) ?>
                                                </span>
                                            </div>
                                            <div class="col-md-7">
                                                <div class="badge bg-teal mb-2"><?= htmlspecialchars($book['category_name']) ?></div>
                                                <h4 class="fw-bold mb-1 text-navy font-serif-title"><?= htmlspecialchars($book['title']) ?></h4>
                                                <p class="text-muted mb-2">By <strong><?= htmlspecialchars($book['author']) ?></strong></p>
                                                
                                                <div class="display-6 fw-bold text-navy mb-3">
                                                    Rs. <?= number_format($book['price'], 2) ?>
                                                </div>

                                                <div class="mb-3">
                                                    <h6 class="fw-bold small text-navy text-uppercase mb-1">Book Description:</h6>
                                                    <div class="p-3 bg-light rounded-3 small text-secondary border" style="max-height: 120px; overflow-y: auto;">
                                                        <?= nl2br(htmlspecialchars($book['description'] ?: 'No additional description provided by the seller.')) ?>
                                                    </div>
                                                </div>

                                                <!-- Seller Contact Card -->
                                                <div class="seller-trust-box mb-4">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="seller-avatar" style="width: 38px; height: 38px; font-size: 1rem;">
                                                                <?= strtoupper(substr($book['seller_name'], 0, 1)) ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold small text-navy"><?= htmlspecialchars($book['seller_name']) ?></div>
                                                                <div class="text-muted small" style="font-size: 0.75rem;"><i class="bi bi-shield-check text-success"></i> Registered Seller</div>
                                                            </div>
                                                        </div>
                                                        <?php if (!empty($book['seller_phone'])): ?>
                                                            <a href="https://wa.me/94<?= ltrim($book['seller_phone'], '0') ?>?text=<?= urlencode('Hi, I am interested in your book "'.$book['title'].'" on Booksy (Rs. '.number_format($book['price'], 2).'). Is it still available?') ?>" target="_blank" class="btn btn-whatsapp btn-sm">
                                                                <i class="bi bi-whatsapp"></i> Chat Seller
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="d-flex gap-2">
                                                    <form method="POST" action="cart.php" class="flex-fill">
                                                        <input type="hidden" name="action" value="add">
                                                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                        <input type="hidden" name="title" value="<?= htmlspecialchars($book['title']) ?>">
                                                        <input type="hidden" name="price" value="<?= $book['price'] ?>">
                                                        <input type="hidden" name="image_url" value="<?= htmlspecialchars($book['image_url'] ?? '') ?>">
                                                        <button type="submit" class="btn btn-booksy-primary w-100 fw-bold py-2">
                                                            <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                                        </button>
                                                    </form>
                                                    <a href="book-details.php?id=<?= $book['id'] ?>" class="btn btn-booksy-navy fw-semibold py-2">
                                                        Full Details
                                                    </a>
                                                </div>
                                                <div class="text-center mt-2">
                                                    <a href="book-details.php?id=<?= $book['id'] ?>" class="small text-danger text-decoration-none">
                                                        <i class="bi bi-flag me-1"></i> Report this listing
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm p-5 text-center rounded-4 bg-white border">
                            <i class="bi bi-search text-muted mb-3" style="font-size: 3rem;"></i>
                            <h4 class="fw-bold mb-2 text-navy">No Matching Books Found</h4>
                            <p class="text-muted mb-4 mx-auto" style="max-width: 500px;">
                                We couldn't find any active listings matching your current search and filter criteria. Try adjusting your keyword or clearing filters.
                            </p>
                            <div>
                                <a href="index.php" class="btn btn-booksy-primary fw-bold px-4 py-2">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Reset All Filters
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Carousel Data in JSON for JS Slides -->
<script>
const heroSlidesData = <?= json_encode($hero_slides) ?>;
</script>

<?php include 'includes/footer.php'; ?>