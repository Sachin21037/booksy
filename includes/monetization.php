<?php
/**
 * Booksy - Monetization & Commission Engine
 * Handles seller monthly sales thresholds, commissions, promotional boosts, and VIP memberships.
 */

if (!defined('BOOKSY_MONETIZATION_LOADED')) {
    define('BOOKSY_MONETIZATION_LOADED', true);
}

/**
 * Fetch all dynamic monetization settings with safe defaults.
 */
function get_monetization_settings(PDO $pdo): array {
    $defaults = [
        'commission_free_threshold'   => 5000.00,
        'standard_commission_rate'    => 0.03,
        'boost_featured_fee'          => 150.00,
        'boost_featured_duration_days'=> 7,
        'boost_homepage_fee'          => 350.00,
        'boost_homepage_duration_days'=> 3,
        'boost_category_fee'          => 250.00,
        'boost_category_duration_days'=> 7,
        'boost_search_fee'            => 150.00,
        'boost_search_duration_days'  => 7,
        'vip_monthly_price'           => 950.00
    ];

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM monetization_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($defaults as $key => $defaultVal) {
            if (isset($rows[$key])) {
                $defaults[$key] = is_numeric($defaultVal) ? floatval($rows[$key]) : $rows[$key];
            }
        }
    } catch (PDOException $e) {
        // Table not initialized or connection issue; return safe defaults
    }

    return $defaults;
}

/**
 * Update or insert a monetization setting.
 */
