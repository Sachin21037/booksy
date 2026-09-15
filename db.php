<?php
/**
 * Booksy - Database Connection Handler
 * 3-Tier Architecture - Database Access Layer
 */

$host    = 'localhost';
$db      = 'booksy_db';
$user    = 'root';
$charset = 'utf8mb4';

// Attempt primary user password ('21037') then fallback to standard XAMPP default ('')
$passwords = ['21037', '', 'root'];
$pdo = null;
$connection_error = null;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

foreach ($passwords as $pass) {
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        break;
    } catch (PDOException $e) {
        $connection_error = $e->getMessage();
    }
}

if (!$pdo) {
    die("<div style='font-family:sans-serif;padding:30px;background:#fff0f0;border:1px solid #f5c2c7;color:#842029;border-radius:8px;max-width:600px;margin:50px auto;'>
        <h2>Database Connection Error</h2>
        <p>Could not connect to database <code>$db</code> on <code>$host</code>.</p>
        <p><small>Error details: " . htmlspecialchars($connection_error) . "</small></p>
        <p>Please make sure MySQL is running in your XAMPP Control Panel and the <code>booksy_db</code> database has been imported via <code>schema.sql</code>.</p>
    </div>");
}

// Helper function to check if user is logged in
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

// Helper function to check if user is Platform Owner
if (!function_exists('is_owner')) {
    function is_owner() {
        return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'owner');
    }
}

// Helper function to check if user is Admin / Site Manager or Owner (administrative privileges)
if (!function_exists('is_admin')) {
    function is_admin() {
        return is_logged_in() && in_array($_SESSION['user_role'] ?? '', ['admin', 'owner']);
    }
}

// Helper function to get current logged in user
if (!function_exists('get_logged_user')) {
    function get_logged_user() {
        if (!is_logged_in()) return null;
        return [
            'id'    => $_SESSION['user_id'],
            'name'  => $_SESSION['user_name'] ?? 'User',
            'email' => $_SESSION['user_email'] ?? '',
            'phone' => $_SESSION['user_phone'] ?? '',
            'role'  => $_SESSION['user_role'] ?? 'user'
        ];
    }
}

// Helper function to set flash message
if (!function_exists('set_flash')) {
    function set_flash($type, $message) {
        $_SESSION['flash'] = [
            'type'    => $type, // 'success', 'danger', 'warning', 'info'
            'message' => $message
        ];
    }
}

// Helper function to get and clear flash message
if (!function_exists('get_flash')) {
    function get_flash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}

// Include Monetization & Commission Helper
if (file_exists(__DIR__ . '/includes/monetization.php')) {
    require_once __DIR__ . '/includes/monetization.php';
}

// Include Banners & Showcase Helper
if (file_exists(__DIR__ . '/includes/banners.php')) {
    require_once __DIR__ . '/includes/banners.php';
}

// Include Fraud Detection & Moderation Engine
if (file_exists(__DIR__ . '/includes/fraud_detection.php')) {
    require_once __DIR__ . '/includes/fraud_detection.php';
}
?>