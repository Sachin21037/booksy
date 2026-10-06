<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Post a Book for Sale';

// Require login for posting books
if (!is_logged_in()) {
    set_flash('warning', 'Please sign in to list a book for sale on the Booksy marketplace.');
    header("Location: login.php?redirect=sell.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$error = '';
$seller_id = $_SESSION['user_id'];

// Form values for preserving inputs upon error
$title = '';
$author = '';
$category_id = '';
$price = '';
$condition = 'Good';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $condition = $_POST['condition'] ?? 'Good';
    $description = trim($_POST['description'] ?? '');
    
    // Validate inputs
    if (empty($title) || empty($author) || $category_id <= 0 || $price <= 0) {
        $error = 'Please fill in the title, author, category, and a valid selling price.';
    } else {
        // Image Upload Validation & Processing (Mandatory)
        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE || empty($_FILES['image']['tmp_name'])) {
            $error = 'Please upload a clear book cover photo. Listings without photos are not permitted on Booksy.';
        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'There was an error uploading your photo. Please try again with a valid JPG, PNG, or WebP image.';
        } else {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            
            $file_tmp = $_FILES['image']['tmp_name'];
            $file_name = $_FILES['image']['name'];
            $file_size = $_FILES['image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            if (!in_array($file_ext, $allowed_exts) || !in_array($mime, $allowed_mimes)) {
                $error = 'Invalid image file format. Only JPG, PNG, and WebP photos are allowed.';
            } elseif ($file_size > 5 * 1024 * 1024) {
                $error = 'Image file size cannot exceed 5MB.';
            } else {
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $image_name = uniqid('book_') . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                if (!move_uploaded_file($file_tmp, $upload_dir . $image_name)) {
                    $error = 'Failed to save uploaded image. Please try again.';
                }
            }
        }

        if (empty($error)) {
            $stmt = $pdo->prepare("
                INSERT INTO books (seller_id, category_id, title, author, price, book_condition, image_url, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')
            ");
            if ($stmt->execute([$seller_id, $category_id, $title, $author, $price, $condition, $image_name, $description])) {
                $new_book_id = $pdo->lastInsertId();
                set_flash('success', 'Your book "<strong>' . htmlspecialchars($title) . '</strong>" has been listed successfully!');
                header("Location: book-details.php?id=" . $new_book_id);
                exit;
            } else {
                $error = 'Database error while saving book listing. Please try again.';
            }
        }
    }
}

include 'includes/header.php';
?>

<!-- Sell Header Banner -->
<div class="hero-banner-subpage mb-4">
    <div class="container text-center">
        <span class="badge bg-teal text-white fw-bold px-3 py-1 mb-2 d-inline-flex align-items-center rounded-pill">
            <i class="bi bi-tag-fill me-1"></i> C2C Book Reseller Hub
        </span>
        <h1 class="display-6 fw-bold text-white mb-2 font-serif-title">List a Book for Sale</h1>
        <p class="text-white-50 mb-0 mx-auto" style="max-width: 600px;">
            Turn your pre-loved textbooks, novels, and rare collectibles into cash. Reach thousands of eager readers and students across Sri Lanka.
        </p>
    </div>
</div>

<div class="container py-3 py-lg-4">
    <div class="row g-4 justify-content-center">
        <!-- Listing Form (Left Column) -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white border">
                <div class="d-flex align-items-center mb-3">
                    <div class="seller-avatar me-3" style="width: 44px; height: 44px; font-size: 1.1rem;">
                        <i class="bi bi-pencil-square text-white"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0 text-navy font-serif-title">Book Information</h3>
                        <p class="text-muted small mb-0">Fill in the book details accurately for maximum buyer interest</p>
                    </div>
                </div>

                <hr class="my-4">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="sell.php" enctype="multipart/form-data" novalidate>
                    <!-- Book Title -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy text-uppercase">Book Title *</label>
                        <input type="text" name="title" id="listingTitle" class="form-control form-control-lg" placeholder="e.g., Introduction to Algorithms (CLRS)" value="<?= htmlspecialchars($title) ?>" required>
                    </div>

                    <!-- Author & Category -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy text-uppercase">Author(s) *</label>
                            <input type="text" name="author" id="listingAuthor" class="form-control" placeholder="e.g., Thomas H. Cormen" value="<?= htmlspecialchars($author) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy text-uppercase">Category *</label>
                            <select name="category_id" id="listingCategory" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Price & Condition -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy text-uppercase">Selling Price (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-teal">Rs.</span>
                                <input type="number" step="0.01" min="1" name="price" id="listingPrice" class="form-control" placeholder="1500.00" value="<?= htmlspecialchars($price) ?>" required>
                            </div>
                            <div class="form-text text-success small" style="font-size: 0.72rem;">
                                <i class="bi bi-shield-check me-1"></i> <strong>0% fee</strong> under LKR 5,000 monthly sales (Only 3% after).
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold small text-navy text-uppercase mb-0">Book Condition *</label>
                                <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#conditionGuideModal" class="small text-teal text-decoration-none" style="font-size: 0.72rem;">Grading Guide?</a>
                            </div>
                            <select name="condition" id="listingCondition" class="form-select" required>
                                <option value="Brand New" <?= $condition === 'Brand New' ? 'selected' : '' ?>>Brand New (Unopened / Sealed)</option>
                                <option value="Like New" <?= $condition === 'Like New' ? 'selected' : '' ?>>Like New (No notes, crisp)</option>
                                <option value="Good" <?= $condition === 'Good' ? 'selected' : '' ?>>Good (Clean, minor wear)</option>
                                <option value="Fair" <?= $condition === 'Fair' ? 'selected' : '' ?>>Fair (Highlights / page bends)</option>
                                <option value="Poor" <?= $condition === 'Poor' ? 'selected' : '' ?>>Poor (Heavy wear, readable)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Drag & Drop Cover Photo Upload -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold small text-navy text-uppercase mb-0">Book Cover Photo <span class="text-danger">*</span></label>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle small" style="font-size: 0.7rem;">Required</span>
                        </div>
                        <div class="drag-drop-zone" id="dragDropZone">
                            <input type="file" name="image" id="listingImage" accept="image/jpeg,image/png,image/webp" required>
                            <i class="bi bi-cloud-arrow-up-fill drag-drop-icon"></i>
                            <div class="fw-bold text-navy mb-1" id="dropZoneText">Drag & drop cover photo here, or browse file</div>
                            <div class="small text-muted">Supports JPG, PNG, WebP (Max 5MB) • Required for listing</div>
                        </div>
                    </div>

                    <!-- Description & Condition Notes -->
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-navy text-uppercase">Description & Condition Notes</label>
                        <textarea name="description" rows="4" class="form-control" placeholder="Specify details such as edition, whether any pages have markings or highlights, binding condition, etc..."><?= htmlspecialchars($description) ?></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-booksy-primary btn-lg w-100 fw-bold py-3 shadow">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Publish Book Listing
                    </button>
                </form>
            </div>
        </div>

        <!-- Live Preview Card (Right Column) -->
        <div class="col-lg-5">
            <div class="sticky-top" style="top: 95px;">
                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-3 bg-white border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold small text-navy text-uppercase"><i class="bi bi-eye-fill text-gold me-1"></i> Live Card Preview</span>
                        <span class="badge bg-teal-light text-teal border">Real-Time</span>
                    </div>
                    <p class="small text-muted mb-3">This is how your book will appear to prospective buyers on the Booksy marketplace:</p>

                    <!-- Preview Book Card -->
                    <div class="card book-card mx-auto shadow" style="max-width: 320px;">
                        <div class="book-card-cover-wrap position-relative">
                            <img id="previewImage" src="uploads/default_book.svg" alt="Preview Cover" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                            <span id="previewCondition" class="position-absolute top-0 end-0 m-2 badge badge-condition badge-good">Good</span>
                        </div>
                        <div class="book-card-body">
                            <span id="previewCategory" class="badge bg-light text-navy mb-1 align-self-start border">Category</span>
                            <h6 id="previewTitle" class="book-card-title text-navy">Book Title</h6>
                            <p id="previewAuthor" class="book-card-author">By Author Name</p>
                            <div class="d-flex justify-content-between align-items-end mt-auto pt-2 border-top">
                                <div>
                                    <div class="small text-muted" style="font-size: 0.72rem;">Marketplace Price</div>
                                    <div id="previewPrice" class="book-card-price">Rs. 0.00</div>
                                </div>
                                <span class="badge bg-light text-navy border">
                                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'You') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips for Sellers -->
                <div class="card border-0 bg-white shadow-sm rounded-4 p-3 border">
                    <h6 class="fw-bold mb-2 text-navy"><i class="bi bi-lightbulb-fill text-gold me-1"></i> Quick Tips for Fast Sales:</h6>
                    <ul class="small text-muted ps-3 mb-0 d-flex flex-column gap-1.5">
                        <li>Price textbooks 30-50% below new bookstore retail prices.</li>
                        <li>Honestly mention any pen or pencil markings in your description.</li>
                        <li>Keep your WhatsApp notifications active for buyer inquiries.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>