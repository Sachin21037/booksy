<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ensure_payment_tables($pdo);

$page_title = 'Shopping Cart & Checkout';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Action: Add Item to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $book_id = intval($_POST['book_id']);
    $qty = isset($_POST['qty']) ? max(1, intval($_POST['qty'])) : 1;
    
    // Check if book exists and is available
    $check_stmt = $pdo->prepare("
        SELECT b.id, b.title, b.author, b.price, b.image_url, b.book_condition, b.status, c.name AS category_name, u.name AS seller_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.id 
        LEFT JOIN users u ON b.seller_id = u.id 
        WHERE b.id = ? LIMIT 1
    ");
    $check_stmt->execute([$book_id]);
    $book = $check_stmt->fetch();

    if ($book && $book['status'] === 'available') {
        if (isset($_SESSION['cart'][$book_id])) {
            $existing_qty = isset($_SESSION['cart'][$book_id]['qty']) ? intval($_SESSION['cart'][$book_id]['qty']) : 1;
            $_SESSION['cart'][$book_id]['qty'] = $existing_qty + $qty;
        } else {
            $_SESSION['cart'][$book_id] = [
                'id'             => $book['id'],
                'title'          => $book['title'],
                'author'         => $book['author'],
                'price'          => floatval($book['price']),
                'image_url'      => $book['image_url'] ?? 'default_book.svg',
                'book_condition' => $book['book_condition'] ?? 'Good',
                'category_name'  => $book['category_name'] ?? 'General',
                'seller_name'    => $book['seller_name'] ?? 'Verified Seller',
                'qty'            => $qty
            ];
        }
        set_flash('success', 'Added "<strong>' . htmlspecialchars($book['title']) . '</strong>" (' . $qty . ' item' . ($qty > 1 ? 's' : '') . ') to your shopping cart!');
    } else {
        set_flash('danger', 'Sorry, this book is no longer available.');
    }
    
    header("Location: cart.php");
    exit;
}

// Action: Update Item Quantity (Supports both AJAX and standard POST/GET)
if ((isset($_POST['action']) && $_POST['action'] === 'update_qty') || isset($_GET['update_qty'])) {
    $book_id = intval($_POST['book_id'] ?? $_GET['book_id'] ?? 0);
    $qty = intval($_POST['qty'] ?? $_GET['qty'] ?? 1);
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']) || isset($_GET['ajax']);
    $promo_code = strtoupper(trim($_POST['promo_code'] ?? $_GET['promo_code'] ?? ''));

    $action_type = 'updated';
    $msg = '';

    if (isset($_SESSION['cart'][$book_id])) {
        if ($qty <= 0) {
            $removed_title = $_SESSION['cart'][$book_id]['title'];
            unset($_SESSION['cart'][$book_id]);
            $msg = 'Removed "' . htmlspecialchars($removed_title) . '" from your cart.';
            $action_type = 'removed';
        } else {
            $_SESSION['cart'][$book_id]['qty'] = $qty;
            $msg = 'Updated quantity for "' . htmlspecialchars($_SESSION['cart'][$book_id]['title']) . '" to ' . $qty . '.';
            $action_type = 'updated';
        }
    } else {
        $msg = 'Item not found in cart.';
        $action_type = 'not_found';
    }

    // Recalculate totals
    $cart_subtotal = 0;
    $cart_item_count = 0;
    $item_subtotal = 0;
    $item_qty = 0;
    $unit_price = 0;

    foreach ($_SESSION['cart'] as $cid => $citem) {
        $q = isset($citem['qty']) ? max(1, intval($citem['qty'])) : 1;
        $cart_subtotal += floatval($citem['price']) * $q;
        $cart_item_count += $q;
        if ($cid == $book_id) {
            $item_qty = $q;
            $unit_price = floatval($citem['price']);
            $item_subtotal = $unit_price * $q;
        }
    }

    $discount = 0;
    if ($promo_code === 'BOOKSY10') {
        $discount = ($cart_subtotal * 10) / 100;
    } elseif ($promo_code === 'STUDENT500') {
        $discount = min($cart_subtotal, 500);
    }
    $total_amount = max(0, $cart_subtotal - $discount);

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status'          => 'success',
            'action'          => $action_type,
            'message'         => $msg,
            'book_id'         => $book_id,
            'qty'             => $item_qty,
            'unit_price'      => number_format($unit_price, 2),
            'item_subtotal'   => number_format($item_subtotal, 2),
            'cart_subtotal'   => number_format($cart_subtotal, 2),
            'raw_subtotal'    => $cart_subtotal,
            'cart_count'      => $cart_item_count,
            'cart_unique'     => count($_SESSION['cart']),
            'discount'        => number_format($discount, 2),
            'raw_discount'    => $discount,
            'total_amount'    => number_format($total_amount, 2),
            'raw_total'       => $total_amount,
            'is_empty'        => empty($_SESSION['cart'])
        ]);
        exit;
    }

    set_flash('info', $msg);
    header("Location: cart.php");
    exit;
}

// Action: Remove Single Item
if (isset($_GET['remove'])) {
    $remove_id = intval($_GET['remove']);
    if (isset($_SESSION['cart'][$remove_id])) {
        $removed_title = $_SESSION['cart'][$remove_id]['title'];
        unset($_SESSION['cart'][$remove_id]);
        set_flash('info', 'Removed "' . htmlspecialchars($removed_title) . '" from your cart.');
    }
    header("Location: cart.php");
    exit;
}

// Action: Clear Whole Cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    set_flash('info', 'Your shopping cart has been cleared.');
    header("Location: cart.php");
    exit;
}