function update_monetization_setting(PDO $pdo, string $key, string $value, ?string $description = null): bool {
    $stmt = $pdo->prepare("
        INSERT INTO monetization_settings (setting_key, setting_value, description) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            description = COALESCE(VALUES(description), description)
    ");
    return $stmt->execute([$key, $value, $description]);
}

/**
 * Check if a user has an active Booksy VIP Membership.
 */
function is_user_vip(PDO $pdo, int $userId): bool {
    if ($userId <= 0) return false;
    try {
        $stmt = $pdo->prepare("
            SELECT membership_id 
            FROM memberships 
            WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Fetch detailed active VIP membership info for a user.
 */
function get_user_vip_details(PDO $pdo, int $userId): ?array {
    if ($userId <= 0) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM memberships 
            WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()
            ORDER BY end_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get a seller's monthly sales, commission threshold status, and earnings summary.
 */
function get_seller_monthly_sales_summary(PDO $pdo, int $sellerId, ?int $month = null, ?int $year = null): array {
    $month = $month ?: intval(date('n'));
    $year  = $year  ?: intval(date('Y'));
    
    $settings = get_monetization_settings($pdo);
    $threshold = floatval($settings['commission_free_threshold']);
    $standardRate = floatval($settings['standard_commission_rate']);
    $isVip = is_user_vip($pdo, $sellerId);

    // Calculate total verified completed sales for this seller in the given month
    // Only completed orders count towards monthly sales
    $salesStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(oi.price * oi.quantity), 0.00) AS total_sales,
            COUNT(DISTINCT o.id) AS completed_orders_count
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN books b ON oi.book_id = b.id
        WHERE b.seller_id = ? 
          AND o.status = 'Completed'
          AND MONTH(o.created_at) = ? 
          AND YEAR(o.created_at) = ?
    ");
    $salesStmt->execute([$sellerId, $month, $year]);
    $salesData = $salesStmt->fetch();

    $totalSales = floatval($salesData['total_sales'] ?? 0.00);
    $completedCount = intval($salesData['completed_orders_count'] ?? 0);

    // Determine commission rate & amounts
    $isOverThreshold = ($totalSales > $threshold);
    $rate = 0.00;

    if ($isVip) {
        $rate = 0.00; // VIP members enjoy 0% flat commission
    } elseif ($isOverThreshold) {
        $rate = $standardRate; // 3%
    } else {
        $rate = 0.00; // 0% under LKR 5,000
    }

    // Calculate itemized commission amount from order_commissions table if records exist,
    // or calculate based on total completed volume
    $commStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(commission_amount), 0.00) AS total_commission,
            COALESCE(SUM(net_earnings), 0.00) AS total_net
        FROM order_commissions oc
        JOIN orders o ON oc.order_id = o.id
        WHERE oc.seller_id = ? 
          AND oc.status != 'refunded'
          AND MONTH(o.created_at) = ? 
          AND YEAR(o.created_at) = ?
    ");
    $commStmt->execute([$sellerId, $month, $year]);
    $commData = $commStmt->fetch();

    $commissionAmount = floatval($commData['total_commission'] ?? 0.00);
    
    // If order_commissions were not pre-calculated, calculate deterministically
    if ($commissionAmount == 0.00 && $totalSales > 0 && $rate > 0) {
        $commissionAmount = round($totalSales * $rate, 2);
    }
    
    $netEarnings = max(0.00, $totalSales - $commissionAmount);
    $thresholdRemaining = max(0.00, $threshold - $totalSales);
    $progressPercent = min(100, round(($totalSales / max(1, $threshold)) * 100, 1));

    // Upsert into seller_monthly_sales table for fast historical caching
    try {
        $upsertStmt = $pdo->prepare("
            INSERT INTO seller_monthly_sales 
                (seller_id, month, year, total_sales, commission_rate, commission_amount, net_earnings, completed_orders_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                total_sales = VALUES(total_sales),
                commission_rate = VALUES(commission_rate),
                commission_amount = VALUES(commission_amount),
                net_earnings = VALUES(net_earnings),
                completed_orders_count = VALUES(completed_orders_count)
        ");
        $upsertStmt->execute([
            $sellerId, $month, $year, $totalSales, $rate, $commissionAmount, $netEarnings, $completedCount
        ]);
    } catch (PDOException $e) {
        // Continue gracefully
    }

    return [
        'seller_id'            => $sellerId,
        'month'                => $month,
        'year'                 => $year,
        'month_name'           => date('F', mktime(0, 0, 0, $month, 10)),
        'total_sales'          => $totalSales,
        'threshold'            => $threshold,
        'threshold_remaining'  => $thresholdRemaining,
        'is_over_threshold'    => $isOverThreshold,
        'commission_rate'      => $rate,
        'commission_rate_pct'  => ($rate * 100) . '%',
        'commission_amount'    => $commissionAmount,
        'net_earnings'         => $netEarnings,
        'completed_orders_count'=> $completedCount,
        'is_vip'               => $isVip,
        'progress_percent'     => $progressPercent
    ];
}

/**
 * Process commission calculation for a completed order.
 * Invoked atomically when an order transitions to 'Completed'.
 */
function process_order_completed_commission(PDO $pdo, int $orderId): array {
    // 1. Fetch Order details
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order || $order['status'] !== 'Completed') {
        return ['status' => 'skipped', 'message' => 'Order not found or not in Completed state.'];
    }

    // 2. Fetch Order Items with seller info
    $itemsStmt = $pdo->prepare("
        SELECT oi.*, b.seller_id, b.title 
        FROM order_items oi
        JOIN books b ON oi.book_id = b.id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();

    if (empty($items)) {
        return ['status' => 'empty', 'message' => 'No order items found.'];
    }

    $settings = get_monetization_settings($pdo);
    $threshold = floatval($settings['commission_free_threshold']);
    $standardRate = floatval($settings['standard_commission_rate']);
    
    $orderMonth = intval(date('n', strtotime($order['created_at'])));
    $orderYear  = intval(date('Y', strtotime($order['created_at'])));

    $results = [];

    foreach ($items as $item) {
        $sellerId  = intval($item['seller_id']);
        $itemTotal = floatval($item['price'] * $item['quantity']);
        $isVip     = is_user_vip($pdo, $sellerId);

        // Calculate seller's completed monthly sales before/including this order
        $summary = get_seller_monthly_sales_summary($pdo, $sellerId, $orderMonth, $orderYear);
        $sellerMonthlyTotal = $summary['total_sales'];

        // Determine rate
        $rate = 0.00;
        if ($isVip) {
            $rate = 0.00;
        } elseif ($sellerMonthlyTotal > $threshold) {
            $rate = $standardRate; // 3%
        } else {
            $rate = 0.00; // 0%
        }

        $commissionAmount = round($itemTotal * $rate, 2);
        $netEarnings = max(0.00, $itemTotal - $commissionAmount);

        // Upsert into order_commissions
        $commStmt = $pdo->prepare("
            INSERT INTO order_commissions 
                (seller_id, order_id, sale_amount, commission_rate, commission_amount, net_earnings, status)
            VALUES (?, ?, ?, ?, ?, ?, 'deducted')
            ON DUPLICATE KEY UPDATE 
                sale_amount = VALUES(sale_amount),
                commission_rate = VALUES(commission_rate),
                commission_amount = VALUES(commission_amount),
                net_earnings = VALUES(net_earnings),
                status = 'deducted'
        ");
        $commStmt->execute([$sellerId, $orderId, $itemTotal, $rate, $commissionAmount, $netEarnings]);

        // Audit Log
        if ($commissionAmount > 0) {
            $auditStmt = $pdo->prepare("
                INSERT INTO financial_transactions (user_id, transaction_type, amount, reference_id, notes)
                VALUES (?, 'commission_deduction', ?, ?, ?)
            ");
            $auditStmt->execute([
                $sellerId, 
                $commissionAmount, 
                "ORD-$orderId", 
                "Commission ({$rate}x) on book sale: {$item['title']} (Order #$orderId)"
            ]);
        }

        // Re-sync monthly summary table
        get_seller_monthly_sales_summary($pdo, $sellerId, $orderMonth, $orderYear);

        $results[] = [
            'seller_id'         => $sellerId,
            'book_title'        => $item['title'],
            'item_total'        => $itemTotal,
            'commission_rate'   => $rate,
            'commission_amount' => $commissionAmount,
            'net_earnings'      => $netEarnings,
            'is_vip'            => $isVip
        ];
    }

    return ['status' => 'success', 'items' => $results];
}

/**
 * Handle Order Refund & Reverse Sales/Commission.
 */
function process_order_refund_reversal(PDO $pdo, int $orderId, string $reason = 'Customer requested refund'): bool {
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();

    if (!$order) return false;

    // Update order status to Refunded
    $updOrder = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
    $updOrder->execute([$orderId]);

    // Mark commission records as refunded
    $updComm = $pdo->prepare("UPDATE order_commissions SET status = 'refunded' WHERE order_id = ?");
    $updComm->execute([$orderId]);

    // Fetch affected sellers and re-evaluate their monthly summaries
    $itemsStmt = $pdo->prepare("
        SELECT DISTINCT b.seller_id 
        FROM order_items oi
        JOIN books b ON oi.book_id = b.id
        WHERE oi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $sellers = $itemsStmt->fetchAll(PDO::FETCH_COLUMN);

    $orderMonth = intval(date('n', strtotime($order['created_at'])));
    $orderYear  = intval(date('Y', strtotime($order['created_at'])));

    foreach ($sellers as $sellerId) {
        // Re-sync seller summary with order refunded
        get_seller_monthly_sales_summary($pdo, intval($sellerId), $orderMonth, $orderYear);

        // Record Audit log
        $auditStmt = $pdo->prepare("
            INSERT INTO financial_transactions (user_id, transaction_type, amount, reference_id, notes)
            VALUES (?, 'refund_reversal', ?, ?, ?)
        ");
        $auditStmt->execute([
            $sellerId,
            floatval($order['total_amount']),
            "ORD-$orderId",
            "Refund reversal for Order #$orderId: $reason"
        ]);
    }

    return true;
}

/**
 * Purchase a Promotional Boost for a book listing.
 */
function purchase_listing_boost(PDO $pdo, int $sellerId, int $bookId, string $boostType): array {
    // 1. Verify seller owns this book and it is available
    $bookStmt = $pdo->prepare("SELECT id, seller_id, title, status FROM books WHERE id = ? LIMIT 1");
    $bookStmt->execute([$bookId]);
    $book = $bookStmt->fetch();

    if (!$book || intval($book['seller_id']) !== $sellerId) {
        return ['success' => false, 'message' => 'You do not own this book listing.'];
    }

    if ($book['status'] !== 'available') {
        return ['success' => false, 'message' => 'Cannot boost a sold or inactive book listing.'];
    }

    $settings = get_monetization_settings($pdo);

    $feeKey = 'boost_featured_fee';
    $durKey = 'boost_featured_duration_days';

    if ($boostType === 'homepage_boost') {
        $feeKey = 'boost_homepage_fee';
        $durKey = 'boost_homepage_duration_days';
    } elseif ($boostType === 'category_boost') {
        $feeKey = 'boost_category_fee';
        $durKey = 'boost_category_duration_days';
    } elseif ($boostType === 'search_boost') {
        $feeKey = 'boost_search_fee';
        $durKey = 'boost_search_duration_days';
    } else {
        $boostType = 'featured_listing';
    }

    $fee = floatval($settings[$feeKey] ?? 150.00);
    $days = intval($settings[$durKey] ?? 7);

    // Insert promotional listing
    $insertStmt = $pdo->prepare("
        INSERT INTO promotional_listings 
            (seller_id, book_id, promotion_type, price, start_date, end_date, status)
        VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active')
    ");
    $insertStmt->execute([$sellerId, $bookId, $boostType, $fee, $days]);
    $promoId = $pdo->lastInsertId();

    // Audit Log
    $auditStmt = $pdo->prepare("
        INSERT INTO financial_transactions (user_id, transaction_type, amount, reference_id, notes)
        VALUES (?, 'boost_purchase', ?, ?, ?)
    ");
    $auditStmt->execute([
        $sellerId,
        $fee,
        "PROMO-$promoId",
        "Purchased $boostType for '{$book['title']}' ($days days)"
    ]);

    return [
        'success'      => true,
        'promo_id'     => $promoId,
        'boost_type'   => $boostType,
        'fee'          => $fee,
        'duration_days'=> $days,
        'message'      => "Boost activated successfully for '{$book['title']}'!"
    ];
}

/**
 * Purchase / Subscribe to Booksy VIP Membership.
 */
function purchase_vip_membership(PDO $pdo, int $userId, string $planType = 'monthly'): array {
    $settings = get_monetization_settings($pdo);
    $price = floatval($settings['vip_monthly_price'] ?? 950.00);
    $months = ($planType === 'annual') ? 12 : 1;
    if ($planType === 'annual') {
        $price = round($price * 10, 2); // 10 months price for annual plan
    }

    // Insert membership record
    $insStmt = $pdo->prepare("
        INSERT INTO memberships (user_id, membership_type, price, start_date, end_date, status)
        VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? MONTH), 'active')
    ");
    $insStmt->execute([$userId, $planType, $price, $months]);
    $memId = $pdo->lastInsertId();

    // Audit Log
    $auditStmt = $pdo->prepare("
        INSERT INTO financial_transactions (user_id, transaction_type, amount, reference_id, notes)
        VALUES (?, 'membership_purchase', ?, ?, ?)
    ");
    $auditStmt->execute([
        $userId,
        $price,
        "VIP-$memId",
        "Subscribed to Booksy VIP Membership ($planType)"
    ]);

    return [
        'success'    => true,
        'membership_id' => $memId,
        'price'      => $price,
        'end_date'   => date('Y-m-d', strtotime("+$months months")),
        'message'    => 'Welcome to Booksy VIP! You now enjoy 0% commission on all sales.'
    ];
}

/**
 * Get active listing boosts for a given book or all active books.
 */
function get_active_listing_promotions(PDO $pdo, ?int $bookId = null): array {
    try {
        if ($bookId !== null) {
            $stmt = $pdo->prepare("
                SELECT * 
                FROM promotional_listings 
                WHERE book_id = ? AND status = 'active' AND end_date >= NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$bookId]);
            $res = $stmt->fetch();
            return $res ? [$res] : [];
        } else {
            $stmt = $pdo->query("
                SELECT pl.*, b.title, b.author, b.price AS book_price, b.image_url, u.name AS seller_name
                FROM promotional_listings pl
                JOIN books b ON pl.book_id = b.id
                JOIN users u ON pl.seller_id = u.id
                WHERE pl.status = 'active' AND pl.end_date >= NOW()
                ORDER BY pl.created_at DESC
            ");
            return $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get platform-wide monetization analytics for the Admin Dashboard.
 */
function get_admin_monetization_analytics(PDO $pdo, ?int $month = null, ?int $year = null): array {
    $month = $month ?: intval(date('n'));
    $year  = $year  ?: intval(date('Y'));

    // 1. Total Marketplace GMV (Completed Orders)
    $gmvStmt = $pdo->query("
        SELECT COALESCE(SUM(total_amount), 0.00) AS all_time_gmv, COUNT(id) AS total_completed_orders 
        FROM orders 
        WHERE status = 'Completed'
    ");
    $gmvData = $gmvStmt->fetch();
    $allTimeGmv = floatval($gmvData['all_time_gmv']);
    $totalCompletedOrders = intval($gmvData['total_completed_orders']);

    // 2. Current Month GMV
    $mGmvStmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0.00) AS monthly_gmv, COUNT(id) AS monthly_orders 
        FROM orders 
        WHERE status = 'Completed' AND MONTH(created_at) = ? AND YEAR(created_at) = ?
    ");
    $mGmvStmt->execute([$month, $year]);
    $mGmvData = $mGmvStmt->fetch();
    $monthlyGmv = floatval($mGmvData['monthly_gmv']);
    $monthlyCompletedOrders = intval($mGmvData['monthly_orders']);

    // 3. Commission Revenues
    $commStmt = $pdo->query("
        SELECT 
            COALESCE(SUM(commission_amount), 0.00) AS total_commission_revenue 
        FROM order_commissions 
        WHERE status != 'refunded'
    ");
    $totalCommissionRevenue = floatval($commStmt->fetchColumn() ?: 0.00);

    $mCommStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(oc.commission_amount), 0.00) AS monthly_commission_revenue 
        FROM order_commissions oc
        JOIN orders o ON oc.order_id = o.id
        WHERE oc.status != 'refunded' AND MONTH(o.created_at) = ? AND YEAR(o.created_at) = ?
    ");
    $mCommStmt->execute([$month, $year]);
    $monthlyCommissionRevenue = floatval($mCommStmt->fetchColumn() ?: 0.00);

    // 4. Revenue from Listing Boosts
    $boostRevStmt = $pdo->query("
        SELECT COALESCE(SUM(price), 0.00) FROM promotional_listings
    ");
    $totalBoostRevenue = floatval($boostRevStmt->fetchColumn() ?: 0.00);

    // 5. Revenue from VIP Memberships
    $vipRevStmt = $pdo->query("
        SELECT COALESCE(SUM(price), 0.00) FROM memberships
    ");
    $totalVipRevenue = floatval($vipRevStmt->fetchColumn() ?: 0.00);

    // 6. Student Subsidy (Sales processed under 0% commission)
    $subStmt = $pdo->query("
        SELECT COALESCE(SUM(sale_amount), 0.00) 
        FROM order_commissions 
        WHERE commission_rate = 0.0000 AND status != 'refunded'
    ");
    $studentSubsidizedVolume = floatval($subStmt->fetchColumn() ?: 0.00);

    // 7. Top Sellers Leaderboard with Threshold Status
    $topSellersStmt = $pdo->prepare("
        SELECT 
            u.id AS seller_id, 
            u.name AS seller_name, 
            u.email AS seller_email, 
            COALESCE(MAX(sms.total_sales), 0.00) AS current_month_sales,
            COALESCE(MAX(sms.commission_amount), 0.00) AS commission_paid,
            COALESCE(MAX(sms.net_earnings), 0.00) AS net_earnings,
            COALESCE(MAX(sms.completed_orders_count), 0) AS orders_count,
            MAX(CASE WHEN m.membership_id IS NOT NULL THEN 1 ELSE 0 END) AS is_vip
        FROM users u
        LEFT JOIN seller_monthly_sales sms ON u.id = sms.seller_id AND sms.month = ? AND sms.year = ?
        LEFT JOIN memberships m ON u.id = m.user_id AND m.status = 'active' AND m.end_date >= CURDATE()
        WHERE u.role != 'admin' OR sms.total_sales > 0
        GROUP BY u.id, u.name, u.email
        ORDER BY current_month_sales DESC
        LIMIT 10
    ");
    $topSellersStmt->execute([$month, $year]);
    $topSellers = $topSellersStmt->fetchAll();

    // 8. Count of Sellers exceeding vs below LKR 5,000 threshold
    $settings = get_monetization_settings($pdo);
    $threshold = floatval($settings['commission_free_threshold']);

    $thresholdCounts = [
        'under_threshold_count' => 0,
        'over_threshold_count'  => 0,
        'vip_count'             => 0
    ];

    foreach ($topSellers as $s) {
        if ($s['is_vip']) {
            $thresholdCounts['vip_count']++;
        } elseif ($s['current_month_sales'] > $threshold) {
            $thresholdCounts['over_threshold_count']++;
        } else {
            $thresholdCounts['under_threshold_count']++;
        }
    }

    return [
        'all_time_gmv'              => $allTimeGmv,
        'total_completed_orders'    => $totalCompletedOrders,
        'monthly_gmv'               => $monthlyGmv,
        'monthly_completed_orders'  => $monthlyCompletedOrders,
        'total_commission_revenue'  => $totalCommissionRevenue,
        'monthly_commission_revenue'=> $monthlyCommissionRevenue,
        'total_boost_revenue'       => $totalBoostRevenue,
        'total_vip_revenue'         => $totalVipRevenue,
        'total_platform_revenue'    => ($totalCommissionRevenue + $totalBoostRevenue + $totalVipRevenue),
        'student_subsidized_volume' => $studentSubsidizedVolume,
        'top_sellers'               => $topSellers,
        'threshold_counts'          => $thresholdCounts,
        'settings'                  => $settings
    ];
}
