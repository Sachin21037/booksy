<?php
/**
 * Comprehensive System & Endpoint Validation Test
 */
require_once __DIR__ . '/../db.php';

$errors = [];
$passes = [];

function recordTest($name, $status, $msg = '') {
    global $errors, $passes;
    if ($status) {
        $passes[] = "[PASS] $name" . ($msg ? " ($msg)" : "");
        echo "  [PASS] $name" . ($msg ? " ($msg)" : "") . "\n";
    } else {
        $errors[] = "[FAIL] $name - $msg";
        echo "  [FAIL] $name - $msg\n";
    }
}

echo "========================================================================\n";
echo "       BOOKSY COMPREHENSIVE CODEBASE AUDIT & SYSTEM TEST RUNNER        \n";
echo "========================================================================\n\n";

// 1. Check DB Connection
echo "--- 1. DATABASE CONNECTIVITY ---\n";
recordTest("PDO Database Connection", $pdo instanceof PDO, "Host: $host, DB: $db");

// 2. Test index.php queries
echo "\n--- 2. INDEX / CATALOG QUERIES ---\n";
try {
    $cat_stmt = $pdo->query("
        SELECT c.*, COUNT(b.id) AS book_count 
        FROM categories c 
        LEFT JOIN books b ON c.id = b.category_id AND b.status = 'available' AND b.image_url IS NOT NULL AND b.image_url != '' AND b.image_url != 'default_book.svg'
        GROUP BY c.id 
        ORDER BY c.name ASC
    ");
    $cats = $cat_stmt->fetchAll();
    recordTest("Categories with book counts query", count($cats) > 0, "Count: " . count($cats));

    // Test main book search query
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
              AND b.image_url != 'default_book.svg'
            GROUP BY b.id, b.seller_id, b.category_id, b.title, b.author, b.price, b.book_condition, b.image_url, b.description, b.status, b.created_at, c.name, c.slug, u.name, u.phone, u.email 
            ORDER BY is_promoted DESC, b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $books = $stmt->fetchAll();
    recordTest("Main catalog books query", is_array($books), "Found " . count($books) . " books");

} catch (Exception $e) {
    recordTest("Index queries", false, $e->getMessage());
}

// 3. Test book-details.php query
echo "\n--- 3. BOOK DETAILS QUERY ---\n";
try {
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
    $stmt->execute([1]);
    $book = $stmt->fetch();
    recordTest("Fetch single book details (ID 1)", !empty($book), $book ? $book['title'] : 'Not found');
} catch (Exception $e) {
    recordTest("Book details query", false, $e->getMessage());
}

// 4. Test seller-earnings.php queries
echo "\n--- 4. SELLER EARNINGS QUERIES ---\n";
try {
    $summary = get_seller_monthly_sales_summary($pdo, 2, intval(date('n')), intval(date('Y')));
    recordTest("Seller summary calculation", is_array($summary), "Seller 2 sales: LKR " . $summary['total_sales']);

    $commOrdersStmt = $pdo->prepare("
        SELECT oc.*, 
               MAX(o.created_at) AS order_date, 
               MAX(o.payment_mode) AS payment_mode, 
               MAX(b.title) AS book_title, 
               MAX(b.image_url) AS image_url
        FROM order_commissions oc
        JOIN orders o ON oc.order_id = o.id
        JOIN order_items oi ON oc.order_id = oi.order_id
        JOIN books b ON oi.book_id = b.id AND b.seller_id = ?
        WHERE oc.seller_id = ? 
          AND oc.status != 'refunded'
          AND MONTH(o.created_at) = ? 
          AND YEAR(o.created_at) = ?
        GROUP BY oc.commission_id, oc.seller_id, oc.order_id, oc.sale_amount, oc.commission_rate, oc.commission_amount, oc.net_earnings, oc.status, oc.created_at
        ORDER BY order_date DESC
    ");
    $commOrdersStmt->execute([2, 2, intval(date('n')), intval(date('Y'))]);
    $itemized = $commOrdersStmt->fetchAll();
    recordTest("Itemized seller commission query", is_array($itemized));
} catch (Exception $e) {
    recordTest("Seller earnings queries", false, $e->getMessage());
}

// 5. Test admin-monetization.php queries
echo "\n--- 5. ADMIN MONETIZATION ANALYTICS ---\n";
try {
    $analytics = get_admin_monetization_analytics($pdo, intval(date('n')), intval(date('Y')));
    recordTest("Admin analytics retrieval", isset($analytics['all_time_gmv']), "GMV: LKR " . $analytics['all_time_gmv']);

    $recentOrdersStmt = $pdo->prepare("
        SELECT o.id, o.buyer_id, o.total_amount, o.shipping_address, o.payment_mode, o.status, o.created_at,
               u.name AS buyer_name, 
               COALESCE(MAX(oc.commission_amount), 0.00) AS commission_amount,
               COALESCE(MAX(oc.commission_rate), 0.0000) AS commission_rate,
               COALESCE(MAX(oc.status), 'pending') AS commission_status,
               MAX(b.title) AS sample_book_title, 
               MAX(s.name) AS seller_name, 
               MAX(s.id) AS seller_id
        FROM orders o
        JOIN users u ON o.buyer_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN books b ON oi.book_id = b.id
        LEFT JOIN users s ON b.seller_id = s.id
        LEFT JOIN order_commissions oc ON o.id = oc.order_id
        GROUP BY o.id, o.buyer_id, o.total_amount, o.shipping_address, o.payment_mode, o.status, o.created_at, u.name
        ORDER BY o.created_at DESC
        LIMIT 15
    ");
    $recentOrdersStmt->execute();
    $recentOrders = $recentOrdersStmt->fetchAll();
    recordTest("Admin recent orders queue query", is_array($recentOrders));
    recordTest("Admin recent orders queue query", is_array($recentOrders));
} catch (Exception $e) {
    recordTest("Admin queries", false, $e->getMessage());
}

// 6. Test search-suggest API query
echo "\n--- 6. SEARCH AUTOCOMPLETE API ---\n";
try {
    $s_sql = "SELECT b.id, b.title, b.author, b.price, b.book_condition, b.image_url, 
                     c.name AS category_name, c.slug AS category_slug, u.name AS seller_name
              FROM books b
              JOIN categories c ON b.category_id = c.id
              JOIN users u ON b.seller_id = u.id
              WHERE b.status = 'available' AND (b.title LIKE :q1 OR b.author LIKE :q2 OR c.name LIKE :q3)
              ORDER BY b.created_at DESC LIMIT 6";
    $s_stmt = $pdo->prepare($s_sql);
    $s_stmt->execute(['q1' => '%data%', 'q2' => '%data%', 'q3' => '%data%']);
    $suggestions = $s_stmt->fetchAll();
    recordTest("Search suggestions for 'data'", is_array($suggestions), "Results: " . count($suggestions));
} catch (Exception $e) {
    recordTest("Search suggest query", false, $e->getMessage());
}

// 7. Check referenced images & uploads
echo "\n--- 7. ASSETS & IMAGE VALIDATION ---\n";
$imagesToCheck = [
    'images/booksy-logo-horizontal.svg',
    'images/booksy-logo.svg',
    'images/hero-academic-study.jpg',
    'images/hero-cozy-books.jpg',
    'images/hero-rare-vintage.jpg',
    'images/library-wide-banner.jpg',
    'uploads/default_book.svg'
];

foreach ($imagesToCheck as $img) {
    $fullPath = __DIR__ . '/../' . $img;
    recordTest("Asset exists: $img", file_exists($fullPath));
}

// Check every book in database has an existing image file
$booksStmt = $pdo->query("SELECT id, title, image_url FROM books");
while ($b = $booksStmt->fetch()) {
    $imgFile = $b['image_url'];
    $exists = !empty($imgFile) && file_exists(__DIR__ . '/../uploads/' . $imgFile);
    recordTest("Book #{$b['id']} ({$b['title']}) cover exists: uploads/{$imgFile}", $exists);
}

// Summary
echo "\n========================================================================\n";
echo "Total Passed: " . count($passes) . "\n";
echo "Total Failed: " . count($errors) . "\n";
if (empty($errors)) {
    echo ">> ALL COMPREHENSIVE TESTS PASSED WITHOUT ANY ERRORS! <<\n";
} else {
    echo ">> ISSUES DETECTED: <<\n";
    foreach ($errors as $err) {
        echo "   - $err\n";
    }
}
echo "========================================================================\n";
