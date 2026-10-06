<?php
/**
 * Booksy - Banner & Showcase Promotion Engine
 * Handles database persistence, querying, auto-setup, and rendering for customizable Hero Carousel slides, Promo Cards, and Announcements.
 */

if (!defined('BOOKSY_BANNERS_LOADED')) {
    define('BOOKSY_BANNERS_LOADED', true);
}

/**
 * Ensure banners table exists and is populated with default seed slides/cards.
 */
function ensure_banners_table(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `banners` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `title` VARCHAR(255) NOT NULL,
              `tag` VARCHAR(100) NULL,
              `description` TEXT NULL,
              `btn_text` VARCHAR(100) NULL DEFAULT 'Browse Now',
              `btn_link` VARCHAR(500) NULL DEFAULT '#bookCatalogGrid',
              `image_url` VARCHAR(500) NOT NULL,
              `position` ENUM('hero_slide', 'promo_card', 'top_announcement') NOT NULL DEFAULT 'hero_slide',
              `bg_color` VARCHAR(50) NULL DEFAULT 'promo-blue',
              `icon` VARCHAR(100) NULL DEFAULT 'bi-fire',
              `display_order` INT NOT NULL DEFAULT 0,
              `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
              `created_by` INT UNSIGNED NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_banners_position_active` (`position`, `is_active`, `display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Check if table is empty, if so, seed default banners
        $count = $pdo->query("SELECT COUNT(*) FROM `banners`")->fetchColumn();
        if ($count == 0) {
            reset_default_banners($pdo);
        }

        $checked = true;
    } catch (PDOException $e) {
        error_log("Banners Table Initialization Error: " . $e->getMessage());
    }
}

/**
 * Reset / Seed initial default banners for Booksy.
 */
function reset_default_banners(PDO $pdo, ?int $userId = null): bool {
    try {
        $pdo->exec("DELETE FROM `banners`");

        $defaultBanners = [
            // 4 Hero Showcase Carousel Slides
            [
                'title' => 'New Arrivals',
                'tag' => '✨ Book Premiere',
                'description' => 'Discover the latest additions to our collection - curated for readers who love cozy reads and great finds.',
                'btn_text' => 'Browse Now',
                'btn_link' => '#bookCatalogGrid',
                'image_url' => 'images/hero-cozy-books.jpg',
                'position' => 'hero_slide',
                'bg_color' => 'promo-blue',
                'icon' => 'bi-stars',
                'display_order' => 1,
                'is_active' => 1,
                'created_by' => $userId
            ],
            [
                'title' => 'Academic Picks',
                'tag' => '🎓 Campus Essentials',
                'description' => 'Save up to 70% on pre-loved engineering, medicine, A/L, and university revision textbooks.',
                'btn_text' => 'Explore Textbooks',
                'btn_link' => 'index.php?category=educational-academic',
                'image_url' => 'images/hero-academic-study.jpg',
                'position' => 'hero_slide',
                'bg_color' => 'promo-teal',
                'icon' => 'bi-mortarboard-fill',
                'display_order' => 2,
                'is_active' => 1,
                'created_by' => $userId
            ],
            [
                'title' => 'Collector Editions',
                'tag' => '💎 Rare & Vintage',
                'description' => 'Timeless Sinhala literature classics, vintage magazines, and rare out-of-print collectibles.',
                'btn_text' => 'View Collectibles',
                'btn_link' => 'index.php?category=biography-history-poetry',
                'image_url' => 'images/hero-rare-vintage.jpg',
                'position' => 'hero_slide',
                'bg_color' => 'promo-gold',
                'icon' => 'bi-gem',
                'display_order' => 3,
                'is_active' => 1,
                'created_by' => $userId
            ],
            [
                'title' => 'Reader Favorites',
                'tag' => '🏡 Community Nook',
                'description' => 'Connect directly with fellow book lovers across Sri Lanka to buy and resell pre-owned novels and stories.',
                'btn_text' => 'Explore Bestsellers',
                'btn_link' => 'index.php?sort=latest',
                'image_url' => 'images/library-wide-banner.jpg',
                'position' => 'hero_slide',
                'bg_color' => 'promo-green',
                'icon' => 'bi-people-fill',
                'display_order' => 4,
                'is_active' => 1,
                'created_by' => $userId
            ],
            // 5 Right Rail Promo Cards
            [
                'title' => 'Best Sellers & Trending',
                'tag' => 'Trending Reads',
                'description' => 'Top rated novels & fiction reads',
                'btn_text' => 'View',
                'btn_link' => 'index.php?sort=latest',
                'image_url' => '',
                'position' => 'promo_card',
                'bg_color' => 'promo-blue',
                'icon' => 'bi-fire',
                'display_order' => 1,
                'is_active' => 1,
                'created_by' => $userId
            ],
            [
                'title' => 'Academic & University',
                'tag' => 'Campus Picks',
                'description' => 'Save up to 70% on textbooks',
                'btn_text' => 'View',
                'btn_link' => 'index.php?category=educational-academic',
                'image_url' => '',
                'position' => 'promo_card',
                'bg_color' => 'promo-teal',
                'icon' => 'bi-mortarboard-fill',
                'display_order' => 2,
                'is_active' => 1,
                'created_by' => $userId
            ],
            [
                'title' => 'Sell Your Used Books',
                'tag' => 'Instant Cash',
                'description' => 'Turn old books into instant cash',
                'btn_text' => 'View',
                'btn_link' => 'sell.php',
                'image_url' => '',
                'position' => 'promo_card',
                'bg_color' => 'promo-gold',
                'icon' => 'bi-tag-fill',
                'display_order' => 3,
                'is_active' => 1,
                'created_by' => $userId
            ],
            [
                'title' => 'Islandwide Cash on Delivery',
                'tag' => 'Fast Delivery',
                'description' => 'Fast courier directly to doorstep',
                'btn_text' => 'View',
                'btn_link' => 'cart.php',
                'image_url' => '',
                'position' => 'promo_card',
                'bg_color' => 'promo-green',
                'icon' => 'bi-truck',
                'display_order' => 4,
                'is_active' => 1,
                'created_by' => $userId
            ],
            [
                'title' => 'Brand New & Like New',
                'tag' => 'Guaranteed Quality',
                'description' => 'Pristine condition guaranteed',
                'btn_text' => 'View',
                'btn_link' => 'index.php?condition=Brand+New',
                'image_url' => '',
                'position' => 'promo_card',
                'bg_color' => 'promo-purple',
                'icon' => 'bi-shield-check',
                'display_order' => 5,
                'is_active' => 1,
                'created_by' => $userId
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO `banners` 
            (`title`, `tag`, `description`, `btn_text`, `btn_link`, `image_url`, `position`, `bg_color`, `icon`, `display_order`, `is_active`, `created_by`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($defaultBanners as $b) {
            $stmt->execute([
                $b['title'],
                $b['tag'],
                $b['description'],
                $b['btn_text'],
                $b['btn_link'],
                $b['image_url'],
                $b['position'],
                $b['bg_color'],
                $b['icon'],
                $b['display_order'],
                $b['is_active'],
                $b['created_by']
            ]);
        }

        return true;
    } catch (PDOException $e) {
        error_log("Error resetting banners: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch active banners by position ordered by display_order.
 */
function get_active_banners(PDO $pdo, string $position = 'hero_slide'): array {
    ensure_banners_table($pdo);
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM `banners` 
            WHERE `position` = ? AND `is_active` = 1 
            ORDER BY `display_order` ASC, `id` ASC
        ");
        $stmt->execute([$position]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching active banners: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch all banners (both active and inactive) with optional position filter for Admin dashboard.
 */
function get_all_banners(PDO $pdo, ?string $position = null): array {
    ensure_banners_table($pdo);
    try {
        if ($position) {
            $stmt = $pdo->prepare("
                SELECT b.*, u.name AS creator_name 
                FROM `banners` b 
                LEFT JOIN `users` u ON b.created_by = u.id 
                WHERE b.`position` = ? 
                ORDER BY b.`display_order` ASC, b.`id` ASC
            ");
            $stmt->execute([$position]);
        } else {
            $stmt = $pdo->query("
                SELECT b.*, u.name AS creator_name 
                FROM `banners` b 
                LEFT JOIN `users` u ON b.created_by = u.id 
                ORDER BY 
                    CASE b.`position` 
                        WHEN 'hero_slide' THEN 1 
                        WHEN 'promo_card' THEN 2 
                        ELSE 3 
                    END,
                    b.`display_order` ASC, b.`id` ASC
            ");
        }
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching all banners: " . $e->getMessage());
        return [];
    }
}

/**
 * Get banner by ID.
 */
function get_banner_by_id(PDO $pdo, int $id): ?array {
    ensure_banners_table($pdo);
    try {
        $stmt = $pdo->prepare("SELECT * FROM `banners` WHERE `id` = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get banner metrics for Admin Dashboard.
 */
function get_banner_stats(PDO $pdo): array {
    ensure_banners_table($pdo);
    try {
        $total = (int)$pdo->query("SELECT COUNT(*) FROM `banners`")->fetchColumn();
        $heroActive = (int)$pdo->query("SELECT COUNT(*) FROM `banners` WHERE `position` = 'hero_slide' AND `is_active` = 1")->fetchColumn();
        $promoActive = (int)$pdo->query("SELECT COUNT(*) FROM `banners` WHERE `position` = 'promo_card' AND `is_active` = 1")->fetchColumn();
        $announcementActive = (int)$pdo->query("SELECT COUNT(*) FROM `banners` WHERE `position` = 'top_announcement' AND `is_active` = 1")->fetchColumn();
        $inactive = (int)$pdo->query("SELECT COUNT(*) FROM `banners` WHERE `is_active` = 0")->fetchColumn();

        return [
            'total' => $total,
            'hero_active' => $heroActive,
            'promo_active' => $promoActive,
            'announcement_active' => $announcementActive,
            'inactive' => $inactive
        ];
    } catch (PDOException $e) {
        return [
            'total' => 0,
            'hero_active' => 0,
            'promo_active' => 0,
            'announcement_active' => 0,
            'inactive' => 0
        ];
    }
}
