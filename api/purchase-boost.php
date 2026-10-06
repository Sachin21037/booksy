<?php
/**
 * Booksy - API: Purchase Listing Boost
 * Authenticated endpoint for sellers to activate promotional boosts on their book listings.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please sign in to purchase listing promotions.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$bookId = isset($input['book_id']) ? intval($input['book_id']) : 0;
$boostType = trim($input['boost_type'] ?? 'featured_listing');
$userId = $_SESSION['user_id'];

if ($bookId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Book ID provided.']);
    exit;
}

$allowedBoosts = ['featured_listing', 'homepage_boost', 'category_boost', 'search_boost'];
if (!in_array($boostType, $allowedBoosts)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid promotion boost type selected.']);
    exit;
}

$result = purchase_listing_boost($pdo, $userId, $bookId, $boostType);

if ($result['success']) {
    echo json_encode([
        'status'  => 'success',
        'message' => $result['message'],
        'data'    => $result
    ]);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => $result['message']
    ]);
}
