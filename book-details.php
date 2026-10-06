<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch book details with promotional boost and VIP status
$stmt = $pdo->prepare("
    SELECT b.*, c.name AS category_name, c.slug AS category_slug, 
           u.name AS seller_name, u.phone AS seller_phone, u.email AS seller_email, u.created_at AS seller_joined,
           pl.promotion_type AS active_boost_type,
           CASE WHEN mem.membership_id IS NOT NULL THEN 1 ELSE 0 END AS seller_is_vip
    FROM books b
    JOIN categories c ON b.category_id = c.id
    JOIN users u ON b.seller_id = u.id
    LEFT JOIN promotional_listings pl ON b.id = pl.book_id AND pl.status = 'active' AND pl.end_date >= NOW()
    LEFT JOIN memberships mem ON u.id = mem.user_id AND mem.status = 'active' AND mem.end_date >= CURDATE()
    WHERE b.id = ?
    LIMIT 1
");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    set_flash('danger', 'The requested book listing was not found or has been removed.');
    header("Location: index.php");
    exit;
}

$page_title = $book['title'] . ' by ' . $book['author'];

// Fetch related books in the same category (only books with photos)
$related_stmt = $pdo->prepare("
    SELECT b.*, c.name AS category_name, c.slug AS category_slug
    FROM books b
    JOIN categories c ON b.category_id = c.id
    WHERE b.category_id = ? AND b.id != ? AND b.status = 'available'
      AND b.image_url IS NOT NULL AND b.image_url != '' AND b.image_url != 'default_book.svg'
    ORDER BY b.created_at DESC
    LIMIT 4
");
$related_stmt->execute([$book['category_id'], $book['id']]);
$related_books = array_values(array_filter($related_stmt->fetchAll(), function($rb) {
    return !empty($rb['image_url']) && file_exists(__DIR__ . '/uploads/' . $rb['image_url']);
}));

$cover_img = !empty($book['image_url']) && file_exists(__DIR__ . '/uploads/' . $book['image_url']) 
    ? 'uploads/' . htmlspecialchars($book['image_url']) 
    : 'uploads/default_book.svg';

$condition_class = 'badge-good';
if ($book['book_condition'] === 'Brand New') $condition_class = 'badge-brand-new';
elseif ($book['book_condition'] === 'Like New') $condition_class = 'badge-like-new';
elseif ($book['book_condition'] === 'Fair') $condition_class = 'badge-fair';
elseif ($book['book_condition'] === 'Poor') $condition_class = 'badge-poor';

$current_full_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$share_message = 'Check out this book on Booksy: "' . $book['title'] . '" by ' . $book['author'] . ' (Rs. ' . number_format($book['price'], 2) . ')';

include 'includes/header.php';
?>

<div class="container py-3 py-lg-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3 mb-lg-4">
        <ol class="breadcrumb bg-white p-2.5 p-md-3 rounded-4 shadow-sm border mb-0 small">
            <li class="breadcrumb-item"><a href="index.php" class="text-teal text-decoration-none fw-semibold">Home</a></li>
            <li class="breadcrumb-item"><a href="index.php?category=<?= urlencode($book['category_slug']) ?>" class="text-navy text-decoration-none fw-semibold"><?= htmlspecialchars($book['category_name']) ?></a></li>
            <li class="breadcrumb-item active text-muted text-truncate" style="max-width: 320px;" aria-current="page"><?= htmlspecialchars($book['title']) ?></li>
        </ol>
    </nav>

    <!-- Main Book Details Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white border">
        <div class="card-body p-3 p-md-4 p-lg-5">
            <div class="row g-4 g-lg-5 align-items-start">
                
                <!-- Left: Cover Image Gallery (Sticky on Desktop) -->
                <div class="col-lg-5">
                    <div class="position-sticky" style="top: 95px;">
                        <div class="p-4 bg-light rounded-4 shadow-sm position-relative mb-3 d-flex align-items-center justify-content-center border" style="min-height: 380px;">
                            <!-- Heart Wishlist Toggle Button -->
                            <button type="button" class="btn-wishlist-toggle" 
                                    data-book-id="<?= $book['id'] ?>" 
                                    data-book-title="<?= htmlspecialchars($book['title']) ?>" 
                                    data-book-price="<?= $book['price'] ?>"
                                    data-book-author="<?= htmlspecialchars($book['author']) ?>"
                                    data-book-cover="<?= $cover_img ?>"
                                    onclick="toggleWishlistItem(this, <?= $book['id'] ?>)" 
                                    title="Add to Wishlist" 
                                    style="top: 15px; left: 15px; width: 42px; height: 42px; font-size: 1.2rem;">
                                <i class="bi bi-heart"></i>
                            </button>

                            <img src="<?= $cover_img ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="img-fluid rounded" style="max-height: 380px; object-fit: contain; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.14));" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                            
                            <!-- Condition Badge with guide trigger -->
                            <span class="position-absolute top-0 end-0 m-3 badge badge-condition <?= $condition_class ?> fs-6 py-2 px-3 shadow-sm" 
                                  style="cursor: pointer;" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#conditionGuideModal" 
                                  title="Click to view condition guide">
                                <?= htmlspecialchars($book['book_condition']) ?>
                            </span>
                        </div>

                        <?php if ($book['status'] === 'sold'): ?>
                            <div class="alert alert-danger fw-bold text-center"><i class="bi bi-x-circle-fill me-1"></i> This book has already been sold!</div>
                        <?php endif; ?>

                        <!-- Quick Action Row Below Image -->
                        <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill py-2" data-bs-toggle="modal" data-bs-target="#shareListingModal">
                                <i class="bi bi-share-fill me-1"></i> Share Listing
                            </button>
                            <button type="button" class="btn btn-outline-dark btn-sm flex-fill py-2" onclick="copyListingLink('<?= htmlspecialchars($current_full_url) ?>')">
                                <i class="bi bi-link-45deg me-1"></i> Copy Link
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right: Information & Actions -->
                <div class="col-lg-7">
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <a href="index.php?category=<?= urlencode($book['category_slug']) ?>" class="badge bg-teal-light text-teal text-decoration-none px-3 py-2 fw-bold">
                            <?= htmlspecialchars($book['category_name']) ?>
                        </a>
                        <span class="text-muted small">• Listed <?= date('M d, Y', strtotime($book['created_at'])) ?></span>
                        <span class="badge bg-light text-success border"><i class="bi bi-shield-check me-1"></i> Genuine Book</span>
                    </div>

                    <h1 class="h2 fw-bold mb-2 text-navy font-serif-title"><?= htmlspecialchars($book['title']) ?></h1>
                    <p class="fs-5 text-muted mb-4">By <strong class="text-navy"><?= htmlspecialchars($book['author']) ?></strong></p>

                    <!-- Price Card -->
                    <div class="p-3 p-md-4 bg-light rounded-4 mb-4 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 border">
                        <div>
                            <div class="small text-muted text-uppercase fw-bold">Marketplace Price</div>
                            <div class="display-6 fw-extrabold text-navy">Rs. <?= number_format($book['price'], 2) ?></div>
                        </div>
                        <div class="text-sm-end">
                            <span class="badge bg-teal py-2 px-3 mb-1 d-inline-block"><i class="bi bi-cash-coin me-1"></i> Cash on Delivery Available</span>
                            <div class="small text-muted" style="font-size: 0.75rem;">1 copy available (First come, first served)</div>
                        </div>
                    </div>

                    <!-- Book Description -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-navy mb-0"><i class="bi bi-file-text me-1 text-teal"></i> Seller's Condition Notes</h5>
                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#conditionGuideModal" class="small text-teal fw-semibold text-decoration-none">Grading Guide &rarr;</a>
                        </div>
                        <div class="p-3 bg-light rounded-3 text-secondary lh-base border">
                            <?= nl2br(htmlspecialchars($book['description'] ?: 'No additional notes provided by the seller. Listing is in ' . $book['book_condition'] . ' condition.')) ?>
                        </div>
                    </div>

                    <!-- Seller Trust Card -->
                    <div class="card border-0 bg-light p-3 rounded-4 mb-4 border">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="seller-avatar <?= !empty($book['seller_is_vip']) ? 'bg-gold text-dark' : '' ?>">
                                    <?= strtoupper(substr($book['seller_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="fw-bold fs-6 text-navy"><?= htmlspecialchars($book['seller_name']) ?></span>
                                        <?php if (!empty($book['seller_is_vip'])): ?>
                                            <span class="badge bg-gold text-dark" style="font-size: 0.68rem;" title="Verified Booksy VIP Seller">
                                                <i class="bi bi-gem"></i> VIP
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-shield-check text-teal me-1"></i> Member since <?= date('M Y', strtotime($book['seller_joined'] ?? 'now')) ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($book['seller_phone'])): ?>
                                <div class="d-flex gap-2">
                                    <a href="https://wa.me/94<?= ltrim($book['seller_phone'], '0') ?>?text=<?= urlencode('Hi, I am interested in your book "'.$book['title'].'" listed on Booksy for Rs. '.number_format($book['price'], 2).'. Is it still available?') ?>" target="_blank" class="btn btn-whatsapp">
                                        <i class="bi bi-whatsapp"></i> Chat WhatsApp
                                    </a>
                                    <a href="tel:<?= htmlspecialchars($book['seller_phone']) ?>" class="btn btn-outline-dark" title="Call Seller">
                                        <i class="bi bi-telephone-fill"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Report Seller / Item Trigger -->
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2.5 border-top" style="border-top: 1px dashed rgba(0,0,0,0.1) !important;">
                            <span class="small text-muted"><i class="bi bi-shield-lock-fill text-teal me-1"></i> Booksy Trust & Community Safety</span>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 fw-semibold" data-bs-toggle="modal" data-bs-target="#reportModal">
                                <i class="bi bi-flag-fill me-1"></i> Report Seller / Item
                            </button>
                        </div>
                    </div>

                    <!-- Sri Lanka Delivery Calculator -->
                    <div class="card border-0 bg-light p-3 rounded-4 mb-4 border">
                        <h6 class="fw-bold text-navy mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Check Delivery to Your District</h6>
                        <div class="row g-2 align-items-center">
                            <div class="col-sm-7">
                                <select class="form-select form-select-sm" id="deliveryDistrictSelect" onchange="calculateDeliveryEstimate(this.value)">
                                    <option value="Colombo">Colombo District</option>
                                    <option value="Gampaha">Gampaha District</option>
                                    <option value="Kalutara">Kalutara District</option>
                                    <option value="Kandy">Kandy District</option>
                                    <option value="Galle">Galle District</option>
                                    <option value="Matara">Matara District</option>
                                    <option value="Kurunegala">Kurunegala District</option>
                                    <option value="Other">Other Provinces</option>
                                </select>
                            </div>
                            <div class="col-sm-5 text-sm-end">
                                <span class="badge bg-success py-1.5 px-2">Free Delivery Promo</span>
                            </div>
                        </div>
                        <div id="deliveryEstimateResult">
                            <div class="card border-0 bg-white p-3 rounded-3 shadow-sm border mt-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-muted"><i class="bi bi-truck text-teal me-1"></i> Delivery to <strong>Colombo</strong>:</span>
                                    <span class="badge bg-success">FREE (Promo)</span>
                                </div>
                                <div class="fw-bold text-navy small mb-1"><i class="bi bi-clock-history me-1 text-teal"></i> Estimated Delivery: 1 - 2 Business Days</div>
                                <div class="small text-muted" style="font-size: 0.75rem;">Shipped via Direct City Express with Cash on Delivery tracking.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Purchase Actions -->
                    <?php if ($book['status'] === 'available'): ?>
                        <div class="d-flex flex-column gap-2.5">
                            <form method="POST" action="cart.php" class="w-100">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                <input type="hidden" name="title" value="<?= htmlspecialchars($book['title']) ?>">
                                <input type="hidden" name="price" value="<?= $book['price'] ?>">
                                <input type="hidden" name="image_url" value="<?= htmlspecialchars($book['image_url'] ?? '') ?>">
                                
                                <div class="d-flex align-items-center justify-content-between mb-3 p-2.5 bg-light rounded-3 border">
                                    <label for="productDetailQty" class="fw-semibold text-navy small mb-0 d-flex align-items-center gap-1">
                                        <i class="bi bi-stack text-teal"></i> Select Quantity:
                                    </label>
                                    <div class="cart-qty-stepper bg-white">
                                        <button type="button" class="btn-qty-step" onclick="stepDetailQty(-1)" aria-label="Decrease quantity" title="Decrease quantity">
                                            <i class="bi bi-dash-lg"></i>
                                        </button>
                                        <input type="number" name="qty" id="productDetailQty" class="cart-qty-input" value="1" min="1" max="99" aria-label="Quantity">
                                        <button type="button" class="btn-qty-step" onclick="stepDetailQty(1)" aria-label="Increase quantity" title="Increase quantity">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-booksy-primary btn-lg w-100 fw-bold py-3 shadow">
                                    <i class="bi bi-cart-plus-fill me-2"></i> Add to Cart
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-lg w-100 py-3 disabled">Item Sold Out</button>
                    <?php endif; ?>

                    <!-- Trust Guarantees Strip -->
                    <div class="row g-2 text-center small text-secondary mt-3 pt-3 border-top">
                        <div class="col-4">
                            <i class="bi bi-shield-check text-success fs-5 d-block mb-1"></i>
                            <strong>Verified Seller</strong>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-arrow-repeat text-teal fs-5 d-block mb-1"></i>
                            <strong>Easy Inspection</strong>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-cash-stack text-gold fs-5 d-block mb-1"></i>
                            <strong>Pay at Doorstep</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Books from Same Category -->
    <?php if (count($related_books) > 0): ?>
        <div class="mb-5">
            <h4 class="fw-bold mb-3"><i class="bi bi-bookmark-star-fill text-teal me-2"></i>More in <?= htmlspecialchars($book['category_name']) ?></h4>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                <?php foreach ($related_books as $rbook): ?>
                    <?php
                    $r_cover = !empty($rbook['image_url']) && file_exists(__DIR__ . '/uploads/' . $rbook['image_url']) 
                        ? 'uploads/' . htmlspecialchars($rbook['image_url']) 
                        : 'uploads/default_book.svg';
                    ?>
                    <div class="col">
                        <div class="card book-card h-100">
                            <div class="book-card-cover-wrap position-relative" style="height: 180px;">
                                <a href="book-details.php?id=<?= $rbook['id'] ?>">
                                    <img src="<?= $r_cover ?>" alt="<?= htmlspecialchars($rbook['title']) ?>" loading="lazy" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                                </a>
                            </div>
                            <div class="card-body p-3 d-flex flex-column">
                                <h6 class="fw-bold text-truncate mb-1">
                                    <a href="book-details.php?id=<?= $rbook['id'] ?>" class="text-navy text-decoration-none"><?= htmlspecialchars($rbook['title']) ?></a>
                                </h6>
                                <p class="small text-muted mb-2">By <?= htmlspecialchars($rbook['author']) ?></p>
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-navy">Rs. <?= number_format($rbook['price'], 2) ?></span>
                                    <a href="book-details.php?id=<?= $rbook['id'] ?>" class="btn btn-sm btn-booksy-outline py-1 px-2.5">View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Social Share Modal -->
<div class="modal fade" id="shareListingModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white py-3 px-4">
                <h6 class="modal-title fw-bold" id="shareModalLabel">
                    <i class="bi bi-share-fill text-teal me-2"></i> Share This Book
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex flex-column gap-2">
                    <a href="https://wa.me/?text=<?= urlencode($share_message . ' - ' . $current_full_url) ?>" target="_blank" class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2">
                        <i class="bi bi-whatsapp"></i> Share on WhatsApp
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($current_full_url) ?>" target="_blank" class="btn btn-primary d-flex align-items-center justify-content-center gap-2 py-2">
                        <i class="bi bi-facebook"></i> Share on Facebook
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=<?= urlencode($share_message) ?>&url=<?= urlencode($current_full_url) ?>" target="_blank" class="btn btn-dark d-flex align-items-center justify-content-center gap-2 py-2">
                        <i class="bi bi-twitter-x"></i> Share on X
                    </a>
                    <button type="button" class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2 py-2" onclick="copyListingLink('<?= htmlspecialchars($current_full_url) ?>')">
                        <i class="bi bi-link-45deg"></i> Copy Link
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Buyer Report Listing / Seller Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 py-3" style="border-bottom: 2px solid var(--teal-primary);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-flag-fill text-danger fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="reportModalLabel">Report Listing or Seller</h5>
                        <small class="text-white-50">Booksy Trust & Safety Community Protection</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <?php if (!is_logged_in()): ?>
                <div class="modal-body p-4 text-center bg-white">
                    <i class="bi bi-shield-lock text-warning mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-navy mb-2">Sign In Required</h5>
                    <p class="text-muted mb-4">Please log in to your Booksy account to report fraudulent activity or policy violations.</p>
                    <a href="login.php?redirect=<?= urlencode('book-details.php?id=' . $book['id']) ?>" class="btn btn-booksy-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Report
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="api/submit-report.php" enctype="multipart/form-data">
                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                    <input type="hidden" name="reported_user_id" value="<?= $book['seller_id'] ?>">
                    <input type="hidden" name="redirect" value="../book-details.php?id=<?= $book['id'] ?>">

                    <div class="modal-body p-4 bg-white">
                        <!-- Target Item Card -->
                        <div class="p-3 bg-light rounded-3 border mb-3 d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= $cover_img ?>" alt="Cover" class="rounded border" style="width: 45px; height: 60px; object-fit: cover;">
                                <div>
                                    <div class="fw-bold text-navy line-clamp-1"><?= htmlspecialchars($book['title']) ?></div>
                                    <small class="text-muted">Seller: <strong><?= htmlspecialchars($book['seller_name']) ?></strong> (Rs. <?= number_format($book['price'], 2) ?>)</small>
                                </div>
                            </div>
                            <span class="badge bg-secondary rounded-pill px-2.5 py-1">Case Target</span>
                        </div>

                        <!-- Categorized Reason Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-navy">Select Reason for Report <span class="text-danger">*</span></label>
                            <select name="reason" class="form-select" required>
                                <option value="">-- Choose specific violation reason --</option>
                                <option value="off_platform_payment">Off-Platform Payment Solicitation (Asking for WhatsApp, direct wire, CashApp, crypto)</option>
                                <option value="counterfeit">Counterfeit / Replica Goods or Unauthorized Photocopy</option>
                                <option value="not_received">Item Not Received / Fake Courier Tracking Number</option>
                                <option value="misleading">Misleading Description or Bait-and-Switch (Damaged, wrong edition, fake photos)</option>
                                <option value="fake_profile">Identity Theft or Fake / Compromised Seller Profile</option>
                                <option value="inappropriate">Inappropriate, Offensive, or Prohibited Content</option>
                                <option value="spam">Spam, Harassment, or Commercial Solicitation</option>
                                <option value="other">Other Fraudulent / Suspicious Activity</option>
                            </select>
                        </div>

                        <!-- Associated Order ID -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-navy">Associated Order ID (If you made a purchase)</label>
                            <input type="number" name="order_id" class="form-control" placeholder="e.g. 1045 (Optional)">
                            <small class="text-muted">Providing your order number helps expedite evidence verification.</small>
                        </div>

                        <!-- Explanation Details -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-navy">Detailed Explanation <span class="text-danger">*</span></label>
                            <textarea name="details" class="form-control" rows="3" placeholder="Please describe what happened in detail (minimum 10 characters)..." minlength="10" required></textarea>
                        </div>

                        <!-- Evidence File Uploads -->
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
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
