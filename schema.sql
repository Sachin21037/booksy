-- ============================================================================
-- Booksy - C2C Online Book Reselling Marketplace
-- MySQL 8.x Database Schema
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `booksy_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `booksy_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `moderation_logs`;
DROP TABLE IF EXISTS `fraud_signals`;
DROP TABLE IF EXISTS `banners`;
DROP TABLE IF EXISTS `reports`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `book_images`;
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. USERS
-- A user can be both a buyer and a seller (core C2C concept).
-- ============================================================================

CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `profile_image` VARCHAR(255) NULL,
  `bio` TEXT NULL,
  `role` ENUM('user','admin','owner') NOT NULL DEFAULT 'user',
  `account_status` ENUM('active','suspended','deactivated') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. ADDRESSES
-- Users can save multiple delivery addresses.
-- ============================================================================

CREATE TABLE `addresses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(50) DEFAULT 'Home',
  `recipient_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) NULL,
  `city` VARCHAR(100) NOT NULL,
  `district` VARCHAR(100) NULL,
  `province` VARCHAR(100) NULL,
  `postal_code` VARCHAR(20) NULL,
  `is_default` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_addresses_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. CATEGORIES
-- ============================================================================

CREATE TABLE `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. BOOK LISTINGS
-- Each listing belongs to one seller.
-- The same user can create many listings and can also buy from other users.
-- ============================================================================

CREATE TABLE `books` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `seller_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,

  `title` VARCHAR(255) NOT NULL,
  `author` VARCHAR(255) NOT NULL,
  `isbn` VARCHAR(20) NULL,
  `edition` VARCHAR(100) NULL,
  `publisher` VARCHAR(150) NULL,
  `publication_year` YEAR NULL,
  `language` VARCHAR(50) NOT NULL DEFAULT 'English',

  `price` DECIMAL(10,2) NOT NULL,
  `negotiable` BOOLEAN NOT NULL DEFAULT FALSE,

  `book_condition` ENUM(
    'Brand New',
    'Like New',
    'Good',
    'Fair',
    'Poor'
  ) NOT NULL,

  `condition_details` TEXT NULL,
  `description` TEXT NULL,

  `location_city` VARCHAR(100) NULL,
  `location_district` VARCHAR(100) NULL,

  `delivery_method` ENUM(
    'Meetup',
    'Courier',
    'Both'
  ) NOT NULL DEFAULT 'Courier',

  `status` ENUM(
    'available',
    'reserved',
    'sold',
    'removed'
  ) NOT NULL DEFAULT 'available',

  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_books_seller`
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_books_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `chk_books_price`
    CHECK (`price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_books_seller` ON `books`(`seller_id`);
CREATE INDEX `idx_books_category` ON `books`(`category_id`);
CREATE INDEX `idx_books_status` ON `books`(`status`);
CREATE INDEX `idx_books_price` ON `books`(`price`);
CREATE INDEX `idx_books_title` ON `books`(`title`);

-- ============================================================================
-- 5. BOOK IMAGES
-- A seller can upload multiple photos for one listing.
-- ============================================================================

CREATE TABLE `book_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `book_id` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `is_primary` BOOLEAN NOT NULL DEFAULT FALSE,
  `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_book_images_book`
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_book_images_book` ON `book_images`(`book_id`);

-- ============================================================================
-- 6. FAVORITES / WISHLIST
-- Users can save books they are interested in.
-- ============================================================================

CREATE TABLE `favorites` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `book_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY `uq_favorite` (`user_id`, `book_id`),

  CONSTRAINT `fk_favorites_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT `fk_favorites_book`
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. ORDERS
-- One order is created by a buyer.
-- In a C2C marketplace, an order can contain listings from different sellers.
-- ============================================================================

CREATE TABLE `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `buyer_id` INT UNSIGNED NOT NULL,
  `shipping_address` TEXT NOT NULL,

  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  `payment_method` ENUM(
    'Cash on Delivery',
    'Bank Transfer',
    'Online Payment'
  ) NOT NULL DEFAULT 'Cash on Delivery',

  `status` ENUM(
    'Pending',
    'Confirmed',
    'Processing',
    'Shipped',
    'Completed',
    'Cancelled'
  ) NOT NULL DEFAULT 'Pending',

  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_orders_buyer`
    FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `chk_orders_amounts`
    CHECK (
      `subtotal` >= 0 AND
      `delivery_fee` >= 0 AND
      `total_amount` >= 0
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_orders_buyer` ON `orders`(`buyer_id`);
CREATE INDEX `idx_orders_status` ON `orders`(`status`);

