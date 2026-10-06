<?php
require_once __DIR__ . '/../db.php';

echo "=== Seeding 3 Users and Book Listings for Uploaded Pictures ===\n";

// 1. Define 3 Users
$usersData = [
    [
        'name' => 'Ruvini Senanayake',
        'email' => 'ruvini.senanayake@gmail.com',
        'password' => 'Password@123',
        'phone' => '0772233445',
        'role' => 'user',
        'account_status' => 'active'
    ],
    [
        'name' => 'Tharindu Bandara',
        'email' => 'tharindu.bandara@gmail.com',
        'password' => 'Password@123',
        'phone' => '0713344556',
        'role' => 'user',
        'account_status' => 'active'
    ],
    [
        'name' => 'Anuki Jayawardena',
        'email' => 'anuki.jayawardena@gmail.com',
        'password' => 'Password@123',
        'phone' => '0764455667',
        'role' => 'user',
        'account_status' => 'active'
    ]
];

$userIds = [];

foreach ($usersData as $u) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$u['email']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $userIds[$u['email']] = $existing['id'];
        echo "User already exists: {$u['name']} (ID: {$existing['id']})\n";
    } else {
        $hashed = password_hash($u['password'], PASSWORD_DEFAULT);
        $insert = $pdo->prepare("
            INSERT INTO users (name, email, password, phone, role, account_status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$u['name'], $u['email'], $hashed, $u['phone'], $u['role'], $u['account_status']]);
        $newId = $pdo->lastInsertId();
        $userIds[$u['email']] = $newId;
        echo "Created User: {$u['name']} (ID: {$newId}, Email: {$u['email']})\n";
    }
}

// 2. Prepare and copy image files to clean, web-safe filenames in uploads/
$uploadsDir = __DIR__ . '/../uploads/';

$imageMappings = [
    'Oliver Twist_ The Original 1838 Classic by Charles Dickens_ Dickens, Charles, Press, Ravenwood_ 9798300475239_ Amazon_com_ Books.jpeg' => 'book_oliver_twist_classic.jpeg',
    'download (35).jpeg' => 'book_nightbooks_jawhite.jpeg',
    'Sherlock Holmes.jpeg' => 'book_sherlock_holmes_adventures.jpeg',
    '“The Dragon’s Whisper - Epic Fantasy eBook Cover Design”.jpeg' => 'book_dragons_whisper_fantasy.jpeg',
    'Book cover Design by David Gardias.jpeg' => 'book_house_of_locks_gardias.jpeg',
    'The Serpent’s Eye - Mysterious eBook Cover Design.jpeg' => 'book_serpents_eye_mystery.jpeg',
    'download (36).jpeg' => 'book_curse_keepers_swank.jpeg'
];

foreach ($imageMappings as $original => $clean) {
    $origPath = $uploadsDir . $original;
    $cleanPath = $uploadsDir . $clean;
    if (file_exists($origPath)) {
        if (!file_exists($cleanPath) || filesize($cleanPath) !== filesize($origPath)) {
            copy($origPath, $cleanPath);
            echo "Copied '$original' -> '$clean'\n";
        } else {
            echo "Clean file '$clean' already up to date.\n";
        }
    } else {
        echo "WARNING: Original file '$original' not found in $uploadsDir\n";
    }
}

