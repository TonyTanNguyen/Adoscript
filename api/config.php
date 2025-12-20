<?php
/**
 * Adoscript Configuration File
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_PATH', __DIR__ . '/../data/adoscript.db');

// Upload configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('SCRIPTS_PATH', UPLOAD_PATH . 'scripts/');
define('IMAGES_PATH', UPLOAD_PATH . 'images/');

// File upload limits
define('MAX_SCRIPT_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);   // 5MB

// Allowed file types
define('ALLOWED_SCRIPT_TYPES', ['jsx', 'jsxbin', 'zip']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Site configuration
define('SITE_NAME', 'Adoscript');
define('SITE_URL', 'https://adoscript.com');

// Default admin credentials (change after first login!)
define('DEFAULT_ADMIN_EMAIL', 'admin@adoscript.com');
define('DEFAULT_ADMIN_PASSWORD', 'admin123');

// CORS headers for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Helper function to send JSON response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Helper function to send error response
function errorResponse($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

// Helper function to send success response
function successResponse($data = [], $message = 'Success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}