-- ============================================================================
-- 8. ORDER ITEMS
-- Stores the seller and price at the time of purchase.
-- This protects order history if a listing changes later.
-- ============================================================================

CREATE TABLE `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `book_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NOT NULL,
  `book_title` VARCHAR(255) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,

  CONSTRAINT `fk_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT `fk_items_book`
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_items_seller`
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `chk_items_price`
    CHECK (`price` >= 0),

  CONSTRAINT `chk_items_quantity`
    CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_order_items_order` ON `order_items`(`order_id`);
CREATE INDEX `idx_order_items_seller` ON `order_items`(`seller_id`);

-- ============================================================================
-- 9. PAYMENTS
-- Payment record is separated from the order.
-- ============================================================================

CREATE TABLE `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` ENUM(
    'Cash on Delivery',
    'Bank Transfer',
    'Online Payment'
  ) NOT NULL,
  `transaction_reference` VARCHAR(150) NULL UNIQUE,
  `status` ENUM('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `paid_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY `uq_payment_order` (`order_id`),

  CONSTRAINT `fk_payments_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `chk_payment_amount`
    CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. REVIEWS
-- Buyers can review sellers after completed purchases.
-- ============================================================================

CREATE TABLE `reviews` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reviewer_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NOT NULL,
  `book_id` INT UNSIGNED NULL,
  `order_id` INT UNSIGNED NULL,

  `rating` TINYINT UNSIGNED NOT NULL,
  `comment` TEXT NULL,

  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_reviews_reviewer`
    FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_reviews_seller`
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_reviews_book`
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT `fk_reviews_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT `chk_reviews_rating`
    CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_reviews_seller` ON `reviews`(`seller_id`);

-- ============================================================================
-- 11. MESSAGES
-- Buyer and seller can communicate before buying.
-- ============================================================================

