<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Create Account';
$redirect = $_GET['redirect'] ?? 'index.php';

if (is_logged_in()) {
    header("Location: $redirect");
    exit;
}

$error = '';
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $redirect = $_POST['redirect'] ?? 'index.php';

    // Form Validations
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match. Please re-type your password.';
    } else {
        // Check if email already registered
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address already exists. Please sign in instead.';
        } else {
            // Hash password and insert
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insert_stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'user')");
            if ($insert_stmt->execute([$name, $email, $hash, $phone])) {
                $new_id = $pdo->lastInsertId();

                // Start Session
                $_SESSION['user_id'] = $new_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;
                $_SESSION['user_role'] = 'user';

                set_flash('success', "Welcome to Booksy, <strong>" . htmlspecialchars($name) . "</strong>! Your account has been created.");
                header("Location: " . (!empty($redirect) ? $redirect : 'index.php'));
                exit;
            } else {
                $error = 'Account registration failed. Please try again.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-page-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5" style="max-width: 540px;">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden auth-card-glass">
                    <div class="bg-white text-center p-4" style="border-bottom: 2px solid var(--border-light);">
                        <img src="images/booksy-logo-horizontal.svg" alt="Booksy" height="44" class="mb-2">
                        <h4 class="fw-bold mb-1 text-navy font-serif-title">Create Your Booksy Account</h4>
                        <p class="small text-muted mb-0">Join our community to buy, sell, and resell books across Sri Lanka</p>
                    </div>
                
                    <div class="card-body p-4 p-md-4 p-lg-5">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 rounded-3" role="alert">
                                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                                <div class="small"><?= htmlspecialchars($error) ?></div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="register.php" novalidate>
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Full Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                                    <input type="text" name="name" class="form-control" placeholder="e.g. Kasun Fernando" value="<?= htmlspecialchars($name) ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($email) ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Phone / WhatsApp Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-telephone text-muted"></i></span>
                                    <input type="tel" name="phone" class="form-control" placeholder="0771234567" value="<?= htmlspecialchars($phone) ?>">
                                </div>
                                <div class="form-text small text-muted">Used for courier delivery and WhatsApp buyer chats.</div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Password *</label>
                                    <div class="input-group">
                                        <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                                        <button class="btn btn-outline-secondary btn-toggle-password" type="button"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Confirm Password *</label>
                                    <div class="input-group">
                                        <input type="password" name="password_confirm" class="form-control" placeholder="Re-type password" required>
                                        <button class="btn btn-outline-secondary btn-toggle-password" type="button"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="termsCheck" required checked>
                                <label class="form-check-label small text-muted" for="termsCheck">
                                    I agree to the Booksy <a href="#" class="text-teal text-decoration-none">Terms of Marketplace Service</a> and <a href="#" class="text-teal text-decoration-none">Privacy Policy</a>.
                                </label>
                            </div>

                            <button type="submit" class="btn btn-booksy-primary w-100 py-2.5 fw-bold fs-6 mb-3">
                                <i class="bi bi-person-check-fill me-1"></i> Register Free Account
                            </button>
                        </form>

                        <div class="text-center small text-muted">
                            Already have a Booksy account? <a href="login.php<?= !empty($redirect) ? '?redirect='.urlencode($redirect) : '' ?>" class="text-teal fw-bold">Sign In</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
