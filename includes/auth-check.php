<?php
/**
 * Authentication Check
 * Include this file at the top of protected pages/APIs
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';

/**
 * Require authentication - redirect to login if not authenticated
 * Use this for HTML pages
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.html');
        exit();
    }
}

/**
 * Require authentication - return JSON error if not authenticated
 * Use this for API endpoints
 */
function requireAuthApi() {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required',
            'redirect' => 'login.html'
        ]);
        exit();
    }
}

/**
 * Get session timeout (24 hours)
 */
function getSessionTimeout() {
    return 24 * 60 * 60; // 24 hours in seconds
}

/**
 * Check if session has expired
 */
function isSessionExpired() {
    if (!isset($_SESSION['logged_in_at'])) {
        return true;
    }
    return (time() - $_SESSION['logged_in_at']) > getSessionTimeout();
}

/**
 * Refresh session timestamp
 */
function refreshSession() {
    if (isLoggedIn()) {
        $_SESSION['logged_in_at'] = time();
    }
}

// Auto-check for session expiry
if (isLoggedIn() && isSessionExpired()) {
    // Clear expired session
    $_SESSION = [];
    session_destroy();
}
