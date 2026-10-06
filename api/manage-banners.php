<?php
/**
 * Booksy - API: Manage Banners & Showcase Promotions
 * Authorizes Platform Admins (Site Managers) and the Platform Owner to create, update, reorder, toggle, and delete website banners.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database table exists
ensure_banners_table($pdo);

// Strict Admin & Owner Authorization Check
if (!is_admin()) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Access Denied: Administrative privileges required to manage banners.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action   = trim($input['action'] ?? ($_POST['action'] ?? ''));
$redirect = trim($input['redirect'] ?? ($_POST['redirect'] ?? '../admin-banners.php'));
$userId   = $_SESSION['user_id'] ?? null;

// Helper to return response according to context (AJAX JSON vs Form Redirect)
function send_response(bool $success, string $message, array $extra = [], ?string $redirect = null) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || isset($_GET['ajax']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
        if (!$success) {
            http_response_code(400);
        }
        echo json_encode(array_merge([
            'status'  => $success ? 'success' : 'error',
            'message' => $message
        ], $extra));
        exit;
    }

    set_flash($success ? 'success' : 'danger', $message);
    $target = $redirect ?: '../admin-banners.php';
    header("Location: $target");
    exit;
}

// Helper to handle image uploads safely
function handle_banner_upload(?array $file): ?string {
    if (!$file || !isset($file['tmp_name']) || empty($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed with error code: ' . $file['error']);
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

    $fileName = $file['name'];
    $fileTmp  = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $fileTmp);
    finfo_close($finfo);

    if (!in_array($fileExt, $allowedExts) || !in_array($mime, $allowedMimes)) {
        throw new Exception('Invalid image file format. Only JPG, PNG, WebP, and SVG are supported.');
    }

    if ($fileSize > 8 * 1024 * 1024) {
        throw new Exception('Image file size cannot exceed 8MB.');
    }

    $uploadDir = __DIR__ . '/../uploads/banners/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newFileName = 'banner_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
    $targetPath  = $uploadDir . $newFileName;

    if (!move_uploaded_file($fileTmp, $targetPath)) {
        throw new Exception('Could not save uploaded banner image file to server.');
    }

    return 'uploads/banners/' . $newFileName;
}

switch ($action) {
    case 'create':
        $title        = trim($_POST['title'] ?? ($input['title'] ?? ''));
        $tag          = trim($_POST['tag'] ?? ($input['tag'] ?? ''));
        $description  = trim($_POST['description'] ?? ($input['description'] ?? ''));
        $btnText      = trim($_POST['btn_text'] ?? ($input['btn_text'] ?? 'Browse Now'));
        $btnLink      = trim($_POST['btn_link'] ?? ($input['btn_link'] ?? '#bookCatalogGrid'));
        $position     = trim($_POST['position'] ?? ($input['position'] ?? 'hero_slide'));
        $bgColor      = trim($_POST['bg_color'] ?? ($input['bg_color'] ?? 'promo-blue'));
        $icon         = trim($_POST['icon'] ?? ($input['icon'] ?? 'bi-stars'));
        $displayOrder = intval($_POST['display_order'] ?? ($input['display_order'] ?? 1));
        $isActive     = isset($_POST['is_active']) ? 1 : (isset($input['is_active']) ? (int)$input['is_active'] : 1);
        $presetImage  = trim($_POST['preset_image'] ?? ($input['preset_image'] ?? ''));
        $customUrl    = trim($_POST['image_url'] ?? ($input['image_url'] ?? ''));

        if (empty($title)) {
            send_response(false, 'Banner title / headline is required.', [], $redirect);
        }

        if (!in_array($position, ['hero_slide', 'promo_card', 'top_announcement'])) {
            $position = 'hero_slide';
        }

        // Determine Image URL
        $finalImageUrl = '';
        try {
            if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
                $finalImageUrl = handle_banner_upload($_FILES['image']);
            }
        } catch (Exception $e) {
            send_response(false, $e->getMessage(), [], $redirect);
        }

        if (empty($finalImageUrl)) {
            if (!empty($presetImage)) {
                $finalImageUrl = $presetImage;
            } elseif (!empty($customUrl)) {
                $finalImageUrl = $customUrl;
            } elseif ($position === 'hero_slide') {
                $finalImageUrl = 'images/hero-cozy-books.jpg'; // default fallback
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO `banners` 
                (`title`, `tag`, `description`, `btn_text`, `btn_link`, `image_url`, `position`, `bg_color`, `icon`, `display_order`, `is_active`, `created_by`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $tag,
                $description,
                $btnText,
                $btnLink,
                $finalImageUrl,
                $position,
                $bgColor,
                $icon,
                $displayOrder,
                $isActive,
                $userId
            ]);

            $newId = $pdo->lastInsertId();
            send_response(true, 'New banner "<strong>' . htmlspecialchars($title) . '</strong>" has been created successfully!', ['id' => $newId], $redirect);
        } catch (PDOException $e) {
            send_response(false, 'Database error while creating banner: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'update':
        $id           = intval($_POST['id'] ?? ($input['id'] ?? 0));
        $title        = trim($_POST['title'] ?? ($input['title'] ?? ''));
        $tag          = trim($_POST['tag'] ?? ($input['tag'] ?? ''));
        $description  = trim($_POST['description'] ?? ($input['description'] ?? ''));
        $btnText      = trim($_POST['btn_text'] ?? ($input['btn_text'] ?? 'Browse Now'));
        $btnLink      = trim($_POST['btn_link'] ?? ($input['btn_link'] ?? '#bookCatalogGrid'));
        $position     = trim($_POST['position'] ?? ($input['position'] ?? 'hero_slide'));
        $bgColor      = trim($_POST['bg_color'] ?? ($input['bg_color'] ?? 'promo-blue'));
        $icon         = trim($_POST['icon'] ?? ($input['icon'] ?? 'bi-stars'));
        $displayOrder = intval($_POST['display_order'] ?? ($input['display_order'] ?? 1));
        $isActive     = isset($_POST['is_active']) ? 1 : (isset($input['is_active']) ? (int)$input['is_active'] : 0);
        $presetImage  = trim($_POST['preset_image'] ?? ($input['preset_image'] ?? ''));
        $customUrl    = trim($_POST['image_url'] ?? ($input['image_url'] ?? ''));

        if ($id <= 0 || empty($title)) {
            send_response(false, 'Valid Banner ID and Title are required for updating.', [], $redirect);
        }

        $current = get_banner_by_id($pdo, $id);
        if (!$current) {
            send_response(false, 'Banner not found in database.', [], $redirect);
        }

        $finalImageUrl = $current['image_url'];

        try {
            if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
                $uploaded = handle_banner_upload($_FILES['image']);
                if ($uploaded) {
                    $finalImageUrl = $uploaded;
                }
            } elseif (!empty($presetImage)) {
                $finalImageUrl = $presetImage;
            } elseif (!empty($customUrl)) {
                $finalImageUrl = $customUrl;
            }
        } catch (Exception $e) {
            send_response(false, $e->getMessage(), [], $redirect);
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE `banners` 
                SET `title` = ?, `tag` = ?, `description` = ?, `btn_text` = ?, `btn_link` = ?, 
                    `image_url` = ?, `position` = ?, `bg_color` = ?, `icon` = ?, 
                    `display_order` = ?, `is_active` = ? 
                WHERE `id` = ?
            ");
            $stmt->execute([
                $title,
                $tag,
                $description,
                $btnText,
                $btnLink,
                $finalImageUrl,
                $position,
                $bgColor,
                $icon,
                $displayOrder,
                $isActive,
                $id
            ]);

            send_response(true, 'Banner "<strong>' . htmlspecialchars($title) . '</strong>" updated successfully!', ['id' => $id], $redirect);
        } catch (PDOException $e) {
            send_response(false, 'Database error while updating banner: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'toggle_status':
        $id = intval($input['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) {
            send_response(false, 'Invalid banner ID provided.', [], $redirect);
        }

        $current = get_banner_by_id($pdo, $id);
        if (!$current) {
            send_response(false, 'Banner not found.', [], $redirect);
        }

        $newStatus = ($current['is_active'] == 1) ? 0 : 1;
        if (isset($input['is_active'])) {
            $newStatus = (int)$input['is_active'];
        }

        try {
            $stmt = $pdo->prepare("UPDATE `banners` SET `is_active` = ? WHERE `id` = ?");
            $stmt->execute([$newStatus, $id]);

            $statusLabel = $newStatus ? 'activated and is now visible on the website' : 'deactivated and hidden';
            send_response(true, "Banner has been $statusLabel.", [
                'id'        => $id,
                'is_active' => $newStatus
            ], $redirect);
        } catch (PDOException $e) {
            send_response(false, 'Failed to toggle banner status: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'delete':
        $id = intval($input['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) {
            send_response(false, 'Invalid banner ID.', [], $redirect);
        }

        $current = get_banner_by_id($pdo, $id);
        if (!$current) {
            send_response(false, 'Banner does not exist.', [], $redirect);
        }

        // Clean up uploaded file if in uploads/banners/
        if (!empty($current['image_url']) && strpos($current['image_url'], 'uploads/banners/') === 0) {
            $filePath = __DIR__ . '/../' . $current['image_url'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM `banners` WHERE `id` = ?");
            $stmt->execute([$id]);

            send_response(true, 'Banner "<strong>' . htmlspecialchars($current['title']) . '</strong>" has been permanently removed.', ['id' => $id], $redirect);
        } catch (PDOException $e) {
            send_response(false, 'Database error while deleting banner: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'reorder':
        $orders = $input['orders'] ?? ($_POST['orders'] ?? []);
        if (!is_array($orders) || empty($orders)) {
            send_response(false, 'No order data provided.', [], $redirect);
        }

        try {
            $stmt = $pdo->prepare("UPDATE `banners` SET `display_order` = ? WHERE `id` = ?");
            foreach ($orders as $item) {
                if (isset($item['id']) && isset($item['order'])) {
                    $stmt->execute([intval($item['order']), intval($item['id'])]);
                }
            }
            send_response(true, 'Banner display sequence reordered successfully!', [], $redirect);
        } catch (PDOException $e) {
            send_response(false, 'Database error updating banner sequence: ' . $e->getMessage(), [], $redirect);
        }
        break;

    case 'reset_defaults':
        if (reset_default_banners($pdo, $userId)) {
            send_response(true, 'Website banners have been successfully reset to Booksy default showcase presets.', [], $redirect);
        } else {
            send_response(false, 'Failed to reset banners. Please check server error logs.', [], $redirect);
        }
        break;

    default:
        send_response(false, 'Invalid action specified for Banner Management API.', [], $redirect);
        break;
}
