<?php
/**
 * Authentication API
 * Handles login, logout, and session management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkAuth();
        break;
    case 'me':
        getCurrentUser();
        break;
    case 'change-password':
        changePassword();
        break;
    case 'update-account':
        updateAccount();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Handle user login
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? $_POST['email'] ?? '';
    $password = $input['password'] ?? $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        errorResponse('Email and password are required');
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, email, password, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            errorResponse('Invalid email or password', 401);
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['logged_in_at'] = time();

        // Regenerate session ID for security
        session_regenerate_id(true);

        successResponse([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ],
            'redirect' => 'dashboard.html'
        ], 'Login successful');

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    // Clear session data
    $_SESSION = [];

    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy session
    session_destroy();

    successResponse(['redirect' => 'login.html'], 'Logged out successfully');
}

/**
 * Check if user is authenticated
 */
function checkAuth() {
    if (isLoggedIn()) {
        successResponse([
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name']
            ]
        ]);
    } else {
        jsonResponse([
            'success' => true,
            'authenticated' => false
        ]);
    }
}

/**
 * Change user password
 */
function changePassword() {
    if (!isLoggedIn()) {
        errorResponse('Not authenticated', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        errorResponse('Current and new passwords are required');
    }

    if (strlen($newPassword) < 8) {
        errorResponse('New password must be at least 8 characters');
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!password_verify($currentPassword, $user['password'])) {
            errorResponse('Current password is incorrect', 401);
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);

        if ($stmt->rowCount() === 0) {
            errorResponse('Failed to update password. Please try again.');
        }

        successResponse([], 'Password changed successfully');

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        errorResponse('Not authenticated', 401);
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, email, name, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            errorResponse('User not found', 404);
        }

        successResponse([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role']
            ]
        ]);

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * Update account info
 */
function updateAccount() {
    if (!isLoggedIn()) {
        errorResponse('Not authenticated', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $name = trim($input['name'] ?? '');

    if (!$email) {
        errorResponse('Valid email is required');
    }

    try {
        $db = getDB();

        // Check if email is already used by another user
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            errorResponse('Email is already in use');
        }

        // Update user
        $stmt = $db->prepare("UPDATE users SET email = ?, name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$email, $name, $_SESSION['user_id']]);

        // Update session
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $name;

        successResponse([
            'user' => [
                'email' => $email,
                'name' => $name
            ]
        ], 'Account updated successfully');

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}
