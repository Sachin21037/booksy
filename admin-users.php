<?php
/**
 * Booksy - Staff & User Governance Center (Platform Owner Exclusive)
 * Manage platform accounts, create Site Managers (admins), and promote/demote user roles.
 */

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Staff & User Governance - Owner Control';

// Strict Platform Owner Authorization
if (!is_owner()) {
    if (is_admin()) {
        set_flash('danger', 'Access Restricted: Only the Platform Owner has authority to manage Site Managers and administrative credentials.');
        header("Location: admin-monetization.php");
        exit;
    }
    set_flash('danger', 'Platform Owner credentials required to access Staff & User Governance.');
    header("Location: login.php?redirect=admin-users.php");
    exit;
}

// Handle Search & Filtering
$searchQuery = trim($_GET['q'] ?? '');
$roleFilter  = trim($_GET['role'] ?? 'all');

// Build query
$whereClauses = [];
$params = [];

if (!empty($searchQuery)) {
    $whereClauses[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%$searchQuery%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($roleFilter) && in_array($roleFilter, ['owner', 'admin', 'user'])) {
    $whereClauses[] = "u.role = ?";
    $params[] = $roleFilter;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Fetch Users with Listings and Orders Counts
$usersQuery = "
    SELECT u.id, u.name, u.email, u.phone, u.role, u.created_at,
           COUNT(DISTINCT b.id) AS total_listings,
           COUNT(DISTINCT o.id) AS total_orders
    FROM users u
    LEFT JOIN books b ON u.id = b.seller_id
    LEFT JOIN orders o ON u.id = o.buyer_id
    $whereSql
    GROUP BY u.id, u.name, u.email, u.phone, u.role, u.created_at
    ORDER BY 
        CASE u.role 
            WHEN 'owner' THEN 1 
            WHEN 'admin' THEN 2 
            ELSE 3 
        END, 
        u.created_at DESC
";

$stmt = $pdo->prepare($usersQuery);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

// Statistics Counters
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalOwners = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
$totalSellers = $pdo->query("SELECT COUNT(DISTINCT seller_id) FROM books")->fetchColumn();

include 'includes/header.php';
?>

<!-- Admin Header Banner -->
<div class="hero-banner-subpage mb-4" style="background: linear-gradient(135deg, #0A192F 0%, #172A45 60%, #0F3460 100%);">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1 d-inline-flex align-items-center rounded-pill">
                        <i class="bi bi-crown-fill me-1"></i> Owner Management Suite
                    </span>
                    <span class="badge bg-white-10 text-white-50 small px-2 py-0.5 rounded-pill border border-white-10">
                        3-Tier Governance Active
                    </span>
                </div>
                <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">Staff & User Governance</h1>
                <p class="text-white-50 mb-0">Manage platform site managers (admins), assign executive privileges, and moderate users</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-warning fw-bold px-3.5 py-2 rounded-pill shadow-sm d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="bi bi-person-plus-fill"></i> Add Site Manager
                </button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="d-flex flex-wrap gap-2 pt-2 border-top border-white-10" style="border-top: 1px solid rgba(255,255,255,0.12);">
            <a href="admin-monetization.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                <i class="bi bi-graph-up-arrow me-1 text-teal"></i> Monetization & Revenue
            </a>
            <a href="admin-banners.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                <i class="bi bi-images me-1 text-teal"></i> Hero & Promo Banners
            </a>
            <a href="admin-fraud.php" class="btn btn-sm btn-outline-light fw-bold rounded-pill px-3 py-1.5">
                <i class="bi bi-shield-exclamation me-1 text-danger"></i> Trust & Fraud Moderation
            </a>
            <a href="admin-users.php" class="btn btn-sm btn-light fw-bold rounded-pill px-3 py-1.5 shadow-sm">
                <i class="bi bi-people-fill me-1 text-warning"></i> Site Managers & Users
            </a>
        </div>
    </div>
</div>

<div class="container py-2 py-lg-4">

    <!-- KPI Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1 fw-semibold">Total Registered Users</p>
                        <h3 class="fw-bold mb-0 text-navy"><?= number_format($totalUsers) ?></h3>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle fs-4">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    <span>Buyers & Sellers across Sri Lanka</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1 fw-semibold">Site Managers (Admins)</p>
                        <h3 class="fw-bold mb-0 text-teal"><?= number_format($totalAdmins) ?></h3>
                    </div>
                    <div class="bg-teal-light text-teal p-3 rounded-circle fs-4">
                        <i class="bi bi-shield-check"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    <span>Appointed administrative managers</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1 fw-semibold">Platform Owner</p>
                        <h3 class="fw-bold mb-0 text-warning"><?= number_format($totalOwners) ?></h3>
                    </div>
                    <div class="bg-warning-subtle text-warning p-3 rounded-circle fs-4">
                        <i class="bi bi-crown"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    <span>Supreme system authority</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1 fw-semibold">Active Book Sellers</p>
                        <h3 class="fw-bold mb-0 text-success"><?= number_format($totalSellers) ?></h3>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-circle fs-4">
                        <i class="bi bi-book"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    <span>Users with active listings</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 p-md-4 mb-4">
        <form method="GET" action="admin-users.php" class="row g-3 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
            </div>

            <div class="col-6 col-md-3 col-lg-3">
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
                    <option value="owner" <?= $roleFilter === 'owner' ? 'selected' : '' ?>>👑 Platform Owner</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>🛡️ Site Managers (Admins)</option>
                    <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>👤 Regular Members</option>
                </select>
            </div>

            <div class="col-6 col-md-3 col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-booksy-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($searchQuery) || $roleFilter !== 'all'): ?>
                    <a href="admin-users.php" class="btn btn-outline-secondary px-3">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Users & Staff Table Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-5">
        <div class="card-header bg-white py-3.5 px-4 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold text-navy">Platform Users & Management Directory</h5>
                <small class="text-muted">Displaying <?= count($usersList) ?> account(s)</small>
            </div>
            <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill small">
                <i class="bi bi-shield-lock-fill text-warning me-1"></i> Owner Control Area
            </span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">User & Contact</th>
                        <th class="py-3">Role Tier</th>
                        <th class="py-3 text-center">Listings</th>
                        <th class="py-3 text-center">Orders</th>
                        <th class="py-3">Member Since</th>
                        <th class="text-end pe-4 py-3">Owner Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersList)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-1 d-block mb-2 text-muted opacity-50"></i>
                                No users matched your search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): ?>
                            <tr>
                                <!-- Name & Contact -->
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle-custom <?= $u['role'] === 'owner' ? 'bg-warning text-dark' : ($u['role'] === 'admin' ? 'bg-teal text-white' : 'bg-light text-navy border') ?>" style="width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1rem;">
                                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-navy d-flex align-items-center gap-1.5">
                                                <?= htmlspecialchars($u['name']) ?>
                                                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary small" style="font-size: 0.65rem;">You</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($u['email']) ?>
                                                <?php if (!empty($u['phone'])): ?>
                                                    <span class="ms-2"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($u['phone']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Role Tier -->
                                <td>
                                    <?php if ($u['role'] === 'owner'): ?>
                                        <span class="badge bg-warning text-dark px-2.5 py-1.5 rounded-pill fw-bold fs-7 d-inline-flex align-items-center gap-1 shadow-sm">
                                            <i class="bi bi-crown-fill text-dark"></i> Platform Owner
                                        </span>
                                    <?php elseif ($u['role'] === 'admin'): ?>
                                        <span class="badge bg-teal text-white px-2.5 py-1.5 rounded-pill fw-bold fs-7 d-inline-flex align-items-center gap-1 shadow-sm">
                                            <i class="bi bi-shield-lock-fill"></i> Site Manager (Admin)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border px-2.5 py-1.5 rounded-pill fw-semibold fs-7 d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-person"></i> Member / User
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Listings Count -->
                                <td class="text-center">
                                    <span class="badge bg-light text-navy border fw-bold px-2 py-1">
                                        <?= intval($u['total_listings']) ?>
                                    </span>
                                </td>

                                <!-- Orders Count -->
                                <td class="text-center">
                                    <span class="badge bg-light text-muted border fw-bold px-2 py-1">
                                        <?= intval($u['total_orders']) ?>
                                    </span>
                                </td>

                                <!-- Member Since -->
                                <td class="small text-muted">
                                    <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                </td>

                                <!-- Owner Actions -->
                                <td class="text-end pe-4">
                                    <?php if ($u['role'] === 'owner'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2.5 py-1 rounded-pill small">
                                            <i class="bi bi-lock-fill me-1"></i> Supreme Owner
                                        </span>
                                    <?php else: ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                Manage Role
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                                <?php if ($u['role'] === 'user'): ?>
                                                    <li>
                                                        <form method="POST" action="api/manage-users.php" onsubmit="return confirm('Are you sure you want to promote <?= htmlspecialchars(addslashes($u['name'])) ?> to Site Manager (Admin)?');">
                                                            <input type="hidden" name="action" value="change_role">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <input type="hidden" name="new_role" value="admin">
                                                            <input type="hidden" name="redirect" value="admin-users.php">
                                                            <button type="submit" class="dropdown-item py-2 text-teal fw-semibold">
                                                                <i class="bi bi-shield-plus me-2"></i> Promote to Site Manager
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php elseif ($u['role'] === 'admin'): ?>
                                                    <li>
                                                        <form method="POST" action="api/manage-users.php" onsubmit="return confirm('Are you sure you want to revoke administrative privileges for <?= htmlspecialchars(addslashes($u['name'])) ?>?');">
                                                            <input type="hidden" name="action" value="change_role">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <input type="hidden" name="new_role" value="user">
                                                            <input type="hidden" name="redirect" value="admin-users.php">
                                                            <button type="submit" class="dropdown-item py-2 text-warning-emphasis fw-semibold">
                                                                <i class="bi bi-shield-minus me-2"></i> Demote to Regular Member
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                    <form method="POST" action="api/manage-users.php" onsubmit="return confirm('Are you sure you want to permanently delete <?= htmlspecialchars(addslashes($u['name'])) ?> account? This cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <input type="hidden" name="redirect" value="admin-users.php">
                                                        <button type="submit" class="dropdown-item py-2 text-danger">
                                                            <i class="bi bi-trash3 me-2"></i> Delete Account
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add New Site Manager / Admin -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white py-3.5 px-4">
                <h5 class="modal-title fw-bold font-serif-title d-flex align-items-center gap-2" id="addAdminModalLabel">
                    <i class="bi bi-person-badge-fill text-warning"></i> Add New Site Manager
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="POST" action="api/manage-users.php">
                <input type="hidden" name="action" value="create_admin">
                <input type="hidden" name="role" value="admin">
                <input type="hidden" name="redirect" value="admin-users.php">

                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 d-flex align-items-start gap-2 mb-3">
                        <i class="bi bi-info-circle-fill text-info fs-5 mt-0.5"></i>
                        <div class="small">
                            Site Managers (Admins) gain full operational dashboard access including monetization monitoring, order status management, and book listing moderation.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Full Name *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Sahan Perera" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email Address *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="e.g. sahan.manager@booksy.lk" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-telephone text-muted"></i></span>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 0771234567">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Initial Password * (Min. 6 chars)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Create a strong password" minlength="6" required>
                            <button class="btn btn-outline-secondary btn-toggle-password" type="button"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Staff Note / Role Designation</label>
                        <textarea name="bio" class="form-control" rows="2" placeholder="e.g. Colombo Operations Manager / Catalog Reviewer"></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark rounded-pill px-4 shadow-sm">
                        <i class="bi bi-shield-check me-1"></i> Create Site Manager
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
