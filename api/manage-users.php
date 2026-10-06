<?php
/**
 * Booksy - API: Manage Users & Staff Roles
 * Allows the Platform Owner to create site managers (admins), promote/demote user roles, and manage accounts.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Strict Owner Authorization Check
if (!is_owner()) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Access Denied: Only the Platform Owner has authority to manage admin privileges.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action   = trim($input['action'] ?? '');
$redirect = trim($input['redirect'] ?? 'admin-users.php');

switch ($action) {
    case 'create_admin':
        $name     = trim($input['name'] ?? '');
        $email    = trim($input['email'] ?? '');
        $phone    = trim($input['phone'] ?? '');
        $password = $input['password'] ?? '';
        $role     = trim($input['role'] ?? 'admin'); // Default to admin / site manager
        $bio      = trim($input['bio'] ?? 'Site Manager / Administrator');

        if (empty($name) || empty($email) || empty($password)) {
            $err = 'Name, email, and password are required to create a staff member.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please provide a valid email address.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        if (strlen($password) < 6) {
            $err = 'Password must be at least 6 characters long.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        // Validate role selection: only 'admin' or 'user' can be created here
        if (!in_array($role, ['admin', 'user'])) {
            $role = 'admin';
        }

        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $err = "An account with email '$email' already exists.";
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        if ($insert->execute([$name, $email, $hash, $phone, $role])) {
            $newId = $pdo->lastInsertId();
            $roleLabel = ($role === 'admin') ? 'Site Manager (Admin)' : 'User';
            $msg = "New $roleLabel account for <strong>" . htmlspecialchars($name) . "</strong> has been created successfully.";
            
            if (!empty($redirect)) {
                set_flash('success', $msg);
                header("Location: $redirect");
                exit;
            }

            echo json_encode([
                'status'  => 'success',
                'message' => $msg,
                'user_id' => $newId
            ]);
            exit;
        } else {
            $err = 'Failed to create new user record.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }
        break;

    case 'change_role':
        $userId  = isset($input['user_id']) ? intval($input['user_id']) : 0;
        $newRole = trim($input['new_role'] ?? '');

        if ($userId <= 0 || !in_array($newRole, ['admin', 'user'])) {
            $err = 'Invalid user ID or target role specified.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        // Fetch target user
        $userStmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$userId]);
        $targetUser = $userStmt->fetch();

        if (!$targetUser) {
            $err = 'User record not found.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        // Safeguard: Cannot change role of Platform Owner
        if ($targetUser['role'] === 'owner') {
            $err = 'The Platform Owner role cannot be demoted or altered.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        $upd = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $upd->execute([$newRole, $userId]);

        $roleLabel = ($newRole === 'admin') ? 'Site Manager (Admin)' : 'Regular Member';
        $msg = "User <strong>" . htmlspecialchars($targetUser['name']) . "</strong> has been assigned the role: <strong>$roleLabel</strong>.";

        if (!empty($redirect)) {
            set_flash('success', $msg);
            header("Location: $redirect");
            exit;
        }

        echo json_encode([
            'status'   => 'success',
            'message'  => $msg,
            'user_id'  => $userId,
            'new_role' => $newRole
        ]);
        exit;
        break;

    case 'delete_user':
        $userId = isset($input['user_id']) ? intval($input['user_id']) : 0;

        if ($userId <= 0) {
            $err = 'Invalid user ID.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        // Fetch user
        $userStmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$userId]);
        $targetUser = $userStmt->fetch();

        if (!$targetUser) {
            $err = 'User not found.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        if ($targetUser['role'] === 'owner') {
            $err = 'The Platform Owner account cannot be deleted.';
            if (!empty($redirect)) {
                set_flash('danger', $err);
                header("Location: $redirect");
                exit;
            }
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => $err]);
            exit;
        }

        // Delete user
        $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $del->execute([$userId]);

        $msg = "Account for <strong>" . htmlspecialchars($targetUser['name']) . "</strong> has been permanently removed.";
        if (!empty($redirect)) {
            set_flash('info', $msg);
            header("Location: $redirect");
            exit;
        }

        echo json_encode([
            'status'  => 'success',
            'message' => $msg,
            'user_id' => $userId
        ]);
        exit;
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action parameter.']);
        exit;
}