// Action: Process Checkout & Database Transaction
$checkout_error = '';
$buyer_name = '';
$buyer_phone = '';
$shipping_street = '';
$shipping_city = '';
$shipping_district = 'Colombo';
$order_notes = '';
$payment_mode = 'Cash on Delivery';
$card_name = '';
$card_number = '';
$card_expiry = '';
$card_cvv = '';
$bank_sender_name = '';
$bank_ref = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (empty($_SESSION['cart'])) {
        $checkout_error = 'Your cart is empty. Please add books to proceed.';
    } else {
        $buyer_name = trim($_POST['buyer_name'] ?? '');
        $buyer_phone = trim($_POST['buyer_phone'] ?? '');
        $shipping_street = trim($_POST['shipping_street'] ?? '');
        $shipping_city = trim($_POST['shipping_city'] ?? '');
        $shipping_district = trim($_POST['shipping_district'] ?? 'Colombo');
        $order_notes = trim($_POST['order_notes'] ?? '');
        $payment_mode = $_POST['payment_mode'] ?? 'Cash on Delivery';
        $promo_code = strtoupper(trim($_POST['promo_code'] ?? ''));

        // Payment input parameters
        $card_name = trim($_POST['card_name'] ?? '');
        $card_number = trim($_POST['card_number'] ?? '');
        $card_expiry = trim($_POST['card_expiry'] ?? '');
        $card_cvv = trim($_POST['card_cvv'] ?? '');
        $bank_sender_name = trim($_POST['bank_sender_name'] ?? '');
        $bank_ref = trim($_POST['bank_ref'] ?? '');

        if (empty($buyer_name) || empty($buyer_phone) || empty($shipping_street) || empty($shipping_city)) {
            $checkout_error = 'Please complete all required recipient details (Name, Phone, Street Address, and City).';
        } elseif ($payment_mode === 'Credit / Debit Card') {
            if (empty($card_name)) {
                $checkout_error = 'Please enter the Cardholder Name appearing on your card.';
            } else {
                $clean_card = preg_replace('/\s+/', '', $card_number);
                if (strlen($clean_card) < 13 || strlen($clean_card) > 19 || !ctype_digit($clean_card)) {
                    $checkout_error = 'Please enter a valid 16-digit Card Number.';
                } elseif (!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*([0-9]{2})$/', $card_expiry, $exp_m)) {
                    $checkout_error = 'Please enter a valid Card Expiry Date in MM/YY format (e.g. 12/28).';
                } else {
                    $exp_month = intval($exp_m[1]);
                    $exp_year = intval('20' . $exp_m[2]);
                    $cur_year = intval(date('Y'));
                    $cur_month = intval(date('n'));
                    if ($exp_year < $cur_year || ($exp_year === $cur_year && $exp_month < $cur_month)) {
                        $checkout_error = 'The card entered has already expired. Please enter an active card.';
                    } elseif (strlen($card_cvv) < 3 || strlen($card_cvv) > 4 || !ctype_digit($card_cvv)) {
                        $checkout_error = 'Please enter a valid 3 or 4-digit CVV / CVC security code.';
                    }
                }
            }
        } elseif ($payment_mode === 'Bank Transfer') {
            if (empty($bank_ref) && empty($bank_sender_name)) {
                $checkout_error = 'Please provide your Bank Transfer Reference ID or Depositor Name for payment verification.';
            }
        }

        if (empty($checkout_error)) {
            // Determine buyer user ID
            $buyer_id = is_logged_in() ? $_SESSION['user_id'] : 1; // Default to demo / guest user
            $notes_line = !empty($order_notes) ? "\nCourier Notes: $order_notes" : "";
            $full_shipping_address = "Recipient: $buyer_name (Phone: $buyer_phone)\nAddress: $shipping_street, $shipping_city, $shipping_district, Sri Lanka$notes_line";
            
            // Calculate subtotal with quantity
            $subtotal = 0;
            foreach ($_SESSION['cart'] as $item) {
                $q = isset($item['qty']) ? max(1, intval($item['qty'])) : 1;
                $subtotal += floatval($item['price']) * $q;
            }

            $discount = 0;
            if ($promo_code === 'BOOKSY10') {
                $discount = ($subtotal * 10) / 100;
            } elseif ($promo_code === 'STUDENT500') {
                $discount = min($subtotal, 500);
            }

            $total_amount = max(0, $subtotal - $discount);

            // Verify all items are still available before beginning transaction
            $book_ids = array_keys($_SESSION['cart']);
            $placeholders = str_repeat('?,', count($book_ids) - 1) . '?';
            $avail_stmt = $pdo->prepare("SELECT id, title, status FROM books WHERE id IN ($placeholders)");
            $avail_stmt->execute($book_ids);
            $db_books = $avail_stmt->fetchAll();

            $unavailable = [];
            foreach ($db_books as $db_book) {
                if ($db_book['status'] !== 'available') {
                    $unavailable[] = $db_book['title'];
                }
            }

            if (!empty($unavailable)) {
                $checkout_error = 'The following book(s) were just purchased by someone else and are no longer available: ' . implode(', ', $unavailable);
            } else {
                // Determine stored payment mode & order status
                $slip_filename = null;
                if ($payment_mode === 'Credit / Debit Card') {
                    $clean_card = preg_replace('/\s+/', '', $card_number);
                    $first_digit = $clean_card[0] ?? '';
                    $first_two = substr($clean_card, 0, 2);
                    $first_four = intval(substr($clean_card, 0, 4));
                    if ($first_digit === '4') {
                        $brand = 'Visa';
                    } elseif (in_array($first_two, ['51','52','53','54','55']) || ($first_four >= 2221 && $first_four <= 2720)) {
                        $brand = 'MasterCard';
                    } elseif (in_array($first_two, ['34','37'])) {
                        $brand = 'American Express';
                    } else {
                        $brand = 'Credit Card';
                    }
                    $last4 = substr($clean_card, -4);
                    $stored_payment_mode = "$brand (•••• $last4)";
                    $order_status = 'Confirmed';
                } elseif ($payment_mode === 'Bank Transfer') {
                    // Handle slip upload if provided
                    if (isset($_FILES['bank_slip']) && $_FILES['bank_slip']['error'] === UPLOAD_ERR_OK) {
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                        $file_ext = strtolower(pathinfo($_FILES['bank_slip']['name'], PATHINFO_EXTENSION));
                        if (in_array($file_ext, $allowed_exts) && $_FILES['bank_slip']['size'] <= 5 * 1024 * 1024) {
                            $upload_dir = __DIR__ . '/uploads/';
                            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                            $slip_filename = 'slip_' . uniqid() . '.' . $file_ext;
                            move_uploaded_file($_FILES['bank_slip']['tmp_name'], $upload_dir . $slip_filename);
                        }
                    }
                    $stored_payment_mode = 'Bank Transfer' . (!empty($bank_ref) ? ' (Ref: ' . $bank_ref . ')' : '');
                    $order_status = 'Pending';
                } elseif ($payment_mode === 'Online Payment (PayHere)') {
                    $stored_payment_mode = 'Online Payment (PayHere)';
                    $order_status = 'Pending';
                } else {
                    $stored_payment_mode = 'Cash on Delivery';
                    $order_status = 'Pending';
                }

                // Begin Atomic Transaction
                $pdo->beginTransaction();
                try {
                    // 1. Insert Order
                    $order_stmt = $pdo->prepare("
                        INSERT INTO orders (buyer_id, total_amount, shipping_address, payment_mode, status) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $order_stmt->execute([$buyer_id, $total_amount, $full_shipping_address, $stored_payment_mode, $order_status]);
                    $order_id = $pdo->lastInsertId();

                    // 2. Insert Order Items & 3. Update Book Status to 'sold'
                    $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, book_id, price, quantity) VALUES (?, ?, ?, ?)");
                    $status_stmt = $pdo->prepare("UPDATE books SET status = 'sold' WHERE id = ?");

                    foreach ($_SESSION['cart'] as $item) {
                        $item_qty = isset($item['qty']) ? max(1, intval($item['qty'])) : 1;
                        $item_stmt->execute([$order_id, $item['id'], $item['price'], $item_qty]);
                        $status_stmt->execute([$item['id']]);
                    }

                    // 4. Record Payment in payments table
                    ensure_payment_tables($pdo);
                    if ($payment_mode === 'Credit / Debit Card') {
                        $tx_id = 'CARD_' . strtoupper(bin2hex(random_bytes(6)));
                        $raw = [
                            'cardholder'   => $card_name,
                            'brand'        => $brand,
                            'last4'        => $last4,
                            'expiry'       => $card_expiry,
                            'processed_at' => date('Y-m-d H:i:s'),
                            'mode'         => 'Direct Card Payment (Verified)'
                        ];
                        $pdo->prepare("
                            INSERT INTO payments (order_id, amount, currency, payment_method, transaction_id, payment_status, raw_response)
                            VALUES (?, ?, 'LKR', ?, ?, 'Completed', ?)
                        ")->execute([$order_id, $total_amount, $stored_payment_mode, $tx_id, json_encode($raw)]);
                    } elseif ($payment_mode === 'Bank Transfer') {
                        $tx_id = !empty($bank_ref) ? $bank_ref : ('BT_' . time());
                        $raw = [
                            'sender_name' => $bank_sender_name,
                            'reference'   => $bank_ref,
                            'slip_file'   => $slip_filename,
                            'bank_name'   => 'Commercial Bank of Ceylon'
                        ];
                        $pdo->prepare("
                            INSERT INTO payments (order_id, amount, currency, payment_method, transaction_id, payment_status, raw_response)
                            VALUES (?, ?, 'LKR', 'Direct Bank Transfer', ?, 'Pending', ?)
                        ")->execute([$order_id, $total_amount, $tx_id, json_encode($raw)]);
                    } elseif ($payment_mode === 'Cash on Delivery') {
                        $tx_id = 'COD_' . $order_id;
                        $pdo->prepare("
                            INSERT INTO payments (order_id, amount, currency, payment_method, transaction_id, payment_status, raw_response)
                            VALUES (?, ?, 'LKR', 'Cash on Delivery', ?, 'Pending', JSON_OBJECT('courier_collect', true))
                        ")->execute([$order_id, $total_amount, $tx_id]);
                    }

                    // Commit Transaction
                    $pdo->commit();

                    // Clear Cart
                    $_SESSION['cart'] = [];

                    if ($payment_mode === 'Online Payment (PayHere)' || $payment_mode === 'Online Payment') {
                        $buyerInfo = [
                            'name'    => $buyer_name,
                            'email'   => is_logged_in() ? ($_SESSION['user_email'] ?? 'customer@booksy.lk') : 'customer@booksy.lk',
                            'phone'   => $buyer_phone,
                            'address' => "$shipping_street, $shipping_city",
                            'city'    => $shipping_city
                        ];
                        $payhereData = build_payhere_payload($pdo, (int)$order_id, ['total_amount' => $total_amount], $buyerInfo);
                        $_SESSION['payhere_checkout_order'] = [
                            'order_id' => $order_id,
                            'payload'  => $payhereData
                        ];
                        header("Location: cart.php?payhere_checkout=" . $order_id);
                        exit;
                    } else {
                        // Redirect to Order Success page
                        header("Location: order-success.php?order_id=" . $order_id);
                        exit;
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $checkout_error = 'Checkout failed due to a database error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Ensure all items in cart have metadata populated
if (!empty($_SESSION['cart'])) {
    $item_ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
    try {
        $meta_stmt = $pdo->prepare("
            SELECT b.id, b.title, b.author, b.price, b.image_url, b.book_condition, b.status, c.name AS category_name, u.name AS seller_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            LEFT JOIN users u ON b.seller_id = u.id 
            WHERE b.id IN ($placeholders)
        ");
        $meta_stmt->execute($item_ids);
        $fetched_books = $meta_stmt->fetchAll();
        foreach ($fetched_books as $fb) {
            $fbid = $fb['id'];
            if (isset($_SESSION['cart'][$fbid])) {
                $_SESSION['cart'][$fbid]['title'] = $fb['title'];
                $_SESSION['cart'][$fbid]['author'] = $fb['author'];
                $_SESSION['cart'][$fbid]['price'] = floatval($fb['price']);
                if (!empty($fb['image_url'])) {
                    $_SESSION['cart'][$fbid]['image_url'] = $fb['image_url'];
                }
                $_SESSION['cart'][$fbid]['book_condition'] = $fb['book_condition'] ?? 'Good';
                $_SESSION['cart'][$fbid]['category_name'] = $fb['category_name'] ?? 'General';
                $_SESSION['cart'][$fbid]['seller_name'] = $fb['seller_name'] ?? 'Verified Seller';
            }
        }
    } catch (Exception $e) {
        // Continue gracefully
    }
}

$cart_items = $_SESSION['cart'];
$total_price = 0;
$total_items_count = 0;
foreach ($cart_items as $item) {
    $q = isset($item['qty']) ? max(1, intval($item['qty'])) : 1;
    $total_price += floatval($item['price']) * $q;
    $total_items_count += $q;
}

// Pre-fill user and form data
$logged = get_logged_user();
$default_name = !empty($buyer_name) ? $buyer_name : ($logged ? $logged['name'] : '');
$default_phone = !empty($buyer_phone) ? $buyer_phone : ($logged ? $logged['phone'] : '');
$default_street = !empty($shipping_street) ? $shipping_street : '';
$default_city = !empty($shipping_city) ? $shipping_city : '';
$default_district = !empty($shipping_district) ? $shipping_district : 'Colombo';
$default_notes = !empty($order_notes) ? $order_notes : '';
$default_payment_mode = !empty($payment_mode) ? $payment_mode : 'Cash on Delivery';
$default_card_name = !empty($card_name) ? $card_name : ($logged ? strtoupper($logged['name']) : '');
$default_card_number = !empty($card_number) ? $card_number : '';
$default_card_expiry = !empty($card_expiry) ? $card_expiry : '';
$default_card_cvv = !empty($card_cvv) ? $card_cvv : '';
$default_bank_sender = !empty($bank_sender_name) ? $bank_sender_name : ($logged ? $logged['name'] : '');
$default_bank_ref = !empty($bank_ref) ? $bank_ref : '';

include 'includes/header.php';
?>

<!-- Cart & Checkout Header Banner -->
<div class="hero-banner-subpage mb-4">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-teal text-white fw-bold px-3 py-1 mb-2 d-inline-flex align-items-center rounded-pill">
                    <i class="bi bi-shield-lock-fill me-1"></i> Secure Checkout & Purchase
                </span>
                <h1 class="display-6 fw-bold text-white mb-1 font-serif-title">Shopping Cart & Order Summary</h1>
                <p class="text-white-50 mb-0">Review your selected books, adjust quantities, and complete order with ease</p>
            </div>
            <?php if (!empty($cart_items)): ?>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php" class="btn btn-outline-light btn-sm px-3 py-2 rounded-pill">
                        <i class="bi bi-plus-circle me-1"></i> Add More Books
                    </a>
                    <a href="cart.php?clear=1" class="btn btn-outline-danger bg-white text-danger btn-sm px-3 py-2 rounded-pill shadow-xs" onclick="return confirm('Are you sure you want to empty your entire cart?')">
                        <i class="bi bi-trash3 me-1"></i> Empty Cart
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container py-3 py-lg-4" id="cartContainer">

    <?php 
    $payhere_checkout_id = isset($_GET['payhere_checkout']) ? intval($_GET['payhere_checkout']) : 0;
    $payhere_order = null;
    $payhere_payload = null;

    if ($payhere_checkout_id > 0) {
        $po_stmt = $pdo->prepare("SELECT o.*, u.name AS buyer_name, u.email AS buyer_email, u.phone AS buyer_phone FROM orders o LEFT JOIN users u ON o.buyer_id = u.id WHERE o.id = ? LIMIT 1");
        $po_stmt->execute([$payhere_checkout_id]);
        $payhere_order = $po_stmt->fetch();
        
        if ($payhere_order) {
            $buyerData = [
                'name'    => $payhere_order['buyer_name'] ?: 'Customer',
                'email'   => $payhere_order['buyer_email'] ?: 'customer@booksy.lk',
                'phone'   => $payhere_order['buyer_phone'] ?: '0771234567',
                'address' => $payhere_order['shipping_address'],
                'city'    => 'Colombo'
            ];
            $payhere_payload = build_payhere_payload($pdo, $payhere_checkout_id, ['total_amount' => $payhere_order['total_amount']], $buyerData);
        }
    }
    ?>

    <?php if ($payhere_order && $payhere_payload): ?>
        <!-- =================================================================== -->
        <!-- PAYHERE PAYMENT GATEWAY SCREEN                                      -->
        <!-- =================================================================== -->
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 border">
                    <div class="bg-navy text-white text-center p-4 position-relative" style="border-bottom: 3px solid var(--teal-primary);">
                        <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
                            <span class="badge bg-teal text-white fw-bold px-3 py-1 rounded-pill">
                                <i class="bi bi-shield-check me-1"></i> PayHere Payment Gateway
                            </span>
                            <span class="badge bg-white-10 text-white-50 small px-2.5 py-1 rounded-pill border border-white-10">
                                Merchant ID: <?= htmlspecialchars(PAYHERE_MERCHANT_ID) ?>
                            </span>
                        </div>
                        <h2 class="display-7 fw-bold text-white mb-1 font-serif-title">Complete Your Online Payment</h2>
                        <p class="text-white-50 small mb-0">Secure 256-bit encrypted checkout via PayHere Sri Lanka</p>
                    </div>

                    <div class="card-body p-4 p-md-5 bg-white text-center">
                        
                        <!-- Order & Amount Box -->
                        <div class="p-3.5 bg-light rounded-4 border mb-4 text-start">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="text-muted small">Order Reference:</span>
                                <strong class="text-navy">#BKY-<?= str_pad($payhere_order['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="text-muted small">Payment Method:</span>
                                <span class="badge bg-teal text-white">Online Payment (PayHere)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <span class="text-muted small">Currency / Security:</span>
                                <span class="text-dark fw-semibold small">LKR (Sri Lankan Rupees) • 256-Bit SSL Encrypted</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-1">
                                <span class="fw-bold text-navy">Total Payable Amount:</span>
                                <span class="fs-4 fw-bold text-teal">Rs. <?= number_format($payhere_order['total_amount'], 2) ?></span>
                            </div>
                        </div>

                        <!-- Card & Wallet Icons Strip -->
                        <div class="mb-4">
                            <p class="text-muted small mb-2 text-uppercase fw-bold" style="font-size: 0.72rem;">Accepted Payment Methods</p>
                            <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                                <span class="badge bg-light text-dark border px-2.5 py-1.5"><i class="bi bi-credit-card-2-front text-primary me-1"></i> Visa</span>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5"><i class="bi bi-credit-card text-danger me-1"></i> MasterCard</span>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5"><i class="bi bi-credit-card-2-back text-info me-1"></i> AMEX</span>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5"><i class="bi bi-phone text-success me-1"></i> Genie</span>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5"><i class="bi bi-wallet2 text-warning text-dark me-1"></i> eZ Cash</span>
                                <span class="badge bg-light text-dark border px-2.5 py-1.5"><i class="bi bi-qr-code text-teal me-1"></i> Frimi</span>
                            </div>
                        </div>

                        <!-- PayHere Primary Action Button -->
                        <div class="d-grid gap-2 mb-3">
                            <button type="button" class="btn btn-warning btn-lg fw-bold rounded-pill py-3 shadow text-dark d-flex align-items-center justify-content-center gap-2" onclick="startPayHereModal()">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Pay Rs. <?= number_format($payhere_order['total_amount'], 2) ?> with PayHere</span>
                            </button>
                        </div>

                        <!-- Standard Form Fallback POST -->
                        <form id="payhereStandardForm" method="POST" action="<?= PAYHERE_CHECKOUT_URL ?>" class="d-none">
                            <?php foreach ($payhere_payload as $key => $val): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars((string)$val) ?>">
                            <?php endforeach; ?>
                        </form>

                        <!-- Instant Approval Confirmation Button -->
                        <div class="p-3 bg-teal-light rounded-3 border border-teal-subtle text-start mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="fw-bold text-teal small"><i class="bi bi-patch-check-fill me-1"></i> Instant Verification & Approval:</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Authorize gateway payment clearance and confirm order immediately.</div>
                                </div>
                                <a href="order-success.php?order_id=<?= $payhere_order['id'] ?>&payhere=simulated_success" class="btn btn-sm btn-teal fw-bold rounded-pill px-3">
                                    Instant Approval
                                </a>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <a href="cart.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left me-1"></i> Cancel & Return to Cart
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-dark rounded-pill px-3" onclick="document.getElementById('payhereStandardForm').submit();">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Open PayHere Web Portal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Load PayHere Official JavaScript SDK -->
        <script type="text/javascript" src="https://www.payhere.lk/lib/payhere.js"></script>
        <script>
        const payherePaymentObject = <?= json_encode($payhere_payload) ?>;

        function startPayHereModal() {
            if (typeof payhere !== 'undefined') {
                payhere.startPayment(payherePaymentObject);
            } else {
                document.getElementById('payhereStandardForm').submit();
            }
        }

        if (typeof payhere !== 'undefined') {
            payhere.onCompleted = function onCompleted(orderId) {
                console.log("PayHere payment completed. OrderID:" + orderId);
                window.location.href = "order-success.php?order_id=" + orderId + "&payhere=completed";
            };

            payhere.onDismissed = function onDismissed() {
                console.log("PayHere payment dismissed by customer.");
            };

            payhere.onError = function onError(error) {
                console.log("PayHere Gateway Error: " + error);
                alert("PayHere Gateway Notice: " + error);
            };
        }
        </script>

    <?php else: ?>

    <?php if (!empty($checkout_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
            <div><?= htmlspecialchars($checkout_error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <!-- Empty Cart View -->
        <div class="card border-0 shadow-sm p-4 p-md-5 text-center rounded-4 bg-white border">
            <div class="mb-3">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 90px; height: 90px;">
                    <i class="bi bi-cart-x text-muted" style="font-size: 3rem;"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-2 text-navy font-serif-title">Your Shopping Cart is Empty</h3>
            <p class="text-muted mb-4 mx-auto" style="max-width: 480px;">
                You don't have any books in your cart right now. Explore thousands of pre-loved textbooks, novels, engineering guides, and rare collectibles from verified sellers.
            </p>
            <div class="mb-4">
                <a href="index.php" class="btn btn-booksy-primary btn-lg fw-bold px-4 py-2.5 shadow">
                    <i class="bi bi-grid me-2"></i> Explore Books Catalog
                </a>
            </div>

            <!-- Popular Category Shortcuts -->
            <div class="pt-4 border-top">
                <h6 class="fw-bold text-muted text-uppercase small mb-3">Popular Categories to Explore</h6>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="index.php?category=textbooks" class="btn btn-sm btn-outline-secondary rounded-pill px-3">📚 Textbooks</a>
                    <a href="index.php?category=academic" class="btn btn-sm btn-outline-secondary rounded-pill px-3">🎓 Academic & University</a>
                    <a href="index.php?category=novels" class="btn btn-sm btn-outline-secondary rounded-pill px-3">📖 Novels & Fiction</a>
                    <a href="index.php?category=children" class="btn btn-sm btn-outline-secondary rounded-pill px-3">🎨 Children's Books</a>
                    <a href="index.php?category=rare" class="btn btn-sm btn-outline-secondary rounded-pill px-3">⭐ Rare & Vintage</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Free Shipping Promotional Bar -->
        <div class="cart-free-shipping-banner mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-xs">
            <div class="d-flex align-items-center gap-2.5">
                <div class="rounded-circle bg-white p-2 d-inline-flex align-items-center justify-content-center shadow-xs text-success flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bi bi-truck fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark small mb-0">🎉 Complimentary Express Courier Delivery Unlocked!</div>
                    <div class="text-secondary small" style="font-size: 0.78rem;">Your book order qualifies for 100% free doorstep courier delivery across all 25 districts in Sri Lanka.</div>
                </div>
            </div>
            <span class="badge bg-success text-white px-3 py-1.5 rounded-pill fw-bold">FREE PROMO</span>
        </div>

        <!-- Cart & 2-Step Checkout Split View -->
        <div class="row g-4">
            <!-- Left: Selected Items List -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-4 border">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom flex-wrap gap-2">
                        <h5 class="fw-bold text-navy mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-bag-check-fill text-teal"></i>
                            <span>Selected Books (<span id="cartHeaderUniqueCount"><?= count($cart_items) ?></span>)</span>
                        </h5>
                        <div class="small text-muted">
                            Total Copies: <strong class="text-teal" id="cartHeaderTotalCopies"><?= $total_items_count ?></strong>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2.5" id="cartItemsList">
                        <?php foreach ($cart_items as $item): ?>
                            <?php
                            $thumb = !empty($item['image_url']) && file_exists(__DIR__ . '/uploads/' . $item['image_url']) 
                                ? 'uploads/' . htmlspecialchars($item['image_url']) 
                                : 'uploads/default_book.svg';
                            $item_qty = isset($item['qty']) ? max(1, intval($item['qty'])) : 1;
                            $item_unit_price = floatval($item['price']);
                            $item_line_total = $item_unit_price * $item_qty;
                            $condition_badge_class = 'bg-light text-navy';
                            if (($item['book_condition'] ?? '') === 'Brand New') $condition_badge_class = 'bg-success text-white';
                            elseif (($item['book_condition'] ?? '') === 'Like New') $condition_badge_class = 'bg-teal text-white';
                            ?>
                            <div class="cart-item-row d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3" id="cartItemRow_<?= $item['id'] ?>" data-book-id="<?= $item['id'] ?>" data-unit-price="<?= $item['price'] ?>">
                                <!-- Book Info & Thumbnail -->
                                <div class="d-flex align-items-center gap-3 flex-grow-1" style="min-width: 0;">
                                    <a href="book-details.php?id=<?= $item['id'] ?>" class="flex-shrink-0" title="View Book Details">
                                        <img src="<?= $thumb ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="rounded shadow-sm" style="width: 54px; height: 72px; object-fit: contain; background: #17324D;" onerror="this.onerror=null;this.src='uploads/default_book.svg';">
                                    </a>
                                    <div class="overflow-hidden">
                                        <h6 class="fw-bold mb-1 text-truncate text-navy" style="max-width: 270px;">
                                            <a href="book-details.php?id=<?= $item['id'] ?>" class="text-navy text-decoration-none hover-teal" title="<?= htmlspecialchars($item['title']) ?>">
                                                <?= htmlspecialchars($item['title']) ?>
                                            </a>
                                        </h6>
                                        <p class="text-muted small mb-1 text-truncate">By <?= htmlspecialchars($item['author'] ?? 'Seller') ?></p>
                                        <div class="d-flex align-items-center flex-wrap gap-1.5">
                                            <span class="badge <?= $condition_badge_class ?> border small" style="font-size: 0.72rem;"><?= htmlspecialchars($item['book_condition'] ?? 'Good') ?></span>
                                            <span class="badge bg-light text-secondary border small" style="font-size: 0.72rem;">Rs. <?= number_format($item_unit_price, 2) ?> each</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quantity Stepper & Line Price -->
                                <div class="d-flex align-items-center justify-content-between justify-content-sm-end gap-3 flex-shrink-0 pt-2 pt-sm-0 border-top border-sm-top-0">
                                    <!-- Stepper Component -->
                                    <div class="d-flex flex-column align-items-center">
                                        <div class="cart-qty-stepper">
                                            <button type="button" class="btn-qty-step" onclick="stepCartQty(<?= $item['id'] ?>, -1)" title="Decrease quantity" aria-label="Decrease quantity">
                                                <i class="bi bi-dash-lg"></i>
                                            </button>
                                            <input type="number" 
                                                   id="cartQtyInput_<?= $item['id'] ?>" 
                                                   class="cart-qty-input" 
                                                   value="<?= $item_qty ?>" 
                                                   min="1" 
                                                   max="99" 
                                                   data-book-id="<?= $item['id'] ?>" 
                                                   onchange="onCartQtyInputChange(<?= $item['id'] ?>)" 
                                                   oninput="onCartQtyInputDebounced(<?= $item['id'] ?>)" 
                                                   aria-label="Item quantity">
                                            <button type="button" class="btn-qty-step" onclick="stepCartQty(<?= $item['id'] ?>, 1)" title="Increase quantity" aria-label="Increase quantity">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                        <span class="badge bg-teal-subtle text-teal mt-1 <?= $item_qty > 1 ? '' : 'd-none' ?>" id="cartMultiplier_<?= $item['id'] ?>" style="font-size: 0.68rem;">
                                            <?= $item_qty ?> × Rs. <?= number_format($item_unit_price, 2) ?>
                                        </span>
                                    </div>

                                    <!-- Line Total & Delete Action -->
                                    <div class="text-end" style="min-width: 110px;">
                                        <div class="fw-bold text-navy fs-5" id="cartItemTotal_<?= $item['id'] ?>">Rs. <?= number_format($item_line_total, 2) ?></div>
                                        <a href="cart.php?remove=<?= $item['id'] ?>" class="text-danger small text-decoration-none d-inline-flex align-items-center gap-1 mt-1 hover-underline" title="Remove book" onclick="return confirmRemoveCartItem(event, <?= $item['id'] ?>)">
                                            <i class="bi bi-trash3"></i> <span>Remove</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Subtotal Banner -->
                    <div class="bg-light p-3 rounded-3 mt-3 d-flex justify-content-between align-items-center border">
                        <span class="text-muted fw-semibold">
                            Subtotal (<span id="cartSubtotalCountText"><?= $total_items_count ?> item<?= $total_items_count > 1 ? 's' : '' ?></span>):
                        </span>
                        <span class="fw-bold fs-4 text-navy" id="cartSubtotalBanner">Rs. <?= number_format($total_price, 2) ?></span>
                    </div>
                </div>

                <!-- Trust Guarantees Strip -->
                <div class="card border-0 bg-light p-3 rounded-4 border">
                    <div class="row g-2 text-center small text-secondary">
                        <div class="col-4">
                            <i class="bi bi-shield-check text-success fs-5 d-block mb-1"></i>
                            <strong>Verified Sellers</strong>
                            <div class="text-muted" style="font-size: 0.72rem;">Authentic Listings</div>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-truck text-navy fs-5 d-block mb-1"></i>
                            <strong>Island Courier</strong>
                            <div class="text-muted" style="font-size: 0.72rem;">Tracked Transit</div>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-cash-stack text-gold fs-5 d-block mb-1"></i>
                            <strong>Pay at Doorstep</strong>
                            <div class="text-muted" style="font-size: 0.72rem;">Inspect Before Paying</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Checkout Information & Sticky Order Summary Form -->
            <div class="col-lg-5">
                <div class="cart-order-summary-card p-3 p-md-4 sticky-top border" style="top: 95px;">
                    <h5 class="fw-bold mb-3 text-navy d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-credit-card-2-front text-teal me-2"></i>Checkout & Payment</span>
                        <span class="badge bg-teal-subtle text-teal fw-semibold small">Step 2 of 2</span>
                    </h5>

                    <form method="POST" action="cart.php" id="checkoutForm" enctype="multipart/form-data">
                        <!-- Hidden Raw Subtotal for dynamic JS price calculations -->
                        <input type="hidden" id="cartRawSubtotal" value="<?= $total_price ?>">

                        <!-- Recipient Name -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-navy"><i class="bi bi-person text-teal me-1"></i> Recipient Full Name *</label>
                            <input type="text" name="buyer_name" class="form-control form-control-sm" placeholder="e.g. Kasun Jayasinghe" value="<?= htmlspecialchars($default_name) ?>" required>
                        </div>

                        <!-- Phone Number -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-navy"><i class="bi bi-telephone text-teal me-1"></i> Delivery Phone Number *</label>
                            <input type="tel" name="buyer_phone" class="form-control form-control-sm" placeholder="e.g. 0771234567" value="<?= htmlspecialchars($default_phone) ?>" required>
                        </div>

                        <!-- Shipping Address -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-navy"><i class="bi bi-geo-alt text-teal me-1"></i> Street Address / House No *</label>
                            <textarea name="shipping_street" class="form-control form-control-sm" rows="2" placeholder="e.g. No 45/2, Temple Road" required><?= htmlspecialchars($default_street) ?></textarea>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-navy">City / Town *</label>
                                <input type="text" name="shipping_city" class="form-control form-control-sm" placeholder="e.g. Colombo 03" value="<?= htmlspecialchars($default_city) ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-navy">District / Province</label>
                                <select name="shipping_district" id="shippingDistrictSelect" class="form-select form-select-sm" onchange="onDistrictChange(this.value)">
                                    <?php 
                                    $districts = ['Colombo', 'Gampaha', 'Kalutara', 'Kandy', 'Galle', 'Matara', 'Kurunegala', 'Other'];
                                    foreach ($districts as $d):
                                    ?>
                                        <option value="<?= $d ?>" <?= $default_district === $d ? 'selected' : '' ?>><?= $d === 'Other' ? 'Other District' : $d ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Delivery Instructions / Courier Notes -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Courier / Delivery Notes (Optional)</label>
                            <input type="text" name="order_notes" class="form-control form-control-sm" placeholder="e.g. Call before arriving, leave with security" value="<?= htmlspecialchars($default_notes) ?>">
                        </div>

                        <!-- Promo Code & Quick Voucher Chips Section -->
                        <div class="mb-3 pb-3 border-bottom">
                            <label class="form-label fw-semibold small text-navy d-flex align-items-center justify-content-between mb-1.5">
                                <span><i class="bi bi-tag-fill text-teal me-1"></i> Have a Promo Code?</span>
                                <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Verified Coupons</span>
                            </label>
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" name="promo_code" id="promoCodeInput" class="form-control text-uppercase fw-semibold" placeholder="Enter coupon code...">
                                <button type="button" class="btn btn-teal fw-semibold" onclick="applyPromoCode()">Apply</button>
                            </div>

                            <!-- Clickable Quick Voucher Chips -->
                            <div class="d-flex flex-wrap gap-1 mb-1">
                                <button type="button" class="badge rounded-pill promo-chip text-decoration-none border-0" onclick="applyQuickPromo('BOOKSY10')">
                                    <i class="bi bi-tag-fill me-1"></i> BOOKSY10 (10% OFF)
                                </button>
                                <button type="button" class="badge rounded-pill promo-chip text-decoration-none border-0" onclick="applyQuickPromo('STUDENT500')">
                                    <i class="bi bi-mortarboard-fill me-1"></i> STUDENT500 (Rs. 500 OFF)
                                </button>
                                <button type="button" class="badge rounded-pill promo-chip text-decoration-none border-0" onclick="applyQuickPromo('FREESHIP')">
                                    <i class="bi bi-truck me-1"></i> FREESHIP
                                </button>
                            </div>
                            <div id="promoFeedback" class="small mt-1 text-muted" style="font-size: 0.75rem;">Click any voucher badge above to instantly apply discount.</div>
                        </div>

                        <!-- Payment Method Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold small text-navy text-uppercase mb-0">Select Payment Method *</label>
                                <span class="badge bg-light text-secondary border small" style="font-size: 0.68rem;"><i class="bi bi-shield-lock-fill text-teal me-1"></i>256-Bit Encrypted</span>
                            </div>
                            
                            <div class="d-flex flex-column gap-2 mb-3">
                                
                                <!-- Method 1: Credit / Debit Card -->
                                <label class="payment-method-card <?= $default_payment_mode === 'Credit / Debit Card' ? 'selected' : '' ?> d-flex align-items-center gap-3">
                                    <input type="radio" name="payment_mode" value="Credit / Debit Card" <?= $default_payment_mode === 'Credit / Debit Card' ? 'checked' : '' ?> class="form-check-input mt-0" onchange="togglePaymentBox('card')">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="fw-bold small text-navy">
                                                <i class="bi bi-credit-card-2-front-fill me-1 text-teal"></i> Credit / Debit Card
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-primary text-white font-monospace" style="font-size: 0.62rem;">VISA</span>
                                                <span class="badge bg-danger text-white font-monospace" style="font-size: 0.62rem;">MC</span>
                                                <span class="badge bg-info text-white font-monospace" style="font-size: 0.62rem;">AMEX</span>
                                            </div>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.74rem;">Instant direct payment with 3D Secure verification</div>
                                    </div>
                                </label>

                                <!-- Method 2: Cash on Delivery -->
                                <label class="payment-method-card <?= $default_payment_mode === 'Cash on Delivery' ? 'selected' : '' ?> d-flex align-items-center gap-3">
                                    <input type="radio" name="payment_mode" value="Cash on Delivery" <?= $default_payment_mode === 'Cash on Delivery' ? 'checked' : '' ?> class="form-check-input mt-0" onchange="togglePaymentBox('cod')">
                                    <div>
                                        <div class="fw-bold small text-navy"><i class="bi bi-cash-stack me-1 text-success"></i> Cash on Delivery (COD)</div>
                                        <div class="text-muted" style="font-size: 0.74rem;">Pay when the book parcel arrives at your doorstep</div>
                                    </div>
                                </label>
                                
                                <!-- Method 3: Direct Bank Transfer -->
                                <label class="payment-method-card <?= (strpos($default_payment_mode, 'Bank Transfer') !== false) ? 'selected' : '' ?> d-flex align-items-center gap-3">
                                    <input type="radio" name="payment_mode" value="Bank Transfer" <?= (strpos($default_payment_mode, 'Bank Transfer') !== false) ? 'checked' : '' ?> class="form-check-input mt-0" onchange="togglePaymentBox('bank')">
                                    <div>
                                        <div class="fw-bold small text-navy"><i class="bi bi-bank me-1 text-primary"></i> Direct Bank Transfer</div>
                                        <div class="text-muted" style="font-size: 0.74rem;">Deposit to escrow account / online banking slip upload</div>
                                    </div>
                                </label>

                                <!-- Method 4: Online Payment (PayHere) -->
                                <label class="payment-method-card <?= $default_payment_mode === 'Online Payment (PayHere)' ? 'selected' : '' ?> d-flex align-items-center gap-3">
                                    <input type="radio" name="payment_mode" value="Online Payment (PayHere)" <?= $default_payment_mode === 'Online Payment (PayHere)' ? 'checked' : '' ?> class="form-check-input mt-0" onchange="togglePaymentBox('payhere')">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="fw-bold small text-navy">
                                                <i class="bi bi-wallet2 me-1 text-teal"></i> PayHere Online Gateway
                                            </div>
                                            <span class="badge bg-teal-subtle text-teal font-monospace" style="font-size: 0.65rem;">SECURE GATEWAY</span>
                                        </div>
                                        <div class="text-muted small d-flex align-items-center gap-1 mt-0.5" style="font-size: 0.72rem;">
                                            <span>Visa, MasterCard, Genie, eZ Cash, Frimi</span>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- ========================================================= -->
                            <!-- EXPANDABLE PAYMENT DETAILS BOXES                          -->
                            <!-- ========================================================= -->

                            <!-- 1. CARD DETAILS INPUT CONTAINER -->
                            <div id="cardDetailsBox" class="p-3 bg-light rounded-3 border mb-3 <?= $default_payment_mode === 'Credit / Debit Card' ? '' : 'd-none' ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-credit-card-2-front-fill text-teal fs-5"></i>
                                        <div>
                                            <strong class="text-navy small d-block">Enter Card Details</strong>
                                            <span class="text-muted" style="font-size: 0.7rem;">Enter your 16-digit debit or credit card</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-teal rounded-pill px-2.5 py-1 text-nowrap" style="font-size: 0.72rem;" onclick="fillDemoCard()">
                                        <i class="bi bi-lightning-fill text-gold me-1"></i> Auto-Fill Sample Card
                                    </button>
                                </div>

                                <!-- Card Brands Row -->
                                <div class="d-flex align-items-center gap-1.5 mb-2.5" id="cardBrandIcons">
                                    <span class="card-brand-pill badge bg-white text-dark border px-2 py-1" id="badgeVisa"><i class="bi bi-credit-card text-primary me-1"></i>Visa</span>
                                    <span class="card-brand-pill badge bg-white text-dark border px-2 py-1" id="badgeMastercard"><i class="bi bi-credit-card-2-front text-danger me-1"></i>Mastercard</span>
                                    <span class="card-brand-pill badge bg-white text-dark border px-2 py-1" id="badgeAmex"><i class="bi bi-credit-card-2-back text-info me-1"></i>AMEX</span>
                                    <span class="card-brand-pill badge bg-white text-dark border px-2 py-1" id="badgeGeneric"><i class="bi bi-shield-check text-success me-1"></i>Secure</span>
                                </div>

                                <!-- Cardholder Name -->
                                <div class="mb-2">
                                    <label class="form-label fw-bold text-navy mb-1" style="font-size: 0.72rem; text-transform: uppercase;">Name on Card *</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                                        <input type="text" name="card_name" id="cardNameInput" class="form-control text-uppercase" placeholder="e.g. KAMAL PERERA" value="<?= htmlspecialchars($default_card_name) ?>">
                                    </div>
                                </div>

                                <!-- Card Number -->
                                <div class="mb-2">
                                    <label class="form-label fw-bold text-navy mb-1" style="font-size: 0.72rem; text-transform: uppercase;">Card Number *</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white" id="cardNumberIcon"><i class="bi bi-credit-card text-muted"></i></span>
                                        <input type="text" name="card_number" id="cardNumberInput" class="form-control font-monospace" placeholder="4111 2222 3333 4444" maxlength="19" value="<?= htmlspecialchars($default_card_number) ?>" oninput="formatCardNumber(this)">
                                    </div>
                                </div>

                                <!-- Expiry and CVV Row -->
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label fw-bold text-navy mb-1" style="font-size: 0.72rem; text-transform: uppercase;">Expiry Date *</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white"><i class="bi bi-calendar3 text-muted"></i></span>
                                            <input type="text" name="card_expiry" id="cardExpiryInput" class="form-control font-monospace" placeholder="MM / YY" maxlength="7" value="<?= htmlspecialchars($default_card_expiry) ?>" oninput="formatCardExpiry(this)">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label fw-bold text-navy mb-0" style="font-size: 0.72rem; text-transform: uppercase;">CVV / CVC *</label>
                                            <span class="text-muted" style="font-size: 0.65rem;" title="3-digit code on back of card" data-bs-toggle="tooltip"><i class="bi bi-info-circle"></i> 3 digits</span>
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                                            <input type="password" name="card_cvv" id="cardCvvInput" class="form-control font-monospace" placeholder="123" maxlength="4" value="<?= htmlspecialchars($default_card_cvv) ?>" oninput="formatCardCVV(this)">
                                        </div>
                                    </div>
                                </div>

                                <!-- Security Guarantee Strip -->
                                <div class="d-flex align-items-center justify-content-between pt-1 text-muted" style="font-size: 0.68rem;">
                                    <span><i class="bi bi-shield-fill-check text-success me-1"></i>256-Bit SSL Encrypted</span>
                                    <span><i class="bi bi-patch-check-fill text-teal me-1"></i>PCI-DSS Compliant</span>
                                    <span><i class="bi bi-lock-fill text-primary me-1"></i>3D Secure 2.0</span>
                                </div>
                            </div>

                            <!-- 2. BANK DETAILS INPUT CONTAINER -->
                            <div id="bankDetailsBox" class="p-3 bg-light rounded-3 border mb-3 <?= (strpos($default_payment_mode, 'Bank Transfer') !== false) ? '' : 'd-none' ?>">
                                <div class="d-flex align-items-start gap-2 mb-2 pb-2 border-bottom">
                                    <i class="bi bi-bank text-primary fs-5"></i>
                                    <div>
                                        <strong class="text-navy small d-block">Booksy Escrow Account Details</strong>
                                        <span class="text-muted" style="font-size: 0.7rem;">Deposit funds to the verified escrow account below</span>
                                    </div>
                                </div>
                                <div class="p-2.5 bg-white rounded-2 border mb-3 small" style="font-size: 0.75rem;">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Bank Name:</span>
                                        <strong class="text-navy">Commercial Bank of Ceylon</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Account Name:</span>
                                        <strong class="text-navy">Booksy Escrow Services</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Account Number:</span>
                                        <strong class="text-teal font-monospace">8009-4567-1234</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Branch / City:</span>
                                        <span class="text-dark">Colombo Main Branch</span>
                                    </div>
                                </div>

                                <!-- Depositor Name -->
                                <div class="mb-2">
                                    <label class="form-label fw-bold text-navy mb-1" style="font-size: 0.72rem; text-transform: uppercase;">Depositor / Sender Name *</label>
                                    <input type="text" name="bank_sender_name" id="bankSenderInput" class="form-control form-control-sm" placeholder="e.g. Kasun Jayasinghe" value="<?= htmlspecialchars($default_bank_sender) ?>">
                                </div>

                                <!-- Bank Reference Number -->
                                <div class="mb-2">
                                    <label class="form-label fw-bold text-navy mb-1" style="font-size: 0.72rem; text-transform: uppercase;">Bank Transfer Reference / Transaction ID *</label>
                                    <input type="text" name="bank_ref" id="bankRefInput" class="form-control form-control-sm font-monospace" placeholder="e.g. CB-9824102 / Deposit Slip No" value="<?= htmlspecialchars($default_bank_ref) ?>">
                                </div>

                                <!-- Bank Slip Upload -->
                                <div class="mb-1">
                                    <label class="form-label fw-bold text-navy mb-1" style="font-size: 0.72rem; text-transform: uppercase;">Upload Deposit Slip / Screenshot (Optional)</label>
                                    <input type="file" name="bank_slip" id="bankSlipInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,application/pdf" onchange="onBankSlipSelected(this)">
                                    <div id="bankSlipFeedback" class="small text-muted mt-1" style="font-size: 0.7rem;">Supported: JPG, PNG, PDF (Max 5MB)</div>
                                </div>
                            </div>

                            <!-- 3. PAYHERE DETAILS CONTAINER -->
                            <div id="payhereDetailsBox" class="alert alert-info small mb-3 <?= $default_payment_mode === 'Online Payment (PayHere)' ? '' : 'd-none' ?> rounded-3 border">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-shield-check text-success fs-5"></i>
                                    <div>
                                        <strong class="text-navy">PayHere Payment Gateway Active:</strong><br>
                                        <span>You will be securely routed through the official PayHere payment portal (Merchant ID: <code>1238355</code>) with 256-bit SSL encryption.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. CASH ON DELIVERY CONTAINER -->
                            <div id="codDetailsBox" class="p-2.5 bg-success-subtle text-success rounded-3 border border-success-subtle mb-3 small <?= $default_payment_mode === 'Cash on Delivery' ? '' : 'd-none' ?>" style="font-size: 0.75rem;">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-cash-coin fs-5 flex-shrink-0"></i>
                                    <div>
                                        <strong>Doorstep Cash Payment:</strong> Please keep the exact cash amount ready for the courier delivery partner upon handover.
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Order Summary Box with Live Pricing -->
                        <div class="bg-light p-3 rounded-3 mb-4 border">
                            <div class="d-flex justify-content-between small text-muted mb-1.5">
                                <span>Items Subtotal (<span id="checkoutSummaryItemsCount"><?= $total_items_count ?></span>):</span>
                                <strong id="checkoutSummarySubtotal">Rs. <?= number_format($total_price, 2) ?></strong>
                            </div>
                            <div id="promoDiscountRow" class="d-flex justify-content-between small text-success fw-semibold mb-1.5 d-none">
                                <span class="d-flex align-items-center gap-1">
                                    <span>Voucher Discount:</span>
                                    <a href="javascript:void(0)" onclick="removePromoCode()" class="text-danger small text-decoration-none" title="Remove voucher">(Remove)</a>
                                </span>
                                <span id="promoDiscountAmount">-Rs. 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-2">
                                <span>Estimated Courier:</span>
                                <span class="badge bg-success text-white fw-bold" id="checkoutDeliveryEstimate">FREE (Promotional)</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-navy">Total Order Amount:</span>
                                <span class="fw-extrabold fs-4 text-navy" id="checkoutFinalTotal">Rs. <?= number_format($total_price, 2) ?></span>
                            </div>
                        </div>

                        <button type="submit" name="place_order" id="placeOrderBtn" class="btn btn-booksy-primary btn-lg w-100 fw-bold py-3 shadow">
                            <i class="bi bi-shield-check me-2"></i> <span id="orderBtnText"><?= $default_payment_mode === 'Credit / Debit Card' ? 'Pay Rs. ' . number_format($total_price, 2) . ' with Card' : ($default_payment_mode === 'Online Payment (PayHere)' ? 'Proceed to PayHere Gateway' : 'Confirm & Place Order (Rs. ' . number_format($total_price, 2) . ')') ?></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>