CREATE TABLE `messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `book_id` INT UNSIGNED NULL,
  `message` TEXT NOT NULL,
  `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_messages_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT `fk_messages_receiver`
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT `fk_messages_book`
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_messages_conversation`
  ON `messages`(`sender_id`, `receiver_id`, `created_at`);

-- ============================================================================
-- 12. REPORTS & FRAUD MODERATION
-- Users can report suspicious listings/users with evidence attachments and risk scoring.
-- ============================================================================

CREATE TABLE `reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reporter_id` INT UNSIGNED NOT NULL,
  `reported_user_id` INT UNSIGNED NULL,
  `book_id` INT UNSIGNED NULL,
  `order_id` INT UNSIGNED NULL,
  `reason` ENUM(
    'counterfeit',
    'not_received',
    'off_platform_payment',
    'fake_profile',
    'misleading',
    'inappropriate',
    'spam',
    'other'
  ) NOT NULL,
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

  CONSTRAINT `fk_reports_reporter`
    FOREIGN KEY (`reporter_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT `fk_reports_user`
    FOREIGN KEY (`reported_user_id`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT `fk_reports_book`
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT `fk_reports_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT `fk_reports_moderator`
    FOREIGN KEY (`moderated_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_reports_status_risk` ON `reports`(`status`, `risk_level`, `risk_score`);
CREATE INDEX `idx_reports_target_user` ON `reports`(`reported_user_id`);
CREATE INDEX `idx_reports_target_book` ON `reports`(`book_id`);

-- ============================================================================
-- 13. AUTOMATED FRAUD SIGNALS
-- Automated heuristic anomaly detections (pricing, off-platform keywords, account velocity, report density).
-- ============================================================================

CREATE TABLE `fraud_signals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `book_id` INT UNSIGNED NULL,
  `signal_type` VARCHAR(100) NOT NULL,
  `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
  `score_impact` INT NOT NULL DEFAULT 20,
  `details` TEXT NULL,
  `status` ENUM('active', 'acknowledged', 'resolved') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_signals_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT `fk_signals_book`
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_signals_user_status` ON `fraud_signals`(`user_id`, `status`);
CREATE INDEX `idx_signals_book_status` ON `fraud_signals`(`book_id`, `status`);

-- ============================================================================
-- 14. MODERATION LOGS (AUDIT TRAIL)
-- Tracks graduated enforcement actions and administrative audit trail.
-- ============================================================================

CREATE TABLE `moderation_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `report_id` INT UNSIGNED NULL,
  `admin_id` INT UNSIGNED NOT NULL,
  `target_user_id` INT UNSIGNED NULL,
  `target_book_id` INT UNSIGNED NULL,
  `action_type` VARCHAR(100) NOT NULL,
  `penalty_level` ENUM('none', 'level1_warning', 'level2_restriction', 'level3_ban') NOT NULL DEFAULT 'none',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_modlogs_report`
    FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT `fk_modlogs_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT `fk_modlogs_target_user`
    FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT `fk_modlogs_target_book`
    FOREIGN KEY (`target_book_id`) REFERENCES `books`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 15. BANNERS
-- Hero slides, promo cards, and announcement banners customizable by admin/owner.
-- ============================================================================

CREATE TABLE `banners` (
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

  CONSTRAINT `fk_banners_creator`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_banners_position_active` ON `banners`(`position`, `is_active`, `display_order`);

-- ============================================================================
-- SEED DATA
-- Default password for sample accounts: 123456
-- ============================================================================

INSERT INTO `users`
(`id`,`name`,`email`,`password`,`phone`,`role`,`bio`,`created_at`)
VALUES
(1, 'A.S.S. Kavinda', 'kavinda@booksy.lk',
 '$2y$10$MkTnIpp1/NKM3vkFDvngFeM7UPfNHgxZMOvFkRZ.PI9JTMBYZKcc.',
 '0771234567', 'owner', 'Booksy Owner & Primary Administrator',
 '2026-08-01 10:00:00'),

(2, 'Nadeesha Perera', 'nadeesha.student@gmail.com',
 '$2y$10$MkTnIpp1/NKM3vkFDvngFeM7UPfNHgxZMOvFkRZ.PI9JTMBYZKcc.',
 '0719876543', 'user', 'University student and book seller',
 '2026-08-02 11:30:00'),

(3, 'Kasun Jayasinghe', 'kasun.j@yahoo.com',
 '$2y$10$MkTnIpp1/NKM3vkFDvngFeM7UPfNHgxZMOvFkRZ.PI9JTMBYZKcc.',
 '0765544332', 'user', 'Reader selling used books',
 '2026-08-03 14:15:00'),

(4, 'Dilani Alwis', 'dilani.reads@gmail.com',
 '$2y$10$MkTnIpp1/NKM3vkFDvngFeM7UPfNHgxZMOvFkRZ.PI9JTMBYZKcc.',
 '0781122334', 'user', 'Book lover and collector',
 '2026-08-04 09:45:00'),

(5, 'Sunil Wickramasinghe', 'sunil.collector@gmail.com',
 '$2y$10$MkTnIpp1/NKM3vkFDvngFeM7UPfNHgxZMOvFkRZ.PI9JTMBYZKcc.',
 '0723344556', 'user', 'Collector of rare books',
 '2026-08-05 16:20:00'),

(6, 'sachin', 'sachinshehan21037@gmail.com',
 '$2y$10$mPVO3aHF3HMvTybXZiq6neSAkyKgPnAocTK7kxfQEnw8cM.NBjvXS',
 NULL, 'admin', 'Site Manager & Platform Administrator',
 '2026-08-06 10:00:00');

INSERT INTO `categories` (`id`,`name`,`slug`,`description`) VALUES
(1, 'Novels and Fiction', 'novels-fiction',
 'Fiction, classics, drama, literary works and Sinhala & English novels.'),
(2, 'Educational and Academic', 'educational-academic',
 'University textbooks, computing, engineering, medicine, school books and past papers.'),
(3, 'Children''s Books', 'childrens-books',
 'Story books, illustrated books, activity books and early learning materials.'),
(4, 'Self-Help and Personal Development', 'self-help',
 'Productivity, habits, finance, leadership and personal development.'),
(5, 'Mystery, Thriller, Fantasy and Science Fiction', 'mystery-thriller-fantasy-scifi',
 'Detective stories, crime, fantasy, science fiction and paranormal books.'),
(6, 'Biography, History and Poetry', 'biography-history-poetry',
 'Biographies, memoirs, history books and poetry collections.'),
(7, 'Other Books', 'other-books',
 'Cookbooks, magazines, hobby books, dictionaries and rare collectibles.');

INSERT INTO `addresses`
(`user_id`,`label`,`recipient_name`,`phone`,`address_line1`,`city`,`district`,`province`,`postal_code`,`is_default`)
VALUES
(2,'Home','Nadeesha Perera','0719876543','No. 24, Temple Road','Colombo','Colombo','Western','01000',TRUE),
(3,'Home','Kasun Jayasinghe','0765544332','No. 18, Lake Road','Kandy','Kandy','Central','20000',TRUE),
(4,'Home','Dilani Alwis','0781122334','No. 51, Main Street','Galle','Galle','Southern','80000',TRUE),
(5,'Home','Sunil Wickramasinghe','0723344556','No. 10, Station Road','Kurunegala','Kurunegala','North Western','60000',TRUE);

INSERT INTO `books`
(`id`,`seller_id`,`category_id`,`title`,`author`,`isbn`,`edition`,`publisher`,
 `publication_year`,`language`,`price`,`negotiable`,`book_condition`,
 `condition_details`,`description`,`location_city`,`location_district`,
 `delivery_method`,`status`,`views`,`created_at`)
VALUES
(1,2,2,'Database System Concepts (6th Edition)',
 'Silberschatz, Korth, Sudarshan','9780073523323','6th Edition','McGraw-Hill',
 2010,'English',2850.00,TRUE,'Good',
 'Clean pages with minor pencil marks in one chapter.',
 'Useful reference for Computer Science and Software Engineering students.',
 'Colombo','Colombo','Both','available',32,'2026-08-10 10:30:00'),

(2,3,1,'Madol Doova (Original Sinhala Edition)',
 'Martin Wickramasinghe',NULL,NULL,NULL,NULL,'Sinhala',750.00,FALSE,'Like New',
 'Very clean copy with no major marks.',
 'Classic Sri Lankan novel about Upali and Jinadasa.',
 'Kandy','Kandy','Meetup','available',48,'2026-08-11 12:00:00'),

(3,4,4,'Atomic Habits',
 'James Clear','9780735211292','1st Edition','Avery',
 2018,'English',1800.00,TRUE,'Brand New',
 'Unopened copy.',
 'Guide to building better habits through small consistent improvements.',
 'Galle','Galle','Courier','available',75,'2026-08-12 14:20:00'),

(4,2,2,'Introduction to Algorithms (CLRS 3rd Edition)',
 'Thomas H. Cormen, Charles E. Leiserson',NULL,'3rd Edition','MIT Press',
 2009,'English',4200.00,FALSE,'Fair',
 'Cover has edge wear; all pages are readable.',
 'Comprehensive reference for algorithms and data structures.',
 'Colombo','Colombo','Both','available',41,'2026-08-13 09:10:00'),

(5,5,6,'An Historical Relation of the Island Ceylon',
 'Robert Knox',NULL,'Collector Edition',NULL,
 2004,'English',3500.00,TRUE,'Good',
 'Hardcover with minor age-related wear.',
 'Historical account of 17th-century Ceylon.',
 'Kurunegala','Kurunegala','Courier','available',29,'2026-08-14 15:40:00'),

(6,4,5,'Dune (Deluxe Hardcover Edition)',
 'Frank Herbert',NULL,'Deluxe Edition',NULL,
 2021,'English',3200.00,TRUE,'Like New',
 'Read once and carefully stored.',
 'Science-fiction classic set on Arrakis.',
 'Galle','Galle','Both','available',67,'2026-08-15 11:00:00'),

(7,3,3,'Charlie and the Chocolate Factory',
 'Roald Dahl',NULL,NULL,NULL,
 NULL,'English',950.00,FALSE,'Good',
 'Minor wear on the cover.',
 'Classic children''s fantasy story.',
 'Kandy','Kandy','Courier','available',25,'2026-08-15 16:30:00'),

(8,5,5,'Sherlock Holmes: The Complete Novels and Stories',
 'Arthur Conan Doyle',NULL,'Complete Edition',NULL,
 NULL,'English',2400.00,TRUE,'Fair',
 'Paper edges have minor tanning; binding is solid.',
 'Complete Sherlock Holmes collection.',
 'Kurunegala','Kurunegala','Courier','available',53,'2026-08-16 08:50:00'),

(9,5,7,'National Geographic Collector Magazine Pack',
 'National Geographic Society',NULL,'Collector Pack',NULL,
 NULL,'English',1500.00,TRUE,'Good',
 'Five issues with good glossy pages.',
 'Collector magazine pack featuring wildlife and world history.',
 'Kurunegala','Kurunegala','Courier','available',21,'2026-08-16 13:15:00'),

(10,1,1,'The Great Gatsby',
 'F. Scott Fitzgerald',NULL,'Penguin Classics', 'Penguin',
 2018,'English',1100.00,FALSE,'Like New',
 'No creased pages.',
 'Classic novel exploring wealth, love and the American dream.',
 'Colombo','Colombo','Courier','sold',91,'2026-08-16 17:00:00'),

(11,2,2,'Campbell Biology (11th Global Edition)',
 'Lisa A. Urry, Michael L. Cain',NULL,'11th Global Edition','Pearson',
 2016,'English',4800.00,TRUE,'Like New',
 'Clean pages and intact binding.',
 'Reference book for Biology and Biomedical students.',
 'Colombo','Colombo','Both','available',38,'2026-08-17 08:30:00'),

(12,3,4,'Rich Dad Poor Dad',
 'Robert T. Kiyosaki',NULL,NULL,NULL,
 NULL,'English',1400.00,TRUE,'Good',
 'Readable copy with light cover wear.',
 'Personal finance classic about financial education.',
 'Kandy','Kandy','Courier','available',44,'2026-08-17 09:45:00');

-- Multiple images per listing.
INSERT INTO `book_images` (`book_id`,`image_url`,`is_primary`,`display_order`) VALUES
(1,'book_database_systems.svg',TRUE,1),
(1,'book_database_back.svg',FALSE,2),
(2,'book_madol_doova.svg',TRUE,1),
(3,'book_atomic_habits.svg',TRUE,1),
(4,'book_algorithms_clrs.svg',TRUE,1),
(5,'book_robert_knox.svg',TRUE,1),
(6,'book_dune.svg',TRUE,1),
(7,'book_charlie_chocolate.svg',TRUE,1),
(8,'book_sherlock_holmes.svg',TRUE,1),
(9,'book_natgeo_pack.svg',TRUE,1),
(10,'book_great_gatsby.svg',TRUE,1),
(11,'book_campbell_biology.svg',TRUE,1),
(12,'book_rich_dad.svg',TRUE,1);

-- Sample favorite/wishlist records.
INSERT INTO `favorites` (`user_id`,`book_id`) VALUES
(2,6),
(2,8),
(3,3),
(4,1),
(5,4);

-- Sample completed order.
INSERT INTO `orders`
(`id`,`buyer_id`,`shipping_address`,`subtotal`,`delivery_fee`,`total_amount`,
 `payment_method`,`status`,`created_at`)
VALUES
(1,3,
 'No. 18, Lake Road, Kandy, Kandy District, Central Province',
 1100.00,350.00,1450.00,
 'Cash on Delivery','Completed','2026-08-16 18:30:00');

INSERT INTO `order_items`
(`id`,`order_id`,`book_id`,`seller_id`,`book_title`,`price`,`quantity`)
VALUES
(1,1,10,1,'The Great Gatsby',1100.00,1);

INSERT INTO `payments`
(`order_id`,`amount`,`method`,`transaction_reference`,`status`,`paid_at`)
VALUES
(1,1450.00,'Cash on Delivery',NULL,'Paid','2026-08-18 13:30:00');

-- Sample seller review.
INSERT INTO `reviews`
(`reviewer_id`,`seller_id`,`book_id`,`order_id`,`rating`,`comment`)
VALUES
(3,1,10,1,5,'Fast delivery and the book was exactly as described.');

-- Sample buyer/seller messages.
INSERT INTO `messages`
(`sender_id`,`receiver_id`,`book_id`,`message`,`is_read`)
VALUES
(3,2,1,'Is the Database System Concepts book still available?',TRUE),
(2,3,1,'Yes, it is available. The price is slightly negotiable.',TRUE),
(4,5,8,'Can you arrange courier delivery for this book?',FALSE);

-- Sample report.
INSERT INTO `reports`
(`reporter_id`,`reported_user_id`,`book_id`,`reason`,`details`,`status`)
VALUES
(4,5,9,'Wrong Information',
 'Please verify the condition and publication details of this listing.',
 'Pending');

-- Sample initial banners (Hero slides and Promo rail cards).
INSERT INTO `banners` 
(`id`, `title`, `tag`, `description`, `btn_text`, `btn_link`, `image_url`, `position`, `bg_color`, `icon`, `display_order`, `is_active`, `created_by`) 
VALUES
(1, 'New Arrivals', '✨ Book Premiere', 'Discover the latest additions to our collection - curated for readers who love cozy reads and great finds.', 'Browse Now', '#bookCatalogGrid', 'images/hero-cozy-books.jpg', 'hero_slide', 'promo-blue', 'bi-stars', 1, 1, 1),
(2, 'Academic Picks', '🎓 Campus Essentials', 'Save up to 70% on pre-loved engineering, medicine, A/L, and university revision textbooks.', 'Explore Textbooks', 'index.php?category=educational-academic', 'images/hero-academic-study.jpg', 'hero_slide', 'promo-teal', 'bi-mortarboard-fill', 2, 1, 1),
(3, 'Collector Editions', '💎 Rare & Vintage', 'Timeless Sinhala literature classics, vintage magazines, and rare out-of-print collectibles.', 'View Collectibles', 'index.php?category=biography-history-poetry', 'images/hero-rare-vintage.jpg', 'hero_slide', 'promo-gold', 'bi-gem', 3, 1, 1),
(4, 'Reader Favorites', '🏡 Community Nook', 'Connect directly with fellow book lovers across Sri Lanka to buy and resell pre-owned novels and stories.', 'Explore Bestsellers', 'index.php?sort=latest', 'images/library-wide-banner.jpg', 'hero_slide', 'promo-green', 'bi-people-fill', 4, 1, 1),
(5, 'Best Sellers & Trending', 'Trending Reads', 'Top rated novels & fiction reads', 'View', 'index.php?sort=latest', '', 'promo_card', 'promo-blue', 'bi-fire', 1, 1, 1),
(6, 'Academic & University', 'Campus Picks', 'Save up to 70% on textbooks', 'View', 'index.php?category=educational-academic', '', 'promo_card', 'promo-teal', 'bi-mortarboard-fill', 2, 1, 1),
(7, 'Sell Your Used Books', 'Instant Cash', 'Turn old books into instant cash', 'View', 'sell.php', '', 'promo_card', 'promo-gold', 'bi-tag-fill', 3, 1, 1),
(8, 'Islandwide Cash on Delivery', 'Fast Delivery', 'Fast courier directly to doorstep', 'View', 'cart.php', '', 'promo_card', 'promo-green', 'bi-truck', 4, 1, 1),
(9, 'Brand New & Like New', 'Guaranteed Quality', 'Pristine condition guaranteed', 'View', 'index.php?condition=Brand+New', '', 'promo_card', 'promo-purple', 'bi-shield-check', 5, 1, 1);

-- ============================================================================
-- USEFUL C2C MARKETPLACE VIEWS
-- ============================================================================

CREATE OR REPLACE VIEW `available_books` AS
SELECT
  b.id,
  b.title,
  b.author,
  b.price,
  b.book_condition,
  b.negotiable,
  b.location_city,
  b.delivery_method,
  c.name AS category,
  u.id AS seller_id,
  u.name AS seller_name,
  u.profile_image AS seller_image
FROM books b
JOIN categories c ON b.category_id = c.id
JOIN users u ON b.seller_id = u.id
WHERE b.status = 'available'
  AND u.account_status = 'active';

CREATE OR REPLACE VIEW `seller_ratings` AS
SELECT
  u.id AS seller_id,
  u.name AS seller_name,
  COUNT(r.id) AS total_reviews,
  ROUND(AVG(r.rating),2) AS average_rating
FROM users u
LEFT JOIN reviews r ON u.id = r.seller_id
GROUP BY u.id, u.name;

CREATE OR REPLACE VIEW `order_summary` AS
SELECT
  o.id AS order_id,
  o.buyer_id,
  u.name AS buyer_name,
  o.subtotal,
  o.delivery_fee,
  o.total_amount,
  o.payment_method,
  o.status,
  o.created_at
FROM orders o
JOIN users u ON o.buyer_id = u.id;

-- ============================================================================
-- END OF BOOKSY DATABASE
-- ============================================================================