// 3. Define Books for each user
$booksData = [
    // Ruvini Senanayake
    [
        'user_email' => 'ruvini.senanayake@gmail.com',
        'category_id' => 1, // Novels and Fiction Books
        'title' => 'Oliver Twist: The Original 1838 Classic',
        'author' => 'Charles Dickens',
        'price' => 2400.00,
        'book_condition' => 'Like New',
        'image_url' => 'book_oliver_twist_classic.jpeg',
        'description' => 'The classic 1838 Victorian masterpiece by Charles Dickens following orphan Oliver Twist navigating London\'s underworld. Premium hardcover edition with ornate gold foil detailing in excellent condition.'
    ],
    [
        'user_email' => 'ruvini.senanayake@gmail.com',
        'category_id' => 3, // Children’s Books
        'title' => 'Nightbooks',
        'author' => 'J. A. White',
        'price' => 1750.00,
        'book_condition' => 'Like New',
        'image_url' => 'book_nightbooks_jawhite.jpeg',
        'description' => 'A thrilling, dark modern fantasy fairy tale by J.A. White. Alex is trapped in a magical apartment by a wicked witch and must tell a scary story every night to survive. Clean pages with vibrant cover artwork.'
    ],

    // Tharindu Bandara
    [
        'user_email' => 'tharindu.bandara@gmail.com',
        'category_id' => 5, // Mystery, Thriller, Fantasy and Science Fiction Books
        'title' => 'The Adventures of Sherlock Holmes',
        'author' => 'Arthur Conan Doyle',
        'price' => 1850.00,
        'book_condition' => 'Brand New',
        'image_url' => 'book_sherlock_holmes_adventures.jpeg',
        'description' => 'Iconic anthology of 12 classic detective mysteries featuring Sherlock Holmes and Dr. Watson. Unread copy in brand new condition with distinctive illustrated cover.'
    ],
    [
        'user_email' => 'tharindu.bandara@gmail.com',
        'category_id' => 5, // Mystery, Thriller, Fantasy and Science Fiction Books
        'title' => 'The Dragon\'s Whisper',
        'author' => 'G. R. Blackwood',
        'price' => 2650.00,
        'book_condition' => 'Brand New',
        'image_url' => 'book_dragons_whisper_fantasy.jpeg',
        'description' => 'An epic fantasy tale chronicling a lone warrior confronting the ancient Azure Dragon guarding the mist-shrouded mountain peaks. Spectacular cover art, pristine spine and crisp uncreased pages.'
    ],
    [
        'user_email' => 'tharindu.bandara@gmail.com',
        'category_id' => 5, // Mystery, Thriller, Fantasy and Science Fiction Books
        'title' => 'House of Locks',
        'author' => 'David Gardias',
        'price' => 2900.00,
        'book_condition' => 'Brand New',
        'image_url' => 'book_house_of_locks_gardias.jpeg',
        'description' => 'A chilling gothic mystery novel set in an ornate manor locked by secret mechanisms and hidden corridors. High quality paperback with intricate gold foil aesthetics.'
    ],

    // Anuki Jayawardena
    [
        'user_email' => 'anuki.jayawardena@gmail.com',
        'category_id' => 5, // Mystery, Thriller, Fantasy and Science Fiction Books
        'title' => 'The Serpent\'s Eye',
        'author' => 'K. A. Sterling',
        'price' => 2100.00,
        'book_condition' => 'Brand New',
        'image_url' => 'book_serpents_eye_mystery.jpeg',
        'description' => 'A dark atmospheric fantasy quest where four explorers journey into the depths of a beast-guarded cavern. Mint condition, collector\'s quality.'
    ],
    [
        'user_email' => 'anuki.jayawardena@gmail.com',
        'category_id' => 5, // Mystery, Thriller, Fantasy and Science Fiction Books
        'title' => 'The Curse Keepers (Book One)',
        'author' => 'D. G. Swank',
        'price' => 2200.00,
        'book_condition' => 'Good',
        'image_url' => 'book_curse_keepers_swank.jpeg',
        'description' => 'New York Times bestselling urban fantasy novel. An ancient prophecy awakens Norse mythological forces, binding two strangers to defend humanity. Minor shelf wear, tight binding.'
    ]
];

// 4. Insert Books
foreach ($booksData as $b) {
    $sellerId = $userIds[$b['user_email']];
    $stmt = $pdo->prepare("SELECT id FROM books WHERE seller_id = ? AND title = ?");
    $stmt->execute([$sellerId, $b['title']]);
    $existingBook = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingBook) {
        // Update image and status just in case
        $update = $pdo->prepare("UPDATE books SET image_url = ?, price = ?, book_condition = ?, description = ?, status = 'available' WHERE id = ?");
        $update->execute([$b['image_url'], $b['price'], $b['book_condition'], $b['description'], $existingBook['id']]);
        echo "Updated Book: '{$b['title']}' (ID: {$existingBook['id']}, Seller ID: {$sellerId})\n";
    } else {
        $insert = $pdo->prepare("
            INSERT INTO books (seller_id, category_id, title, author, price, book_condition, image_url, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')
        ");
        $insert->execute([
            $sellerId,
            $b['category_id'],
            $b['title'],
            $author = $b['author'],
            $b['price'],
            $b['book_condition'],
            $b['image_url'],
            $b['description']
        ]);
        $newBookId = $pdo->lastInsertId();
        echo "Created Book: '{$b['title']}' (ID: {$newBookId}, Seller ID: {$sellerId}, Price: LKR {$b['price']})\n";
    }
}

echo "\n=== Seeding completed successfully! ===\n";
