<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Sign In';
$redirect = $_GET['redirect'] ?? 'index.php';

// If already logged in, redirect
if (is_logged_in()) {
    header("Location: $redirect");
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? 'index.php';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_role'] = $user['role'];

            set_flash('success', "Welcome back, <strong>" . htmlspecialchars($user['name']) . "</strong>!");
            header("Location: " . (!empty($redirect) ? $redirect : 'index.php'));
            exit;
        } else {
            $error = 'Invalid email or password. Please check your credentials and try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-page-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5 col-xl-4" style="max-width: 480px;">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden auth-card-glass">
                    <div class="bg-white text-center p-4" style="border-bottom: 2px solid var(--border-light);">
                        <img src="images/booksy-logo-horizontal.svg" alt="Booksy" height="44" class="mb-2">
                        <h4 class="fw-bold mb-1 text-navy font-serif-title">Welcome to Booksy</h4>
                        <p class="small text-muted mb-0">Sign in to manage your book listings and orders</p>
                    </div>
                
                    <div class="card-body p-4 p-md-4 p-lg-5">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 rounded-3" role="alert">
                                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                                <div class="small"><?= htmlspecialchars($error) ?></div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php" novalidate>
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($email) ?>" required autofocus>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label fw-semibold small">Password</label>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock text-muted"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                                    <button class="btn btn-outline-secondary btn-toggle-password" type="button"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-booksy-primary w-100 py-2.5 fw-bold fs-6 mb-3">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Account
                            </button>
                        </form>

                        <div class="text-center small text-muted mb-0">
                            Don't have an account yet? <a href="register.php<?= !empty($redirect) ? '?redirect='.urlencode($redirect) : '' ?>" class="text-teal fw-bold">Create Account</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
