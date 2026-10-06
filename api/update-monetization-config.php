<?php
/**
 * Booksy - API: Update Monetization Parameters (Admin Only)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin authorization required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$redirect = trim($input['redirect'] ?? 'admin-monetization.php');

$threshold = isset($input['commission_free_threshold']) ? max(0, floatval($input['commission_free_threshold'])) : null;
$commRate  = isset($input['standard_commission_rate']) ? max(0, floatval($input['standard_commission_rate'])) : null;

// Normalize commission rate if entered as percentage (e.g. 3 -> 0.03)
if ($commRate !== null && $commRate > 1.0) {
    $commRate = $commRate / 100.0;
}

$boostFeaturedFee = isset($input['boost_featured_fee']) ? max(0, floatval($input['boost_featured_fee'])) : null;
$boostHomepageFee = isset($input['boost_homepage_fee']) ? max(0, floatval($input['boost_homepage_fee'])) : null;
$boostCategoryFee = isset($input['boost_category_fee']) ? max(0, floatval($input['boost_category_fee'])) : null;
$boostSearchFee   = isset($input['boost_search_fee']) ? max(0, floatval($input['boost_search_fee'])) : null;
$vipPrice         = isset($input['vip_monthly_price']) ? max(0, floatval($input['vip_monthly_price'])) : null;

if ($threshold !== null) {
    update_monetization_setting($pdo, 'commission_free_threshold', number_format($threshold, 2, '.', ''));
}
if ($commRate !== null) {
    update_monetization_setting($pdo, 'standard_commission_rate', strval($commRate));
}
if ($boostFeaturedFee !== null) {
    update_monetization_setting($pdo, 'boost_featured_fee', number_format($boostFeaturedFee, 2, '.', ''));
}
if ($boostHomepageFee !== null) {
    update_monetization_setting($pdo, 'boost_homepage_fee', number_format($boostHomepageFee, 2, '.', ''));
}
if ($boostCategoryFee !== null) {
    update_monetization_setting($pdo, 'boost_category_fee', number_format($boostCategoryFee, 2, '.', ''));
}
if ($boostSearchFee !== null) {
    update_monetization_setting($pdo, 'boost_search_fee', number_format($boostSearchFee, 2, '.', ''));
}
if ($vipPrice !== null) {
    update_monetization_setting($pdo, 'vip_monthly_price', number_format($vipPrice, 2, '.', ''));
}

if (!empty($redirect)) {
    set_flash('success', 'Monetization parameters updated successfully.');
    header("Location: " . $redirect);
    exit;
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Monetization parameters updated successfully.'
]);
