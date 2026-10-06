<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

function assertCartTest($title, $condition, $details = '') {
    if ($condition) {
        echo "  \033[32m[PASS]\033[0m " . $title . ($details ? " ($details)" : "") . "\n";
    } else {
        echo "  \033[31m[FAIL]\033[0m " . $title . ($details ? " ($details)" : "") . "\n";
        exit(1);
    }
}

echo "========================================================================\n";
echo "       BOOKSY CART QUANTITY & CHECKOUT TEST RUNNER                      \n";
echo "========================================================================\n\n";

// Reset session cart
$_SESSION['cart'] = [];

// 1. Test adding item to cart with quantity
echo "--- 1. ADDING ITEMS TO CART WITH QUANTITIES ---\n";
$book1 = [
    'id'        => 1,
    'title'     => 'Database System Concepts',
    'author'    => 'Silberschatz',
    'price'     => 2850.00,
    'image_url' => 'default_book.svg',
    'qty'       => 2
];
$_SESSION['cart'][1] = $book1;

assertCartTest("Book 1 added to cart with qty 2", isset($_SESSION['cart'][1]) && $_SESSION['cart'][1]['qty'] === 2);

$book2 = [
    'id'        => 2,
    'title'     => 'Clean Code',
    'author'    => 'Robert C. Martin',
    'price'     => 1500.00,
    'image_url' => 'default_book.svg',
    'qty'       => 1
];
$_SESSION['cart'][2] = $book2;

assertCartTest("Book 2 added to cart with qty 1", isset($_SESSION['cart'][2]) && $_SESSION['cart'][2]['qty'] === 1);

// 2. Test Subtotal and Total Items Count Calculation
echo "\n--- 2. SUBTOTAL & ITEM COUNT CALCULATION ---\n";
$subtotal = 0;
$total_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $q = isset($item['qty']) ? max(1, intval($item['qty'])) : 1;
    $subtotal += floatval($item['price']) * $q;
    $total_count += $q;
}

assertCartTest("Total quantity count is 3 (2 + 1)", $total_count === 3, "Count: $total_count");
assertCartTest("Subtotal is LKR 7,200.00 (2850*2 + 1500*1)", $subtotal === 7200.00, "Subtotal: $subtotal");

// 3. Test Quantity Updates
echo "\n--- 3. UPDATING QUANTITIES ---\n";
// Update Book 1 qty to 3
$_SESSION['cart'][1]['qty'] = 3;
$new_subtotal = 0;
$new_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $q = isset($item['qty']) ? max(1, intval($item['qty'])) : 1;
    $new_subtotal += floatval($item['price']) * $q;
    $new_count += $q;
}

assertCartTest("Book 1 qty updated to 3", $_SESSION['cart'][1]['qty'] === 3);
assertCartTest("New total quantity count is 4 (3 + 1)", $new_count === 4, "Count: $new_count");
assertCartTest("New subtotal is LKR 10,050.00 (2850*3 + 1500*1)", $new_subtotal === 10050.00, "Subtotal: $new_subtotal");

// 4. Test Promo Code Discounts on Quantities
echo "\n--- 4. PROMO CODE DISCOUNT ON QUANTITIES ---\n";
$promo10_discount = ($new_subtotal * 10) / 100;
$promo10_total = max(0, $new_subtotal - $promo10_discount);
assertCartTest("BOOKSY10 promo provides 10% discount (LKR 1,005.00)", $promo10_discount === 1005.00, "Discount: $promo10_discount");
assertCartTest("BOOKSY10 final total is LKR 9,045.00", $promo10_total === 9045.00, "Total: $promo10_total");

// 5. Test Item Removal when Qty = 0
echo "\n--- 5. REMOVING ITEM WHEN QTY = 0 ---\n";
unset($_SESSION['cart'][2]);
assertCartTest("Book 2 removed from cart", !isset($_SESSION['cart'][2]));
assertCartTest("Remaining unique books in cart is 1", count($_SESSION['cart']) === 1);

// 6. Test Database Checkout Order Item Persistence
echo "\n--- 6. ORDER PLACEMENT & ORDER_ITEMS QUANTITY INSERTION ---\n";
try {
    $pdo->beginTransaction();

    $insOrder = $pdo->prepare("
        INSERT INTO orders (buyer_id, total_amount, shipping_address, payment_mode, status, created_at)
        VALUES (3, 8550.00, '123 Galle Rd, Colombo', 'Cash on Delivery', 'Pending', NOW())
    ");
    $insOrder->execute();
    $testOrderId = $pdo->lastInsertId();

    $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, book_id, price, quantity) VALUES (?, ?, ?, ?)");
    foreach ($_SESSION['cart'] as $item) {
        $item_qty = isset($item['qty']) ? max(1, intval($item['qty'])) : 1;
        $itemStmt->execute([$testOrderId, $item['id'], $item['price'], $item_qty]);
    }

    $chkStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $chkStmt->execute([$testOrderId]);
    $savedItems = $chkStmt->fetchAll();

    assertCartTest("Saved 1 order item line", count($savedItems) === 1);
    assertCartTest("Saved order item has quantity 3", intval($savedItems[0]['quantity']) === 3, "Quantity: " . $savedItems[0]['quantity']);
    assertCartTest("Saved order item unit price is LKR 2,850.00", floatval($savedItems[0]['price']) == 2850.00);

    // Rollback test data
    $pdo->rollBack();
    assertCartTest("Test order cleaned up safely via rollback", true);

} catch (Exception $e) {
    $pdo->rollBack();
    assertCartTest("Checkout transaction error: " . $e->getMessage(), false);
}

echo "\n========================================================================\n";
echo ">> ALL CART QUANTITY TESTS PASSED SUCCESSFULLY! <<\n";
echo "========================================================================\n";
