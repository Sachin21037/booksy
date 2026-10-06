<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Preserve cart if desired or reset user session
$cart = $_SESSION['cart'] ?? [];

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Start a fresh session to retain cart and show flash message
session_start();
$_SESSION['cart'] = $cart;
set_flash('info', 'You have been successfully signed out.');

header("Location: index.php");
exit;
