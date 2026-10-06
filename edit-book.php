<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Edit Book Listing';

if (!is_logged_in()) {
    set_flash('warning', 'Please sign in to edit your book listing.');
    header("Location: login.php");
    exit;
}

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// Fetch book and check ownership
$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? LIMIT 1");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book || (!$is_admin && $book['seller_id'] != $user_id)) {
    set_flash('danger', 'You do not have permission to edit this listing.');
    header("Location: my-listings.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $condition = $_POST['condition'] ?? 'Good';
    $status = $_POST['status'] ?? 'available';
    $description = trim($_POST['description'] ?? '');
    
    if (empty($title) || empty($author) || $category_id <= 0 || $price <= 0) {
        $error = 'Please fill in all required fields with valid details.';
    } else {
        $image_name = $book['image_url'];
        
        // Handle optional new image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            $file_tmp = $_FILES['image']['tmp_name'];
            $file_name = $_FILES['image']['name'];
            $file_size = $_FILES['image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_exts) && $file_size <= 5 * 1024 * 1024) {
                $upload_dir = __DIR__ . '/uploads/';
                $image_name = uniqid('book_') . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                move_uploaded_file($file_tmp, $upload_dir . $image_name);
            }
        }

        $update_stmt = $pdo->prepare("
            UPDATE books 
            SET title = ?, author = ?, category_id = ?, price = ?, book_condition = ?, image_url = ?, description = ?, status = ?
            WHERE id = ?
        ");
        if ($update_stmt->execute([$title, $author, $category_id, $price, $condition, $image_name, $description, $status, $book_id])) {
            set_flash('success', 'Book listing updated successfully.');
            header("Location: my-listings.php");
            exit;
        } else {
            $error = 'Failed to update book listing. Please try again.';
        }
    }
}

$current_cover = (!empty($book['image_url']) && file_exists(__DIR__ . '/uploads/' . $book['image_url']))
    ? 'uploads/' . htmlspecialchars($book['image_url'])
    : 'uploads/default_book.svg';

include 'includes/header.php';
?>

<!-- Edit Book Header Banner -->
<div class="hero-banner-subpage mb-4">
    <div class="container text-center">
        <span class="badge bg-teal text-white fw-bold px-3 py-1 mb-2 d-inline-flex align-items-center rounded-pill">
            <i class="bi bi-pencil-square me-1"></i> Listing Management
        </span>
        <h1 class="display-6 fw-bold text-white mb-2 font-serif-title">Edit Book Listing</h1>
        <p class="text-white-50 mb-0 mx-auto" style="max-width: 600px;">
            Update the pricing, description, condition, or cover image of your book listing.
        </p>
    </div>
</div>

<div class="container py-3 py-lg-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white border">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center">
                        <div class="seller-avatar me-3" style="width: 44px; height: 44px; font-size: 1.1rem;">
                            <i class="bi bi-pencil text-white"></i>
                        </div>
                        <div>
                            <h3 class="fw-bold mb-0 text-navy font-serif-title">Update Listing</h3>
                            <p class="text-muted small mb-0">Modify listing details or mark as sold</p>
                        </div>
                    </div>
                    <a href="my-listings.php" class="btn btn-sm btn-booksy-outline">Back to My Listings</a>
                </div>

                <hr class="my-4">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="edit-book.php?id=<?= $book['id'] ?>" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy text-uppercase">Book Title *</label>
                        <input type="text" name="title" class="form-control form-control-lg" value="<?= htmlspecialchars($book['title']) ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy text-uppercase">Author(s) *</label>
                            <input type="text" name="author" class="form-control" value="<?= htmlspecialchars($book['author']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-navy text-uppercase">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $book['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-navy text-uppercase">Price (Rs.) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-teal">Rs.</span>
                                <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($book['price']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold small text-navy text-uppercase mb-0">Condition *</label>
                                <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#conditionGuideModal" class="small text-teal text-decoration-none" style="font-size: 0.72rem;">Guide?</a>
                            </div>
                            <select name="condition" class="form-select" required>
                                <option value="Brand New" <?= $book['book_condition'] === 'Brand New' ? 'selected' : '' ?>>Brand New</option>
                                <option value="Like New" <?= $book['book_condition'] === 'Like New' ? 'selected' : '' ?>>Like New</option>
                                <option value="Good" <?= $book['book_condition'] === 'Good' ? 'selected' : '' ?>>Good</option>
                                <option value="Fair" <?= $book['book_condition'] === 'Fair' ? 'selected' : '' ?>>Fair</option>
                                <option value="Poor" <?= $book['book_condition'] === 'Poor' ? 'selected' : '' ?>>Poor</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-navy text-uppercase">Listing Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="available" <?= $book['status'] === 'available' ? 'selected' : '' ?>>Available (Active)</option>
                                <option value="sold" <?= $book['status'] === 'sold' ? 'selected' : '' ?>>Sold Out</option>
                            </select>
                        </div>
                    </div>

                    <!-- Current Photo & Drag-Drop Replacement -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy text-uppercase">Book Cover Photo</label>
                        <div class="d-flex align-items-center gap-3 mb-2 p-2 bg-light rounded-3 border">
                            <img src="<?= $current_cover ?>" alt="Current cover" class="rounded" style="width: 48px; height: 60px; object-fit: contain; background: #17324D;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                            <div>
                                <div class="small fw-bold text-navy">Current Cover File</div>
                                <div class="small text-muted" style="font-size: 0.75rem;"><code><?= htmlspecialchars($book['image_url'] ?? 'default_book.svg') ?></code></div>
                            </div>
                        </div>

                        <div class="drag-drop-zone" id="dragDropZone">
                            <input type="file" name="image" id="listingImage" accept="image/jpeg,image/png,image/webp">
                            <i class="bi bi-cloud-arrow-up-fill drag-drop-icon"></i>
                            <div class="fw-bold text-navy mb-1" id="dropZoneText">Upload new photo (Optional)</div>
                            <div class="small text-muted">Supports JPG, PNG, WebP (Max 5MB)</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-navy text-uppercase">Description & Seller Notes</label>
                        <textarea name="description" rows="4" class="form-control"><?= htmlspecialchars($book['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-booksy-primary fw-bold py-2.5 px-4 shadow">
                            <i class="bi bi-check-lg me-1"></i> Save Changes
                        </button>
                        <a href="my-listings.php" class="btn btn-light border py-2.5 px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
