<?php
/**
 * Booksy - Quick Listing Status Toggle API
 * Toggles book status between 'available' and 'sold' for verified sellers
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please log in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($book_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid book ID provided.']);
    exit;
}

try {
    // Check ownership
    $stmt = $pdo->prepare("SELECT id, seller_id, title, status FROM books WHERE id = ? LIMIT 1");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();

    if (!$book || (!$is_admin && $book['seller_id'] != $user_id)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to modify this listing.']);
        exit;
    }

    $new_status = ($book['status'] === 'available') ? 'sold' : 'available';

    $update_stmt = $pdo->prepare("UPDATE books SET status = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $book_id]);

    echo json_encode([
        'status'     => 'success',
        'book_id'    => $book_id,
        'new_status' => $new_status,
        'message'    => 'Listing status updated to ' . ucfirst($new_status) . '.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database update error: ' . $e->getMessage()]);
}
