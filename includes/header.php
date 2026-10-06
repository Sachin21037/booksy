<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $c_item) {
        $cart_count += isset($c_item['qty']) ? max(1, intval($c_item['qty'])) : 1;
    }
}
$logged_user = get_logged_user();
$flash = get_flash();

// Fetch categories for header navigation
try {
    $nav_categories = $pdo->query("SELECT name, slug FROM categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $nav_categories = [];
}

$current_cat = trim($_GET['category'] ?? '');
$current_search = trim($_GET['search'] ?? '');
$current_script = basename($_SERVER['PHP_SELF']);
$selected_cat_name = 'All';
if (!empty($current_cat)) {
    foreach ($nav_categories as $ncat) {
        if ($ncat['slug'] === $current_cat) {
            $selected_cat_name = $ncat['name'];
            break;
        }
    }
}
// Active top announcement banner
$top_announcements = function_exists('get_active_banners') ? get_active_banners($pdo, 'top_announcement') : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' | Booksy' : 'Booksy | Finding New Homes For Books' ?></title>
    <meta name="description" content="Booksy is Sri Lanka's leading C2C marketplace to buy and sell used & new textbooks, novels, academic books, and rare collectibles.">
    
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom Design Tokens & Concept Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (!empty($top_announcements)): ?>
    <div class="bg-navy text-white py-1.5 px-3 border-bottom border-teal text-center small fw-semibold d-flex align-items-center justify-content-center gap-2 flex-wrap" style="background: linear-gradient(90deg, #0A192F 0%, #0F3460 50%, #0A192F 100%);">
        <i class="bi <?= htmlspecialchars($top_announcements[0]['icon'] ?: 'bi-megaphone-fill') ?> text-teal"></i>
        <span><?= htmlspecialchars($top_announcements[0]['title']) ?></span>
        <?php if (!empty($top_announcements[0]['description'])): ?>
            <span class="d-none d-md-inline text-white-50">| <?= htmlspecialchars($top_announcements[0]['description']) ?></span>
        <?php endif; ?>
        <?php if (!empty($top_announcements[0]['btn_link']) && $top_announcements[0]['btn_link'] !== '#'): ?>
            <a href="<?= htmlspecialchars($top_announcements[0]['btn_link']) ?>" class="badge bg-teal text-white text-decoration-none ms-1 px-2 py-1">
                <?= htmlspecialchars($top_announcements[0]['btn_text'] ?: 'Learn More') ?> &rarr;
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Floating Header Navigation -->
<header class="header-wrapper">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-booksy-floating flex-column p-2 p-lg-3">
            
            <!-- Row 1: Brand, Search Bar, and Main CTA Actions -->
            <div class="container-fluid px-0 d-flex align-items-center justify-content-between gap-2">
                
                <!-- Left: Brand Logo -->
                <a class="navbar-brand navbar-brand-booksy me-2 me-lg-3" href="index.php">
                    <img src="images/booksy-logo-horizontal.svg" alt="Booksy" height="40">
                </a>

                <!-- Mobile Quick Actions & Toggler -->
                <div class="d-flex align-items-center gap-1 d-lg-none ms-auto">
                    <!-- Wishlist Icon Mobile -->
                    <a href="javascript:void(0)" class="btn-header-icon" title="My Wishlist" onclick="toggleWishlistModal();">
                        <i class="bi bi-heart"></i>
                        <span class="badge-counter d-none" id="wishlistNavCountMobile">0</span>
                    </a>
                    
                    <!-- Shopping Cart Icon Mobile -->
                    <a href="cart.php" class="btn-header-icon" title="Shopping Cart">
                        <i class="bi bi-cart3"></i>
                        <span class="badge-counter <?= $cart_count > 0 ? '' : 'd-none' ?>" id="headerCartBadgeMobile"><?= $cart_count ?></span>
                    </a>

                    <!-- Mobile Hamburger Toggler -->
                    <button class="navbar-toggler border-0 shadow-none p-1 ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#headerNavContent" aria-controls="headerNavContent" aria-expanded="false" aria-label="Toggle navigation">
                        <i class="bi bi-list fs-2 text-navy"></i>
                    </button>
                </div>

                <!-- Center & Right Navigation (Collapsible on Mobile) -->
                <div class="collapse navbar-collapse" id="headerNavContent">
                    
                    <!-- Center: Integrated Pill Search Bar -->
                    <form method="GET" action="index.php" class="header-search-pill mx-auto my-2 my-lg-0" id="headerSearchForm">
                        <input type="hidden" name="category" id="headerSearchCategoryInput" value="<?= htmlspecialchars($current_cat) ?>">
                        
                        <!-- Category Dropdown Selector inside Search Bar -->
                        <div class="dropdown">
                            <button class="category-select-btn dropdown-toggle" type="button" id="headerCategoryDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <span id="headerSelectedCatLabel" class="text-truncate" style="max-width: 90px;"><?= htmlspecialchars($selected_cat_name) ?></span>
                            </button>
                            <ul class="dropdown-menu shadow border-0 mt-2 rounded-3" aria-labelledby="headerCategoryDropdown" style="font-size: 0.88rem; z-index: 1060;">
                                <li><a class="dropdown-item py-2 <?= empty($current_cat) ? 'active bg-teal' : '' ?>" href="#" onclick="selectHeaderCategory('', 'All'); return false;">All Categories</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <?php foreach ($nav_categories as $ncat): ?>
                                    <li>
                                        <a class="dropdown-item py-2 <?= $current_cat === $ncat['slug'] ? 'active bg-teal' : '' ?>" href="#" onclick="selectHeaderCategory('<?= htmlspecialchars($ncat['slug']) ?>', '<?= htmlspecialchars(addslashes($ncat['name'])) ?>'); return false;">
                                            <?= htmlspecialchars($ncat['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="search-divider"></div>

                        <!-- Search Input -->
                        <i class="bi bi-search text-muted ms-1"></i>
                        <input type="text" name="search" class="search-input" placeholder="Search by title, author, or ISBN..." value="<?= htmlspecialchars($current_search) ?>" autocomplete="off">

                        <!-- Search Submit Button -->
                        <button type="submit" class="btn btn-search-teal">
                            Search
                        </button>
                    </form>

                    <!-- Right: Desktop Action Items -->
                    <div class="d-none d-lg-flex align-items-center gap-2 ms-lg-3 justify-content-end">
                        <!-- VIP Club Button -->
                        <a href="vip.php" class="btn btn-sm btn-outline-light rounded-pill d-none d-xl-flex align-items-center gap-1 px-3 py-1.5" style="border-color: rgba(245, 158, 11, 0.4); color: #F59E0B;">
                            <i class="bi bi-gem"></i>
                            <span class="small fw-semibold">VIP Club</span>
                        </a>

                        <!-- Sell Books CTA -->
                        <a href="sell.php" class="btn-sell-books">
                            <i class="bi bi-tag-fill"></i>
                            <span>Sell Books</span>
                        </a>

                        <!-- Wishlist Heart Button -->
                        <a href="javascript:void(0)" class="btn-header-icon" id="wishlistHeaderBtn" title="My Wishlist" onclick="toggleWishlistModal();">
                            <i class="bi bi-heart"></i>
                            <span class="badge-counter d-none" id="wishlistNavCount">0</span>
                        </a>

                        <!-- Shopping Cart Button -->
                        <a href="cart.php" class="btn-header-icon" title="Shopping Cart">
                            <i class="bi bi-cart3"></i>
                            <span class="badge-counter <?= $cart_count > 0 ? '' : 'd-none' ?>" id="headerCartBadge"><?= $cart_count ?></span>
                        </a>

                        <?php if ($logged_user): ?>
                            <?php $nav_is_vip = is_user_vip($pdo, $logged_user['id']); ?>
                            <div class="dropdown">
                                <button class="user-avatar-btn dropdown-toggle <?= is_owner() ? 'border border-2 border-warning' : ($nav_is_vip ? 'border border-2 border-warning' : '') ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars($logged_user['name']) ?>">
                                    <?= strtoupper(substr($logged_user['name'], 0, 1)) ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3" style="z-index: 1060; min-width: 230px;">
                                    <li class="px-3 py-2 border-bottom">
                                        <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                            <p class="mb-0 fw-bold small text-navy text-truncate"><?= htmlspecialchars($logged_user['name']) ?></p>
                                            <?php if (is_owner()): ?>
                                                <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.65rem;"><i class="bi bi-crown-fill me-0.5"></i> Owner</span>
                                            <?php elseif (($logged_user['role'] ?? '') === 'admin'): ?>
                                                <span class="badge bg-teal text-white fw-bold" style="font-size: 0.65rem;"><i class="bi bi-shield-lock-fill me-0.5"></i> Manager</span>
                                            <?php elseif ($nav_is_vip): ?>
                                                <span class="badge bg-gold text-dark" style="font-size: 0.65rem;">VIP</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-0 text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($logged_user['email']) ?></p>
                                    </li>
                                    <li><a class="dropdown-item py-2" href="seller-earnings.php"><i class="bi bi-wallet2 me-2 text-teal"></i> Seller Earnings & Fees</a></li>
                                    <li><a class="dropdown-item py-2" href="my-listings.php"><i class="bi bi-journal-album me-2 text-teal"></i> My Listed Books</a></li>
                                    <li><a class="dropdown-item py-2" href="my-orders.php"><i class="bi bi-box-seam me-2 text-teal"></i> My Orders</a></li>
                                    <li><a class="dropdown-item py-2" href="vip.php"><i class="bi bi-gem me-2 text-gold"></i> Booksy VIP Club</a></li>
                                    <li><a class="dropdown-item py-2" href="sell.php"><i class="bi bi-plus-circle me-2 text-teal"></i> Sell New Book</a></li>
                                    <?php if (is_admin()): ?>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li><a class="dropdown-item py-2 fw-semibold text-primary" href="admin-monetization.php"><i class="bi bi-shield-lock-fill me-2"></i> Admin Monetization</a></li>
                                        <li><a class="dropdown-item py-2 fw-semibold text-teal" href="admin-banners.php"><i class="bi bi-images me-2"></i> Manage Banners</a></li>
                                        <li><a class="dropdown-item py-2 fw-semibold text-danger" href="admin-fraud.php"><i class="bi bi-shield-exclamation me-2"></i> Trust & Fraud Control</a></li>
                                        <?php if (is_owner()): ?>
                                            <li><a class="dropdown-item py-2 fw-semibold text-dark" href="admin-users.php"><i class="bi bi-people-fill me-2 text-warning"></i> Manage Admins & Users</a></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="dropdown">
                                <button class="user-avatar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
                                    <i class="bi bi-person-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3" style="z-index: 1060; min-width: 200px;">
                                    <li class="px-3 py-2 border-bottom">
                                        <p class="mb-0 fw-bold small text-navy">Welcome to Booksy</p>
                                        <p class="mb-0 text-muted" style="font-size: 0.75rem;">Finding new homes for books</p>
                                    </li>
                                    <li><a class="dropdown-item py-2 fw-semibold text-teal" href="login.php"><i class="bi bi-box-arrow-in-right me-2"></i> Sign In</a></li>
                                    <li><a class="dropdown-item py-2" href="register.php"><i class="bi bi-person-plus me-2 text-navy"></i> Create Free Account</a></li>
                                    <li><a class="dropdown-item py-2" href="vip.php"><i class="bi bi-gem me-2 text-gold"></i> Booksy VIP Club</a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item py-2" href="sell.php"><i class="bi bi-tag me-2 text-warning"></i> Sell Books</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mobile Navigation Links (Visible on collapse) -->
                    <div class="d-lg-none w-100 mt-2 pt-2 border-top">
                        <!-- Mobile User Action Strip -->
                        <div class="d-flex align-items-center justify-content-between mb-3 p-2 bg-light rounded-3">
                            <?php if ($logged_user): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-btn" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                        <?= strtoupper(substr($logged_user['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold small text-navy"><?= htmlspecialchars($logged_user['name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($logged_user['email']) ?></div>
                                    </div>
                                </div>
                                <a href="logout.php" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.75rem;">Sign Out</a>
                            <?php else: ?>
                                <div class="small fw-semibold text-navy">Welcome to Booksy</div>
                                <div class="d-flex gap-2">
                                    <a href="login.php" class="btn btn-sm btn-booksy-primary py-1 px-2" style="font-size: 0.78rem;">Sign In</a>
                                    <a href="register.php" class="btn btn-sm btn-outline-dark py-1 px-2" style="font-size: 0.78rem;">Register</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Mobile Sell Book CTA -->
                        <a href="sell.php" class="btn btn-booksy-primary w-100 mb-2 py-2 fw-bold">
                            <i class="bi bi-tag-fill me-1"></i> Sell Your Books
                        </a>

                        <!-- Mobile Navigation Category Links -->
                        <div class="d-flex flex-column gap-1">
                            <a href="index.php" class="sub-nav-link <?= $current_script === 'index.php' && empty($current_cat) ? 'active' : '' ?>">
                                <i class="bi bi-house-door-fill text-teal me-2"></i> Home
                            </a>
                            <a href="index.php?sort=latest" class="sub-nav-link">
                                <i class="bi bi-fire text-danger me-2"></i> Bestsellers & Trending
                            </a>
                            <a href="index.php?category=educational-academic" class="sub-nav-link <?= $current_cat === 'educational-academic' ? 'active' : '' ?>">
                                <i class="bi bi-mortarboard-fill text-teal me-2"></i> Academic & Textbooks
                            </a>
                            <a href="index.php?category=novels-fiction" class="sub-nav-link <?= $current_cat === 'novels-fiction' ? 'active' : '' ?>">
                                <i class="bi bi-book-half text-navy me-2"></i> Novels & Fiction
                            </a>
                            <a href="index.php?category=self-help" class="sub-nav-link <?= $current_cat === 'self-help' ? 'active' : '' ?>">
                                <i class="bi bi-lightning-charge-fill text-warning me-2"></i> Self-Help & Growth
                            </a>
                            <a href="index.php?category=other-books" class="sub-nav-link <?= $current_cat === 'other-books' ? 'active' : '' ?>">
                                <i class="bi bi-stars text-gold me-2"></i> Rare & Collectibles
                            </a>
                            <?php if ($logged_user): ?>
                                <hr class="my-1">
                                <a href="my-listings.php" class="sub-nav-link <?= $current_script === 'my-listings.php' ? 'active' : '' ?>">
                                    <i class="bi bi-journal-album text-teal me-2"></i> My Listed Books
                                </a>
                                <a href="my-orders.php" class="sub-nav-link <?= $current_script === 'my-orders.php' ? 'active' : '' ?>">
                                    <i class="bi bi-box-seam text-teal me-2"></i> My Orders
                                </a>
                                <?php if (is_admin()): ?>
                                    <a href="admin-monetization.php" class="sub-nav-link <?= $current_script === 'admin-monetization.php' ? 'active' : '' ?>">
                                        <i class="bi bi-shield-lock-fill text-primary me-2"></i> Admin Monetization
                                    </a>
                                    <a href="admin-banners.php" class="sub-nav-link <?= $current_script === 'admin-banners.php' ? 'active' : '' ?>">
                                        <i class="bi bi-images text-teal me-2"></i> Manage Banners
                                    </a>
                                    <a href="admin-fraud.php" class="sub-nav-link <?= $current_script === 'admin-fraud.php' ? 'active' : '' ?>">
                                        <i class="bi bi-shield-exclamation text-danger me-2"></i> Trust & Fraud Moderation
                                    </a>
                                    <?php if (is_owner()): ?>
                                        <a href="admin-users.php" class="sub-nav-link <?= $current_script === 'admin-users.php' ? 'active' : '' ?>">
                                            <i class="bi bi-people-fill text-warning me-2"></i> Manage Admins & Users
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Secondary Navigation Bar Links (Desktop Sub-Navbar) -->
            <div class="sub-nav-wrapper d-none d-lg-block w-100 mt-2 pt-2">
                <ul class="sub-nav-links">
                    <li class="nav-item">
                        <a class="sub-nav-link <?= $current_script === 'index.php' && empty($current_cat) && empty($current_search) ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-house-door-fill text-teal me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="sub-nav-link dropdown-toggle" href="#" id="subnavCatDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-grid-3x3-gap-fill text-teal me-1"></i> All Categories
                        </a>
                        <ul class="dropdown-menu shadow border-0 mt-1 rounded-3" aria-labelledby="subnavCatDropdown" style="z-index: 1060;">
                            <li><a class="dropdown-item py-2" href="index.php"><i class="bi bi-collection me-2 text-teal"></i> Browse All</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <?php foreach ($nav_categories as $ncat): ?>
                                <li>
                                    <a class="dropdown-item py-2 <?= $current_cat === $ncat['slug'] ? 'active bg-teal' : '' ?>" href="index.php?category=<?= urlencode($ncat['slug']) ?>">
                                        <?= htmlspecialchars($ncat['name']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="sub-nav-link <?= isset($_GET['sort']) && $_GET['sort'] === 'latest' ? 'active' : '' ?>" href="index.php?sort=latest">
                            <i class="bi bi-fire text-danger me-1"></i> Bestsellers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sub-nav-link <?= $current_cat === 'educational-academic' ? 'active' : '' ?>" href="index.php?category=educational-academic">
                            <i class="bi bi-mortarboard-fill text-teal me-1"></i> Academic Textbooks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sub-nav-link <?= $current_cat === 'novels-fiction' ? 'active' : '' ?>" href="index.php?category=novels-fiction">
                            <i class="bi bi-book-half text-navy me-1"></i> Novels & Fiction
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sub-nav-link <?= $current_cat === 'self-help' ? 'active' : '' ?>" href="index.php?category=self-help">
                            <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Self-Help
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sub-nav-link <?= $current_cat === 'other-books' ? 'active' : '' ?>" href="index.php?category=other-books">
                            <i class="bi bi-stars text-gold me-1"></i> Rare Finds
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sub-nav-link <?= $current_script === 'sell.php' ? 'active' : '' ?>" href="sell.php">
                            <i class="bi bi-tag-fill text-teal me-1"></i> Sell Books
                        </a>
                    </li>
                    <?php if ($logged_user): ?>
                        <li class="nav-item">
                            <a class="sub-nav-link <?= $current_script === 'my-orders.php' ? 'active' : '' ?>" href="my-orders.php">
                                <i class="bi bi-truck text-muted me-1"></i> My Orders
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </div>
</header>

<!-- Global Wishlist Modal -->
<div class="modal fade" id="wishlistModal" tabindex="-1" aria-labelledby="wishlistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white py-3 px-4">
                <h6 class="modal-title fw-bold" id="wishlistModalLabel">
                    <i class="bi bi-heart-fill text-danger me-2"></i> My Saved Wishlist
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4" id="wishlistModalBody">
                <!-- Populated dynamically via JS -->
            </div>
        </div>
    </div>
</div>

<!-- Mobile App Bottom Navigation Bar -->
<nav class="mobile-bottom-nav">
    <a href="index.php" class="nav-bottom-item <?= $current_script === 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-house-door-fill"></i>
        <span>Home</span>
    </a>
    <a href="javascript:void(0)" class="nav-bottom-item" onclick="toggleWishlistModal();">
        <i class="bi bi-heart-fill"></i>
        <span>Wishlist</span>
        <span class="badge-counter d-none" id="bottomNavWishlistBadge">0</span>
    </a>
    <a href="sell.php" class="nav-bottom-item nav-bottom-fab" title="Sell Books">
        <i class="bi bi-plus-lg"></i>
    </a>
    <a href="cart.php" class="nav-bottom-item <?= $current_script === 'cart.php' ? 'active' : '' ?>">
        <i class="bi bi-cart3"></i>
        <span>Cart</span>
        <?php if ($cart_count > 0): ?>
            <span class="badge-counter"><?= $cart_count ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= $logged_user ? 'my-listings.php' : 'login.php' ?>" class="nav-bottom-item <?= in_array($current_script, ['my-listings.php', 'login.php', 'register.php', 'my-orders.php']) ? 'active' : '' ?>">
        <i class="bi bi-person-circle"></i>
        <span><?= $logged_user ? 'Account' : 'Sign In' ?></span>
    </a>
</nav>

<!-- Flash Alerts Notification System -->
<?php if ($flash): ?>
<div class="container mt-2">
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show shadow-sm border-0 d-flex align-items-center rounded-3" role="alert">
        <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill') ?> fs-5 me-2 text-<?= $flash['type'] ?>"></i>
        <div><?= htmlspecialchars($flash['message']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>

<main>