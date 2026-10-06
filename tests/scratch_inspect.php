<?php
require_once __DIR__ . '/../db.php';

echo "=== USERS ===\n";
$users = $pdo->query("SELECT id, name, email, phone, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

echo "\n=== CATEGORIES ===\n";
$cats = $pdo->query("SELECT id, name, slug FROM categories")->fetchAll(PDO::FETCH_ASSOC);
print_r($cats);

echo "\n=== EXISTING BOOKS ===\n";
$books = $pdo->query("SELECT id, seller_id, category_id, title, author, price, image_url, status FROM books ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($books);
