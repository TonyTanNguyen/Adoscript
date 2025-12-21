<?php
/**
 * Adoscript Configuration File
 */

// Error reporting - log errors but don't display them (prevents HTML breaking JSON)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
define('ALLOWED_SCRIPT_TYPES', ['js', 'jsx', 'jsxbin', 'zip']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Claude API Configuration
// Get your API key from: https://console.anthropic.com/
// Option 1: Set it here (not recommended for production)
// define('CLAUDE_API_KEY', 'your-api-key-here');
//
// Option 2: Create a file called 'api-keys.php' with:
// <?php define('CLAUDE_API_KEY', 'your-api-key-here');
//
// Option 3: Set environment variable CLAUDE_API_KEY
if (file_exists(__DIR__ . '/api-keys.php')) {
    require_once __DIR__ . '/api-keys.php';
}

// Site configuration
define('SITE_NAME', 'Adoscript');

// Auto-detect local vs production environment
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8888', 'localhost:8080']) 
           || strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false
           || strpos($_SERVER['HTTP_HOST'] ?? '', '.test') !== false;

if ($isLocal) {
    // Local development - adjust port if needed (MAMP default is 8888)
    $port = $_SERVER['SERVER_PORT'] ?? '80';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('SITE_URL', $scheme . '://localhost' . ($port != '80' && $port != '443' ? ':' . $port : ''));
} else {
    // Production
    define('SITE_URL', 'https://adoscript.com');
}

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
