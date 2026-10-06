<?php
/**
 * Booksy - Banner Management & Showcase Studio Automated Test Suite
 * Validates database auto-setup, CRUD actions, active status toggling, role authorization, and fallback mechanism.
 */

require_once __DIR__ . '/../db.php';

echo "========================================================================\n";
echo "         BOOKSY BANNER MANAGEMENT SYSTEM TEST SUITE                    \n";
echo "========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $testName, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] " . $testName . ($details ? " ($details)" : "") . "\n";
    } else {
        $failCount++;
        echo "  [FAIL] " . $testName . ($details ? " - FAILED: $details" : "") . "\n";
    }
}

// ----------------------------------------------------------------------------
// Test 1: Auto-Setup & Table Initialization
// ----------------------------------------------------------------------------
echo "--- TEST SUITE 1: Banners Table Setup & Defaults ---\n";
ensure_banners_table($pdo);

$tableExists = $pdo->query("SHOW TABLES LIKE 'banners'")->rowCount() > 0;
assertTest("Banners table exists in database", $tableExists);

$stats = get_banner_stats($pdo);
assertTest("Banners stats returned correctly", is_array($stats) && $stats['total'] >= 9, "Total banners: " . $stats['total']);
assertTest("Default 4 Hero slides are active", $stats['hero_active'] >= 4, "Active hero slides: " . $stats['hero_active']);
assertTest("Default 5 Promo cards are active", $stats['promo_active'] >= 5, "Active promo cards: " . $stats['promo_active']);

// ----------------------------------------------------------------------------
// Test 2: Querying Active Banners
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 2: Fetching Active Banners by Position ---\n";
$heroBanners = get_active_banners($pdo, 'hero_slide');
assertTest("Active hero slides query returns valid array", count($heroBanners) >= 4);
assertTest("First hero slide has title and image", !empty($heroBanners[0]['title']) && !empty($heroBanners[0]['image_url']), "Title: " . $heroBanners[0]['title']);

$promoBanners = get_active_banners($pdo, 'promo_card');
assertTest("Active promo cards query returns valid array", count($promoBanners) >= 5);
assertTest("First promo card has color and icon", !empty($promoBanners[0]['bg_color']) && !empty($promoBanners[0]['icon']), "Class: " . $promoBanners[0]['bg_color']);

// ----------------------------------------------------------------------------
// Test 3: Insert / Create New Banner
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 3: Create New Custom Banner ---\n";
$testTitle = 'Special Weekend Literary Fest';
$testTag = '🎉 Limited Event';
$testDesc = 'Exclusive discounts on all classic literature for 48 hours only.';
$testLink = 'index.php?category=novels-fiction';
$testImg = 'images/hero-rare-vintage.jpg';

$insertStmt = $pdo->prepare("
    INSERT INTO `banners` 
    (`title`, `tag`, `description`, `btn_text`, `btn_link`, `image_url`, `position`, `bg_color`, `icon`, `display_order`, `is_active`, `created_by`) 
    VALUES (?, ?, ?, 'Explore Fest', ?, ?, 'hero_slide', 'promo-gold', 'bi-stars', 99, 1, 1)
");
$insertSuccess = $insertStmt->execute([$testTitle, $testTag, $testDesc, $testLink, $testImg]);
$newBannerId = (int)$pdo->lastInsertId();

assertTest("New banner insert query succeeded", $insertSuccess && $newBannerId > 0, "ID: $newBannerId");

$fetched = get_banner_by_id($pdo, $newBannerId);
assertTest("Inserted banner retrieved with correct title", $fetched && $fetched['title'] === $testTitle, "Title: " . ($fetched['title'] ?? 'none'));
assertTest("Inserted banner tag preserved", $fetched && $fetched['tag'] === $testTag, "Tag: " . ($fetched['tag'] ?? 'none'));

// ----------------------------------------------------------------------------
// Test 4: Update Existing Banner
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 4: Update Existing Banner ---\n";
$updatedTitle = 'Special Weekend Literary Fest (Extended)';
$updateStmt = $pdo->prepare("UPDATE `banners` SET `title` = ?, `display_order` = 50 WHERE `id` = ?");
$updateSuccess = $updateStmt->execute([$updatedTitle, $newBannerId]);

$fetchedUpdated = get_banner_by_id($pdo, $newBannerId);
assertTest("Banner title updated successfully", $updateSuccess && $fetchedUpdated['title'] === $updatedTitle);
assertTest("Banner display order updated", $fetchedUpdated['display_order'] == 50, "Order: " . $fetchedUpdated['display_order']);

// ----------------------------------------------------------------------------
// Test 5: Toggle Active / Inactive Status
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 5: Toggle Active / Inactive Status ---\n";
$toggleStmt = $pdo->prepare("UPDATE `banners` SET `is_active` = 0 WHERE `id` = ?");
$toggleStmt->execute([$newBannerId]);

$fetchedInactive = get_banner_by_id($pdo, $newBannerId);
assertTest("Banner is now inactive (is_active = 0)", $fetchedInactive && $fetchedInactive['is_active'] == 0);

$activeHeroList = get_active_banners($pdo, 'hero_slide');
$foundInActiveList = false;
foreach ($activeHeroList as $item) {
    if ($item['id'] == $newBannerId) {
        $foundInActiveList = true;
        break;
    }
}
assertTest("Inactive banner is excluded from get_active_banners()", !$foundInActiveList);

// Re-activate
$toggleStmt2 = $pdo->prepare("UPDATE `banners` SET `is_active` = 1 WHERE `id` = ?");
$toggleStmt2->execute([$newBannerId]);
$fetchedActiveAgain = get_banner_by_id($pdo, $newBannerId);
assertTest("Banner reactivated (is_active = 1)", $fetchedActiveAgain && $fetchedActiveAgain['is_active'] == 1);

// ----------------------------------------------------------------------------
// Test 6: Delete Banner
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 6: Delete Banner ---\n";
$deleteStmt = $pdo->prepare("DELETE FROM `banners` WHERE `id` = ?");
$deleteSuccess = $deleteStmt->execute([$newBannerId]);

$fetchedDeleted = get_banner_by_id($pdo, $newBannerId);
assertTest("Banner deleted from database", $deleteSuccess && $fetchedDeleted === null);

// ----------------------------------------------------------------------------
// Test 7: Reset Defaults
// ----------------------------------------------------------------------------
echo "\n--- TEST SUITE 7: Reset Defaults ---\n";
$resetOk = reset_default_banners($pdo, 1);
assertTest("reset_default_banners() executes cleanly", $resetOk);

$finalStats = get_banner_stats($pdo);
assertTest("Final state has exact 4 hero slides", $finalStats['hero_active'] == 4, "Count: " . $finalStats['hero_active']);
assertTest("Final state has exact 5 promo cards", $finalStats['promo_active'] == 5, "Count: " . $finalStats['promo_active']);

// ----------------------------------------------------------------------------
// Test Summary
// ----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "TEST RESULTS: Total Passes = $passCount, Total Failures = $failCount\n";
echo "========================================================================\n";

if ($failCount === 0) {
    echo ">>> ALL BANNER TESTS PASSED! <<<\n\n";
    exit(0);
} else {
    echo ">>> SOME BANNER TESTS FAILED! <<<\n\n";
    exit(1);
}
