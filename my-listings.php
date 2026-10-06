<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'My Listed Books';

// Require login
if (!is_logged_in()) {
    set_flash('warning', 'Please sign in to manage your book listings.');
    header("Location: login.php?redirect=my-listings.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Filter by status if specified
$status_filter = trim($_GET['status'] ?? 'all');

// Fetch user's listings with active boost information
$sql = "
    SELECT b.*, c.name AS category_name,
           MAX(pl.promotion_type) AS active_boost_type,
           MAX(pl.end_date) AS active_boost_end
    FROM books b 
    JOIN categories c ON b.category_id = c.id 
    LEFT JOIN promotional_listings pl ON b.id = pl.book_id AND pl.status = 'active' AND pl.end_date >= NOW()
    WHERE b.seller_id = ?
";
$params = [$user_id];

if ($status_filter === 'available' || $status_filter === 'sold') {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
}

$sql .= " GROUP BY b.id, b.seller_id, b.category_id, b.title, b.author, b.price, b.book_condition, b.image_url, b.description, b.status, b.created_at, c.name ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$my_books = $stmt->fetchAll();

// All listings for stats
$all_stmt = $pdo->prepare("SELECT status, price FROM books WHERE seller_id = ?");
$all_stmt->execute([$user_id]);
$all_books = $all_stmt->fetchAll();

// Statistics
$total_listings = count($all_books);
$active_listings = count(array_filter($all_books, fn($b) => $b['status'] === 'available'));
$sold_listings = count(array_filter($all_books, fn($b) => $b['status'] === 'sold'));
$total_earnings = array_sum(array_column(array_filter($all_books, fn($b) => $b['status'] === 'sold'), 'price'));

include 'includes/header.php';
?>

<!-- My Listings Header Banner -->
<div class="hero-banner-subpage mb-4">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-teal text-white fw-bold px-3 py-1 mb-2 d-inline-flex align-items-center rounded-pill">
                    <i class="bi bi-person-badge-fill me-1"></i> Seller Control Center
                </span>
                <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">My Listed Books</h1>
                <p class="text-white-50 mb-0">Manage your active and sold book listings, view promotion status, and track earnings</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="seller-earnings.php" class="btn btn-outline-light fw-bold px-3 py-2.5 rounded-pill shadow-sm">
                    <i class="bi bi-wallet2 me-1 text-gold"></i> Earnings & 0% Fee Hub
                </a>
                <a href="sell.php" class="btn btn-booksy-primary fw-bold px-4 py-2.5 shadow rounded-pill">
                    <i class="bi bi-plus-circle-fill me-1"></i> Post Another Book
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container py-3 py-lg-4">
    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="my-listings.php?status=all" class="card border-0 shadow-sm rounded-4 p-3 bg-white border text-decoration-none <?= $status_filter === 'all' ? 'border-primary' : '' ?>">
                <div class="text-muted small fw-semibold">Total Listings</div>
                <div class="display-6 fw-bold text-navy"><?= $total_listings ?></div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="my-listings.php?status=available" class="card border-0 shadow-sm rounded-4 p-3 bg-white border text-decoration-none <?= $status_filter === 'available' ? 'border-success' : '' ?>">
                <div class="text-muted small fw-semibold">Active in Catalog</div>
                <div class="display-6 fw-bold text-success"><?= $active_listings ?></div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="my-listings.php?status=sold" class="card border-0 shadow-sm rounded-4 p-3 bg-white border text-decoration-none <?= $status_filter === 'sold' ? 'border-secondary' : '' ?>">
                <div class="text-muted small fw-semibold">Sold Books</div>
                <div class="display-6 fw-bold text-navy"><?= $sold_listings ?></div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="seller-earnings.php" class="card border-0 shadow-sm rounded-4 p-3 bg-white border text-decoration-none">
                <div class="text-muted small fw-semibold">Completed Sales</div>
                <div class="fs-4 fw-bold text-teal mt-2">Rs. <?= number_format($total_earnings, 2) ?></div>
            </a>
        </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="btn-group btn-group-sm">
            <a href="my-listings.php?status=all" class="btn <?= $status_filter === 'all' ? 'btn-navy' : 'btn-outline-secondary' ?>">
                All (<?= $total_listings ?>)
            </a>
            <a href="my-listings.php?status=available" class="btn <?= $status_filter === 'available' ? 'btn-navy' : 'btn-outline-secondary' ?>">
                Active Available (<?= $active_listings ?>)
            </a>
            <a href="my-listings.php?status=sold" class="btn <?= $status_filter === 'sold' ? 'btn-navy' : 'btn-outline-secondary' ?>">
                Sold Out (<?= $sold_listings ?>)
            </a>
        </div>
        <span class="small text-muted"><i class="bi bi-info-circle text-teal me-1"></i> Tip: Use the <strong>Boost</strong> button to promote listings for faster sales.</span>
    </div>

    <!-- Listings Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white border">
        <div class="card-body p-0">
            <?php if (empty($my_books)): ?>
                <div class="p-5 text-center">
                    <i class="bi bi-book-half text-muted mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold text-navy">No listings found in this filter</h5>
                    <p class="text-muted small mb-3">Turn your pre-loved books and textbooks into cash by listing them today.</p>
                    <a href="sell.php" class="btn btn-booksy-primary fw-bold">Post a New Book</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Book Details</th>
                                <th>Category</th>
                                <th>Condition</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Promotion</th>
                                <th>Quick Toggle</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_books as $book): ?>
                                <?php
                                $thumb = !empty($book['image_url']) && file_exists(__DIR__ . '/uploads/' . $book['image_url']) 
                                    ? 'uploads/' . htmlspecialchars($book['image_url']) 
                                    : 'uploads/default_book.svg';
                                
                                $is_available = ($book['status'] === 'available');
                                $is_boosted   = !empty($book['active_boost_type']);
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= $thumb ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="rounded" style="width: 45px; height: 60px; object-fit: contain; background: #17324D;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                                            <div>
                                                <a href="book-details.php?id=<?= $book['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= htmlspecialchars($book['title']) ?>
                                                </a>
                                                <div class="small text-muted">By <?= htmlspecialchars($book['author']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($book['category_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($book['book_condition']) ?></span>
                                    </td>
                                    <td class="fw-bold text-teal">
                                        Rs. <?= number_format($book['price'], 2) ?>
                                    </td>
                                    <td>
                                        <span id="statusBadge_<?= $book['id'] ?>" class="badge <?= $is_available ? 'bg-success' : 'bg-secondary' ?>">
                                            <i class="bi <?= $is_available ? 'bi-check-circle' : 'bi-bag-check' ?> me-1"></i>
                                            <?= ucfirst($book['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($is_boosted): ?>
                                            <span class="badge bg-teal" title="Active until <?= date('M d', strtotime($book['active_boost_end'])) ?>">
                                                <i class="bi bi-lightning-fill text-gold me-1"></i>
                                                <?= ucwords(str_replace('_', ' ', $book['active_boost_type'])) ?>
                                            </span>
                                        <?php elseif ($is_available): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning py-1 px-2 text-dark fw-semibold" onclick="openBoostModalForBook(<?= $book['id'] ?>, '<?= htmlspecialchars(addslashes($book['title'])) ?>')">
                                                <i class="bi bi-rocket-takeoff-fill text-gold me-1"></i> Boost
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm <?= $is_available ? 'btn-outline-warning' : 'btn-outline-success' ?> py-1 px-2" onclick="toggleListingStatus(this, <?= $book['id'] ?>)">
                                            <?= $is_available ? '<i class="bi bi-bag-check me-1"></i> Mark Sold' : '<i class="bi bi-arrow-clockwise me-1"></i> Re-list' ?>
                                        </button>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <a href="book-details.php?id=<?= $book['id'] ?>" class="btn btn-outline-secondary" title="View Public Page">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary" title="Copy Share Link" onclick="copyListingLink('<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/book-details.php?id=" . $book['id'] ?>')">
                                                <i class="bi bi-share"></i>
                                            </button>
                                            <a href="edit-book.php?id=<?= $book['id'] ?>" class="btn btn-outline-primary" title="Edit Listing">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete-book.php?id=<?= $book['id'] ?>" class="btn btn-outline-danger" title="Delete Listing" onclick="return confirm('Are you sure you want to delete this book listing?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
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

<!-- Modal: Boost a Specific Book -->
<div class="modal fade" id="quickBoostModal" tabindex="-1" aria-labelledby="quickBoostLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white py-3 px-4" style="border-bottom: 2px solid var(--teal-primary);">
                <h5 class="modal-title fw-bold" id="quickBoostLabel">
                    <i class="bi bi-lightning-charge-fill text-gold me-2"></i> Boost Listing
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <form id="quickBoostForm" onsubmit="submitQuickBoost(event)">
                    <input type="hidden" name="book_id" id="quickBoostBookId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy text-uppercase mb-1">Target Book</label>
                        <div class="p-2.5 bg-light rounded-3 fw-bold text-navy border" id="quickBoostBookTitle">
                            Select Book
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-navy text-uppercase mb-2">Select Promotion Tier</label>
                        <div class="d-flex flex-column gap-2">
                            <label class="payment-method-card selected d-flex align-items-center gap-3">
                                <input type="radio" name="boost_type" value="featured_listing" checked class="form-check-input mt-0">
                                <div class="flex-fill">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong class="text-navy small"><i class="bi bi-star-fill text-gold me-1"></i> Featured Listing</strong>
                                        <span class="badge bg-teal">LKR 150.00</span>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem;">7 Days • Distinctive border & "Featured" badge</div>
                                </div>
                            </label>

                            <label class="payment-method-card d-flex align-items-center gap-3">
                                <input type="radio" name="boost_type" value="homepage_boost" class="form-check-input mt-0">
                                <div class="flex-fill">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong class="text-navy small"><i class="bi bi-house-door-fill text-teal me-1"></i> Homepage Boost</strong>
                                        <span class="badge bg-teal">LKR 350.00</span>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem;">3 Days • Featured on Homepage Carousel</div>
                                </div>
                            </label>

                            <label class="payment-method-card d-flex align-items-center gap-3">
                                <input type="radio" name="boost_type" value="category_boost" class="form-check-input mt-0">
                                <div class="flex-fill">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong class="text-navy small"><i class="bi bi-tag-fill text-primary me-1"></i> Category Leader</strong>
                                        <span class="badge bg-teal">LKR 250.00</span>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem;">7 Days • Top row in category archives</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="quickBoostAlert" class="alert alert-danger d-none small"></div>

                    <button type="submit" class="btn btn-booksy-primary btn-lg w-100 fw-bold py-2.5 shadow" id="btnSubmitQuickBoost">
                        <i class="bi bi-check-circle-fill me-1"></i> Activate Boost
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openBoostModalForBook(bookId, bookTitle) {
    document.getElementById('quickBoostBookId').value = bookId;
    document.getElementById('quickBoostBookTitle').textContent = bookTitle;
    document.getElementById('quickBoostAlert').classList.add('d-none');
    const modal = new bootstrap.Modal(document.getElementById('quickBoostModal'));
    modal.show();
}

function submitQuickBoost(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitQuickBoost');
    const alertBox = document.getElementById('quickBoostAlert');
    const bookId = document.getElementById('quickBoostBookId').value;
    const boostType = document.querySelector('#quickBoostForm input[name="boost_type"]:checked').value;

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
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Activate Boost';
        }
    })
    .catch(err => {
        alertBox.textContent = 'Connection error. Please try again.';
        alertBox.classList.remove('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Activate Boost';
    });
}
</script>

<?php include 'includes/footer.php'; ?>
