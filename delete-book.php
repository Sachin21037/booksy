<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// Check ownership
$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? LIMIT 1");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if ($book && ($is_admin || $book['seller_id'] == $user_id)) {
    // Delete book record
    $del_stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
    $del_stmt->execute([$book_id]);
    set_flash('info', 'Book listing "<strong>' . htmlspecialchars($book['title']) . '</strong>" has been deleted.');
} else {
    set_flash('danger', 'Listing could not be deleted or access was denied.');
}

header("Location: my-listings.php");
exit;
