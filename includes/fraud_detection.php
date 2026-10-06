<?php
/**
 * Booksy - C2C Fraud Detection, Risk Scoring & Moderation Engine
 * Implements Buyer Reporting flow, Automated Risk Signals, Heuristic Anomaly Detectors,
 * Composite Triage Risk Scoring, and Graduated Enforcement Logging.
 */

if (!defined('BOOKSY_FRAUD_ENGINE_LOADED')) {
    define('BOOKSY_FRAUD_ENGINE_LOADED', true);
}

/**
 * Ensure all fraud detection, reporting, and moderation audit tables exist in the database.
 */
function ensure_fraud_tables(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;

    try {
        // 1. Create or ensure reports table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `reports` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `reporter_id` INT UNSIGNED NOT NULL,
              `reported_user_id` INT UNSIGNED NULL,
              `book_id` INT UNSIGNED NULL,
              `order_id` INT UNSIGNED NULL,
              `reason` ENUM('counterfeit','not_received','off_platform_payment','fake_profile','misleading','inappropriate','spam','other') NOT NULL,
              `details` TEXT NOT NULL,
              `attachments` JSON NULL,
              `risk_score` INT UNSIGNED NOT NULL DEFAULT 0,
              `risk_level` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
              `risk_signals` JSON NULL,
              `status` ENUM('pending', 'reviewing', 'action_taken', 'dismissed') NOT NULL DEFAULT 'pending',
              `penalty_applied` ENUM('none', 'level1_warning', 'level2_restriction', 'level3_ban') NOT NULL DEFAULT 'none',
              `moderation_notes` TEXT NULL,
              `moderated_by` INT UNSIGNED NULL,
              `moderated_at` DATETIME NULL,
              `ip_address` VARCHAR(45) NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_reports_status_risk` (`status`, `risk_level`, `risk_score`),
              INDEX `idx_reports_target_user` (`reported_user_id`),
              INDEX `idx_reports_target_book` (`book_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 2. Create automated fraud signals table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `fraud_signals` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `user_id` INT UNSIGNED NULL,
              `book_id` INT UNSIGNED NULL,
              `signal_type` VARCHAR(100) NOT NULL,
              `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
              `score_impact` INT NOT NULL DEFAULT 20,
              `details` TEXT NULL,
              `status` ENUM('active', 'acknowledged', 'resolved') NOT NULL DEFAULT 'active',
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_signals_user_status` (`user_id`, `status`),
              INDEX `idx_signals_book_status` (`book_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. Create moderation audit log table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `moderation_logs` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `report_id` INT UNSIGNED NULL,
              `admin_id` INT UNSIGNED NOT NULL,
              `target_user_id` INT UNSIGNED NULL,
              `target_book_id` INT UNSIGNED NULL,
              `action_type` VARCHAR(100) NOT NULL,
              `penalty_level` ENUM('none', 'level1_warning', 'level2_restriction', 'level3_ban') NOT NULL DEFAULT 'none',
              `notes` TEXT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Ensure books table status enum supports 'quarantined'
        try {
            $pdo->exec("ALTER TABLE `books` MODIFY COLUMN `status` ENUM('available','reserved','sold','removed','quarantined') NOT NULL DEFAULT 'available'");
        } catch (PDOException $e) {}

        // Ensure users table account_status column exists and supports 'restricted'
        try {
            $userCols = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'account_status'")->fetchAll();
            if (empty($userCols)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `account_status` ENUM('active','suspended','deactivated','restricted') NOT NULL DEFAULT 'active' AFTER `role`");
            } else {
                $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `account_status` ENUM('active','suspended','deactivated','restricted') NOT NULL DEFAULT 'active'");
            }
        } catch (PDOException $e) {}

        // 4. Check if sample seed reports are needed
        $reportCount = $pdo->query("SELECT COUNT(*) FROM `reports`")->fetchColumn();
        if ($reportCount == 0) {
            seed_sample_reports($pdo);
        }

        $checked = true;
    } catch (PDOException $e) {
        error_log("Fraud Engine Setup Error: " . $e->getMessage());
    }
}

/**
 * Seed realistic C2C report cases and fraud signals for demonstration and testing.
 */
function seed_sample_reports(PDO $pdo): void {
    try {
        // Sample Report 1: Off-platform payment attempt (High/Critical risk)
        $pdo->exec("
            INSERT INTO `reports` 
            (`id`, `reporter_id`, `reported_user_id`, `book_id`, `order_id`, `reason`, `details`, `attachments`, `risk_score`, `risk_level`, `risk_signals`, `status`, `penalty_applied`, `created_at`) 
            VALUES (
                1, 2, 5, 5, NULL, 'off_platform_payment', 
                'Seller sent a message asking to bypass Booksy checkout and wire money directly to personal bank account or send via crypto.',
                JSON_ARRAY('images/hero-rare-vintage.jpg'),
                85, 'critical', 
                JSON_ARRAY('Off-platform payment solicitation flagged', 'Blacklisted payment keywords found in message text', 'Seller account report density threshold approaching'),
                'pending', 'none', NOW() - INTERVAL 4 HOUR
            )
        ");

        // Sample Report 2: Counterfeit textbook reproduction (Medium/High risk)
        $pdo->exec("
            INSERT INTO `reports` 
            (`id`, `reporter_id`, `reported_user_id`, `book_id`, `order_id`, `reason`, `details`, `attachments`, `risk_score`, `risk_level`, `risk_signals`, `status`, `penalty_applied`, `created_at`) 
            VALUES (
                2, 3, 2, 1, 1, 'counterfeit', 
                'Received a low quality spiral-bound photocopy instead of the genuine McGraw-Hill 6th Edition textbook pictured in the listing.',
                JSON_ARRAY('uploads/book_database_systems.svg'),
                65, 'high', 
                JSON_ARRAY('Counterfeit / unauthorized replica claim', 'Verified transaction with Order #1'),
                'reviewing', 'none', NOW() - INTERVAL 12 HOUR
            )
        ");

        // Sample Report 3: Bait and switch condition (Medium risk)
        $pdo->exec("
            INSERT INTO `reports` 
            (`id`, `reporter_id`, `reported_user_id`, `book_id`, `order_id`, `reason`, `details`, `attachments`, `risk_score`, `risk_level`, `risk_signals`, `status`, `penalty_applied`, `created_at`) 
            VALUES (
                3, 4, 3, 2, NULL, 'misleading', 
                'Listed as Like New but book has extensive water damage and highlighted pages throughout chapters 1-5.',
                NULL,
                42, 'medium', 
                JSON_ARRAY('Misleading condition report'),
                'pending', 'none', NOW() - INTERVAL 1 DAY
            )
        ");

        // Sample Automated Signal
        $pdo->exec("
            INSERT INTO `fraud_signals` 
            (`user_id`, `book_id`, `signal_type`, `severity`, `score_impact`, `details`, `status`, `created_at`) 
            VALUES (
                5, 5, 'off_platform_keywords', 'high', 35, 
                'Automated scanner detected solicitation pattern: direct bank transfer keyword detected.', 'active', NOW() - INTERVAL 4 HOUR
            )
        ");
    } catch (PDOException $e) {
        error_log("Sample Report Seeding Error: " . $e->getMessage());
    }
}

/**
 * Automated Heuristic: Scan text for off-platform payment keywords and communication bypasses.
 */
function scan_text_for_offplatform_keywords(string $text): array {
    $keywords = [
        'whatsapp'       => 25,
        'wire'           => 30,
        'wire transfer'  => 35,
        'crypto'         => 35,
        'bitcoin'        => 35,
        'usdt'           => 35,
        'cashapp'        => 30,
        'zelle'          => 35,
        'paypal friends' => 30,
        'direct bank'    => 25,
        'bank transfer outside' => 35,
        'send to my account'   => 25,
        'telegram'       => 20,
        'viber'          => 15,
        'call me directly' => 15,
        'pay outside'    => 35,
        'bypass booksy'  => 40
    ];

    $detected = [];
    $totalImpact = 0;
    $lower = strtolower($text);

    foreach ($keywords as $kw => $impact) {
        if (strpos($lower, $kw) !== false) {
            $detected[] = $kw;
            $totalImpact += $impact;
        }
    }

    // Phone number pattern match (e.g. 07XXXXXXXX or +94XXXXXXXXX)
    if (preg_match('/(?:07\d{8}|\+94\d{9})/', $text)) {
        $detected[] = 'direct_phone_number_detected';
        $totalImpact += 15;
    }

    return [
        'has_keywords' => !empty($detected),
        'keywords'     => array_unique($detected),
        'score_impact' => min(50, $totalImpact)
    ];
}

/**
 * Automated Risk Signals Engine: Compute Composite Triage Risk Score (0-100) and Priority Level.
 */
function calculate_triage_score(PDO $pdo, array $reportData): array {
    $score = 0;
    $signals = [];

    $reason = $reportData['reason'] ?? 'other';
    $details = $reportData['details'] ?? '';
    $reporterId = intval($reportData['reporter_id'] ?? 0);
    $sellerId = intval($reportData['reported_user_id'] ?? 0);
    $bookId = intval($reportData['book_id'] ?? 0);
    $orderId = intval($reportData['order_id'] ?? 0);
    $attachments = $reportData['attachments'] ?? [];

    // 1. Base Severity by Violation Reason
    $reasonScores = [
        'off_platform_payment' => 35,
        'counterfeit'          => 30,
        'not_received'         => 28,
        'fake_profile'         => 25,
        'misleading'           => 18,
        'inappropriate'        => 15,
        'spam'                 => 10,
        'other'                => 10
    ];
    $baseReason = $reasonScores[$reason] ?? 10;
    $score += $baseReason;
    $signals[] = "Base violation severity weight: +{$baseReason} pts (" . strtoupper(str_replace('_', ' ', $reason)) . ")";

    // 2. Off-Platform Keyword Scan in Details
    $keywordScan = scan_text_for_offplatform_keywords($details);
    if ($keywordScan['has_keywords']) {
        $kwPts = $keywordScan['score_impact'];
        $score += $kwPts;
        $signals[] = "Off-platform trigger keywords detected: " . implode(', ', $keywordScan['keywords']) . " (+{$kwPts} pts)";
    }

    // 3. Evidence Attachment Multiplier
    if (!empty($attachments) && is_array($attachments) && count($attachments) > 0) {
        $attPts = min(15, count($attachments) * 5);
        $score += $attPts;
        $signals[] = "Documented proof attached: " . count($attachments) . " file(s) (+{$attPts} pts)";
    }

    // 4. Verified Order Transaction Confirmation
    if ($orderId > 0) {
        try {
            $orderStmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? AND buyer_id = ? LIMIT 1");
            $orderStmt->execute([$orderId, $reporterId]);
            if ($orderStmt->fetch()) {
                $score += 15;
                $signals[] = "Verified purchase transaction on Order #{$orderId} (+15 pts)";
            }
        } catch (PDOException $e) {}
    }

    // 5. Reporter Credibility & Account Age
    if ($reporterId > 0) {
        try {
            $repStmt = $pdo->prepare("
                SELECT created_at, 
                       (SELECT COUNT(*) FROM orders WHERE buyer_id = ?) AS total_orders,
                       (SELECT COUNT(*) FROM reports WHERE reporter_id = ? AND status = 'dismissed') AS fake_reports
                FROM users WHERE id = ? LIMIT 1
            ");
            $repStmt->execute([$reporterId, $reporterId, $reporterId]);
            $rep = $repStmt->fetch();
            if ($rep) {
                $accountAgeDays = (time() - strtotime($rep['created_at'])) / (60 * 60 * 24);
                if ($accountAgeDays > 30 && $rep['total_orders'] >= 1) {
                    $score += 10;
                    $signals[] = "Established buyer with purchase history (+10 confidence pts)";
                }
                if ($rep['fake_reports'] >= 2) {
                    $score = max(5, $score - 15);
                    $signals[] = "Reporter has prior dismissed reports (-15 deduction)";
                }
            }
        } catch (PDOException $e) {}
    }

    // 6. Seller History & Report Density
    if ($sellerId > 0) {
        $density72h = check_report_density($pdo, $sellerId, 72);
        if ($density72h >= 2) {
            $score += 25;
            $signals[] = "HIGH REPORT DENSITY: {$density72h} distinct reports in past 72 hours (+25 pts)";
        }

        // Check if seller account is brand new (< 48 hours)
        try {
            $sellerStmt = $pdo->prepare("SELECT created_at, account_status FROM users WHERE id = ? LIMIT 1");
            $sellerStmt->execute([$sellerId]);
            $sellerData = $sellerStmt->fetch();
            if ($sellerData) {
                $sellerAgeHours = (time() - strtotime($sellerData['created_at'])) / 3600;
                if ($sellerAgeHours < 48) {
                    $score += 15;
                    $signals[] = "New seller account created <48h ago (+15 velocity risk pts)";
                }
                if ($sellerData['account_status'] === 'restricted') {
                    $score += 15;
                    $signals[] = "Seller is currently under restriction (+15 pts)";
                }
            }
        } catch (PDOException $e) {}
    }

    // 7. Cap Score between 0 and 100
    $finalScore = min(100, max(5, $score));

    // Determine Risk Level / Priority
    if ($finalScore >= 75) {
        $level = 'critical';
    } elseif ($finalScore >= 60) {
        $level = 'high';
    } elseif ($finalScore >= 40) {
        $level = 'medium';
    } else {
        $level = 'low';
    }

    return [
        'score'   => $finalScore,
        'level'   => $level,
        'signals' => $signals
    ];
}

/**
 * Automated Heuristic: Scan a book listing for pricing anomalies and suspicious patterns.
 */
function scan_listing_for_anomalies(PDO $pdo, int $bookId): array {
    ensure_fraud_tables($pdo);
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, c.name AS category_name, u.created_at AS seller_created_at, u.account_status,
                   (SELECT AVG(price) FROM books WHERE category_id = b.category_id AND id != b.id AND status = 'available') AS category_avg_price,
                   (SELECT COUNT(*) FROM books WHERE seller_id = b.seller_id AND created_at >= NOW() - INTERVAL 48 HOUR) AS recent_listings_count
            FROM books b
            JOIN categories c ON b.category_id = c.id
            JOIN users u ON b.seller_id = u.id
            WHERE b.id = ? LIMIT 1
        ");
        $stmt->execute([$bookId]);
        $listing = $stmt->fetch();

        if (!$listing) return ['anomalies_found' => false];

        $anomalies = [];
        $totalImpact = 0;

        // 1. Price Anomaly Check: 60%+ below category median for brand new / like new condition
        $catAvg = floatval($listing['category_avg_price'] ?: 0);
        $price = floatval($listing['price']);
        if ($catAvg > 1000 && in_array($listing['book_condition'], ['Brand New', 'Like New'])) {
            $discountPct = (($catAvg - $price) / $catAvg) * 100;
            if ($discountPct >= 65 && $price > 0) {
                $anomalies[] = "Unusually low price anomaly: Rs. " . number_format($price, 2) . " (" . round($discountPct) . "% below category avg Rs. " . number_format($catAvg, 2) . ")";
                $totalImpact += 30;
            }
        }

        // 2. Off-Platform Keywords in Title or Description
        $kwScan = scan_text_for_offplatform_keywords($listing['title'] . ' ' . ($listing['description'] ?? ''));
        if ($kwScan['has_keywords']) {
            $anomalies[] = "Off-platform keyword detected in listing: " . implode(', ', $kwScan['keywords']);
            $totalImpact += $kwScan['score_impact'];
        }

        // 3. High Inventory Velocity for Fresh Accounts (<48h)
        $sellerAgeHours = (time() - strtotime($listing['seller_created_at'])) / 3600;
        $recentCount = intval($listing['recent_listings_count']);
        if ($sellerAgeHours < 48 && $recentCount >= 4) {
            $anomalies[] = "Rapid account velocity: {$recentCount} listings posted within 48 hours of account registration.";
            $totalImpact += 25;
        }

        if (!empty($anomalies)) {
            // Record active signal
            $sigStmt = $pdo->prepare("
                INSERT INTO `fraud_signals` 
                (`user_id`, `book_id`, `signal_type`, `severity`, `score_impact`, `details`, `status`) 
                VALUES (?, ?, 'listing_anomaly', ?, ?, ?, 'active')
            ");
            $severity = $totalImpact >= 50 ? 'critical' : ($totalImpact >= 30 ? 'high' : 'medium');
            $sigStmt->execute([
                $listing['seller_id'],
                $bookId,
                $severity,
                $totalImpact,
                implode(' | ', $anomalies)
            ]);
        }

        return [
            'anomalies_found' => !empty($anomalies),
            'anomalies'       => $anomalies,
            'score_impact'    => $totalImpact
        ];
    } catch (PDOException $e) {
        return ['anomalies_found' => false];
    }
}

/**
 * Report Density: Check number of distinct user reports against a seller in the given window.
 * If density >= 2, auto-quarantines active listings to protect buyers.
 */
function check_report_density(PDO $pdo, int $sellerId, int $hours = 72): int {
    if ($sellerId <= 0) return 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT reporter_id) 
            FROM `reports` 
            WHERE `reported_user_id` = ? 
              AND `status` IN ('pending', 'reviewing', 'action_taken')
              AND `created_at` >= NOW() - INTERVAL ? HOUR
        ");
        $stmt->execute([$sellerId, $hours]);
        $count = (int)$stmt->fetchColumn();

        // Automatic quarantine safeguard if 2+ distinct reports in 72h
        if ($count >= 2) {
            $pdo->prepare("
                UPDATE `books` 
                SET `status` = 'quarantined' 
                WHERE `seller_id` = ? AND `status` = 'available'
            ")->execute([$sellerId]);
        }

        return $count;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Fetch platform-wide fraud & moderation analytics for Admin Dashboard.
 */
function get_fraud_analytics(PDO $pdo): array {
    ensure_fraud_tables($pdo);
    try {
        $pending = (int)$pdo->query("SELECT COUNT(*) FROM `reports` WHERE `status` = 'pending'")->fetchColumn();
        $criticalHigh = (int)$pdo->query("SELECT COUNT(*) FROM `reports` WHERE `status` IN ('pending', 'reviewing') AND `risk_level` IN ('critical', 'high')")->fetchColumn();
        $quarantinedListings = (int)$pdo->query("SELECT COUNT(*) FROM `books` WHERE `status` = 'quarantined'")->fetchColumn();
        $restrictedSellers = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `account_status` IN ('restricted', 'suspended')")->fetchColumn();
        $activeSignals = (int)$pdo->query("SELECT COUNT(*) FROM `fraud_signals` WHERE `status` = 'active'")->fetchColumn();
        $resolvedCount = (int)$pdo->query("SELECT COUNT(*) FROM `reports` WHERE `status` IN ('action_taken', 'dismissed')")->fetchColumn();

        return [
            'pending_reports'      => $pending,
            'critical_high_risk'   => $criticalHigh,
            'quarantined_listings' => $quarantinedListings,
            'restricted_sellers'   => $restrictedSellers,
            'active_signals'       => $activeSignals,
            'resolved_count'       => $resolvedCount
        ];
    } catch (PDOException $e) {
        return [
            'pending_reports'      => 0,
            'critical_high_risk'   => 0,
            'quarantined_listings' => 0,
            'restricted_sellers'   => 0,
            'active_signals'       => 0,
            'resolved_count'       => 0
        ];
    }
}

/**
 * Fetch triage queue reports for Admin Moderation Center.
 */
function get_triage_reports(PDO $pdo, array $filters = []): array {
    ensure_fraud_tables($pdo);
    try {
        $where = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'reviewing', 'action_taken', 'dismissed'])) {
            $where[] = "r.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority']) && in_array($filters['priority'], ['critical', 'high', 'medium', 'low'])) {
            $where[] = "r.risk_level = ?";
            $params[] = $filters['priority'];
        }

        if (!empty($filters['reason'])) {
            $where[] = "r.reason = ?";
            $params[] = $filters['reason'];
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT r.*,
                   rep.name AS reporter_name, rep.email AS reporter_email, rep.created_at AS reporter_joined,
                   sel.name AS seller_name, sel.email AS seller_email, sel.phone AS seller_phone, sel.account_status AS seller_status, sel.created_at AS seller_joined,
                   b.title AS book_title, b.price AS book_price, b.image_url AS book_image, b.status AS book_status,
                   o.total_amount AS order_amount, o.status AS order_status,
                   modr.name AS moderator_name
            FROM `reports` r
            JOIN `users` rep ON r.reporter_id = rep.id
            LEFT JOIN `users` sel ON r.reported_user_id = sel.id
            LEFT JOIN `books` b ON r.book_id = b.id
            LEFT JOIN `orders` o ON r.order_id = o.id
            LEFT JOIN `users` modr ON r.moderated_by = modr.id
            $whereSql
            ORDER BY 
                CASE r.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'reviewing' THEN 2 
                    ELSE 3 
                END,
                CASE r.risk_level 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    ELSE 4 
                END,
                r.risk_score DESC,
                r.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Decode JSON fields
        foreach ($rows as &$row) {
            $row['attachments_arr'] = !empty($row['attachments']) ? json_decode($row['attachments'], true) : [];
            $row['risk_signals_arr'] = !empty($row['risk_signals']) ? json_decode($row['risk_signals'], true) : [];
        }

        return $rows ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching triage reports: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch detailed single report dossier for modal inspection.
 */
function get_report_details(PDO $pdo, int $reportId): ?array {
    ensure_fraud_tables($pdo);
    try {
        $stmt = $pdo->prepare("
            SELECT r.*,
                   rep.name AS reporter_name, rep.email AS reporter_email, rep.phone AS reporter_phone, rep.created_at AS reporter_joined,
                   (SELECT COUNT(*) FROM orders WHERE buyer_id = rep.id) AS reporter_orders_count,
                   sel.name AS seller_name, sel.email AS seller_email, sel.phone AS seller_phone, sel.account_status AS seller_status, sel.created_at AS seller_joined,
                   (SELECT COUNT(*) FROM books WHERE seller_id = sel.id) AS seller_total_listings,
                   (SELECT COUNT(*) FROM reports WHERE reported_user_id = sel.id) AS seller_total_reports,
                   b.title AS book_title, b.price AS book_price, b.book_condition AS book_condition, b.image_url AS book_image, b.status AS book_status, b.description AS book_desc,
                   c.name AS category_name,
                   o.total_amount AS order_amount, o.status AS order_status, o.created_at AS order_created_at,
                   modr.name AS moderator_name
            FROM `reports` r
            JOIN `users` rep ON r.reporter_id = rep.id
            LEFT JOIN `users` sel ON r.reported_user_id = sel.id
            LEFT JOIN `books` b ON r.book_id = b.id
            LEFT JOIN `categories` c ON b.category_id = c.id
            LEFT JOIN `orders` o ON r.order_id = o.id
            LEFT JOIN `users` modr ON r.moderated_by = modr.id
            WHERE r.id = ? LIMIT 1
        ");
        $stmt->execute([$reportId]);
        $row = $stmt->fetch();

        if ($row) {
            $row['attachments_arr'] = !empty($row['attachments']) ? json_decode($row['attachments'], true) : [];
            $row['risk_signals_arr'] = !empty($row['risk_signals']) ? json_decode($row['risk_signals'], true) : [];
            return $row;
        }
        return null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Log a moderation enforcement action to audit trail.
 */
function log_moderation_action(PDO $pdo, ?int $reportId, int $adminId, ?int $targetUserId, ?int $targetBookId, string $actionType, string $penaltyLevel, string $notes): bool {
    ensure_fraud_tables($pdo);
    try {
        $stmt = $pdo->prepare("
            INSERT INTO `moderation_logs` 
            (`report_id`, `admin_id`, `target_user_id`, `target_book_id`, `action_type`, `penalty_level`, `notes`) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$reportId, $adminId, $targetUserId, $targetBookId, $actionType, $penaltyLevel, $notes]);
    } catch (PDOException $e) {
        error_log("Moderation Log Error: " . $e->getMessage());
        return false;
    }
}
