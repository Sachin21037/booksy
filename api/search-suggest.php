<?php
/**
 * Booksy - Live Search Suggestions API Endpoint
 * Returns JSON results matching search query for live autocomplete dropdown
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

$query = trim($_GET['q'] ?? '');
$category = trim($_GET['cat'] ?? '');

if (empty($query) && empty($category)) {
    echo json_encode(['results' => [], 'total' => 0]);
    exit;
}

try {
    $sql = "SELECT b.id, b.title, b.author, b.price, b.book_condition, b.image_url, 
                   c.name AS category_name, c.slug AS category_slug, u.name AS seller_name
            FROM books b
            JOIN categories c ON b.category_id = c.id
            JOIN users u ON b.seller_id = u.id
            WHERE b.status = 'available'
              AND b.image_url IS NOT NULL 
              AND b.image_url != '' 
              AND b.image_url != 'default_book.svg'";
    
    $params = [];

    if (!empty($query)) {
        $sql .= " AND (b.title LIKE :q1 OR b.author LIKE :q2 OR c.name LIKE :q3)";
        $params['q1'] = "%$query%";
        $params['q2'] = "%$query%";
        $params['q3'] = "%$query%";
    }

    if (!empty($category)) {
        $sql .= " AND c.slug = :category";
        $params['category'] = $category;
    }

    $sql .= " ORDER BY b.created_at DESC LIMIT 6";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    $results = [];
    foreach ($books as $b) {
        $cover = (!empty($b['image_url']) && file_exists(__DIR__ . '/../uploads/' . $b['image_url']))
            ? 'uploads/' . $b['image_url']
            : 'uploads/default_book.svg';

        $results[] = [
            'id'             => (int)$b['id'],
            'title'          => $b['title'],
            'author'         => $b['author'],
            'price'          => (float)$b['price'],
            'formatted_price'=> 'Rs. ' . number_format($b['price'], 2),
            'condition'      => $b['book_condition'],
            'category'       => $b['category_name'],
            'category_slug'  => $b['category_slug'],
            'seller'         => $b['seller_name'],
            'cover'          => $cover,
            'url'            => 'book-details.php?id=' . $b['id']
        ];
    }

    echo json_encode([
        'status'  => 'success',
        'results' => $results,
        'count'   => count($results),
        'query'   => $query
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error during search suggestion query: ' . $e->getMessage()
    ]);
}
